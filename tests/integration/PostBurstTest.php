<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\integration;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Flags\Flag;
use Flarum\Post\Post;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\AntiSpam\ContentFilter\ConfigurationManager;
use FoF\AntiSpam\Extend\ContentFilter;
use PHPUnit\Framework\Attributes\Test;

/**
 * A new account posting faster than a person joining a community does.
 */
class PostBurstTest extends TestCase
{
    protected function setup(): void
    {
        parent::setup();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $this->prepareDatabase([
            User::class => [
                ['id' => 3, 'username' => 'new_user', 'email' => 'new@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()],
                ['id' => 4, 'username' => 'old_user', 'email' => 'old@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()->subYear(), 'comment_count' => 100],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Welcome', 'created_at' => Carbon::now()->subYear(), 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'is_approved' => 1],
                ['id' => 2, 'title' => 'Help thread', 'created_at' => Carbon::now()->subYear(), 'user_id' => 1, 'first_post_id' => 3, 'comment_count' => 1, 'is_approved' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Welcome</p></t>', 'number' => 1, 'created_at' => Carbon::now()->subYear(), 'is_approved' => 1],
                // The established member's first post, so they are past the age limit too.
                ['id' => 2, 'discussion_id' => 1, 'user_id' => 4, 'type' => 'comment', 'content' => '<t><p>Hello all</p></t>', 'number' => 99, 'created_at' => Carbon::now()->subYear(), 'is_approved' => 1],
                ['id' => 3, 'discussion_id' => 2, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>What is wrong?</p></t>', 'number' => 1, 'created_at' => Carbon::now()->subYear(), 'is_approved' => 1],
            ],
        ]);
    }

    /**
     * Earlier posts by the user, each a different harmless reply, the given minutes ago.
     *
     * @param array<int> $minutesAgo
     */
    private function postedEarlier(int $userId, array $minutesAgo, bool $approved = true, int $discussionId = 2): void
    {
        $this->prepareDatabase([
            Post::class => array_map(fn (int $i) => [
                'id' => 100 + $i,
                'discussion_id' => $discussionId,
                'user_id' => $userId,
                'type' => 'comment',
                'content' => "<t><p>Reply number $i</p></t>",
                'number' => 2 + $i,
                'created_at' => Carbon::now()->subMinutes($minutesAgo[$i]),
                'is_approved' => $approved ? 1 : 0,
            ], array_keys($minutesAgo)),
        ]);
    }

    private function reply(int $userId, string $content = 'Thanks, that sorted it out for me.'): Post
    {
        $response = $this->send(
            $this->request('POST', '/api/posts', [
                'authenticatedAs' => $userId,
                'json' => ['data' => [
                    'type' => 'posts',
                    'attributes' => ['content' => $content],
                    'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => '1']]],
                ]],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        return Post::where('user_id', $userId)->orderBy('id', 'desc')->firstOrFail();
    }

    private function spamFlag(Post $post): ?Flag
    {
        return Flag::where('post_id', $post->id)->where('type', 'spam')->first();
    }

    #[Test]
    public function the_post_after_the_limit_is_held()
    {
        $this->postedEarlier(3, [1, 2, 3, 4, 5]);

        $post = $this->reply(3);

        $this->assertFalse((bool) $post->is_approved);
        $this->assertStringContainsString('5 posts in the last 10 minutes', $this->spamFlag($post)->reason_detail);
    }

    #[Test]
    public function a_conversation_in_one_discussion_is_not_a_burst()
    {
        // A new member going back and forth with whoever is helping them.
        $this->postedEarlier(3, [1, 2, 3, 4, 5], discussionId: 1);

        $this->assertTrue((bool) $this->reply(3)->is_approved);
    }

    #[Test]
    public function the_flag_gives_the_limit_not_the_count()
    {
        // The count would include posts in tags the moderator reading the flag cannot see.
        $this->postedEarlier(3, [1, 2, 3, 4, 5, 6, 7]);

        $reason = $this->spamFlag($this->reply(3))->reason_detail;

        $this->assertStringContainsString('5 posts in the last 10 minutes', $reason);
        $this->assertStringNotContainsString('7', $reason);
    }

    #[Test]
    public function a_blank_setting_keeps_the_default_rather_than_turning_the_limit_off()
    {
        // An emptied number field in the admin panel saves an empty string.
        $this->setting('fof-anti-spam.content-filter.burst_post_count', '');
        $this->setting('fof-anti-spam.content-filter.burst_minutes', '');
        $this->postedEarlier(3, [1, 2, 3, 4, 5]);

        $this->assertFalse((bool) $this->reply(3)->is_approved);
    }

    #[Test]
    public function posts_up_to_the_limit_are_left_alone()
    {
        $this->postedEarlier(3, [1, 2, 3, 4]);

        $post = $this->reply(3);

        $this->assertTrue((bool) $post->is_approved);
        $this->assertNull($this->spamFlag($post));
    }

    #[Test]
    public function posts_from_before_the_window_do_not_count()
    {
        $this->postedEarlier(3, [1, 2, 3, 4, 11]);

        $this->assertTrue((bool) $this->reply(3)->is_approved);
    }

    #[Test]
    public function posts_already_held_still_count()
    {
        // Otherwise holding one post would let the next few through.
        $this->postedEarlier(3, [1, 2, 3, 4, 5], approved: false);

        $this->assertFalse((bool) $this->reply(3)->is_approved);
    }

    #[Test]
    public function starting_a_discussion_counts_and_is_held()
    {
        $this->postedEarlier(3, [1, 2, 3, 4, 5]);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => ['data' => ['type' => 'discussions', 'attributes' => [
                    'title' => 'How do I reset my password?',
                    'content' => 'I cannot find the link anywhere.',
                ]]],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        $discussion = Discussion::where('user_id', 3)->firstOrFail();
        $this->assertFalse((bool) $discussion->is_approved);
        $this->assertFalse((bool) $discussion->firstPost->is_approved);
    }

    #[Test]
    public function editing_a_post_is_not_posting_again()
    {
        $this->postedEarlier(3, [1, 2, 3, 4, 5]);

        $response = $this->send(
            $this->request('PATCH', '/api/posts/101', [
                'authenticatedAs' => 3,
                'json' => ['data' => ['type' => 'posts', 'id' => '101', 'attributes' => ['content' => 'Reply number 1, corrected']]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertTrue((bool) Post::findOrFail(101)->is_approved);
    }

    #[Test]
    public function an_established_member_is_not_limited()
    {
        $this->postedEarlier(4, [1, 2, 3, 4, 5]);

        $this->assertTrue((bool) $this->reply(4)->is_approved);
    }

    #[Test]
    public function the_limit_can_be_turned_off()
    {
        $this->setting('fof-anti-spam.content-filter.burst_post_count', 0);
        $this->postedEarlier(3, [1, 2, 3, 4, 5]);

        $this->assertTrue((bool) $this->reply(3)->is_approved);
    }

    #[Test]
    public function the_limit_and_window_follow_the_settings()
    {
        $this->setting('fof-anti-spam.content-filter.burst_post_count', 2);
        $this->setting('fof-anti-spam.content-filter.burst_minutes', 60);
        $this->postedEarlier(3, [30, 50]);

        $post = $this->reply(3);

        $this->assertFalse((bool) $post->is_approved);
        $this->assertStringContainsString('2 posts in the last 60 minutes', $this->spamFlag($post)->reason_detail);
    }

    #[Test]
    public function the_limit_can_be_set_in_code()
    {
        $this->extend((new ContentFilter())->limitNewAccountPosts(3, 30));

        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        $this->assertSame(3, $config->get('burst_post_count'));
        $this->assertSame(30, $config->get('burst_minutes'));
        $this->assertTrue($config->toArray()['burstPostCount']['isCodeConfigured']);
        $this->assertTrue($config->toArray()['burstMinutes']['isCodeConfigured']);
    }
}

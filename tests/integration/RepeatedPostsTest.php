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
 * A new account posting the same thing again.
 */
class RepeatedPostsTest extends TestCase
{
    private const string TEXT = 'Segera laporkan ke layanan WhatsApp resmi, siapkan bukti transfer seperti mutasi rekening atau struk.';

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
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Welcome</p></t>', 'number' => 1, 'created_at' => Carbon::now()->subYear(), 'is_approved' => 1],
                ['id' => 2, 'discussion_id' => 1, 'user_id' => 4, 'type' => 'comment', 'content' => '<t><p>Hello all</p></t>', 'number' => 2, 'created_at' => Carbon::now()->subYear(), 'is_approved' => 1],
            ],
        ]);
    }

    private function postedEarlier(int $userId, string $text, bool $approved = true): void
    {
        $this->prepareDatabase([
            Post::class => [
                ['id' => 100, 'discussion_id' => 1, 'user_id' => $userId, 'type' => 'comment', 'content' => '<t><p>'.htmlspecialchars($text).'</p></t>', 'number' => 3, 'created_at' => Carbon::now()->subHour(), 'is_approved' => $approved ? 1 : 0],
            ],
        ]);
    }

    private function reply(int $userId, string $content): Post
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

    private function edit(int $userId, int $postId, string $content): Post
    {
        $response = $this->send(
            $this->request('PATCH', "/api/posts/$postId", [
                'authenticatedAs' => $userId,
                'json' => ['data' => ['type' => 'posts', 'id' => (string) $postId, 'attributes' => ['content' => $content]]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        return Post::findOrFail($postId);
    }

    #[Test]
    public function posting_the_same_thing_again_is_held()
    {
        $this->postedEarlier(3, self::TEXT);

        $post = $this->reply(3, self::TEXT);

        $this->assertFalse((bool) $post->is_approved);

        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->firstOrFail();
        $this->assertStringContainsString('Repeats', $flag->reason_detail);
    }

    #[Test]
    public function case_spacing_punctuation_and_formatting_do_not_hide_a_repeat()
    {
        $this->postedEarlier(3, self::TEXT);

        $post = $this->reply(3, "**SEGERA** laporkan ke layanan  WhatsApp resmi -\n\nsiapkan bukti transfer seperti mutasi rekening atau struk!!");

        $this->assertFalse((bool) $post->is_approved);
    }

    #[Test]
    public function a_repeat_of_a_held_post_is_held()
    {
        $this->postedEarlier(3, self::TEXT, approved: false);

        $this->assertFalse((bool) $this->reply(3, self::TEXT)->is_approved);
    }

    #[Test]
    public function a_different_post_is_left_alone()
    {
        $this->postedEarlier(3, self::TEXT);

        $this->assertTrue((bool) $this->reply(3, 'Thanks everyone, I got my money back from the bank in the end.')->is_approved);
    }

    #[Test]
    public function a_short_reply_may_be_repeated()
    {
        // People say thank you more than once.
        $this->postedEarlier(3, 'Thanks, that worked for me!');

        $this->assertTrue((bool) $this->reply(3, 'Thanks, that worked for me!')->is_approved);
    }

    #[Test]
    public function someone_else_saying_the_same_thing_is_not_a_repeat()
    {
        $this->postedEarlier(1, self::TEXT);

        $this->assertTrue((bool) $this->reply(3, self::TEXT)->is_approved);
    }

    #[Test]
    public function an_edit_is_not_compared_with_the_post_it_edits()
    {
        $this->postedEarlier(3, self::TEXT);

        $this->assertTrue((bool) $this->edit(3, 100, self::TEXT.' Terima kasih.')->is_approved);
    }

    #[Test]
    public function editing_a_post_into_a_repeat_is_held()
    {
        $this->postedEarlier(3, self::TEXT);
        $this->prepareDatabase([
            Post::class => [
                ['id' => 101, 'discussion_id' => 1, 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>Something quite different to say about the topic here.</p></t>', 'number' => 4, 'created_at' => Carbon::now()->subMinutes(30), 'is_approved' => 1],
            ],
        ]);

        $this->assertFalse((bool) $this->edit(3, 101, self::TEXT)->is_approved);
    }

    #[Test]
    public function an_established_member_may_repeat_themselves()
    {
        $this->postedEarlier(4, self::TEXT);

        $this->assertTrue((bool) $this->reply(4, self::TEXT)->is_approved);
    }

    #[Test]
    public function repeats_can_be_ignored()
    {
        $this->setting('fof-anti-spam.content-filter.detect_repeats', false);
        $this->postedEarlier(3, self::TEXT);

        $this->assertTrue((bool) $this->reply(3, self::TEXT)->is_approved);
    }

    #[Test]
    public function repeats_can_be_ignored_in_code()
    {
        $this->extend((new ContentFilter())->blockRepeatedPosts(false));

        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        $this->assertFalse($config->get('detect_repeats'));
        $this->assertTrue($config->toArray()['detectRepeats']['isCodeConfigured']);
    }
}

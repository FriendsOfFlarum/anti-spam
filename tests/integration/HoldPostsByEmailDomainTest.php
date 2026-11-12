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
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\AntiSpam\Extend\ContentFilter;
use FoF\AntiSpam\Listener\HoldPostsByEmailDomain;
use PHPUnit\Framework\Attributes\Test;

/**
 * Holding every post from a new account for approval when its email address is on a listed
 * domain, whatever the post says.
 *
 * "New" is the content filter's window: up to a post count, or up to an age. Held posts are
 * private until approved, so they do not count towards the post count: an account leaves the
 * window once moderators have approved enough of its posts and it is old enough.
 */
class HoldPostsByEmailDomainTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private const SETTING = 'fof-anti-spam.content-filter.held_email_domains';

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $user = fn (int $id, string $username, string $email, bool $new = true) => [
            'id' => $id,
            'username' => $username,
            'email' => $email,
            'is_email_confirmed' => 1,
            'joined_at' => $new ? Carbon::now() : Carbon::now()->subYear(),
            'comment_count' => $new ? 0 : 500,
        ];

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                $user(3, 'newcomer', 'newcomer@example.com'),
                $user(4, 'established', 'established@example.com', new: false),
                $user(5, 'elsewhere', 'elsewhere@example.org'),
                $user(6, 'a_moderator', 'a_moderator@example.com'),
                $user(7, 'subdomain', 'subdomain@mail.example.com'),
                $user(8, 'lookalike', 'lookalike@notexample.com'),
            ],
            'group_user' => [
                ['user_id' => 6, 'group_id' => Group::MODERATOR_ID],
            ],
            'group_permission' => [
                ['group_id' => Group::MODERATOR_ID, 'permission' => 'discussion.hide'],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Welcome', 'user_id' => 1, 'created_at' => Carbon::now(), 'first_post_id' => 1, 'comment_count' => 1, 'is_approved' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Welcome</p></t>', 'number' => 1, 'created_at' => Carbon::now(), 'is_approved' => 1],
            ],
        ]);
    }

    private function reply(int $userId): Post
    {
        $response = $this->send(
            $this->request('POST', '/api/posts', [
                'authenticatedAs' => $userId,
                'json' => [
                    'data' => [
                        'type' => 'posts',
                        'attributes' => ['content' => 'Thanks for having me, glad to be here.'],
                        'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => '1']]],
                    ],
                ],
            ])
        );

        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());

        return Post::findOrFail(json_decode((string) $response->getBody(), true)['data']['id']);
    }

    private function startDiscussion(int $userId): Discussion
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => $userId,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Hello everyone',
                            'content' => 'Has anyone tried the new release? Curious what people think of it.',
                        ],
                    ],
                ],
            ])
        );

        $this->assertSame(201, $response->getStatusCode(), (string) $response->getBody());

        return Discussion::findOrFail(json_decode((string) $response->getBody(), true)['data']['id']);
    }

    private function heldFlags(Post $post): int
    {
        return Flag::where('post_id', $post->id)->where('type', HoldPostsByEmailDomain::FLAG_TYPE)->count();
    }

    private function assertHeld(Post $post): void
    {
        $this->assertFalse((bool) $post->is_approved, 'The post should be held for approval');
        $this->assertSame(1, $this->heldFlags($post), 'A held post should be flagged once, for a moderator to find');
    }

    private function assertNotHeld(Post $post): void
    {
        $this->assertTrue((bool) $post->is_approved, 'The post should not be held');
        $this->assertSame(0, $this->heldFlags($post));
    }

    #[Test]
    public function a_new_user_on_a_listed_domain_has_their_reply_held()
    {
        $this->setting(self::SETTING, 'example.com');

        $this->assertHeld($this->reply(3));
    }

    #[Test]
    public function the_flag_names_the_domain_that_matched()
    {
        $this->setting(self::SETTING, "example.net\nexample.com");

        $post = $this->reply(3);

        $this->assertSame('example.com', Flag::where('post_id', $post->id)->value('reason'));
    }

    #[Test]
    public function a_new_user_on_a_listed_domain_has_their_discussion_held()
    {
        $this->setting(self::SETTING, 'example.com');

        $discussion = $this->startDiscussion(3);

        $this->assertFalse((bool) $discussion->is_approved, 'The discussion should be held along with its first post');
        $this->assertHeld($discussion->firstPost);
    }

    #[Test]
    public function an_established_user_on_a_listed_domain_is_not_held()
    {
        $this->setting(self::SETTING, 'example.com');

        $this->assertNotHeld($this->reply(4));
    }

    #[Test]
    public function monitoring_everyone_does_not_extend_the_hold_past_the_window()
    {
        $this->setting(self::SETTING, 'example.com');
        $this->setting('fof-anti-spam.content-filter.monitor_all_users', true);

        // Otherwise an account on a listed domain could never get out of the hold.
        $this->assertNotHeld($this->reply(4));
    }

    #[Test]
    public function a_user_leaves_the_window_once_enough_posts_are_approved()
    {
        $this->setting(self::SETTING, 'example.com');
        $this->setting('fof-anti-spam.content-filter.monitor_post_count', 2);
        $this->setting('fof-anti-spam.content-filter.monitor_hours_old', 0);

        $this->prepareDatabase([
            User::class => [
                ['id' => 9, 'username' => 'graduate', 'email' => 'graduate@example.com', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()->subDays(2), 'comment_count' => 3],
            ],
        ]);

        $this->assertNotHeld($this->reply(9));
    }

    #[Test]
    public function an_old_account_that_has_not_posted_enough_is_still_held()
    {
        $this->setting(self::SETTING, 'example.com');

        $this->prepareDatabase([
            User::class => [
                ['id' => 9, 'username' => 'dormant', 'email' => 'dormant@example.com', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()->subYear(), 'comment_count' => 0],
            ],
        ]);

        // Age alone does not release an account: waiting the clock out is not a way out.
        $this->assertHeld($this->reply(9));
    }

    #[Test]
    public function a_user_on_an_unlisted_domain_is_not_held()
    {
        $this->setting(self::SETTING, 'example.com');

        $this->assertNotHeld($this->reply(5));
    }

    #[Test]
    public function a_domain_ending_in_a_listed_one_is_not_held()
    {
        $this->setting(self::SETTING, 'example.com');

        $this->assertNotHeld($this->reply(8));
    }

    #[Test]
    public function a_subdomain_of_a_listed_domain_is_held()
    {
        $this->setting(self::SETTING, 'example.com');

        $this->assertHeld($this->reply(7));
    }

    #[Test]
    public function the_listed_domain_is_matched_whatever_its_case_or_decoration()
    {
        $this->setting(self::SETTING, "  @EXAMPLE.com  \n\n");

        $this->assertHeld($this->reply(3));
    }

    #[Test]
    public function users_who_can_hide_discussions_are_exempt()
    {
        $this->setting(self::SETTING, 'example.com');

        $this->assertNotHeld($this->reply(6));
    }

    #[Test]
    public function nothing_is_held_while_the_list_is_empty()
    {
        $this->assertNotHeld($this->reply(3));
    }

    #[Test]
    public function the_hold_applies_with_the_content_filter_switched_off()
    {
        $this->setting(self::SETTING, 'example.com');
        $this->setting('fof-anti-spam.content-filter.enabled', false);

        // A policy about who posts, not a score of what they post.
        $this->assertHeld($this->reply(3));
    }

    #[Test]
    public function an_edit_is_not_held()
    {
        $this->setting(self::SETTING, 'example.com');

        $this->prepareDatabase([
            Post::class => [
                ['id' => 2, 'discussion_id' => 1, 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>Hi</p></t>', 'number' => 2, 'created_at' => Carbon::now()->subHour(), 'is_approved' => 1],
            ],
        ]);

        $response = $this->send(
            $this->request('PATCH', '/api/posts/2', [
                'authenticatedAs' => 3,
                'json' => ['data' => ['type' => 'posts', 'id' => '2', 'attributes' => ['content' => 'Hi again, all.']]],
            ])
        );

        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertNotHeld(Post::findOrFail(2));
    }

    #[Test]
    public function a_post_core_approval_also_holds_is_flagged_by_both_without_conflict()
    {
        $this->setting(self::SETTING, 'example.com');
        $this->app();

        // Members can no longer reply without approval, so core holds the post as well.
        $this->database()->table('group_permission')->where('permission', 'discussion.replyWithoutApproval')->delete();

        $post = $this->reply(3);

        $this->assertHeld($post);
        $this->assertSame(1, Flag::where('post_id', $post->id)->where('type', 'approval')->count());
    }

    #[Test]
    public function domains_can_be_listed_in_code()
    {
        $this->extend((new ContentFilter())->holdPostsFromEmailDomains(['example.com']));

        $this->assertHeld($this->reply(3));
    }

    #[Test]
    public function domains_listed_in_code_and_in_the_admin_panel_both_apply()
    {
        $this->extend((new ContentFilter())->holdPostsFromEmailDomains(['example.org']));
        $this->setting(self::SETTING, 'example.com');

        $this->assertHeld($this->reply(3));
        $this->assertHeld($this->reply(5));
    }
}

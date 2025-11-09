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
use Flarum\Group\Group;
use Flarum\Post\CommentPost;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;

class SpamblockTest extends TestCase
{
    protected function setup(): void
    {
        parent::setup();

        $this->extension('fof-anti-spam');

        $this->prepareDatabase([
            User::class => [
                ['id' => 3, 'username' => 'a_moderator', 'email' => 'a_mod@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'toby', 'email' => 'toby@machine.local', 'is_email_confirmed' => 1],
                ['id' => 5, 'username' => 'bad_user', 'email' => 'bad_user@machine.local', 'is_email_confirmed' => 1],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => Group::MODERATOR_ID],
            ],
            'group_permission' => [
                ['group_id' => Group::MODERATOR_ID, 'permission' => 'user.spamblock'],
            ],
            Discussion::class => [
                // Spammer's first discussion with multiple posts
                ['id' => 2, 'title' => 'Spam Discussion 1', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 5, 'first_post_id' => 4, 'comment_count' => 3, 'last_post_id' => 6],
                // Spammer's second discussion
                ['id' => 3, 'title' => 'Spam Discussion 2', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 5, 'first_post_id' => 7, 'comment_count' => 2, 'last_post_id' => 8],
                // Regular user's discussion with spammer reply
                ['id' => 4, 'title' => 'Normal Discussion', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'user_id' => 4, 'first_post_id' => 9, 'comment_count' => 2, 'last_post_id' => 10],
            ],
            Post::class => [
                // Discussion 2 - spammer's posts
                ['id' => 4, 'number' => 1, 'discussion_id' => 2, 'created_at' => Carbon::now(), 'user_id' => 5, 'type' => 'comment', 'content' => '<r>Spam post 1</r>'],
                ['id' => 5, 'number' => 2, 'discussion_id' => 2, 'created_at' => Carbon::now(), 'user_id' => 4, 'type' => 'comment', 'content' => '<r>Regular reply</r>'],
                ['id' => 6, 'number' => 3, 'discussion_id' => 2, 'created_at' => Carbon::now(), 'user_id' => 5, 'type' => 'comment', 'content' => '<r>Spam post 2</r>'],

                // Discussion 3 - all spammer's posts
                ['id' => 7, 'number' => 1, 'discussion_id' => 3, 'created_at' => Carbon::now(), 'user_id' => 5, 'type' => 'comment', 'content' => '<r>Another spam post</r>'],
                ['id' => 8, 'number' => 2, 'discussion_id' => 3, 'created_at' => Carbon::now(), 'user_id' => 5, 'type' => 'comment', 'content' => '<r>More spam</r>'],

                // Discussion 4 - normal user's discussion with spammer reply
                ['id' => 9, 'number' => 1, 'discussion_id' => 4, 'created_at' => Carbon::now(), 'user_id' => 4, 'type' => 'comment', 'content' => '<r>Normal discussion content</r>'],
                ['id' => 10, 'number' => 2, 'discussion_id' => 4, 'created_at' => Carbon::now(), 'user_id' => 5, 'type' => 'comment', 'content' => '<r>Spam reply in normal discussion</r>'],
            ],
        ]);
    }

    #[Test]
    public function moderator_cannot_spamblock_self()
    {
        $response = $this->send(
            $this->request('POST', 'api/users/3/spamblock', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function user_without_permissions_cannot_spamblock()
    {
        $response = $this->send(
            $this->request('POST', 'api/users/3/spamblock', [
                'authenticatedAs' => 4,
            ])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function moderator_can_spamblock_and_posts_are_hidden()
    {
        $response = $this->send(
            $this->request('POST', 'api/users/5/spamblock', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());

        // Verify ALL spammer's discussions are hidden
        $discussion2 = Discussion::find(2);
        $discussion3 = Discussion::find(3);
        $discussion4 = Discussion::find(4); // Normal user's discussion

        $this->assertNotNull($discussion2->hidden_at, 'Spammer discussion 1 should be hidden');
        $this->assertNotNull($discussion3->hidden_at, 'Spammer discussion 2 should be hidden');
        $this->assertNull($discussion4->hidden_at, 'Normal user discussion should NOT be hidden');

        // Verify ALL spammer's posts are hidden (including replies in other discussions)
        $this->assertNotNull(CommentPost::find(4)->hidden_at, 'Spammer post 1 should be hidden');
        $this->assertNotNull(CommentPost::find(6)->hidden_at, 'Spammer post 2 should be hidden');
        $this->assertNotNull(CommentPost::find(7)->hidden_at, 'Spammer post 3 should be hidden');
        $this->assertNotNull(CommentPost::find(8)->hidden_at, 'Spammer post 4 should be hidden');
        $this->assertNotNull(CommentPost::find(10)->hidden_at, 'Spammer reply in normal discussion should be hidden');

        // Verify normal user's posts are NOT hidden
        $this->assertNull(CommentPost::find(5)->hidden_at, 'Normal user reply should NOT be hidden');
        $this->assertNull(CommentPost::find(9)->hidden_at, 'Normal user post should NOT be hidden');
    }

    #[Test]
    public function normal_user_cannot_see_spamblocked_posts()
    {
        $response = $this->send(
            $this->request('POST', 'api/users/5/spamblock', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());

        // Normal users should not be able to see any of the spammer's discussions
        $response = $this->send(
            $this->request('GET', 'api/discussions/2', [
                'authenticatedAs' => 4,
            ])
        );
        $this->assertEquals(404, $response->getStatusCode(), 'Spammer discussion 1 should not be visible');

        $response = $this->send(
            $this->request('GET', 'api/discussions/3', [
                'authenticatedAs' => 4,
            ])
        );
        $this->assertEquals(404, $response->getStatusCode(), 'Spammer discussion 2 should not be visible');

        // But they should still see their own discussion
        $response = $this->send(
            $this->request('GET', 'api/discussions/4', [
                'authenticatedAs' => 4,
            ])
        );
        $this->assertEquals(200, $response->getStatusCode(), 'Normal user should see their own discussion');
    }

    #[Test]
    public function user_is_also_suspended_when_suspend_is_enabled()
    {
        $this->extension('flarum-suspend');

        $this->app();

        $user = User::find(5);
        $this->assertNull($user->suspended_until, 'User should not be suspended');

        $response = $this->send(
            $this->request('POST', 'api/users/5/spamblock', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());

        $user = User::find(5);

        $this->assertNotNull($user->suspended_until, 'User should be suspended');
        $this->assertTrue(Carbon::parse($user->suspended_until)->greaterThan(Carbon::now()->addYears(19)), 'User should be suspended for 20 years');
    }

    #[Test]
    public function all_content_can_be_deleted_instead_of_hidden()
    {
        $this->setting('fof-anti-spam.actions.deletePosts', true);
        $this->setting('fof-anti-spam.actions.deleteDiscussions', true);

        $this->app();

        // Verify content exists before spamblock
        $this->assertCount(2, Discussion::where('user_id', 5)->get(), 'Spammer should have 2 discussions');
        $this->assertCount(5, CommentPost::where('user_id', 5)->get(), 'Spammer should have 5 posts');

        $response = $this->send(
            $this->request('POST', 'api/users/5/spamblock', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());

        // Verify ALL spammer's content is deleted
        $this->assertCount(0, Discussion::where('user_id', 5)->get(), 'All spammer discussions should be deleted');
        $this->assertCount(0, CommentPost::where('user_id', 5)->get(), 'All spammer posts should be deleted');

        // Verify normal user's content is NOT deleted
        $this->assertCount(1, Discussion::where('user_id', 4)->get(), 'Normal user discussion should remain');
        $this->assertCount(2, CommentPost::where('user_id', 4)->get(), 'Normal user posts should remain');
    }

    #[Test]
    public function user_account_can_be_deleted()
    {
        $this->setting('fof-anti-spam.actions.deleteUser', true);

        $this->app();

        // Verify user exists
        $this->assertNotNull(User::find(5), 'User should exist before spamblock');

        $response = $this->send(
            $this->request('POST', 'api/users/5/spamblock', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());

        // Verify user is deleted
        $this->assertNull(User::find(5), 'User should be deleted');
    }
}

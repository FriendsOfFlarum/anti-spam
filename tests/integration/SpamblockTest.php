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
use Flarum\Discussion\Event\Deleted as DiscussionDeleted;
use Flarum\Group\Group;
use Flarum\Post\CommentPost;
use Flarum\Post\Event\Deleted as PostDeleted;
use Flarum\Post\Post;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use PHPUnit\Framework\Attributes\Test;

class SpamblockTest extends TestCase
{
    /**
     * The spam block acts on a spammer's content one record at a time, on
     * purpose: hiding or deleting each post and discussion through its model so
     * the per-model events fire (core reconciles discussion metadata, and
     * flarum/audit logs each action). The per-record UPDATE/DELETE that follows
     * is inherent to that design, not an accidental N+1 that grows with page
     * size, so exempt those shapes from the repeated-query detector.
     *
     * Listed for both identifier-quoting styles, since CI runs every driver
     * (MySQL uses backticks; SQLite and PostgreSQL use double quotes).
     */
    protected function allowedRepeatedQueries(): array
    {
        // Matched as substrings of the query after numbers/strings are
        // normalised to `?`. The table name is kept but its opening quote is
        // dropped, so a configured prefix (`posts` -> `fl_posts`) is still a
        // match while the fragment stays specific to the intended table.
        return [
            // Per-post hide.
            'posts` set `hidden_at` = ?, `hidden_user_id` = ? where `id` = ?',
            'posts" set "hidden_at" = ?, "hidden_user_id" = ? where "id" = ?',
            // Per-post delete.
            'posts` where `id` = ?',
            'posts" where "id" = ?',
            // Per-post flag cleanup.
            '`.`post_id` = ? and',
            '"."post_id" = ? and',
            // Per-post notification cleanup.
            'notifications` where ? = ? and `subject_id` = ?',
            'notifications" where ? = ? and "subject_id" = ?',
            // Per-user comment-count refresh during content deletion.
            'users` set `comment_count` = ? where `id` = ?',
            'users" set "comment_count" = ? where "id" = ?',
        ];
    }

    protected function setup(): void
    {
        parent::setup();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

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
                ['id' => 2, 'title' => 'Spam Discussion 1', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'last_posted_user_id' => 5, 'user_id' => 5, 'first_post_id' => 4, 'comment_count' => 3, 'last_post_id' => 6, 'last_post_number' => 3],
                // Spammer's second discussion
                ['id' => 3, 'title' => 'Spam Discussion 2', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'last_posted_user_id' => 5, 'user_id' => 5, 'first_post_id' => 7, 'comment_count' => 2, 'last_post_id' => 8, 'last_post_number' => 2],
                // Regular user's discussion with spammer reply
                ['id' => 4, 'title' => 'Normal Discussion', 'created_at' => Carbon::now(), 'last_posted_at' => Carbon::now(), 'last_posted_user_id' => 5, 'user_id' => 4, 'first_post_id' => 9, 'comment_count' => 2, 'last_post_id' => 10, 'last_post_number' => 2],
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

    /**
     * When a spammer's reply in someone else's discussion is hidden, that
     * discussion's cached metadata must be reconciled — otherwise it still
     * counts the hidden post, so comment_count and last_post_number point past
     * what a normal reader can see and the discussion list shows a phantom
     * unread (discuss.flarum.org d/39415). Hiding through hide() only queues
     * the Hidden event; the handler must dispatch it so core's
     * DiscussionMetadataUpdater runs.
     */
    #[Test]
    public function hiding_a_spammer_reply_reconciles_the_discussion_metadata()
    {
        // Discussion 4 is a normal user's discussion: post 9 (visible) and the
        // spammer's post 10 (hidden by the block). Before: comment_count 2,
        // last_post_number 2, last_post_id 10 (the soon-to-be-hidden reply).
        $response = $this->send(
            $this->request('POST', 'api/users/5/spamblock', ['authenticatedAs' => 3])
        );

        $this->assertEquals(204, $response->getStatusCode());

        $discussion = Discussion::find(4);

        $this->assertSame(1, (int) $discussion->comment_count, 'The hidden spam reply must not be counted.');
        $this->assertSame(1, (int) $discussion->last_post_number, 'The last post must be the last visible post, not the hidden reply.');
        $this->assertSame(9, (int) $discussion->last_post_id, 'The last post must point at the visible post 9.');
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

        // Verify at least the post in normal user's own discussion remains (post #9)
        $normalUserPosts = CommentPost::where('user_id', 4)->get();
        $this->assertGreaterThanOrEqual(1, $normalUserPosts->count(), 'At least normal user posts in their own discussion should remain');
        $this->assertTrue($normalUserPosts->contains('id', 9), 'Normal user post in their own discussion (post #9) must remain');

        // Note: Post #5 (normal user's reply in spammer's discussion) may or may not be cascade-deleted
        // depending on database foreign key enforcement. We only verify critical post #9 remains.
    }

    #[Test]
    public function deleting_spammer_posts_refreshes_discussion_last_post_metadata()
    {
        $this->setting('fof-anti-spam.actions.deletePosts', true);

        $this->app();

        $discussion = Discussion::find(4);
        $this->assertEquals(10, $discussion->last_post_id, 'Fixture should start with spam reply as last post');
        $this->assertEquals(5, $discussion->last_posted_user_id, 'Fixture should start with spammer as last poster');

        $response = $this->send(
            $this->request('POST', 'api/users/5/spamblock', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNull(CommentPost::find(10), 'Spammer reply in normal discussion should be deleted');

        $discussion->refresh();

        $this->assertEquals(9, $discussion->last_post_id, 'Last post should be recalculated after deleting spammer reply');
        $this->assertEquals(4, $discussion->last_posted_user_id, 'Last poster should be recalculated after deleting spammer reply');
        $this->assertEquals(1, $discussion->comment_count, 'Comment count should be recalculated after deleting spammer reply');
    }

    #[Test]
    public function deleting_spammer_discussions_only_leaves_replies_in_other_discussions()
    {
        $this->setting('fof-anti-spam.actions.deleteDiscussions', true);

        $this->app();

        $deletedDiscussionIds = [];

        $events = $this->app()->getContainer()->make(Dispatcher::class);
        $events->listen(DiscussionDeleted::class, function (DiscussionDeleted $event) use (&$deletedDiscussionIds) {
            $deletedDiscussionIds[] = $event->discussion->id;
        });

        $response = $this->send(
            $this->request('POST', 'api/users/5/spamblock', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());

        // The spammer's own discussions (and their posts) are deleted, firing a Deleted event each.
        $this->assertEqualsCanonicalizing([2, 3], $deletedDiscussionIds, 'Discussion deleted events should fire for both spammer discussions');
        $this->assertNull(Discussion::find(2), 'Spammer discussion 1 should be deleted');
        $this->assertNull(Discussion::find(3), 'Spammer discussion 2 should be deleted');
        $this->assertCount(0, CommentPost::whereIn('discussion_id', [2, 3])->get(), 'Posts in deleted discussions should be gone');

        // The spammer's reply in another user's discussion is hidden (not
        // deleted) because deletePosts is off — so the post row remains, but the
        // discussion's metadata is reconciled to the last visible post.
        $normalDiscussion = Discussion::find(4);
        $this->assertNotNull($normalDiscussion, 'Normal user discussion should not be deleted');
        $this->assertNotNull(CommentPost::find(10), 'Spammer reply in normal discussion should remain when only discussions are deleted');
        $this->assertNotNull(CommentPost::find(10)->hidden_at, 'Spammer reply should be hidden');
        $this->assertEquals(9, $normalDiscussion->last_post_id, 'The last post must reconcile to the last visible post once the reply is hidden');
    }

    #[Test]
    public function deleting_spammer_content_fires_model_events()
    {
        $this->setting('fof-anti-spam.actions.deletePosts', true);
        $this->setting('fof-anti-spam.actions.deleteDiscussions', true);

        $this->app();

        $deletedPostIds = [];
        $deletedDiscussionIds = [];

        $events = $this->app()->getContainer()->make(Dispatcher::class);
        $events->listen(PostDeleted::class, function (PostDeleted $event) use (&$deletedPostIds) {
            $deletedPostIds[] = $event->post->id;
        });
        $events->listen(DiscussionDeleted::class, function (DiscussionDeleted $event) use (&$deletedDiscussionIds) {
            $deletedDiscussionIds[] = $event->discussion->id;
        });

        $response = $this->send(
            $this->request('POST', 'api/users/5/spamblock', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());

        // Discussion deletion fires Discussion\Event\Deleted for each of the spammer's discussions.
        $this->assertEqualsCanonicalizing([2, 3], $deletedDiscussionIds, 'Discussion deleted events should fire for both spammer discussions');

        // Post deletion fires Post\Event\Deleted. The spammer's reply (post 10) lives in another
        // user's discussion, so it must be deleted individually and fire its event.
        $this->assertContains(10, $deletedPostIds, 'Post deleted event should fire for the spammer reply in the normal discussion');
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

    #[Test]
    public function spammer_bio_is_cleared_when_user_bio_is_enabled()
    {
        $this->extension('fof-user-bio');

        $this->app();

        $user = User::find(5);
        $user->bio = 'Buy cheap pills at spam.example';
        $user->save();

        $this->assertSame('Buy cheap pills at spam.example', User::find(5)->bio, 'Spammer should start with a bio');

        $response = $this->send(
            $this->request('POST', 'api/users/5/spamblock', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNull(User::find(5)->bio, 'Spammer bio should be cleared');
    }

    #[Test]
    public function spamblock_works_without_user_bio_extension()
    {
        // fof-user-bio is not enabled here; spamblock must not error.
        $response = $this->send(
            $this->request('POST', 'api/users/5/spamblock', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());
    }

    #[Test]
    public function deleting_spammer_with_bio_does_not_error()
    {
        $this->extension('fof-user-bio');
        $this->setting('fof-anti-spam.actions.deleteUser', true);

        $this->app();

        $user = User::find(5);
        $user->bio = 'Buy cheap pills at spam.example';
        $user->save();

        $response = $this->send(
            $this->request('POST', 'api/users/5/spamblock', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertNull(User::find(5), 'User should be deleted');
    }
}

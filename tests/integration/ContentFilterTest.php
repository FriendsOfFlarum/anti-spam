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
use PHPUnit\Framework\Attributes\Test;

class ContentFilterTest extends TestCase
{
    protected function setup(): void
    {
        parent::setup();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $this->prepareDatabase([
            User::class => [
                ['id' => 3, 'username' => 'new_user', 'email' => 'new@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()],
                ['id' => 4, 'username' => 'old_user', 'email' => 'old@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()->subDays(7)],
            ],
        ]);
    }

    #[Test]
    public function fresh_user_post_with_phone_number_is_detected()
    {
        // Lower the thresholds so a single indicator triggers actions
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 20);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Test Discussion',
                            'content' => 'Contact me at +1234567890 for details',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        // Get the created post
        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post, 'Post should be created');

        // Check if post was flagged
        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNotNull($flag, 'Post should be flagged as spam');
        $this->assertStringContainsString('phone', strtolower($flag->reason_detail), 'Flag reason should mention phone');

        // Check if post was unapproved
        if (isset($post->is_approved)) {
            $this->assertFalse($post->is_approved, 'Post should be unapproved');
        }
    }

    #[Test]
    public function fresh_user_post_with_email_is_detected()
    {
        // Lower the thresholds so a single indicator triggers actions
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 20);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Test Discussion',
                            'content' => 'Email me at spam@suspicious-site.com for more info',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        // Get the created post
        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post, 'Post should be created');

        // Check if post was flagged
        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNotNull($flag, 'Post should be flagged as spam');
        $this->assertStringContainsString('email', strtolower($flag->reason_detail), 'Flag reason should mention email');
    }

    #[Test]
    public function fresh_user_post_with_external_url_is_detected()
    {
        // Lower the thresholds so a single indicator triggers actions
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 20);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Test Discussion',
                            'content' => 'Check out http://suspicious-site.com for great deals',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        // Get the created post
        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post, 'Post should be created');

        // Check if post was flagged
        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNotNull($flag, 'Post should be flagged as spam');
        $this->assertStringContainsString('url', strtolower($flag->reason_detail), 'Flag reason should mention URL');
    }

    #[Test]
    public function unapproving_spam_opening_post_also_unapproves_the_discussion()
    {
        // A spam opening post must take its discussion down with it. Otherwise the post is held
        // for approval but the discussion stays public, so the thread (and any moderation replies)
        // remain visible to everyone.
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 20);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Totally innocent title',
                            'content' => 'Contact me at +1234567890 for details',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post, 'Post should be created');
        $this->assertFalse((bool) $post->is_approved, 'Spam opening post should be unapproved');

        $discussion = Discussion::find($post->discussion_id);
        $this->assertNotNull($discussion);
        $this->assertFalse((bool) $discussion->is_approved, 'Discussion started by a spam opening post should be unapproved too');

        // A normal user must not be able to see the unapproved discussion.
        $response = $this->send(
            $this->request('GET', '/api/discussions/'.$discussion->id, [
                'authenticatedAs' => 4,
            ])
        );
        $this->assertEquals(404, $response->getStatusCode(), 'Unapproved spam discussion should not be visible to others');
    }

    #[Test]
    public function spam_in_the_discussion_title_unapproves_the_discussion_and_its_first_post()
    {
        // Discussion\Event\Started is released *after* the discussion row has been written, so
        // unapproving the model in memory was silently discarded: title spam earned a flag but
        // stayed publicly visible. The first post goes down with it, otherwise approving the
        // discussion later restores a post no one ever reviewed.
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 20);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Buy cheap stuff at http://suspicious-site.com',
                            'content' => 'Hello everyone, nice to meet you.',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post, 'Post should be created');
        $this->assertFalse((bool) $post->is_approved, 'First post of a spam-titled discussion should be unapproved');

        $discussion = Discussion::find($post->discussion_id);
        $this->assertNotNull($discussion);
        $this->assertFalse((bool) $discussion->is_approved, 'Discussion with a spam title should be unapproved');

        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNotNull($flag, 'Spam title should be flagged on the first post');

        // A normal user must not be able to see the unapproved discussion.
        $response = $this->send(
            $this->request('GET', '/api/discussions/'.$discussion->id, [
                'authenticatedAs' => 4,
            ])
        );
        $this->assertEquals(404, $response->getStatusCode(), 'Unapproved spam discussion should not be visible to others');
    }

    #[Test]
    public function renaming_a_discussion_to_a_spam_title_unapproves_it()
    {
        // Authors may rename their own discussions for a while after starting them, so a clean
        // title can be swapped for the payload once the discussion is through.
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 20);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => ['data' => ['type' => 'discussions', 'attributes' => [
                    'title' => 'Hello from a new member',
                    'content' => 'Hello everyone, nice to meet you.',
                ]]],
            ])
        );
        $this->assertEquals(201, $response->getStatusCode());
        $id = json_decode((string) $response->getBody(), true)['data']['id'];
        $this->assertTrue((bool) Discussion::find($id)->is_approved, 'The clean discussion starts out approved');

        $response = $this->send(
            $this->request('PATCH', '/api/discussions/'.$id, [
                'authenticatedAs' => 3,
                'json' => ['data' => ['type' => 'discussions', 'id' => $id, 'attributes' => [
                    'title' => 'Buy cheap stuff at http://suspicious-site.com',
                ]]],
            ])
        );
        $this->assertEquals(200, $response->getStatusCode());

        $discussion = Discussion::find($id);
        $this->assertFalse((bool) $discussion->is_approved, 'A spam rename should take the discussion down');
        $this->assertFalse((bool) Post::find($discussion->first_post_id)->is_approved, 'Its first post goes with it');
        $this->assertNotNull(Flag::where('post_id', $discussion->first_post_id)->where('type', 'spam')->first());

        $response = $this->send($this->request('GET', '/api/discussions/'.$id, ['authenticatedAs' => 4]));
        $this->assertEquals(404, $response->getStatusCode(), 'Others should no longer see it');
    }

    #[Test]
    public function a_moderator_renaming_a_discussion_is_not_checked()
    {
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 20);
        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 51, 'title' => 'Fresh thread', 'created_at' => Carbon::now(), 'user_id' => 3, 'first_post_id' => 51, 'comment_count' => 1, 'is_approved' => 1],
            ],
            Post::class => [
                ['id' => 51, 'number' => 1, 'discussion_id' => 51, 'created_at' => Carbon::now(), 'user_id' => 3, 'type' => 'comment', 'content' => '<t>Hi</t>'],
            ],
        ]);

        $response = $this->send(
            $this->request('PATCH', '/api/discussions/51', [
                'authenticatedAs' => 1,
                'json' => ['data' => ['type' => 'discussions', 'id' => '51', 'attributes' => [
                    'title' => 'Moved: see http://suspicious-site.com',
                ]]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue((bool) Discussion::find(51)->is_approved);
    }

    #[Test]
    public function an_established_member_renaming_their_discussion_is_not_checked()
    {
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 20);
        $this->prepareDatabase([
            Discussion::class => [
                ['id' => 50, 'title' => 'Old thread', 'created_at' => Carbon::now(), 'user_id' => 4, 'first_post_id' => 50, 'comment_count' => 1, 'is_approved' => 1],
            ],
            Post::class => [
                ['id' => 50, 'number' => 1, 'discussion_id' => 50, 'created_at' => Carbon::now(), 'user_id' => 4, 'type' => 'comment', 'content' => '<t>Hi</t>'],
            ],
        ]);

        // Past the account age window already; past the post count window too.
        $this->app();
        User::query()->where('id', 4)->update(['comment_count' => 50]);

        $response = $this->send(
            $this->request('PATCH', '/api/discussions/50', [
                'authenticatedAs' => 4,
                'json' => ['data' => ['type' => 'discussions', 'id' => '50', 'attributes' => [
                    'title' => 'See http://suspicious-site.com',
                ]]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue((bool) Discussion::find(50)->is_approved);
    }

    #[Test]
    public function fresh_user_post_with_schemeless_url_is_detected()
    {
        // Dropping the scheme used to make a link invisible to the detector, which is free for a
        // spammer and the most common way spam reaches a forum as plain text.
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 20);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Test Discussion',
                            'content' => 'Great deals at www.suspicious-site.com and cheap-pills.xyz today',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post, 'Post should be created');
        $this->assertFalse((bool) $post->is_approved, 'Schemeless spam links should be unapproved');

        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNotNull($flag, 'Schemeless URLs should be flagged as spam');
        $this->assertStringContainsString('url', strtolower($flag->reason_detail), 'Flag reason should mention URL');
    }

    /**
     * Start a discussion as the fresh user 3, at the default thresholds, and return its first post.
     */
    private function freshUserPosts(string $content): Post
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => ['data' => ['type' => 'discussions', 'attributes' => [
                    'title' => 'Hello there',
                    'content' => $content,
                ]]],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        return Post::where('user_id', 3)->orderBy('id', 'desc')->firstOrFail();
    }

    #[Test]
    public function a_bare_hostname_is_flagged_but_left_visible()
    {
        // Nobody can click a bare hostname. It is worth a moderator's look, not taking the post down.
        $post = $this->freshUserPosts('Great deals at cheap-pills.xyz today');

        $this->assertTrue((bool) $post->is_approved);
        $this->assertNotNull(Flag::where('post_id', $post->id)->where('type', 'spam')->first());
    }

    #[Test]
    public function a_bare_hostname_in_a_title_is_hidden()
    {
        // A title is shown on every discussion list, where a domain is the payload whether or not
        // anyone can click it. Only a post body gets the softer weight.
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => ['data' => ['type' => 'discussions', 'attributes' => [
                    'title' => 'Cheap pills at cheap-pills.xyz',
                    'content' => 'Hello everyone, nice to meet you.',
                ]]],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());
        $id = json_decode((string) $response->getBody(), true)['data']['id'];
        $this->assertFalse((bool) Discussion::find($id)->is_approved);
    }

    #[Test]
    public function a_clickable_link_is_hidden_at_the_default_thresholds()
    {
        $post = $this->freshUserPosts('Great deals at https://cheap-pills.xyz today');

        $this->assertFalse((bool) $post->is_approved);
    }

    #[Test]
    public function an_email_on_an_allowed_domain_is_not_held_against_the_poster()
    {
        // The allowlist says those addresses are this forum's own, or ones it trusts. A member
        // pointing at the support address must not be held for approval.
        $this->setting('fof-anti-spam.content-filter.allowed_domains', 'mycompany.com');

        $post = $this->freshUserPosts('Please write to support@mycompany.com about it');

        $this->assertTrue((bool) $post->is_approved);
        $this->assertNull(Flag::where('post_id', $post->id)->where('type', 'spam')->first());
    }

    #[Test]
    public function an_email_on_a_reserved_example_domain_is_not_held_against_the_poster()
    {
        $post = $this->freshUserPosts('Set MAIL_FROM to someone@example.com in your .env');

        $this->assertTrue((bool) $post->is_approved);
        $this->assertNull(Flag::where('post_id', $post->id)->where('type', 'spam')->first());
    }

    #[Test]
    public function an_email_on_any_other_domain_still_counts()
    {
        $post = $this->freshUserPosts('Mail me at spammer@suspicious-site.com');

        $this->assertFalse((bool) $post->is_approved);
    }

    #[Test]
    public function fresh_user_talking_about_source_files_is_not_flagged()
    {
        // The flip side of matching bare domains: a new user asking an ordinary question must not
        // be held for approval because they named a file.
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 20);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Where do I put this?',
                            'content' => 'I read README.md, edited extend.php and ran install.sh, but forum.js still fails.',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post, 'Post should be created');
        $this->assertNotFalse($post->is_approved, 'Naming source files should not unapprove a post');

        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNull($flag, 'Naming source files should not be flagged as spam');
    }

    #[Test]
    public function fresh_user_schemeless_link_to_allowlisted_domain_is_not_flagged()
    {
        $this->setting('fof-anti-spam.content-filter.allowed_domains', "youtube.com\nyoutu.be");
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 20);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'My introduction',
                            'content' => 'My channel is at www.youtube.com/@unetouchedharmonie and youtu.be/abc',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post, 'Post should be created');

        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNull($flag, 'A schemeless link to an allowlisted domain should not be flagged');
        $this->assertNotFalse($post->is_approved, 'Post to an allowlisted domain should not be unapproved');
    }

    #[Test]
    public function fresh_user_link_to_allowlisted_domain_is_not_flagged()
    {
        // Issue #22: the admin UI saves the allowlist newline-separated, but the backend used to
        // json_decode it, so any UI-configured allowlist was silently ignored and every external
        // link from a monitored user was flagged. Configure the allowlist exactly as the UI does.
        $this->setting('fof-anti-spam.content-filter.allowed_domains', "youtube.com\nyoutu.be");
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 20);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'My introduction',
                            'content' => 'Check https://youtube.com/@unetouchedharmonie for my channel',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post, 'Post should be created');

        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNull($flag, 'A link to an allowlisted domain should not be flagged');
        $this->assertNotFalse($post->is_approved, 'Post to an allowlisted domain should not be unapproved');
    }

    #[Test]
    public function old_user_with_many_posts_is_not_monitored()
    {
        $this->app();

        // Set old_user as having many posts
        $user = User::find(4);
        $user->comment_count = 100;
        $user->save();

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 4,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Test Discussion',
                            'content' => 'Contact me at +1234567890 and spam@suspicious-site.com',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        // Check that NO spam flag was created (user is not monitored)
        $post = Post::where('user_id', 4)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post, 'Post should be created');

        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNull($flag, 'Spam should NOT be detected for established users');
    }

    #[Test]
    public function admin_posts_are_not_monitored()
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 1, // Admin
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Test Discussion',
                            'content' => 'Contact me at +1234567890 and spam@suspicious-site.com',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        // Check that NO spam flag was created (admin is exempt)
        $post = Post::where('user_id', 1)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post, 'Post should be created');

        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNull($flag, 'Spam should NOT be detected for admin users');
    }

    #[Test]
    public function clean_post_is_not_flagged()
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Legitimate Discussion',
                            'content' => 'This is a normal post with no spam indicators.',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        // Check that NO spam flag was created
        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post, 'Post should be created');

        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNull($flag, 'Clean content should NOT trigger spam detection');
    }

    #[Test]
    public function discussion_title_with_spam_is_detected()
    {
        // Lower the thresholds so a single indicator triggers actions
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 20);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Call me at +1234567890',
                            'content' => 'Discussion content',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        // Get the created post (first post in discussion)
        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post, 'Post should be created');

        // Check if post was flagged (discussion titles are detected and flagged on the first post)
        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNotNull($flag, 'Spam detection should be flagged for discussion title');
    }

    #[Test]
    public function content_filter_can_be_disabled()
    {
        $this->setting('fof-anti-spam.content-filter.enabled', false);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Test Discussion',
                            'content' => 'Contact me at +1234567890',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        // Check that NO spam flag was created (filtering disabled)
        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post, 'Post should be created');

        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNull($flag, 'Spam should NOT be detected when filtering is disabled');
    }

    #[Test]
    public function multiple_spam_indicators_increase_score()
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Test Discussion',
                            'content' => 'Contact me at +1234567890 or email spam@suspicious-site.com or visit http://suspicious-site.com',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        // Get the created post
        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post, 'Post should be created');

        // Check if post was flagged with multiple indicators
        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNotNull($flag, 'Spam detection should be recorded');

        // Check that the flag reason contains multiple indicators
        $reason = strtolower($flag->reason_detail);
        $indicators = 0;
        if (str_contains($reason, 'phone')) {
            $indicators++;
        }
        if (str_contains($reason, 'email')) {
            $indicators++;
        }
        if (str_contains($reason, 'url')) {
            $indicators++;
        }

        $this->assertGreaterThan(1, $indicators, 'Multiple detectors should have triggered');

        // Check if post was unapproved (high spam score)
        if (isset($post->is_approved)) {
            $this->assertFalse($post->is_approved, 'Post with multiple spam indicators should be unapproved');
        }
    }
}

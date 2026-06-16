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
                            'content' => 'Email me at spam@example.com for more info',
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
                            'content' => 'Contact me at +1234567890 and spam@example.com',
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
                            'content' => 'Contact me at +1234567890 and spam@example.com',
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
                            'content' => 'Contact me at +1234567890 or email spam@example.com or visit http://suspicious-site.com',
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

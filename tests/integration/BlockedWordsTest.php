<?php

namespace FoF\AntiSpam\Tests\integration;

use Carbon\Carbon;
use Flarum\Flags\Flag;
use Flarum\Post\Post;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class BlockedWordsTest extends TestCase
{
    protected function setup(): void
    {
        parent::setup();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $this->prepareDatabase([
            User::class => [
                ['id' => 3, 'username' => 'new_user', 'email' => 'new@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()],
            ],
        ]);

        // Increase monitor_post_count to ensure user is always monitored despite multiple test runs
        $this->setting('fof-anti-spam.content-filter.monitor_post_count', 100);
    }

    #[Test]
    public function blocked_word_is_detected()
    {
        // Configure blocked words
        $this->setting('fof-anti-spam.content-filter.blocked_words', "viagra\ncialis");
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 15);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Test Discussion',
                            'content' => 'Check out this viagra product',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post);

        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNotNull($flag, 'Post should be flagged for blocked word');
        $this->assertStringContainsString('viagra', strtolower($flag->reason));
    }

    #[Test]
    public function blocked_phrase_is_detected()
    {
        // Configure blocked phrase
        $this->setting('fof-anti-spam.content-filter.blocked_words', "crypto pump\nget rich quick");
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 15);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Test Discussion',
                            'content' => 'Join our crypto pump group',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post);

        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNotNull($flag, 'Post should be flagged for blocked phrase');
        $this->assertStringContainsString('crypto pump', strtolower($flag->reason));
    }

    #[Test]
    public function word_boundary_is_respected()
    {
        // "viagra" should match "viagra" but not "niagara"
        $this->setting('fof-anti-spam.content-filter.blocked_words', "viagra");
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 15);

        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Travel to Niagara Falls',
                            'content' => 'I visited niagara falls last week',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->first();
        $this->assertNotNull($post);

        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();
        $this->assertNull($flag, 'Post should NOT be flagged - word boundary not matched');
    }
}

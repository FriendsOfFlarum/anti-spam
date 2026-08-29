<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\integration\content;

use Carbon\Carbon;
use Flarum\Flags\Flag;
use Flarum\Post\Post;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Detection must not depend on which renderer is installed.
 *
 * flarum/markdown swaps in Litedown, which changes how a post is parsed and therefore what
 * `$post->content` unparses back to. The detectors read that value, so every case here runs twice:
 * once on core's formatter alone and once with markdown enabled.
 */
abstract class EvasionTestCase extends TestCase
{
    abstract protected function markdownEnabled(): bool;

    protected function setUp(): void
    {
        parent::setUp();

        $extensions = ['flarum-flags', 'flarum-approval', 'fof-anti-spam'];

        if ($this->markdownEnabled()) {
            $extensions[] = 'flarum-markdown';
        }

        $this->extension(...$extensions);

        $this->prepareDatabase([
            User::class => [
                ['id' => 3, 'username' => 'new_user', 'email' => 'new@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()],
            ],
        ]);

        // Pin the thresholds so these cases stay meaningful regardless of what the shipped
        // defaults are: 30 flags for review, 50 also hides.
        $this->setting('fof-anti-spam.content-filter.monitor_post_count', 100);
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 30);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 50);
    }

    private function post(string $content): Post
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'A perfectly ordinary title',
                            'content' => $content,
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        return Post::where('user_id', 3)->orderBy('id', 'desc')->first();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function spamContent(): iterable
    {
        yield 'plain url' => ['Buy at https://suspicious-site.com now'];

        // A CommonMark autolink. The whole token used to be stripped as if it were a markup tag,
        // so this rendered as a live link and scored nothing.
        yield 'angle bracket autolink' => ['Buy at <https://suspicious-site.com> now'];
        yield 'markdown link, angle destination' => ['See [here](<https://suspicious-site.com>) now'];
        yield 'angle bracket email' => ['Mail <spammer@suspicious-site.com> today'];
        yield 'schemeless in angle brackets' => ['Buy at <www.suspicious-site.com> now'];
    }

    #[Test]
    #[DataProvider('spamContent')]
    public function spam_is_detected_and_hidden(string $content): void
    {
        $post = $this->post($content);

        $this->assertNotNull(
            Flag::where('post_id', $post->id)->where('type', 'spam')->first(),
            'Content should be flagged as spam'
        );
        $this->assertFalse((bool) $post->is_approved, 'Spam should be unapproved');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function innocentContent(): iterable
    {
        yield 'source files' => ['I read README.md, edited extend.php and ran install.sh'];
        yield 'prose comparison' => ['It holds while a < b and c > d, which surprised me'];
        yield 'inline code' => ['Use `<p>` around it, then check the `<br/>` handling'];
    }

    #[Test]
    #[DataProvider('innocentContent')]
    public function innocent_content_is_left_alone(string $content): void
    {
        $post = $this->post($content);

        $this->assertNull(
            Flag::where('post_id', $post->id)->where('type', 'spam')->first(),
            'Ordinary content should not be flagged'
        );
        $this->assertNotFalse($post->is_approved, 'Ordinary content should not be unapproved');
    }

    #[Test]
    public function markup_tags_are_still_stripped_before_analysis(): void
    {
        // The tag itself must never be what trips a detector.
        $post = $this->post('Here is a <b>bold</b> word and a <br/> break');

        $this->assertNull(
            Flag::where('post_id', $post->id)->where('type', 'spam')->first(),
            'Markup should not register as spam'
        );
    }
}

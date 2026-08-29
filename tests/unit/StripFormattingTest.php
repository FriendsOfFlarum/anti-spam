<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\unit;

use Flarum\User\User;
use FoF\AntiSpam\ContentFilter\AbstractDetector;
use FoF\AntiSpam\ContentFilter\ConfigurationManager;
use FoF\AntiSpam\ContentFilter\SpamScore;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StripFormattingTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function strip(string $content): string
    {
        $detector = new class(Mockery::mock(ConfigurationManager::class)) extends AbstractDetector {
            public function analyze(string $content, User $user, array $context = []): SpamScore
            {
                return new SpamScore();
            }

            public function getName(): string
            {
                return 'Test';
            }

            public function getDescription(): string
            {
                return 'Test';
            }

            public function expose(string $content): string
            {
                return $this->stripFormatting($content);
            }
        };

        return $detector->expose($content);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function markup(): iterable
    {
        // TextFormatter markup still has to come out: a detector must not match on the tags.
        yield 'textformatter wrapper' => ['<r><p>hello there</p></r>', 'hello there'];
        yield 'textformatter close tag' => ['<t>plain text</t>', 'plain text'];
        yield 'tag with attributes' => ['<URL url="https://a.com">https://a.com</URL>', 'https://a.com'];
        yield 'self closing tag' => ['line<br/>break', 'line break'];
        yield 'hyphenated tag name' => ['<UPL-IMAGE-PREVIEW src="x">shot</UPL-IMAGE-PREVIEW>', 'shot'];
        yield 'entities are decoded' => ['tom &amp; jerry', 'tom & jerry'];
        yield 'whitespace collapses' => ["a\n\n  b\tc", 'a b c'];
    }

    #[Test]
    #[DataProvider('markup')]
    public function it_strips_formatting(string $content, string $expected): void
    {
        $this->assertSame($expected, $this->strip($content));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function preserved(): iterable
    {
        // These are CommonMark autolinks, not markup. Treating them as tags deleted the payload
        // outright, so `<https://spam.com>` rendered as a live link and scored zero.
        yield 'angle bracket url' => ['Buy at <https://suspicious-site.com> now'];
        yield 'angle bracket url, no scheme' => ['Buy at <www.suspicious-site.com> now'];
        yield 'angle bracket bare host' => ['Buy at <suspicious-site.com> now'];
        yield 'angle bracket email' => ['Mail <spammer@suspicious-site.com> today'];
        yield 'markdown link with angle destination' => ['See [here](<https://suspicious-site.com>) now'];
    }

    #[Test]
    #[DataProvider('preserved')]
    public function it_preserves_angle_bracket_autolinks(string $content): void
    {
        $this->assertSame($content, $this->strip($content));
    }

    #[Test]
    public function it_leaves_prose_comparisons_alone(): void
    {
        $this->assertSame('use a < b and c > d here', $this->strip('use a < b and c > d here'));
    }
}

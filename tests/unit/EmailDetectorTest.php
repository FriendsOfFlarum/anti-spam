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

use FoF\AntiSpam\ContentFilter\Detectors\EmailDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EmailDetectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array<string>}>
     */
    public static function emails(): iterable
    {
        yield 'plain' => ['Mail spammer@suspicious-site.com today', ['spammer@suspicious-site.com']];
        yield 'dots, plus and a second-level domain' => ['Write to first.last+deals@mail.co.uk now', ['first.last+deals@mail.co.uk']];
        yield 'uppercase' => ['Contact SALES@SUSPICIOUS-SITE.COM', ['SALES@SUSPICIOUS-SITE.COM']];
        yield 'in brackets' => ['Reach me (spammer@suspicious-site.com)', ['spammer@suspicious-site.com']];
        yield 'end of sentence' => ['My address is spammer@suspicious-site.com.', ['spammer@suspicious-site.com']];
        yield 'unicode domain' => ['Пишите spam@пример.рф', ['spam@пример.рф']];
        yield 'two' => ['a@one-site.com or b@two-site.net', ['a@one-site.com', 'b@two-site.net']];

        // Trailing characters a spammer can add for free.
        yield 'smiley after' => ['Mail bob@spam-site.com:) now', ['bob@spam-site.com']];
        yield 'colon note after' => ['bob@spam-site.com:ok', ['bob@spam-site.com']];
        yield 'digit after' => ['bob@spam-site.com2', ['bob@spam-site.com']];
        yield 'underscore after' => ['bob@spam-site.com_', ['bob@spam-site.com']];
        yield 'at sign after' => ['bob@spam-site.com@', ['bob@spam-site.com']];
        yield 'zero for an o' => ['bob@spam-site.c0m', ['bob@spam-site.c0m']];
        yield 'punycode tld' => ['bob@spam-site.xn--p1ai', ['bob@spam-site.xn--p1ai']];
    }

    #[Test]
    #[DataProvider('emails')]
    public function it_finds_email_addresses(string $content, array $expected): void
    {
        $this->assertSame($expected, EmailDetector::extractEmails($content));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notEmails(): iterable
    {
        // Package pins and remotes, all common in a developer forum, all read as addresses before.
        yield 'composer constraint' => ['composer require flarum/core@2.0.2'];
        yield 'npm version' => ['yarn add flarum-webpack-config@3.0.7 and react@18.3.1'];
        yield 'scoped npm package' => ['Install @flarum/core@2.0.2'];
        yield 'git remote' => ['git clone git@github.com:FriendsOfFlarum/anti-spam.git'];
        yield 'git remote with a tilde' => ['git clone git@gitlab.com:~me/repo.git'];
        yield 'docker digest' => ['Pull image@sha256:abc123def456'];
        yield 'mention' => ['Thanks @"Ian M"#p123.'];
        yield 'handle' => ['Ping @IanM about it'];
        yield 'no tld' => ['Logged in as root@localhost'];
    }

    #[Test]
    public function a_huge_dotted_run_neither_errors_nor_hides_an_earlier_address(): void
    {
        $content = 'Mail bob@spam-site.com now. x@'.str_repeat('a.', 50000);

        $this->assertSame(['bob@spam-site.com'], EmailDetector::extractEmails($content));
    }

    #[Test]
    #[DataProvider('notEmails')]
    public function it_leaves_other_at_signs_alone(string $content): void
    {
        $this->assertSame([], EmailDetector::extractEmails($content));
    }
}

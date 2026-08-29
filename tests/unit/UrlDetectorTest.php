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

use FoF\AntiSpam\ContentFilter\Detectors\UrlDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UrlDetectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array<string>}>
     */
    public static function detectedUrls(): iterable
    {
        yield 'http' => ['Check out http://suspicious-site.com for deals', ['http://suspicious-site.com']];
        yield 'https with path' => ['See https://suspicious-site.com/buy?ref=1 now', ['https://suspicious-site.com']];
        yield 'other scheme' => ['Grab it from ftp://files.suspicious-site.com', ['ftp://files.suspicious-site.com']];
        yield 'host without a tld' => ['Running on http://localhost:8000 here', ['http://localhost']];

        // Schemeless forms. A spammer drops the scheme precisely because it used to cost nothing.
        yield 'www' => ['Great deals at www.suspicious-site.com today', ['www.suspicious-site.com']];
        yield 'www uppercase' => ['Great deals at WWW.SUSPICIOUS-SITE.COM today', ['WWW.SUSPICIOUS-SITE.COM']];
        yield 'protocol relative' => ['Great [deals](//suspicious-site.com) today', ['//suspicious-site.com']];
        yield 'bare domain' => ['Great deals at suspicious-site.com today', ['suspicious-site.com']];
        yield 'bare domain with subdomain' => ['Go to shop.suspicious-site.co.uk now', ['shop.suspicious-site.co.uk']];
        yield 'bare domain, spammy tld' => ['cheap-pills.xyz has the best prices', ['cheap-pills.xyz']];

        yield 'several at once' => [
            'Visit http://one.com or www.two.net or three.org',
            ['http://one.com', 'www.two.net', 'three.org'],
        ];
    }

    #[Test]
    #[DataProvider('detectedUrls')]
    public function it_extracts_urls(string $content, array $expected): void
    {
        $this->assertSame($expected, UrlDetector::extractUrls($content));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ignoredContent(): iterable
    {
        // Filenames are the expensive false positive: a generic `word.word` rule would hold a new
        // user's post for approval for asking a perfectly ordinary question.
        yield 'markdown file' => ['See README.md for the install steps'];
        yield 'php file' => ['You need to edit extend.php and rebuild'];
        yield 'js file' => ['The error comes from forum.js, not Node.js'];
        yield 'shell script' => ['Just run install.sh as root'];
        yield 'python file' => ['My setup.py is failing'];
        yield 'rust file' => ['It panics in main.rs'];
        yield 'config files' => ['Compare config.yml, composer.lock and .env'];
        yield 'archive' => ['I attached the logs.zip'];

        yield 'version number' => ['I am on 2.0.1 and it broke'];
        yield 'sentence' => ['It works.Then it stops.Weird'];
        yield 'abbreviation' => ['Use a tag, e.g. support, and move on'];
        yield 'class reference' => ['Look at Illuminate.Support.Str for that'];

        // Property access and method calls read exactly like a bare hostname, which is why the
        // ordinary-English-word gTLDs are kept out of the bare-hostname list.
        yield 'property access' => ['Set el.style then read user.name'];
        yield 'method call' => ['Add a logger.info(...) call and check app.click behaviour'];

        // Email addresses are the EmailDetector's job. Counting the domain here too would double
        // the score of any post that shares a contact address.
        yield 'email address' => ['Mail me at spam@example.com please'];
    }

    #[Test]
    #[DataProvider('ignoredContent')]
    public function it_ignores_content_that_is_not_a_url(string $content): void
    {
        $this->assertSame([], UrlDetector::extractUrls($content));
    }
}

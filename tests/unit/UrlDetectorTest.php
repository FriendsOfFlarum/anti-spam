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

        // A word-like country code is still a link once a path follows it. These are the short
        // hosts spam leans on most. As everywhere, the host is what is extracted.
        yield 'telegram' => ['Join t.me/cheap_followers now', ['t.me']];
        yield 'whatsapp' => ['Message wa.me/919876543210', ['wa.me']];
        yield 'youtube short' => ['Watch youtu.be/dQw4w9WgXcQ', ['youtu.be']];
        yield 'amazon short' => ['Buy at amzn.to/3xYz', ['amzn.to']];
        yield 'shortener on at' => ['See shorturl.at/abc12', ['shorturl.at']];
        yield 'ccTLD with a path' => ['Bonus at best-casino.in/bonus', ['best-casino.in']];

        // A bracket straight after a host is only a method call when it is an empty one.
        yield 'bracket after a host' => ['Go to spam-site.com(click here)', ['spam-site.com']];

        // Dropping a TLD from the bare-hostname list never stops an explicit link being caught.
        yield 'explicit link on an excluded tld' => ['Go to http://spam-shop.id today', ['http://spam-shop.id']];
        yield 'www on an excluded tld' => ['Go to www.spam-shop.it today', ['www.spam-shop.it']];

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

        // Country codes that are also English words or common identifiers. Each was being read as
        // a link in ordinary posts.
        yield 'id property' => ['In my extension user.id is null after save'];
        yield 'id in a call' => ['console.log(post.id) prints undefined'];
        yield 'at method' => ['Use arr.at(-1) instead'];
        yield 'missing space, capitalised' => ['I tried that.It did not work'];
        yield 'missing space, lowercase' => ['i tried that.it did not work, so.no luck'];
        yield 'perl files' => ['See the script.pl and Module.pm files'];
        yield 'illustrator file' => ['Open logo.ai in Illustrator'];
        yield 'method on a ccTLD-looking name' => ['Call str.to(\'x\') and user.is(\'admin\')'];
        yield 'empty call on a kept tld' => ['Read window.top() first'];
    }

    #[Test]
    public function a_huge_dotted_run_neither_errors_nor_hides_an_earlier_link(): void
    {
        $content = 'See http://spam-site.com now. '.str_repeat('a.', 50000).'com';

        $this->assertSame('http://spam-site.com', UrlDetector::extractUrls($content)[0] ?? null);
    }

    #[Test]
    #[DataProvider('ignoredContent')]
    public function it_ignores_content_that_is_not_a_url(string $content): void
    {
        $this->assertSame([], UrlDetector::extractUrls($content));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function hosts(): iterable
    {
        // Nobody can be sent anywhere by these: they name the reader's own machine, a private
        // network, or a domain reserved for examples.
        yield 'localhost' => ['localhost', true];
        yield 'localhost subdomain' => ['forum.localhost', true];
        yield 'loopback' => ['127.0.0.1', true];
        yield 'loopback range' => ['127.8.9.10', true];
        yield 'ipv6 loopback' => ['[::1]', true];
        yield 'private 10' => ['10.0.0.5', true];
        yield 'private 192.168' => ['192.168.1.20', true];
        yield 'private 172.16' => ['172.16.4.1', true];
        yield 'example.com' => ['example.com', true];
        yield 'example.org subdomain' => ['docs.example.org', true];
        yield 'example.net' => ['example.net', true];
        yield '.test' => ['myforum.test', true];
        yield '.example' => ['shop.example', true];
        yield '.invalid' => ['nothing.invalid', true];

        yield 'public ip' => ['8.8.8.8', false];
        // Documentation ranges are not private: nothing about them stops a page being served.
        yield 'documentation range ip' => ['203.0.113.9', false];
        yield 'public 172 outside the private block' => ['172.32.0.1', false];
        yield 'lookalike' => ['example.com.spam-site.top', false];
        yield 'suffix lookalike' => ['notexample.com', false];
        yield 'ordinary domain' => ['suspicious-site.com', false];
    }

    #[Test]
    #[DataProvider('hosts')]
    public function it_knows_which_hosts_cannot_send_anyone_anywhere(string $host, bool $expected): void
    {
        $this->assertSame($expected, UrlDetector::isLocalOrReserved($host));
    }
}

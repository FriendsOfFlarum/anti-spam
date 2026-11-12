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

use FoF\AntiSpam\ContentFilter\HeldEmailDomains;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HeldEmailDomainsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, array<string>, ?string}>
     */
    public static function cases(): iterable
    {
        yield 'exact' => ['someone@example.com', ['example.com'], 'example.com'];
        yield 'email in another case' => ['Someone@EXAMPLE.com', ['example.com'], 'example.com'];
        yield 'subdomain' => ['someone@mail.example.com', ['example.com'], 'example.com'];
        yield 'deep subdomain' => ['someone@a.b.example.com', ['example.com'], 'example.com'];
        yield 'second of several' => ['someone@example.org', ['example.com', 'example.org'], 'example.org'];
        yield 'most specific wins' => ['someone@mail.example.com', ['example.com', 'mail.example.com'], 'mail.example.com'];

        yield 'unlisted' => ['someone@example.org', ['example.com'], null];
        yield 'ends in a listed domain' => ['someone@notexample.com', ['example.com'], null];
        yield 'listed domain as a prefix' => ['someone@example.com.evil.test', ['example.com'], null];
        yield 'listed domain in the local part' => ['example.com@example.org', ['example.com'], null];
        yield 'no domain' => ['someone', ['example.com'], null];
        yield 'empty email' => ['', ['example.com'], null];
        yield 'empty list' => ['someone@example.com', [], null];
        yield 'empty entry' => ['someone@example.com', [''], null];
        yield 'trailing dot' => ['someone@example.com.', ['example.com'], 'example.com'];
    }

    #[Test]
    #[DataProvider('cases')]
    public function finds_the_listed_domain(string $email, array $domains, ?string $expected)
    {
        $this->assertSame($expected, HeldEmailDomains::match($email, $domains));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function entries(): iterable
    {
        yield 'bare' => ['example.com', 'example.com'];
        yield 'uppercase' => ['EXAMPLE.COM', 'example.com'];
        yield 'with an at sign' => ['@example.com', 'example.com'];
        yield 'as an address' => ['someone@example.com', 'example.com'];
        yield 'whitespace' => ["  example.com\r", 'example.com'];
        yield 'leading dot' => ['.example.com', 'example.com'];
        yield 'wildcard' => ['*.example.com', 'example.com'];
        yield 'trailing dot' => ['example.com.', 'example.com'];
        yield 'empty' => ['  ', ''];
    }

    #[Test]
    #[DataProvider('entries')]
    public function normalizes_an_entry(string $entry, string $expected)
    {
        $this->assertSame($expected, HeldEmailDomains::normalize($entry));
    }
}

<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests;

use Flarum\Testing\integration\TestCase;
use FoF\AntiSpam\Filter;

class AntiSpamTestCase extends TestCase
{
    protected function setUp(): void
    {
        $this->extension('flarum-lang-dutch');
        parent::setUp();

        $this->app();

        Filter::$acceptableDomains = [];
    }

    /**
     * @test
     * @covers \Blomstra\Spam\Filter::allowLinksFromDomain
     */
    public function allows_full_uri()
    {
        (new Filter)
            ->allowLinksFromDomain('https://google.com/clark-kent');

        $this->assertEquals(
            'google.com',
            Filter::getAcceptableDomains()[0]
        );
    }

    /**
     * @test
     * @covers \Blomstra\Spam\Filter::allowLinksFromDomains
     */
    public function allows_multiple_domains()
    {
        (new Filter)
            ->allowLinksFromDomains([
                'google.com',
                'flarum.org'
            ]);

        $this->assertEquals(
            'flarum.org',
            Filter::getAcceptableDomains()[1]
        );
    }

    /**
     * @test
     * @covers \Blomstra\Spam\Filter
     */
    public function allows_fqdn()
    {
        (new Filter)
            ->allowLinksFromDomain('google.com');

        $this->assertEquals(
            'google.com',
            Filter::getAcceptableDomains()[0]
        );
    }

    /**
     * @test
     * @covers \Blomstra\Spam\Filter
     */
    public function allows_ftp()
    {
        (new Filter)
            ->allowLinksFromDomain('ftp://google.com');

        $this->assertEquals(
            'google.com',
            Filter::getAcceptableDomains()[0]
        );
    }

    /**
     * @test
     * @covers \Blomstra\Spam\Filter
     */
    public function allows_ip()
    {
        (new Filter)
            ->allowLinksFromDomain('127.0.0.1');

        $this->assertEquals(
            '127.0.0.1',
            Filter::getAcceptableDomains()[0]
        );
    }
}

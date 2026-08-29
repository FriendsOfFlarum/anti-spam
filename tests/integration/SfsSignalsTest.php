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
use Flarum\Testing\integration\TestCase;
use FoF\AntiSpam\Api\SfsClient;
use FoF\AntiSpam\Api\SfsResponse;
use FoF\AntiSpam\StopForumSpam;
use PHPUnit\Framework\Attributes\Test;

/**
 * Signals StopForumSpam hands back on every lookup that were parsed and then ignored.
 *
 * `asn` and `country` arrive even when the address is not listed at all, so they say something
 * about traffic the database has never seen. `normalized` is StopForumSpam undoing plus-addressing
 * and Gmail dot-tricks for us. `lastseen` separates a sighting from this morning from one in 2011.
 */
class SfsSignalsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');
    }

    private function sfsFor(array $data): StopForumSpam
    {
        $stub = $this->createStub(SfsClient::class);
        $stub->method('check')->willReturn(new SfsResponse($data));

        $container = $this->app()->getContainer();

        return new StopForumSpam($container->make('flarum.settings'), $container->make('events'), $stub);
    }

    /**
     * An address nobody has reported, hosted in a datacentre.
     */
    private function cleanIpOnAsn(int $asn, string $country = 'ua'): array
    {
        return [
            'success' => 1,
            'ip' => ['value' => '1.2.3.4', 'appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'asn' => $asn, 'country' => $country],
        ];
    }

    private function quietThresholds(): void
    {
        $this->setting('fof-anti-spam.sfs-lookup', true);
        $this->setting('fof-anti-spam.ip', true);
        $this->setting('fof-anti-spam.frequency', 100);
        $this->setting('fof-anti-spam.confidence', 100.0);
    }

    #[Test]
    public function an_asn_on_the_deny_list_is_blocked_even_though_the_address_is_unlisted()
    {
        $this->quietThresholds();
        $this->setting('fof-anti-spam.blockedAsns', '31272, 14061');
        $this->app();

        $this->assertTrue(
            $this->sfsFor($this->cleanIpOnAsn(31272))->shouldPreventRegistration('1.2.3.4', 'a@example.com', 'someone'),
            'A denied ASN should block on its own'
        );
    }

    #[Test]
    public function an_asn_that_is_not_on_the_deny_list_is_allowed()
    {
        $this->quietThresholds();
        $this->setting('fof-anti-spam.blockedAsns', '31272');
        $this->app();

        $this->assertFalse(
            $this->sfsFor($this->cleanIpOnAsn(9999))->shouldPreventRegistration('1.2.3.4', 'a@example.com', 'someone')
        );
    }

    #[Test]
    public function an_empty_deny_list_changes_nothing()
    {
        $this->quietThresholds();
        $this->app();

        $this->assertFalse(
            $this->sfsFor($this->cleanIpOnAsn(31272))->shouldPreventRegistration('1.2.3.4', 'a@example.com', 'someone'),
            'ASN blocking must be entirely opt in'
        );
    }

    #[Test]
    public function a_missing_asn_is_not_treated_as_a_match()
    {
        $this->quietThresholds();
        $this->setting('fof-anti-spam.blockedAsns', '31272');
        $this->app();

        $response = ['success' => 1, 'ip' => ['value' => '1.2.3.4', 'appears' => 0, 'frequency' => 0]];

        $this->assertFalse($this->sfsFor($response)->shouldPreventRegistration('1.2.3.4', 'a@example.com', 'someone'));
    }

    #[Test]
    public function the_deny_list_ignores_blank_entries_and_an_as_prefix()
    {
        $this->quietThresholds();
        $this->setting('fof-anti-spam.blockedAsns', "AS31272\n\n , ");
        $this->app();

        $this->assertTrue(
            $this->sfsFor($this->cleanIpOnAsn(31272))->shouldPreventRegistration('1.2.3.4', 'a@example.com', 'someone'),
            'Operators write ASNs as AS31272 as often as 31272'
        );
    }

    #[Test]
    public function a_stale_listing_can_be_ignored()
    {
        $this->setting('fof-anti-spam.sfs-lookup', true);
        $this->setting('fof-anti-spam.email', true);
        $this->setting('fof-anti-spam.frequency', 5);
        $this->setting('fof-anti-spam.confidence', 50.0);
        $this->setting('fof-anti-spam.maxListingAgeDays', 90);
        $this->app();

        $ancient = [
            'success' => 1,
            'email' => [
                'value' => 'a@example.com', 'appears' => 1, 'frequency' => 200, 'confidence' => 99.0,
                'lastseen' => Carbon::now()->subYears(10)->toDateTimeString(),
            ],
        ];

        $this->assertFalse(
            $this->sfsFor($ancient)->shouldPreventRegistration('1.2.3.4', 'a@example.com', 'someone'),
            'A sighting from ten years ago should not block a registration today'
        );
    }

    #[Test]
    public function a_recent_listing_still_blocks_when_an_age_limit_is_set()
    {
        $this->setting('fof-anti-spam.sfs-lookup', true);
        $this->setting('fof-anti-spam.email', true);
        $this->setting('fof-anti-spam.frequency', 5);
        $this->setting('fof-anti-spam.confidence', 50.0);
        $this->setting('fof-anti-spam.maxListingAgeDays', 90);
        $this->app();

        $recent = [
            'success' => 1,
            'email' => [
                'value' => 'a@example.com', 'appears' => 1, 'frequency' => 200, 'confidence' => 99.0,
                'lastseen' => Carbon::now()->subDays(3)->toDateTimeString(),
            ],
        ];

        $this->assertTrue($this->sfsFor($recent)->shouldPreventRegistration('1.2.3.4', 'a@example.com', 'someone'));
    }

    #[Test]
    public function an_age_limit_of_zero_means_no_limit()
    {
        $this->setting('fof-anti-spam.sfs-lookup', true);
        $this->setting('fof-anti-spam.email', true);
        $this->setting('fof-anti-spam.frequency', 5);
        $this->setting('fof-anti-spam.confidence', 50.0);
        $this->app();

        $ancient = [
            'success' => 1,
            'email' => [
                'value' => 'a@example.com', 'appears' => 1, 'frequency' => 200, 'confidence' => 99.0,
                'lastseen' => Carbon::now()->subYears(10)->toDateTimeString(),
            ],
        ];

        $this->assertTrue(
            $this->sfsFor($ancient)->shouldPreventRegistration('1.2.3.4', 'a@example.com', 'someone'),
            'The default must keep the behaviour that shipped'
        );
    }

    #[Test]
    public function a_blacklisting_ignores_the_age_limit()
    {
        $this->setting('fof-anti-spam.sfs-lookup', true);
        $this->setting('fof-anti-spam.email', true);
        $this->setting('fof-anti-spam.frequency', 500);
        $this->setting('fof-anti-spam.confidence', 100.0);
        $this->setting('fof-anti-spam.maxListingAgeDays', 90);
        $this->app();

        $blacklisted = [
            'success' => 1,
            'email' => [
                'value' => 'a@example.com', 'appears' => 1, 'frequency' => 255, 'blacklisted' => 1,
                'lastseen' => Carbon::now()->subYears(10)->toDateTimeString(),
            ],
        ];

        $this->assertTrue(
            $this->sfsFor($blacklisted)->shouldPreventRegistration('1.2.3.4', 'a@example.com', 'someone'),
            'A toxic domain is toxic regardless of when it was last seen'
        );
    }

    #[Test]
    public function the_normalised_address_is_parsed()
    {
        $this->app();

        // StopForumSpam undoes plus addressing and Gmail dot tricks; the canonical form is the one
        // worth recording against a blocked attempt.
        $response = new SfsResponse([
            'success' => 1,
            'email' => ['value' => 'w.a.spigi+25@gmail.com', 'normalized' => 'waspigi@gmail.com', 'frequency' => 0, 'appears' => 0],
        ]);

        $this->assertEquals('waspigi@gmail.com', $response->email->normalized);
    }

    #[Test]
    public function a_normalised_address_is_null_when_the_api_does_not_send_one()
    {
        $this->app();

        $response = new SfsResponse([
            'success' => 1,
            'email' => ['value' => 'plain@example.com', 'frequency' => 0, 'appears' => 0],
        ]);

        $this->assertNull($response->email->normalized);
    }
}

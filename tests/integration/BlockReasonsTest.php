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

use Flarum\Testing\integration\TestCase;
use FoF\AntiSpam\Api\SfsClient;
use FoF\AntiSpam\Api\SfsResponse;
use FoF\AntiSpam\BlockReasons;
use FoF\AntiSpam\Model\BlockedRegistration;
use FoF\AntiSpam\StopForumSpam;
use PHPUnit\Framework\Attributes\Test;

/**
 * The rules that fired are recorded when the block happens.
 *
 * Deriving them afterwards from the stored StopForumSpam response cannot be trusted: the
 * thresholds are settings, so a row blocked under one configuration may match none of the
 * rules under the next. Recording the decision at the point it is made is the only way an
 * admin can be told why a registration was actually turned away.
 */
class BlockReasonsTest extends TestCase
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

    private function quietThresholds(): void
    {
        $this->setting('fof-anti-spam.sfs-lookup', true);
        $this->setting('fof-anti-spam.ip', true);
        $this->setting('fof-anti-spam.email', true);
        $this->setting('fof-anti-spam.username', true);
        $this->setting('fof-anti-spam.frequency', 100);
        $this->setting('fof-anti-spam.confidence', 100.0);
    }

    /**
     * @return array{reasons: list<string>, context: array<string, mixed>}
     */
    private function recordedReasons(): array
    {
        $row = BlockedRegistration::query()->latest('attempted_at')->first();

        $this->assertNotNull($row, 'The block should have been recorded');
        $this->assertNotNull($row->reasons, 'Reasons should have been recorded');

        return json_decode($row->reasons, true);
    }

    #[Test]
    public function a_blacklisted_field_is_recorded_with_the_field_that_carried_it()
    {
        $this->quietThresholds();
        $this->app();

        $this->sfsFor([
            'success' => 1,
            'email' => ['value' => 'spam@example.com', 'appears' => 1, 'frequency' => 1, 'confidence' => 1.0, 'blacklisted' => 1],
        ])->shouldPreventRegistration('1.2.3.4', 'spam@example.com', 'someone');

        $recorded = $this->recordedReasons();

        $this->assertContains(BlockReasons::BLACKLISTED, $recorded['reasons']);
        $this->assertSame(['email'], $recorded['context']['blacklistedFields']);
    }

    #[Test]
    public function a_confidence_block_records_the_value_and_the_threshold_that_it_beat()
    {
        $this->setting('fof-anti-spam.sfs-lookup', true);
        $this->setting('fof-anti-spam.email', true);
        $this->setting('fof-anti-spam.frequency', 100);
        $this->setting('fof-anti-spam.confidence', 50.0);
        $this->app();

        $this->sfsFor([
            'success' => 1,
            'email' => ['value' => 'spam@example.com', 'appears' => 1, 'frequency' => 1, 'confidence' => 99.5],
        ])->shouldPreventRegistration('1.2.3.4', 'spam@example.com', 'someone');

        $recorded = $this->recordedReasons();

        $this->assertContains(BlockReasons::CONFIDENCE, $recorded['reasons']);
        $this->assertSame(99.5, $recorded['context']['confidence']);
        // The threshold is stored alongside it: without it, a reader cannot tell whether 99.5
        // was a landslide or a hair over the line. Compared loosely because json_encode writes
        // a whole float (50.0) as an int, so the type does not survive the round trip.
        $this->assertEquals(50.0, $recorded['context']['confidenceThreshold']);
    }

    #[Test]
    public function a_frequency_block_records_the_cumulative_total_and_threshold()
    {
        $this->setting('fof-anti-spam.sfs-lookup', true);
        $this->setting('fof-anti-spam.ip', true);
        $this->setting('fof-anti-spam.email', true);
        $this->setting('fof-anti-spam.frequency', 10);
        $this->setting('fof-anti-spam.confidence', 100.0);
        $this->app();

        $this->sfsFor([
            'success' => 1,
            'ip' => ['value' => '1.2.3.4', 'appears' => 1, 'frequency' => 7, 'confidence' => 1.0, 'asn' => 1234],
            'email' => ['value' => 'spam@example.com', 'appears' => 1, 'frequency' => 6, 'confidence' => 1.0],
        ])->shouldPreventRegistration('1.2.3.4', 'spam@example.com', 'someone');

        $recorded = $this->recordedReasons();

        $this->assertContains(BlockReasons::FREQUENCY, $recorded['reasons']);
        // Frequency is summed across enabled fields, not taken per field.
        $this->assertSame(13, $recorded['context']['frequency']);
        $this->assertSame(10, $recorded['context']['frequencyThreshold']);
    }

    #[Test]
    public function a_tor_exit_node_is_recorded()
    {
        $this->quietThresholds();
        $this->setting('fof-anti-spam.blockTorExitNodes', true);
        $this->app();

        $this->sfsFor([
            'success' => 1,
            'ip' => ['value' => '1.2.3.4', 'appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'asn' => 1234, 'torexit' => 1],
        ])->shouldPreventRegistration('1.2.3.4', 'a@example.com', 'someone');

        $this->assertContains(BlockReasons::TOR_EXIT, $this->recordedReasons()['reasons']);
    }

    #[Test]
    public function a_denied_asn_is_recorded_with_the_asn()
    {
        $this->quietThresholds();
        $this->setting('fof-anti-spam.blockedAsns', '31272');
        $this->app();

        $this->sfsFor([
            'success' => 1,
            'ip' => ['value' => '1.2.3.4', 'appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'asn' => 31272],
        ])->shouldPreventRegistration('1.2.3.4', 'a@example.com', 'someone');

        $recorded = $this->recordedReasons();

        $this->assertContains(BlockReasons::DENIED_ASN, $recorded['reasons']);
        $this->assertSame(31272, $recorded['context']['asn']);
    }

    #[Test]
    public function every_rule_that_fired_is_recorded_not_just_the_first()
    {
        $this->setting('fof-anti-spam.sfs-lookup', true);
        $this->setting('fof-anti-spam.ip', true);
        $this->setting('fof-anti-spam.email', true);
        $this->setting('fof-anti-spam.frequency', 5);
        $this->setting('fof-anti-spam.confidence', 50.0);
        $this->setting('fof-anti-spam.blockTorExitNodes', true);
        $this->app();

        $this->sfsFor([
            'success' => 1,
            'ip' => ['value' => '1.2.3.4', 'appears' => 1, 'frequency' => 40, 'confidence' => 80.0, 'asn' => 1234, 'torexit' => 1],
            'email' => ['value' => 'spam@example.com', 'appears' => 1, 'frequency' => 20, 'confidence' => 90.0, 'blacklisted' => 1],
        ])->shouldPreventRegistration('1.2.3.4', 'spam@example.com', 'someone');

        $recorded = $this->recordedReasons();

        // Most blocks trip several rules at once; showing only one would misrepresent why the
        // registration was turned away.
        foreach ([BlockReasons::BLACKLISTED, BlockReasons::TOR_EXIT, BlockReasons::CONFIDENCE, BlockReasons::FREQUENCY] as $expected) {
            $this->assertContains($expected, $recorded['reasons']);
        }
    }

    #[Test]
    public function a_registration_that_is_not_blocked_records_nothing()
    {
        $this->quietThresholds();
        $this->app();

        $blocked = $this->sfsFor([
            'success' => 1,
            'email' => ['value' => 'fine@example.com', 'appears' => 0, 'frequency' => 0, 'confidence' => 0.0],
        ])->shouldPreventRegistration('1.2.3.4', 'fine@example.com', 'someone');

        $this->assertFalse($blocked);
        $this->assertNull(BlockedRegistration::query()->latest('attempted_at')->first());
    }
}

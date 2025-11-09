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
use FoF\AntiSpam\StopForumSpam;
use PHPUnit\Framework\Attributes\Test;

class StopForumSpamTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->extension('fof-anti-spam');
    }

    private function createMockSfsClient(SfsResponse $response): SfsClient
    {
        $mock = $this->createMock(SfsClient::class);
        $mock->method('check')->willReturn($response);

        return $mock;
    }

    private function createSfsResponse(array $data): SfsResponse
    {
        return new SfsResponse($data);
    }

    #[Test]
    public function tor_exit_node_blocks_when_enabled()
    {
        // Setup: Tor blocking enabled
        $this->setting('fof-anti-spam.sfs-lookup', true);
        $this->setting('fof-anti-spam.blockTorExitNodes', true);
        $this->setting('fof-anti-spam.ip', true);
        $this->setting('fof-anti-spam.frequency', 100);  // High threshold
        $this->setting('fof-anti-spam.confidence', 100.0);  // High threshold

        $this->app();

        // Mock SFS response with Tor exit node
        $sfsResponse = $this->createSfsResponse([
            'success' => 1,
            'ip' => [
                'value' => '1.2.3.4',
                'appears' => 1,
                'frequency' => 1,  // Low frequency
                'confidence' => 10.0,  // Low confidence
                'torexit' => 1,  // IS a Tor exit node
            ],
        ]);

        $client = $this->createMockSfsClient($sfsResponse);
        $settings = $this->app()->getContainer()->make('flarum.settings');
        $dispatcher = $this->app()->getContainer()->make('events');

        $sfs = new StopForumSpam($settings, $dispatcher, $client);

        // Should block because it's a Tor exit node
        $result = $sfs->shouldPreventRegistration('1.2.3.4', 'test@example.com', 'testuser');

        $this->assertTrue($result, 'Should block Tor exit node when setting is enabled');
    }

    #[Test]
    public function tor_exit_node_allowed_when_disabled()
    {
        $this->setting('fof-anti-spam.sfs-lookup', true);
        $this->setting('fof-anti-spam.blockTorExitNodes', false);  // Disabled
        $this->setting('fof-anti-spam.ip', true);
        $this->setting('fof-anti-spam.frequency', 100);
        $this->setting('fof-anti-spam.confidence', 100.0);

        $this->app();

        $sfsResponse = $this->createSfsResponse([
            'success' => 1,
            'ip' => [
                'value' => '1.2.3.4',
                'appears' => 1,
                'frequency' => 1,
                'confidence' => 10.0,
                'torexit' => 1,  // IS a Tor exit node
            ],
        ]);

        $client = $this->createMockSfsClient($sfsResponse);
        $settings = $this->app()->getContainer()->make('flarum.settings');
        $dispatcher = $this->app()->getContainer()->make('events');

        $sfs = new StopForumSpam($settings, $dispatcher, $client);

        // Should NOT block because Tor blocking is disabled
        $result = $sfs->shouldPreventRegistration('1.2.3.4', 'test@example.com', 'testuser');

        $this->assertFalse($result, 'Should not block Tor exit node when setting is disabled');
    }

    #[Test]
    public function non_tor_exit_node_not_blocked_by_tor_setting()
    {
        $this->setting('fof-anti-spam.sfs-lookup', true);
        $this->setting('fof-anti-spam.blockTorExitNodes', true);
        $this->setting('fof-anti-spam.ip', true);
        $this->setting('fof-anti-spam.frequency', 100);
        $this->setting('fof-anti-spam.confidence', 100.0);

        $this->app();

        $sfsResponse = $this->createSfsResponse([
            'success' => 1,
            'ip' => [
                'value' => '1.2.3.4',
                'appears' => 1,
                'frequency' => 1,
                'confidence' => 10.0,
                'torexit' => 0,  // NOT a Tor exit node
            ],
        ]);

        $client = $this->createMockSfsClient($sfsResponse);
        $settings = $this->app()->getContainer()->make('flarum.settings');
        $dispatcher = $this->app()->getContainer()->make('events');

        $sfs = new StopForumSpam($settings, $dispatcher, $client);

        // Should NOT block because it's not a Tor exit node and doesn't meet other thresholds
        $result = $sfs->shouldPreventRegistration('1.2.3.4', 'test@example.com', 'testuser');

        $this->assertFalse($result, 'Should not block non-Tor IP that does not meet other thresholds');
    }

    #[Test]
    public function tor_check_requires_ip_data()
    {
        $this->setting('fof-anti-spam.sfs-lookup', true);
        $this->setting('fof-anti-spam.blockTorExitNodes', true);
        $this->setting('fof-anti-spam.ip', false);  // IP checking disabled
        $this->setting('fof-anti-spam.frequency', 100);
        $this->setting('fof-anti-spam.confidence', 100.0);

        $this->app();

        $sfsResponse = $this->createSfsResponse([
            'success' => 1,
            'username' => [
                'value' => 'testuser',
                'appears' => 1,
                'frequency' => 1,
            ],
        ]);

        $client = $this->createMockSfsClient($sfsResponse);
        $settings = $this->app()->getContainer()->make('flarum.settings');
        $dispatcher = $this->app()->getContainer()->make('events');

        $sfs = new StopForumSpam($settings, $dispatcher, $client);

        // Should NOT block because there's no IP data
        $result = $sfs->shouldPreventRegistration('1.2.3.4', 'test@example.com', 'testuser');

        $this->assertFalse($result, 'Should not crash when IP data is missing');
    }

    #[Test]
    public function combines_tor_with_other_checks()
    {
        $this->setting('fof-anti-spam.sfs-lookup', true);
        $this->setting('fof-anti-spam.blockTorExitNodes', false);  // Tor blocking OFF
        $this->setting('fof-anti-spam.ip', true);
        $this->setting('fof-anti-spam.username', true);
        $this->setting('fof-anti-spam.frequency', 5);
        $this->setting('fof-anti-spam.confidence', 50.0);

        $this->app();

        $sfsResponse = $this->createSfsResponse([
            'success' => 1,
            'ip' => [
                'value' => '1.2.3.4',
                'appears' => 1,
                'frequency' => 3,
                'confidence' => 30.0,
                'torexit' => 1,  // Tor exit but setting is disabled
            ],
            'username' => [
                'value' => 'spammer',
                'appears' => 1,
                'frequency' => 3,  // 3 + 3 = 6, exceeds threshold of 5
                'confidence' => 30.0,
            ],
        ]);

        $client = $this->createMockSfsClient($sfsResponse);
        $settings = $this->app()->getContainer()->make('flarum.settings');
        $dispatcher = $this->app()->getContainer()->make('events');

        $sfs = new StopForumSpam($settings, $dispatcher, $client);

        // Should block because frequency threshold is met (not because of Tor)
        $result = $sfs->shouldPreventRegistration('1.2.3.4', 'test@example.com', 'spammer');

        $this->assertTrue($result, 'Should block when frequency threshold is met');
    }
}

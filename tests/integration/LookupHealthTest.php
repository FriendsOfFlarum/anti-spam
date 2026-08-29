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

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\TestCase;
use FoF\AntiSpam\Api\SfsClient;
use FoF\AntiSpam\StopForumSpam;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Cache\Store;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;

/**
 * A lookup that cannot reach StopForumSpam lets the registration through, by design — better an
 * open door than a forum nobody can join. What is not acceptable is that happening in silence: an
 * admin currently cannot tell "working, just not reporting" from "timing out for a week and
 * checking nothing".
 */
class LookupHealthTest extends TestCase
{
    public const FAILURE_KEY = 'fof-anti-spam.lookupFailedAt';

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');
    }

    private function settings(): SettingsRepositoryInterface
    {
        return $this->app()->getContainer()->make(SettingsRepositoryInterface::class);
    }

    /**
     * @param array<int, mixed> $queue
     */
    private function client(array $queue): SfsClient
    {
        $container = $this->app()->getContainer();

        return new SfsClient(
            $container->make('flarum.settings'),
            $container->make(Store::class),
            $container->make(LoggerInterface::class),
            new Client(['handler' => HandlerStack::create(new MockHandler($queue))])
        );
    }

    #[Test]
    public function a_failed_lookup_is_recorded()
    {
        $client = $this->client([new ConnectException('timeout', new Request('POST', 'api'))]);

        $client->check('1.2.3.4', 'a@example.com', 'someone');

        $this->assertNotNull(
            $this->settings()->get(self::FAILURE_KEY),
            'An unreachable API must leave a trace an admin can be shown'
        );
    }

    #[Test]
    public function a_successful_lookup_clears_a_previous_failure()
    {
        $this->settings()->set(self::FAILURE_KEY, '2026-01-01T00:00:00+00:00');

        $client = $this->client([
            new Response(200, [], json_encode(['success' => 1, 'ip' => ['value' => '1.2.3.4', 'frequency' => 0, 'appears' => 0]])),
        ]);

        $client->check('5.6.7.8', 'b@example.com', 'someone-else');

        $this->assertNull($this->settings()->get(self::FAILURE_KEY), 'Recovery should clear the warning');
    }

    #[Test]
    public function a_healthy_lookup_records_nothing()
    {
        $client = $this->client([
            new Response(200, [], json_encode(['success' => 1, 'ip' => ['value' => '1.2.3.4', 'frequency' => 0, 'appears' => 0]])),
        ]);

        $client->check('1.2.3.4', 'a@example.com', 'someone');

        $this->assertNull($this->settings()->get(self::FAILURE_KEY));
    }

    #[Test]
    public function reporting_needs_a_key_but_looking_up_does_not()
    {
        $this->app();

        /** @var StopForumSpam $sfs */
        $sfs = $this->app()->getContainer()->make(StopForumSpam::class);

        $this->assertFalse($sfs->canReport(), 'With no API key configured the forum cannot submit spammers');

        $this->settings()->set(SfsClient::KEY, 'a-real-key');

        $this->assertTrue($this->app()->getContainer()->make(StopForumSpam::class)->canReport());
    }

    #[Test]
    public function the_old_name_still_answers()
    {
        $this->app();

        /** @var StopForumSpam $sfs */
        $sfs = $this->app()->getContainer()->make(StopForumSpam::class);

        // Third party code may call isEnabled(); it has always meant "can report".
        $this->assertEquals($sfs->canReport(), $sfs->isEnabled());
    }

    #[Test]
    public function the_forum_payload_separates_reporting_from_checking()
    {
        $this->app();

        $response = $this->send($this->request('GET', '/api', ['authenticatedAs' => 1]));
        $body = json_decode((string) $response->getBody(), true);

        $sfs = $body['data']['attributes']['fof-anti-spam']['stopforumspam'];

        $this->assertArrayHasKey('canReport', $sfs);
        $this->assertArrayHasKey('enabled', $sfs, 'The old key stays so existing frontends keep working');
        $this->assertEquals($sfs['canReport'], $sfs['enabled']);
    }
}

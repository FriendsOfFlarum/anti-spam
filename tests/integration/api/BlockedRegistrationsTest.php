<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\AntiSpam\Model\BlockedRegistration;
use PHPUnit\Framework\Attributes\Test;

class BlockedRegistrationsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'moderator', 'email' => 'moderator@machine.local', 'is_email_confirmed' => true]
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => 4]
            ],
            'group_permission' => [
                ['permission' => 'fof-anti-spam.viewBlockedRegistrations', 'group_id' => 4]
            ],
            BlockedRegistration::class => [
                // A real time of day, deliberately: a midnight fixture cannot tell a serializer
                // that keeps the time from one that throws it away.
                ['id' => 1, 'ip' => '127.0.0.1', 'email' => 'spammer@machine.local', 'username' => 'spammer', 'attempted_at' => '2020-01-01 14:37:05'],
                ['id' => 2, 'ip' => '127.0.0.2', 'email' => 'later@machine.local', 'username' => 'later', 'attempted_at' => '2021-06-02 09:15:00']
            ]
        ]);
    }

    #[Test]
    public function user_without_permission_cannot_list_blocked_registrations()
    {
        $response = $this->send(
            $this->request(
                'GET',
                '/api/blocked-registrations',
                [
                    'authenticatedAs' => 2,
                ]
            )
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function user_with_permission_can_list_blocked_registrations()
    {
        $response = $this->send(
            $this->request(
                'GET',
                '/api/blocked-registrations',
                [
                    'authenticatedAs' => 3,
                ]
            )
        );

        $this->assertEquals(200, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);

        // assert response has data
        $this->assertArrayHasKey('data', $body);
        $this->assertCount(2, $body['data']);

        // Newest attempt first.
        $data = $body['data'][1];

        $this->assertEquals('1', $data['id']);
        $this->assertEquals('blocked-registrations', $data['type']);
        $this->assertEquals('127.0.0.1', $data['attributes']['ip']);
        $this->assertEquals('spammer@machine.local', $data['attributes']['email']);
        $this->assertEquals('spammer', $data['attributes']['username']);
    }

    #[Test]
    public function the_attempt_time_survives_serialization()
    {
        $response = $this->send(
            $this->request('GET', '/api/blocked-registrations', ['authenticatedAs' => 3])
        );

        $body = json_decode($response->getBody()->getContents(), true);

        $attemptedAt = collect($body['data'])->firstWhere('id', '1')['attributes']['attemptedAt'];

        // Serialized as a date alone ('2020-01-01'), every attempt reads as midnight — and as
        // 1am to anyone an hour ahead of UTC. The time of day has to survive the round trip.
        $this->assertNotEquals('2020-01-01', $attemptedAt, 'The time of day must not be discarded');

        $parsed = new \DateTimeImmutable($attemptedAt);

        $this->assertSame('14:37:05', $parsed->format('H:i:s'));
        $this->assertSame('2020-01-01', $parsed->format('Y-m-d'));
    }

    #[Test]
    public function blocked_registrations_are_listed_newest_first()
    {
        $response = $this->send(
            $this->request('GET', '/api/blocked-registrations', ['authenticatedAs' => 3])
        );

        $ids = array_column(json_decode($response->getBody()->getContents(), true)['data'], 'id');

        // An admin opening this page is looking at what just happened. Without an explicit
        // default sort the database returns insertion order, which puts the oldest first.
        $this->assertSame(['2', '1'], $ids);
    }

    #[Test]
    public function user_without_permission_cannot_delete_blocked_registrations()
    {
        $response = $this->send(
            $this->request(
                'DELETE',
                '/api/blocked-registrations/1',
                [
                    'authenticatedAs' => 3,
                ]
            )
        );

        $this->assertEquals(403, $response->getStatusCode());

        // Assert on the record itself rather than a total, so adding fixtures cannot break this.
        $this->assertNotNull(BlockedRegistration::find(1), 'The record must survive an unauthorised delete');
    }

    #[Test]
    public function user_with_permission_can_delete_blocked_registrations()
    {
        $response = $this->send(
            $this->request(
                'DELETE',
                '/api/blocked-registrations/1',
                [
                    'authenticatedAs' => 1,
                ]
            )
        );

        $this->assertEquals(204, $response->getStatusCode());

        $this->assertNull(BlockedRegistration::find(1), 'The deleted record should be gone');
        $this->assertNotNull(BlockedRegistration::find(2), 'Only the requested record should be deleted');
    }
}

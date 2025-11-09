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
        $this->extension('fof-anti-spam');

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
                ['id' => 1, 'ip' => '127.0.0.1', 'email' => 'spammer@machine.local', 'username' => 'spammer', 'attempted_at' => '2020-01-01 00:00:00']
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
        $this->assertCount(1, $body['data']);

        $data = $body['data'][0];

        $this->assertEquals('1', $data['id']);
        $this->assertEquals('blocked-registrations', $data['type']);
        $this->assertEquals('127.0.0.1', $data['attributes']['ip']);
        $this->assertEquals('spammer@machine.local', $data['attributes']['email']);
        $this->assertEquals('spammer', $data['attributes']['username']);
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

        $blocked = BlockedRegistration::all();

        $this->assertCount(1, $blocked);
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

        $blocked = BlockedRegistration::all();

        $this->assertCount(0, $blocked);
    }
}

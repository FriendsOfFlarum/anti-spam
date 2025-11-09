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

use Flarum\Group\Group;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class ForumAttributesTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-anti-spam');

        $this->prepareDatabase([
            User::class => [
                ['id' => 3, 'username' => 'a_moderator', 'email' => 'a_mod@machine.local', 'is_email_confirmed' => 1],
                ['id' => 4, 'username' => 'toby', 'email' => 'toby@machine.local', 'is_email_confirmed' => 1],
                ['id' => 5, 'username' => 'bad_user', 'email' => 'bad_user@machine.local', 'is_email_confirmed' => 1],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => Group::MODERATOR_ID],
            ],
            'group_permission' => [
                ['group_id' => Group::MODERATOR_ID, 'permission' => 'user.spamblock'],
            ],
        ]);
    }

    #[Test]
    public function normal_user_does_not_see_spamblock_default_options()
    {
        $response = $this->send(
            $this->request('GET', 'api', [
                'authenticatedAs' => 4,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayNotHasKey('fof-anti-spam', $json['data']['attributes']);
    }

    #[Test]
    public function user_with_permission_does_see_spamblock_default_options()
    {
        $response = $this->send(
            $this->request('GET', 'api', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('fof-anti-spam', $json['data']['attributes']);

        $defaultOptions = $json['data']['attributes']['fof-anti-spam']['default-options'];

        $this->assertFalse($defaultOptions['deleteUser']);
        $this->assertFalse($defaultOptions['deletePosts']);
        $this->assertFalse($defaultOptions['deleteDiscussions']);
    }
}

<?php

namespace FoF\AntiSpam\Tests\integration\api;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;

class BlockedRegistrationsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        $this->extension('fof-anti-spam');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'moderator', 'email' => 'moderator@machine.local', 'is_email_confirmed' => true]
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => 4]
            ],
            'group_permission' => [
                ['permission' => 'fof-anti-spam.viewBlockedRegistrations', 'group_id' => 4]
            ]
        ]);
    }

    /**
     * @test
     */
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

    /**
     * @test
     */
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

        $body = json_decode($response->getBody()->getContents());

        // assert response has pagination links
        $this->assertObjectHasProperty('links', $body);
        $this->assertObjectHasProperty('first', $body->links);

        // assert response has data
        $this->assertObjectHasProperty('data', $body);
    }
}

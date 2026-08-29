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

use Flarum\Extend;
use Flarum\Testing\integration\TestCase;
use Flarum\User\RegistrationToken;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * The address an account was created from was thrown away.
 *
 * It is kept for blocked attempts but not for successful ones, which is why reporting a spammer
 * has to scrape it back off their first post and gives up entirely when they never posted.
 */
class RegistrationIpTest extends TestCase
{
    use FakesStopForumSpam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeStopForumSpam();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $this->extend((new Extend\Csrf())->exemptRoute('register')->exemptRoute('users.create'));
    }

    private function register(string $username): int
    {
        $request = $this->request('POST', '/api/users', [
            'json' => [
                'data' => [
                    'type' => 'users',
                    'attributes' => [
                        'username' => $username,
                        'password' => 'too-obscure',
                        'email' => $username.'@example.com',
                    ],
                ],
            ],
        ]);

        $response = $this->send($request);

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        return (int) json_decode((string) $response->getBody(), true)['data']['id'];
    }

    #[Test]
    public function the_address_a_registration_came_from_is_kept()
    {
        $id = $this->register('someone_new');

        // Whatever the proxy put in REMOTE_ADDR, which under the test harness is the 127.0.0.1
        // core falls back to. The point is that it is captured, not discarded.
        $this->assertEquals('127.0.0.1', User::find($id)->registration_ip);
    }

    #[Test]
    public function a_forwarding_header_cannot_choose_the_recorded_address()
    {
        // Anyone can set these. If they decided what we record, they would decide what we check
        // against StopForumSpam and rate limit on, which is to say nothing at all.
        $response = $this->send(
            $this->request('POST', '/api/users', [
                'json' => [
                    'data' => [
                        'type' => 'users',
                        'attributes' => [
                            'username' => 'header_forger',
                            'password' => 'too-obscure',
                            'email' => 'header_forger@example.com',
                        ],
                    ],
                ],
            ])->withHeader('X-Forwarded-For', '203.0.113.9')
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertEquals('127.0.0.1', User::where('username', 'header_forger')->firstOrFail()->registration_ip);
    }

    #[Test]
    public function a_client_cannot_choose_its_own_registration_address()
    {
        $response = $this->send(
            $this->request('POST', '/api/users', [
                'json' => [
                    'data' => [
                        'type' => 'users',
                        'attributes' => [
                            'username' => 'liar',
                            'password' => 'too-obscure',
                            'email' => 'liar@example.com',
                            'registrationIp' => '10.0.0.1',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(403, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function the_address_is_hidden_from_users_who_cannot_spamblock()
    {
        $id = $this->register('someone_new');

        $body = json_decode((string) $this->send($this->request('GET', '/api/users/'.$id))->getBody(), true);

        $this->assertArrayNotHasKey('registrationIp', $body['data']['attributes']);
    }

    #[Test]
    public function the_address_is_visible_to_a_moderator_who_can_spamblock()
    {
        $id = $this->register('someone_new');

        $body = json_decode((string) $this->send($this->request('GET', '/api/users/'.$id, ['authenticatedAs' => 1]))->getBody(), true);

        $this->assertEquals('127.0.0.1', $body['data']['attributes']['registrationIp']);
    }

    #[Test]
    public function an_oauth_registration_records_an_address_too()
    {
        $this->app();

        // This is the shape core gives OAuth: the provider callback issues a RegistrationToken and
        // the browser posts it to /register, which proxies to users.create. The capture has to fire
        // on that path as well, or every social sign-up lands with no address recorded.
        $token = RegistrationToken::generate('github', '1234', [
            'username' => 'via_github',
            'email' => 'via_github@example.com',
        ], ['username' => 'via_github']);
        $token->save();

        $response = $this->send(
            $this->request('POST', '/register', [
                'json' => [
                    'token' => $token->token,
                    'username' => 'via_github',
                    'email' => 'via_github@example.com',
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        $user = User::where('username', 'via_github')->firstOrFail();

        // The internal request carries the address core resolved on the parent. Under the test
        // harness that is the 127.0.0.1 ProcessIp falls back to; in production the proxy headers
        // are present in $_SERVER and ClientIp picks the real client out of them.
        $this->assertNotNull($user->registration_ip, 'An OAuth sign-up must record an address');
        $this->assertEquals('127.0.0.1', $user->registration_ip);
    }

    #[Test]
    public function a_plain_register_route_signup_records_an_address_too()
    {
        $this->app();

        $response = $this->send(
            $this->request('POST', '/register', [
                'json' => ['username' => 'via_form', 'password' => 'too-obscure', 'email' => 'via_form@example.com'],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertNotNull(User::where('username', 'via_form')->firstOrFail()->registration_ip);
    }

    #[Test]
    public function an_existing_account_without_a_recorded_address_still_works()
    {
        $this->app();

        $this->assertNull(User::find(1)->registration_ip);

        $body = json_decode((string) $this->send($this->request('GET', '/api/users/1', ['authenticatedAs' => 1]))->getBody(), true);

        $this->assertNull($body['data']['attributes']['registrationIp'] ?? null);
    }
}

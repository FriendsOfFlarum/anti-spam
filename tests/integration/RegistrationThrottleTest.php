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
use Flarum\User\User;
use FoF\AntiSpam\Throttler\RegistrationThrottler;
use Illuminate\Contracts\Cache\Store;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * Core throttles posting — ten seconds between posts, per user — but nothing throttles account
 * creation, so a script can open accounts as fast as it can send requests. That also makes the
 * per-user post throttle beside the point to anyone holding fifty accounts.
 */
class RegistrationThrottleTest extends TestCase
{
    use FakesStopForumSpam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeStopForumSpam();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $this->extend((new Extend\Csrf())->exemptRoute('register')->exemptRoute('users.create'));
    }

    private function register(string $username, ?int $actor = null): ResponseInterface
    {
        $options = [
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
        ];

        if ($actor !== null) {
            $options['authenticatedAs'] = $actor;
        }

        return $this->send($this->request('POST', '/api/users', $options));
    }

    #[Test]
    public function a_second_registration_from_the_same_address_is_turned_away()
    {
        $this->setting('fof-anti-spam.registrationThrottleSeconds', 30);

        $this->assertEquals(201, $this->register('first_one')->getStatusCode());
        $this->assertEquals(429, $this->register('second_one')->getStatusCode());

        $this->assertNull(User::where('username', 'second_one')->first());
    }

    #[Test]
    public function the_window_is_kept_per_address()
    {
        $this->setting('fof-anti-spam.registrationThrottleSeconds', 30);

        // Driven directly: every request the harness builds carries the same REMOTE_ADDR, so two
        // distinct addresses cannot be told apart over HTTP.
        $container = $this->app()->getContainer();
        $throttle = new RegistrationThrottler($container->make('flarum.settings'), $container->make(Store::class));

        $first = $this->creationRequestFrom('203.0.113.9');

        $this->assertNull($throttle($first), 'The first attempt from an address is allowed');
        $this->assertTrue($throttle($first), 'A second attempt from the same address is throttled');
        $this->assertNull($throttle($this->creationRequestFrom('198.51.100.4')), 'A different address is unaffected');
    }

    #[Test]
    public function other_endpoints_are_left_alone_by_the_throttler()
    {
        $this->setting('fof-anti-spam.registrationThrottleSeconds', 30);

        $container = $this->app()->getContainer();
        $throttle = new RegistrationThrottler($container->make('flarum.settings'), $container->make(Store::class));

        $request = (new ServerRequest(['REMOTE_ADDR' => '203.0.113.9'], [], '/api/posts', 'POST'))
            ->withAttribute('routeName', 'posts.create');

        $this->assertNull($throttle($request));
        $this->assertNull($throttle($request), 'Posting is core\'s business, not this throttler\'s');
    }

    private function creationRequestFrom(string $ip): ServerRequest
    {
        return (new ServerRequest(['REMOTE_ADDR' => $ip], [], '/api/users', 'POST'))
            ->withAttribute('routeName', 'users.create');
    }

    #[Test]
    public function the_throttle_can_be_switched_off()
    {
        $this->setting('fof-anti-spam.registrationThrottleSeconds', 0);

        $this->assertEquals(201, $this->register('first_one')->getStatusCode());
        $this->assertEquals(201, $this->register('second_one')->getStatusCode());
    }

    #[Test]
    public function an_admin_creating_accounts_is_not_throttled()
    {
        $this->setting('fof-anti-spam.registrationThrottleSeconds', 30);

        // Bulk-creating members by hand is a deliberate act, not a signup flood.
        $this->assertEquals(201, $this->register('first_one', 1)->getStatusCode());
        $this->assertEquals(201, $this->register('second_one', 1)->getStatusCode());
    }

    #[Test]
    public function a_rejected_attempt_still_consumes_the_window()
    {
        $this->setting('fof-anti-spam.registrationThrottleSeconds', 30);

        // Otherwise the limit is free to evade: send rubbish until the window is clear.
        $rejected = $this->send(
            $this->request('POST', '/api/users', [
                'json' => ['data' => ['type' => 'users', 'attributes' => ['username' => 'x']]],
            ])
        );

        $this->assertEquals(422, $rejected->getStatusCode());
        $this->assertEquals(429, $this->register('after_the_bad_one')->getStatusCode());
    }

    #[Test]
    public function posting_is_not_affected()
    {
        $this->setting('fof-anti-spam.registrationThrottleSeconds', 30);

        $this->assertEquals(201, $this->register('first_one')->getStatusCode());

        // Only account creation is throttled here; core already handles post flooding.
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 1,
                'json' => ['data' => ['type' => 'discussions', 'attributes' => ['title' => 'Hello there', 'content' => 'Nothing to see.']]],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
    }
}

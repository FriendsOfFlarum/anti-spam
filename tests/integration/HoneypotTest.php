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
use Flarum\Extend;
use Flarum\Testing\integration\TestCase;
use Flarum\User\RegistrationToken;
use Flarum\User\User;
use FoF\AntiSpam\Api\SfsClient;
use FoF\AntiSpam\Model\BlockedRegistration;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * A trap for form-filling bots StopForumSpam has not seen yet: a field people never see, and a
 * signed timestamp that tells a person from a script by how fast the form came back.
 */
class HoneypotTest extends TestCase
{
    use FakesStopForumSpam;

    private const HONEYPOT = 'fof_as_comment';
    private const TOKEN = 'fof_as_token';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeStopForumSpam();
        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');
        $this->extend((new Extend\Csrf())->exemptRoute('register')->exemptRoute('users.create'));
        $this->setting('fof-anti-spam.registrationThrottleSeconds', 0);
    }

    private function enable(bool $strict = false): void
    {
        $this->setting('fof-anti-spam.honeypot.enabled', true);
        $this->setting('fof-anti-spam.honeypot.strict', $strict);
    }

    /**
     * The token the sign-up form is given, from the forum payload a guest receives.
     */
    private function formToken(): ?string
    {
        $response = $this->send($this->request('GET', '/api'));

        return json_decode((string) $response->getBody(), true)['data']['attributes']['fof-anti-spam.formToken'] ?? null;
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function register(string $username, array $extra = []): ResponseInterface
    {
        return $this->send(
            $this->request('POST', '/api/users', [
                'json' => ['data' => ['type' => 'users', 'attributes' => [
                    'username' => $username,
                    'password' => 'too-obscure',
                    'email' => $username.'@example.com',
                ] + $extra]],
            ])
        );
    }

    private function blockedFor(string $username): ?BlockedRegistration
    {
        return BlockedRegistration::where('username', $username)->first();
    }

    /**
     * @return list<string>
     */
    private function reasonsFor(string $username): array
    {
        return json_decode((string) $this->blockedFor($username)?->reasons, true)['reasons'] ?? [];
    }

    /**
     * Take a form token now, and return it as if submitted the given number of seconds later.
     */
    private function tokenSubmittedAfter(int $seconds): string
    {
        Carbon::setTestNow('2026-05-01 12:00:00');
        $token = $this->formToken();
        Carbon::setTestNow(Carbon::parse('2026-05-01 12:00:00')->addSeconds($seconds));

        return (string) $token;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function it_is_off_until_an_admin_turns_it_on()
    {
        // A new check must not start refusing sign-ups on existing forums the moment they upgrade.
        $response = $this->register('filled_in', [self::HONEYPOT => 'http://spam.example']);

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertNull($this->formToken(), 'No token is handed out while it is off');
    }

    #[Test]
    public function a_person_who_leaves_the_field_empty_and_takes_their_time_signs_up()
    {
        $this->enable();
        $token = $this->tokenSubmittedAfter(20);

        $response = $this->register('patient_person', [self::HONEYPOT => '', self::TOKEN => $token]);

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function a_filled_in_honeypot_is_refused_and_recorded()
    {
        $this->enable();
        $token = $this->tokenSubmittedAfter(20);

        $response = $this->register('form_filler', [self::HONEYPOT => 'http://spam.example', self::TOKEN => $token]);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertNull(User::where('username', 'form_filler')->first());
        $this->assertSame(['honeypot'], $this->reasonsFor('form_filler'));
    }

    #[Test]
    public function a_bot_caught_by_the_trap_costs_no_stopforumspam_lookup()
    {
        $this->enable();

        $this->register('form_filler', [self::HONEYPOT => 'anything']);

        $this->assertSame([], $this->sfsLookups());
    }

    #[Test]
    public function a_form_sent_back_faster_than_a_person_could_is_refused()
    {
        $this->enable();
        $token = $this->tokenSubmittedAfter(1);

        $response = $this->register('speedy_script', [self::HONEYPOT => '', self::TOKEN => $token]);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertSame(['tooFast'], $this->reasonsFor('speedy_script'));
    }

    #[Test]
    public function the_minimum_time_can_be_changed()
    {
        $this->enable();
        $this->setting('fof-anti-spam.honeypot.minSeconds', 30);
        $token = $this->tokenSubmittedAfter(20);

        $this->assertEquals(422, $this->register('quick_typist', [self::TOKEN => $token])->getStatusCode());
    }

    #[Test]
    public function a_forged_token_is_refused()
    {
        $this->enable();
        $forged = (Carbon::now()->timestamp - 60).'.not-the-signature';

        $response = $this->register('forger', [self::TOKEN => $forged]);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertSame(['formToken'], $this->reasonsFor('forger'));
    }

    #[Test]
    public function an_old_timestamp_cannot_be_reused_by_rewriting_it()
    {
        // The timestamp is part of what is signed, so making a fresh token "older" breaks it.
        $this->enable();
        $token = $this->tokenSubmittedAfter(0);
        [, $signature] = explode('.', $token, 2);

        $response = $this->register('rewriter', [self::TOKEN => (Carbon::now()->timestamp - 600).'.'.$signature]);

        $this->assertEquals(422, $response->getStatusCode());
    }

    #[Test]
    public function lenient_mode_lets_a_sign_up_without_the_form_through()
    {
        // Apps and SSO bridges register over the API without the form; only strict mode refuses them.
        $this->enable();

        $response = $this->register('api_client');

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function strict_mode_refuses_a_sign_up_without_the_form()
    {
        $this->enable(strict: true);

        $response = $this->register('direct_poster');

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertSame(['noForm'], $this->reasonsFor('direct_poster'));
    }

    #[Test]
    public function strict_mode_refuses_an_expired_token()
    {
        $this->enable(strict: true);
        $token = $this->tokenSubmittedAfter(2 * 86400);

        $this->assertEquals(422, $this->register('stale_form', [self::TOKEN => $token])->getStatusCode());
    }

    #[Test]
    public function an_admin_creating_an_account_is_exempt_even_in_strict_mode()
    {
        $this->enable(strict: true);

        $response = $this->send(
            $this->request('POST', '/api/users', [
                'authenticatedAs' => 1,
                'json' => ['data' => ['type' => 'users', 'attributes' => [
                    'username' => 'made_by_admin', 'password' => 'too-obscure', 'email' => 'made@example.com',
                ]]],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function the_forum_sign_up_route_is_checked_too()
    {
        $this->enable();
        $token = $this->tokenSubmittedAfter(20);

        $response = $this->send(
            $this->request('POST', '/register', [
                'json' => [
                    'username' => 'forum_filler', 'password' => 'too-obscure', 'email' => 'forum_filler@example.com',
                    self::HONEYPOT => 'filled', self::TOKEN => $token,
                ],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertNull(User::where('username', 'forum_filler')->first());
    }

    #[Test]
    public function an_oauth_sign_up_through_the_form_is_checked_the_same_way()
    {
        $this->enable();
        $this->app();
        $registration = RegistrationToken::generate('github', '4242', ['username' => 'oauth_filler', 'email' => 'oauth@example.com'], []);
        $registration->save();

        $response = $this->register('oauth_filler', ['token' => $registration->token, self::HONEYPOT => 'filled']);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertSame('github', $this->blockedFor('oauth_filler')?->provider);
    }

    #[Test]
    public function a_trapped_bot_is_never_reported_to_stopforumspam()
    {
        // The trap says something about the client, nothing about the identity it typed in.
        $this->enable();
        $this->setting(SfsClient::KEY, 'a-real-key');

        $this->register('form_filler', [self::HONEYPOT => 'filled']);

        $this->assertSame([], $this->sfsReports());
    }
}

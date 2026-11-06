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
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\RegistrationToken;
use Flarum\User\User;
use FoF\AntiSpam\Model\BlockedRegistration;
use Laminas\Diactoros\ServerRequest;

/**
 * The StopForumSpam check covers both ways of creating an account, judges the address the
 * request really came from, and reads the values core will register.
 */
class RegistrationCheckTest extends TestCase
{
    use FakesStopForumSpam;
    use MakesClientRequests;
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension(...$this->extensions());

        $this->extend(
            (new Extend\Csrf())
                ->exemptRoute('register')
                ->exemptRoute('users.create')
        );

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
        ]);
    }

    protected function extensions(): array
    {
        return ['fof-anti-spam'];
    }

    /**
     * A registration from a given connection address, through either route.
     */
    protected function registration(string $route, array $attributes, string $remoteAddr = '10.0.0.5', array $headers = []): ServerRequest
    {
        $attributes += ['password' => 'too-obscure'];

        return $this->rawRegistration(
            $route === 'api' ? '/api/users' : '/register',
            $route === 'api' ? ['data' => ['attributes' => $attributes]] : $attributes,
            $remoteAddr,
            $headers
        );
    }

    protected function rawRegistration(string $path, array $body, string $remoteAddr = '10.0.0.5', array $headers = []): ServerRequest
    {
        return $this->requestWithJsonBody($this->clientRequest('POST', $path, $remoteAddr, $headers), $body);
    }

    protected function assertRefused($response): void
    {
        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('validation_error', $body['errors'][0]['code']);
        $this->assertEquals('/data/attributes/username', $body['errors'][0]['source']['pointer']);
    }

    public function routes(): array
    {
        return ['form' => ['register'], 'api' => ['api']];
    }

    /**
     * @test
     * @dataProvider routes
     */
    public function a_listed_email_is_refused(string $route)
    {
        $this->fakeSfs();

        $response = $this->send($this->registration($route, ['username' => 'spammer', 'email' => self::$listed['email']]));

        $this->assertRefused($response);
        $this->assertNull(User::where('username', 'spammer')->first());
        $this->assertEquals(1, BlockedRegistration::where('email', self::$listed['email'])->count());
    }

    /**
     * @test
     * @dataProvider routes
     */
    public function a_clean_registration_is_checked_once_and_succeeds(string $route)
    {
        $this->fakeSfs();

        $response = $this->send($this->registration($route, ['username' => 'clean', 'email' => 'clean@machine.local']));

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(['10.0.0.5'], $this->checkedIps(), 'The registration should be checked exactly once');
    }

    /**
     * @test
     */
    public function an_admin_creating_an_account_through_the_api_is_not_checked()
    {
        $this->fakeSfs();

        $request = $this->registration('api', ['username' => 'spammer', 'email' => self::$listed['email']], self::$listed['ip']);

        $response = $this->send($this->requestAsUser($request, 1));

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals([], $this->checkedIps());
    }

    /**
     * @test
     */
    public function a_member_creating_an_account_through_the_api_is_checked()
    {
        $this->fakeSfs();

        $request = $this->registration('api', ['username' => 'sock', 'email' => 'sock@machine.local'], self::$listed['ip']);

        $this->assertRefused($this->send($this->requestAsUser($request, 2)));
    }

    /**
     * @test
     * @dataProvider routes
     */
    public function forwarding_headers_do_not_choose_the_address_checked(string $route)
    {
        $this->fakeSfs();

        $response = $this->send($this->registration($route, ['username' => 'clean', 'email' => 'clean@machine.local'], '10.0.0.5', [
            'Client-IP' => self::$listed['ip'],
            'X-Forwarded-For' => self::$listed['ip'],
            'CF-Connecting-IP' => self::$listed['ip'],
        ]));

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(['10.0.0.5'], $this->checkedIps());
    }

    /**
     * @test
     * @dataProvider routes
     */
    public function a_listed_address_cannot_hide_behind_a_forwarding_header(string $route)
    {
        $this->fakeSfs();

        $response = $this->send($this->registration($route, ['username' => 'spammer', 'email' => 'spammer@machine.local'], self::$listed['ip'], [
            'Client-IP' => '1.1.1.1',
            'X-Forwarded-For' => '1.1.1.1',
        ]));

        $this->assertRefused($response);
        $this->assertEquals(self::$listed['ip'], BlockedRegistration::where('username', 'spammer')->value('ip'));
    }

    /**
     * Core reads `data`, then `attributes.*` within it, and Arr::get prefers a literal dotted key:
     * a top-level `data.attributes` key must not hide the attributes core will register.
     *
     * @test
     */
    public function a_dotted_data_key_does_not_hide_the_attributes()
    {
        $this->fakeSfs();

        $response = $this->send($this->rawRegistration('/api/users', [
            'data.attributes' => null,
            'data' => ['attributes' => ['username' => 'spammer', 'email' => self::$listed['email'], 'password' => 'too-obscure']],
        ]));

        $this->assertRefused($response);
        $this->assertNull(User::where('username', 'spammer')->first());
    }

    /**
     * @test
     */
    public function a_dotted_attribute_key_is_checked_as_core_registers_it()
    {
        $this->fakeSfs();

        $response = $this->send($this->rawRegistration('/api/users', [
            'data' => [
                'attributes.email' => self::$listed['email'],
                'attributes' => ['username' => 'spammer', 'email' => 'clean@machine.local', 'password' => 'too-obscure'],
            ],
        ]));

        $this->assertRefused($response);
        $this->assertNull(User::where('email', self::$listed['email'])->first());
    }

    /**
     * @test
     * @dataProvider routes
     */
    public function a_numeric_username_is_checked(string $route)
    {
        $this->setting('fof-anti-spam.username', true);
        $this->fakeSfs();

        $response = $this->send($this->registration($route, ['username' => (int) self::$listed['username'], 'email' => 'numeric@machine.local']));

        $this->assertRefused($response);
    }

    /**
     * @test
     */
    public function a_refused_oauth_registration_is_recorded_with_its_provider()
    {
        $this->fakeSfs();

        $token = RegistrationToken::generate('github', 'gh-1', ['email' => 'oauth@machine.local'], ['login' => 'octo']);
        $token->save();

        $response = $this->send($this->registration('api', [
            'username' => 'octo',
            'email' => 'oauth@machine.local',
            'token' => $token->token,
        ], self::$listed['ip']));

        $this->assertRefused($response);

        $blocked = BlockedRegistration::where('username', 'octo')->firstOrFail();
        $this->assertEquals('github', $blocked->provider);
    }

    public function tokenAttributes(): array
    {
        $cases = [];

        foreach (['form' => 'register', 'api' => 'api'] as $name => $route) {
            $cases["email, $name"] = [$route, 'email'];
            $cases["username, $name"] = [$route, 'username'];
        }

        return $cases;
    }

    /**
     * Core applies the token's attributes over the body's, so those are what the account carries.
     *
     * @test
     * @dataProvider tokenAttributes
     */
    public function an_oauth_registration_is_checked_against_the_token_attributes(string $route, string $field)
    {
        $this->setting('fof-anti-spam.username', true);
        $this->fakeSfs();

        $token = RegistrationToken::generate('github', 'gh-2', [$field => self::$listed[$field]], []);
        $token->save();

        $response = $this->send($this->registration($route, [
            'username' => 'decoy',
            'email' => 'decoy@machine.local',
            'token' => $token->token,
        ]));

        $this->assertRefused($response);
        $this->assertEquals(self::$listed[$field], BlockedRegistration::query()->value($field));
    }

    /**
     * The inverse: a listed value the token replaces never reaches the account, so it is not judged.
     *
     * @test
     * @dataProvider routes
     */
    public function a_body_value_the_token_replaces_is_not_checked(string $route)
    {
        $this->fakeSfs();

        $token = RegistrationToken::generate('github', 'gh-3', ['email' => 'oauth-clean@machine.local'], []);
        $token->save();

        $response = $this->send($this->registration($route, [
            'username' => 'octo',
            'email' => self::$listed['email'],
            'token' => $token->token,
        ]));

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(['oauth-clean@machine.local'], array_column($this->sfs->checked, 'email'));
        $this->assertEquals('oauth-clean@machine.local', User::where('username', 'octo')->value('email'));
    }
}

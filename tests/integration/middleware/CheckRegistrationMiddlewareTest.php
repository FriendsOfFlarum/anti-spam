<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\integration\middleware;

use Flarum\Extend;
use Flarum\Testing\integration\TestCase;
use Flarum\User\RegistrationToken;
use Flarum\User\User;
use FoF\AntiSpam\Model\BlockedRegistration;
use FoF\AntiSpam\Tests\integration\FakesStopForumSpam;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class CheckRegistrationMiddlewareTest extends TestCase
{
    use FakesStopForumSpam;

    public function setUp(): void
    {
        parent::setUp();

        $this->fakeStopForumSpam();
        $this->knownSpammer('xrumer');

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $this->extend(
            (new Extend\Csrf)->exemptRoute('register')->exemptRoute('users.create')
        );
    }

    /**
     * Register straight against the JSON:API resource, the way a script does.
     *
     * @param array<string, string> $attributes
     */
    private function registerViaApi(array $attributes): ResponseInterface
    {
        return $this->send(
            $this->request('POST', '/api/users', [
                'json' => [
                    'data' => [
                        'type' => 'users',
                        'attributes' => $attributes,
                    ],
                ],
            ])
        );
    }

    #[Test]
    public function the_lookup_is_faked_and_no_test_here_reaches_the_real_api()
    {
        $this->assertSfsClientIsFaked();

        // A name StopForumSpam has certainly never heard of. If it is turned away, the verdict came
        // from the fake — a live lookup would wave it straight through.
        $this->knownSpammer('quietbadger_4f2b');

        $response = $this->registerViaApi([
            'username' => 'quietbadger_4f2b',
            'password' => 'too-obscure',
            'email' => 'quietbadger@example.com',
        ]);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertNotEmpty($this->sfsLookups(), 'The extension should have consulted the lookup');
    }

    #[Test]
    public function it_blocks_registration_via_the_api_users_endpoint()
    {
        // /register is only a wrapper that proxies to this endpoint, so matching on the forum path
        // left the API wide open: anyone scripting POST /api/users skipped the lookup entirely.
        $response = $this->registerViaApi([
            'username' => 'xrumer',
            'password' => 'too-obscure',
            'email' => 'testing@xrumer.ru',
        ]);

        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('validation_error', $body['errors'][0]['code']);
        $this->assertEquals('/data/attributes/username', $body['errors'][0]['source']['pointer']);

        $this->assertNull(User::where('username', 'xrumer')->first(), 'Blocked spammer should not be created');
    }

    #[Test]
    public function it_records_a_blocked_registration_made_via_the_api()
    {
        $this->registerViaApi([
            'username' => 'xrumer',
            'password' => 'too-obscure',
            'email' => 'testing@xrumer.ru',
        ]);

        $blocked = BlockedRegistration::where('username', 'xrumer')->first();

        $this->assertNotNull($blocked, 'Blocking via the API should be recorded like blocking via /register');
        $this->assertEquals('testing@xrumer.ru', $blocked->email);
    }

    #[Test]
    public function it_allows_clean_registration_via_the_api_users_endpoint()
    {
        $response = $this->registerViaApi([
            'username' => 'cleanapiuser',
            'password' => 'too-obscure',
            'email' => 'cleanapi@example.com',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertNotNull(User::where('username', 'cleanapiuser')->first());
    }

    #[Test]
    public function it_does_not_block_an_admin_creating_an_account()
    {
        // POST /api/users is also how an admin adds a user by hand. That is a deliberate act, not
        // a registration attempt, so StopForumSpam recognising the address must not stop it.
        $response = $this->send(
            $this->request('POST', '/api/users', [
                'authenticatedAs' => 1,
                'json' => [
                    'data' => [
                        'type' => 'users',
                        'attributes' => [
                            'username' => 'xrumer',
                            'password' => 'too-obscure',
                            'email' => 'testing@xrumer.ru',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
        $this->assertNotNull(User::where('username', 'xrumer')->first());
    }

    #[Test]
    public function it_attributes_a_blocked_oauth_registration_to_its_provider()
    {
        // The OAuth token used to be read off a flat request body; it now arrives nested under
        // data.attributes, and losing it would silently record the block as a plain sign-up.
        $this->app();

        $token = RegistrationToken::generate('github', '1234', [
            'username' => 'xrumer',
            'email' => 'testing@xrumer.ru',
        ], ['username' => 'xrumer', 'email' => 'testing@xrumer.ru']);
        $token->save();

        $response = $this->send(
            $this->request('POST', '/register', [
                'json' => [
                    'token' => $token->token,
                    'username' => 'xrumer',
                    'email' => 'testing@xrumer.ru',
                ],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());

        $blocked = BlockedRegistration::where('username', 'xrumer')->first();

        $this->assertNotNull($blocked);
        $this->assertEquals('github', $blocked->provider, 'Blocked OAuth registration should keep its provider');
    }

    #[Test]
    public function it_skips_the_api_spam_check_when_sfs_lookup_disabled()
    {
        $this->setting('fof-anti-spam.sfs-lookup', false);

        $response = $this->registerViaApi([
            'username' => 'xrumer',
            'password' => 'too-obscure',
            'email' => 'testing@xrumer.ru',
        ]);

        $this->assertEquals(201, $response->getStatusCode());
    }

    #[Test]
    public function it_blocks_registration_with_known_spammer_username_and_email()
    {
        // Use same data as RegistrationTest to ensure consistency
        $response = $this->send(
            $this->request('POST', '/register', [
                'json' => [
                    'username' => 'xrumer',
                    'password' => 'too-obscure',
                    'email' => 'testing@xrumer.ru',
                ]
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertEquals('validation_error', $body['errors'][0]['code']);
        $this->assertEquals('/data/attributes/username', $body['errors'][0]['source']['pointer']);
    }

    #[Test]
    public function the_lookup_uses_the_remote_address_and_not_a_forwarding_header()
    {
        // Forwarding headers are the client's to set. If one decided which address we looked up,
        // a spammer could have us check a clean address every time. Getting the true client
        // address into REMOTE_ADDR is the reverse proxy's job.
        $this->send(
            $this->request('POST', '/register', [
                'json' => ['username' => 'headeruser', 'password' => 'too-obscure', 'email' => 'header@example.com'],
            ])->withHeader('X-Forwarded-For', '203.0.113.9')
        );

        $lookups = $this->sfsLookups();

        $this->assertNotEmpty($lookups);
        $this->assertNotEquals('203.0.113.9', $lookups[0]['ip'], 'A forged header must not choose the address we check');
    }

    #[Test]
    public function it_allows_registration_with_clean_credentials()
    {
        $response = $this->send(
            $this->request('POST', '/register', [
                'json' => [
                    'username' => 'cleanuser',
                    'password' => 'too-obscure',
                    'email' => 'clean@example.com',
                ]
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_missing_email_gracefully()
    {
        $response = $this->send(
            $this->request('POST', '/register', [
                'json' => [
                    'username' => 'testuser',
                    'password' => 'too-obscure',
                    // email missing - middleware passes through, lets normal validation handle it
                ]
            ])
        );

        // Should get validation error from Flarum's normal validation
        $this->assertEquals(422, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_missing_username_gracefully()
    {
        $response = $this->send(
            $this->request('POST', '/register', [
                'json' => [
                    // username missing
                    'password' => 'too-obscure',
                    'email' => 'test@example.com',
                ]
            ])
        );

        // Should get validation error from Flarum's normal validation
        $this->assertEquals(422, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_both_missing_gracefully()
    {
        $response = $this->send(
            $this->request('POST', '/register', [
                'json' => [
                    'password' => 'too-obscure',
                    // both username and email missing
                ]
            ])
        );

        // Should get normal validation error
        $this->assertEquals(422, $response->getStatusCode());
    }

    #[Test]
    public function it_skips_spam_check_when_sfs_lookup_disabled()
    {
        $this->setting('fof-anti-spam.sfs-lookup', false);

        $response = $this->send(
            $this->request('POST', '/register', [
                'json' => [
                    'username' => 'xrumer',
                    'password' => 'too-obscure',
                    'email' => 'testing@xrumer.ru',
                ]
            ])
        );

        // Should succeed because SFS lookup is disabled
        $this->assertEquals(201, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_empty_string_credentials()
    {
        $response = $this->send(
            $this->request('POST', '/register', [
                'json' => [
                    'username' => '',
                    'password' => 'too-obscure',
                    'email' => '',
                ]
            ])
        );

        // Should let normal validation handle empty strings
        $this->assertEquals(422, $response->getStatusCode());
    }

}

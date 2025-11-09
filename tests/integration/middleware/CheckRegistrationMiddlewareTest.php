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
use PHPUnit\Framework\Attributes\Test;

class CheckRegistrationMiddlewareTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-anti-spam');

        $this->extend(
            (new Extend\Csrf)->exemptRoute('register')
        );
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

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('validation_error', $body['errors'][0]['code']);
        $this->assertEquals('/data/attributes/username', $body['errors'][0]['source']['pointer']);
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

    #[Test]
    public function it_extracts_ip_from_x_forwarded_for_header()
    {
        // This is harder to test directly, but we can verify the registration works
        // with X-Forwarded-For header present
        $response = $this->send(
            $this->request('POST', '/register', [
                'json' => [
                    'username' => 'testxforwarduser',
                    'password' => 'too-obscure',
                    'email' => 'testxforward@example.com',
                ]
            ])->withHeader('X-Forwarded-For', '1.2.3.4, 5.6.7.8')
        );

        // Should succeed with valid credentials even with proxy header
        $this->assertEquals(201, $response->getStatusCode());
    }

    #[Test]
    public function it_handles_cloudflare_connecting_ip_header()
    {
        $response = $this->send(
            $this->request('POST', '/register', [
                'json' => [
                    'username' => 'testcfuser',
                    'password' => 'too-obscure',
                    'email' => 'testcf@example.com',
                ]
            ])->withHeader('CF-Connecting-IP', '8.8.8.8')
        );

        // Should succeed with valid credentials and CF header
        $this->assertEquals(201, $response->getStatusCode());
    }
}

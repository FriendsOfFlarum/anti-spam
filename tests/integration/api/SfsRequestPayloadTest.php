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

use Flarum\Testing\integration\TestCase;
use FoF\AntiSpam\Api\SfsClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Cache\Store;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;

/**
 * What actually goes over the wire to StopForumSpam.
 *
 * The "use hashed email address" setting exists so a forum need not hand a third party its members'
 * addresses. Its help text says so: "Pass a MD5 hash of the email address should you wish to not
 * pass the email address itself." The address was being sent anyway, alongside the hash.
 */
class SfsRequestPayloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');
    }

    /**
     * Run a lookup and return the form fields the client actually sent.
     *
     * @return array<string, mixed>
     */
    private function payloadFor(?string $email): array
    {
        $history = [];

        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode(['success' => 1])),
        ]));
        $stack->push(Middleware::history($history));

        $container = $this->app()->getContainer();

        $client = new SfsClient(
            $container->make('flarum.settings'),
            $container->make(Store::class),
            $container->make(LoggerInterface::class),
            new Client(['handler' => $stack])
        );

        $client->check('1.2.3.4', $email, 'someone');

        $this->assertCount(1, $history, 'Expected exactly one request to StopForumSpam');

        parse_str((string) $history[0]['request']->getBody(), $fields);

        return $fields;
    }

    #[Test]
    public function the_address_is_sent_when_hashing_is_off()
    {
        $this->app();

        $payload = $this->payloadFor('someone@example.com');

        $this->assertEquals('someone@example.com', $payload['email'] ?? null);
        $this->assertArrayNotHasKey('emailhash', $payload);
    }

    #[Test]
    public function only_the_hash_is_sent_when_hashing_is_on()
    {
        $this->setting('fof-anti-spam.emailhash', true);
        $this->app();

        $payload = $this->payloadFor('someone@example.com');

        $this->assertEquals(md5('someone@example.com'), $payload['emailhash'] ?? null);
        $this->assertArrayNotHasKey(
            'email',
            $payload,
            'Sending the address alongside the hash defeats the entire point of the setting'
        );
    }

    #[Test]
    public function the_plain_address_never_appears_anywhere_in_the_body_when_hashing_is_on()
    {
        $this->setting('fof-anti-spam.emailhash', true);
        $this->app();

        $history = [];

        $stack = HandlerStack::create(new MockHandler([new Response(200, [], json_encode(['success' => 1]))]));
        $stack->push(Middleware::history($history));

        $container = $this->app()->getContainer();

        (new SfsClient(
            $container->make('flarum.settings'),
            $container->make(Store::class),
            $container->make(LoggerInterface::class),
            new Client(['handler' => $stack])
        ))->check('1.2.3.4', 'someone@example.com', 'someone');

        $this->assertStringNotContainsString(
            'someone%40example.com',
            (string) $history[0]['request']->getBody(),
            'The address must not survive anywhere in the request, however it is encoded'
        );
    }

    #[Test]
    public function no_email_field_is_sent_at_all_when_there_is_no_address()
    {
        $this->app();

        $payload = $this->payloadFor(null);

        $this->assertArrayNotHasKey('email', $payload);
        $this->assertArrayNotHasKey('emailhash', $payload);
    }

    #[Test]
    public function no_hash_of_nothing_is_sent_when_there_is_no_address()
    {
        $this->setting('fof-anti-spam.emailhash', true);
        $this->app();

        $payload = $this->payloadFor(null);

        // md5(null) is the hash of the empty string, which is a real value that means nothing.
        $this->assertArrayNotHasKey('emailhash', $payload);
        $this->assertArrayNotHasKey('email', $payload);
    }

    #[Test]
    public function the_other_fields_are_unaffected()
    {
        $this->app();

        $payload = $this->payloadFor('someone@example.com');

        $this->assertEquals('1.2.3.4', $payload['ip'] ?? null);
        $this->assertEquals('someone', $payload['username'] ?? null);
        $this->assertArrayHasKey('json', $payload);
        $this->assertArrayHasKey('confidence', $payload);
    }
}

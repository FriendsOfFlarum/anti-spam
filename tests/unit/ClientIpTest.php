<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\unit;

use FoF\AntiSpam\Http\ClientIp;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClientIpTest extends TestCase
{
    /**
     * @param array<string, mixed> $serverParams
     * @param array<string, mixed> $headers
     * @param array<string, mixed> $attributes
     */
    private function request(array $serverParams = [], array $headers = [], array $attributes = []): ServerRequest
    {
        $request = new ServerRequest($serverParams, [], '/', 'POST');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        foreach ($attributes as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        return $request;
    }

    #[Test]
    public function it_reads_the_remote_address()
    {
        $this->assertEquals('192.0.2.7', ClientIp::fromRequest($this->request(['REMOTE_ADDR' => '192.0.2.7'])));
    }

    #[Test]
    public function it_handles_ipv6()
    {
        $this->assertEquals('2001:db8::1', ClientIp::fromRequest($this->request(['REMOTE_ADDR' => '2001:db8::1'])));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function forgeableHeaders(): iterable
    {
        yield 'x-forwarded-for' => ['X-Forwarded-For'];
        yield 'cloudflare' => ['CF-Connecting-IP'];
        yield 'client-ip' => ['Client-IP'];
        yield 'x-real-ip' => ['X-Real-IP'];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('forgeableHeaders')]
    public function a_forwarding_header_never_overrides_the_remote_address(string $header)
    {
        // Anyone reaching the site directly can set these. Honouring them would let a spammer pick
        // the address we check, record and rate limit against. Getting the real address into
        // REMOTE_ADDR is the proxy's job.
        $request = $this->request(['REMOTE_ADDR' => '192.0.2.7'], [$header => '203.0.113.9']);

        $this->assertEquals('192.0.2.7', ClientIp::fromRequest($request));
    }

    #[Test]
    public function a_forwarding_header_alone_yields_nothing()
    {
        $this->assertNull(ClientIp::fromRequest($this->request([], ['X-Forwarded-For' => '203.0.113.9'])));
    }

    #[Test]
    public function it_falls_back_to_the_resolved_attribute_for_internal_requests()
    {
        // /register proxies to users.create through the API client, which forwards the address
        // core resolved rather than server params.
        $this->assertEquals('192.0.2.7', ClientIp::fromRequest($this->request([], [], ['ipAddress' => '192.0.2.7'])));
    }

    #[Test]
    public function the_remote_address_wins_over_the_attribute()
    {
        $request = $this->request(['REMOTE_ADDR' => '192.0.2.7'], [], ['ipAddress' => '198.51.100.4']);

        $this->assertEquals('192.0.2.7', ClientIp::fromRequest($request));
    }

    #[Test]
    public function it_ignores_rubbish()
    {
        $this->assertNull(ClientIp::fromRequest($this->request(['REMOTE_ADDR' => 'not-an-ip'])));
        $this->assertNull(ClientIp::fromRequest($this->request(['REMOTE_ADDR' => ''])));
        $this->assertNull(ClientIp::fromRequest($this->request()));
    }
}

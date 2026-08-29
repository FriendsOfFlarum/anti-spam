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

use FoF\AntiSpam\Api\SfsClient;
use FoF\AntiSpam\Api\SfsResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Stands in for the real StopForumSpam API.
 *
 * Every registration test used to make a live HTTP call to api.stopforumspam.org. Fanned out over
 * the CI matrix that is a few hundred requests per push against someone else's service, and it
 * made the suite fail for reasons that had nothing to do with the code (see the flake on #34).
 *
 * Register a verdict per username with `FakeSfsClient::willReport()`; anything not registered comes
 * back clean.
 *
 * @see FakesStopForumSpam
 */
class FakeSfsClient extends SfsClient
{
    /**
     * Canned verdicts, keyed by username.
     *
     * @var array<string, array<string, mixed>>
     */
    public static array $verdicts = [];

    /**
     * Every payload passed to report(), so tests can assert what would have been sent.
     *
     * @var array<int, array<string, mixed>>
     */
    public static array $reported = [];

    /**
     * Every set of arguments check() was called with.
     *
     * @var array<int, array{ip: ?string, email: ?string, username: ?string}>
     */
    public static array $checked = [];

    public function __construct()
    {
        // Deliberately does not call the parent constructor: there is no Guzzle client, no
        // settings and no cache here, because nothing should reach the network.
    }

    /**
     * Mark a username as a known spammer, optionally tuning the fields SFS would return.
     *
     * @param array<string, mixed> $overrides
     */
    public static function willReport(string $username, array $overrides = []): void
    {
        self::$verdicts[$username] = array_replace_recursive([
            'success' => true,
            'ip' => ['value' => '109.104.183.88', 'appears' => 1, 'frequency' => 255, 'confidence' => 99.9, 'blacklisted' => 1],
            'email' => ['value' => 'testing@xrumer.ru', 'appears' => 1, 'frequency' => 255, 'confidence' => 99.9, 'blacklisted' => 1],
            'username' => ['value' => $username, 'appears' => 1, 'frequency' => 255, 'confidence' => 99.9, 'blacklisted' => 1],
        ], $overrides);
    }

    public static function reset(): void
    {
        self::$verdicts = [];
        self::$reported = [];
        self::$checked = [];
    }

    public function check(?string $ip, ?string $email, ?string $username): SfsResponse
    {
        self::$checked[] = ['ip' => $ip, 'email' => $email, 'username' => $username];

        if ($username !== null && isset(self::$verdicts[$username])) {
            return new SfsResponse(self::$verdicts[$username]);
        }

        // Clean: the API answered, and had nothing on any of the three fields.
        return new SfsResponse([
            'success' => true,
            'ip' => ['value' => (string) $ip, 'appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'blacklisted' => 0],
            'email' => ['value' => (string) $email, 'appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'blacklisted' => 0],
            'username' => ['value' => (string) $username, 'appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'blacklisted' => 0],
        ]);
    }

    public function report(array $data): ResponseInterface
    {
        self::$reported[] = $data;

        return new \Laminas\Diactoros\Response\TextResponse('success');
    }
}

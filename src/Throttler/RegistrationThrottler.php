<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Throttler;

use Flarum\Settings\SettingsRepositoryInterface;
use FoF\AntiSpam\Http\ClientIp;
use Illuminate\Contracts\Cache\Repository;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Rate limits account creation per address.
 *
 * Core ships a throttler for posting but none for registration, so nothing stands between a script
 * and as many accounts as it cares to open — which also makes the per-user post throttle beside
 * the point to anyone holding fifty of them.
 *
 * Both signup routes are covered by this one throttler: /register proxies to users.create through
 * the API client, and the client's middleware stack includes ThrottleApi, so neither path is
 * missed and neither is counted twice.
 *
 * The window is consumed by the attempt rather than by the account, so sending rubbish until the
 * window clears does not work.
 */
class RegistrationThrottler
{
    public const SETTING = 'fof-anti-spam.registrationThrottleSeconds';

    protected const string CACHE_PREFIX = 'fof-anti-spam.registration-throttle.';

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Repository $cache
    ) {
    }

    public function __invoke(ServerRequestInterface $request): ?bool
    {
        if ($request->getAttribute('routeName') !== 'users.create') {
            return null;
        }

        $seconds = (int) $this->settings->get(self::SETTING);

        if ($seconds <= 0) {
            return null;
        }

        // An admin adding members by hand is doing something deliberate.
        $actor = $request->getAttribute('actorReference')?->getActor();

        if ($actor !== null && $actor->isAdmin()) {
            return false;
        }

        $ip = ClientIp::fromRequest($request);

        if ($ip === null) {
            return null;
        }

        $key = self::CACHE_PREFIX.self::network($ip);

        // One atomic claim: a separate read and write let concurrent requests all see the slot
        // free and all go through.
        return $this->cache->add($key, 1, $seconds) ? null : true;
    }

    /**
     * The part of an address that identifies one subscriber.
     *
     * An IPv6 subscriber is routinely given a whole /64, so the full address would let every
     * registration arrive from a fresh one. IPv4 stays per address: a /24 is commonly shared by
     * unrelated households behind one provider. An IPv4-mapped IPv6 address is its IPv4 address.
     *
     * Someone holding a /56 or /48 can still rotate through /64s; going wider would start to
     * throttle unrelated customers of providers that assign from a shared /48.
     */
    protected static function network(string $ip): string
    {
        $packed = inet_pton($ip);

        // ClientIp only passes validated addresses, but an unparseable one must not share a key
        // with every other.
        if ($packed === false) {
            return md5($ip);
        }

        if (strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            $packed = substr($packed, 12);
        } elseif (strlen($packed) === 16) {
            $packed = substr($packed, 0, 8);
        }

        return bin2hex($packed);
    }
}

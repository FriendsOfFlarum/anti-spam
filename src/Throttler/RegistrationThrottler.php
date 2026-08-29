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
use Illuminate\Contracts\Cache\Store;
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

    private const CACHE_PREFIX = 'fof-anti-spam.registration-throttle.';

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Store $cache
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

        $key = self::CACHE_PREFIX.md5($ip);

        if ($this->cache->get($key) !== null) {
            return true;
        }

        $this->cache->put($key, 1, $seconds);

        return null;
    }
}

<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Http;

use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the address a request came from.
 *
 * REMOTE_ADDR only. Forwarding headers are trivially forged by anyone who can reach the site
 * directly, and trusting them here would let a spammer pick the address we check against
 * StopForumSpam, record against their account, and rate limit — which is to say, choose to have no
 * address at all. Putting the real client address into REMOTE_ADDR is the reverse proxy's job, and
 * a well documented one for Cloudflare, nginx and the rest; by the time the request reaches PHP it
 * is expected to be correct already.
 *
 * Shared so that the address recorded against a new account is the same one the lookup judged.
 * Recording one address while having judged another makes both useless.
 */
class ClientIp
{
    public static function fromRequest(ServerRequestInterface $request): ?string
    {
        $ip = Arr::get($request->getServerParams(), 'REMOTE_ADDR')
            // Registering through /register arrives as an internal API request, which carries the
            // address core resolved on the parent rather than server params of its own.
            ?? $request->getAttribute('ipAddress');

        if (! is_string($ip) || trim($ip) === '') {
            return null;
        }

        $ip = trim($ip);

        return filter_var($ip, FILTER_VALIDATE_IP) === false ? null : $ip;
    }
}

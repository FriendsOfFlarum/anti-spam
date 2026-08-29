<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\User\User;
use FoF\AntiSpam\Http\ClientIp;

/**
 * Records the address an account was created from.
 *
 * Filled by the field's default rather than by anything the client sends, the same way core stamps
 * a post's createdAt: defaults are applied after the writability check, so the value is set on
 * creation while remaining impossible for a registering client to choose. Both registration paths
 * reach it, since /register proxies to this endpoint carrying the parent request's resolved
 * address.
 */
class AddUserRegistrationIp
{
    public function __invoke(): array
    {
        return [
            Schema\Str::make('registrationIp')
                ->property('registration_ip')
                ->writable(fn () => false)
                ->default(fn (Context $context) => ClientIp::fromRequest($context->request))
                ->visible(fn (User $user, Context $context) => $context->getActor()->hasPermission('user.spamblock')),
        ];
    }
}

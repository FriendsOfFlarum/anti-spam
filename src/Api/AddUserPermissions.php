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

use Flarum\Api\Schema;
use Flarum\Api\Context;
use Flarum\User\User;

class AddUserPermissions
{
    public function __invoke(): array
    {
        return [
            Schema\Boolean::make('canSpamblock')
                ->get(function (User $user, Context $context) {
                    return $context->getActor()->can('spamblock', $user);
                }),
        ];
    }
}

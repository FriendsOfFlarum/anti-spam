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

use Flarum\Api\Serializer\UserSerializer;
use Flarum\User\User;

class AddUserPermissions
{
    public function __invoke(UserSerializer $serializer, User $user, array $attributes): array
    {
        $attributes['canSpamblock'] = $serializer->getActor()->can('spamblock', $user);

        return $attributes;
    }
}

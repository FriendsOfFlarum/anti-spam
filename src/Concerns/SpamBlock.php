<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Concerns;

use Flarum\Api\Client;
use Flarum\User\User;

trait SpamBlock
{
    use Users;

    protected function markAsSpammer(User $user)
    {
        /** @var Client $api */
        $api = resolve(Client::class);

        $response = $api
            ->withActor($this->getModerator())
            ->send(
                'post',
                "/users/$user->id/spamblock"
            );

        return $response->getStatusCode() === 204;
    }
}

<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Model;

use Flarum\Database\AbstractModel;
use Flarum\Foundation\ValidationException;
use Flarum\User\User;

class ChallengeToken extends AbstractModel
{
    public static function validateToken(?string $token, User $user)
    {
        if (! $token) {
            throw new ValidationException(['fof-challenge-token' => 'No challenge token provided']);
        }

        $challengeToken = self::query()->where('token', $token)->first();

        if (! $challengeToken) {
            throw new ValidationException(['fof-challenge-token' => 'Invalid challenge token']);
        }

        $user->afterSave(function (User $user) use ($challengeToken) {
            $challengeToken->delete();
        });
    }
}

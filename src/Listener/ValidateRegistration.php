<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Listener;

use Flarum\User\Event\Saving;
use FoF\AntiSpam\Model\ChallengeToken;
use FoF\AntiSpam\Repository\ChallengeRepository;
use Illuminate\Support\Arr;

class ValidateRegistration
{
    protected $challengeRepository;

    public function __construct(ChallengeRepository $challengeRepository)
    {
        $this->challengeRepository = $challengeRepository;
    }

    public function handle(Saving $event)
    {
        // We also check for the actor's admin status, so that we can allow admins to create users from the admin panel without a challenge token.
        if (! $event->user->exists && ! $event->actor->isAdmin() && $this->challengeRepository->challengeEnabled()) {
            ChallengeToken::validateToken(Arr::get($event->data, 'attributes.fof-challenge-token'), $event->actor);
        }
    }
}

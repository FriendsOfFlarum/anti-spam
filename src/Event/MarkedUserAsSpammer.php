<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Event;

use Flarum\User\User;

class MarkedUserAsSpammer
{
    /**
     * @var User
     */
    public $user;

    /**
     * @var User|null
     */
    public $actor;

    public function __construct(User $user, User $actor = null)
    {
        $this->user = $user;
        $this->actor = $actor;
    }
}

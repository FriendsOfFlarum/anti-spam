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
    public function __construct(public User $user, public ?User $actor = null)
    {
    }
}

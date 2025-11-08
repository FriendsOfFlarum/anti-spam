<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Command;

use Flarum\User\User;

class MarkUserAsSpammer
{
    public function __construct(public User $user, public array $options = [], public ?User $actor = null)
    {
    }
}

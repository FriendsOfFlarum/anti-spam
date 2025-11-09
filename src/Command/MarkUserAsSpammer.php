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
    /**
     * The user being marked as a spammer.
     *
     * @var User
     */
    public $user;

    /**
     * The options to be used when marking the user as a spammer.
     * If empty, the default options will be used.
     *
     * @var array
     */
    public $options;

    /**
     * The user performing the action.
     *
     * @var User|null
     */
    public $actor;

    public function __construct(User $user, array $options = [], ?User $actor = null)
    {
        $this->user = $user;
        $this->options = $options;
        $this->actor = $actor;
    }
}

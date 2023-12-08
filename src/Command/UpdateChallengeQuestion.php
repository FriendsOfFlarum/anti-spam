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

class UpdateChallengeQuestion
{
    /**
     * @var int
     */
    public $id;

    /**
     * @var User
     */
    public $actor;

    /**
     * @var array
     */
    public $data;

    public function __construct(int $id, User $actor, array $data)
    {
        $this->id = $id;
        $this->actor = $actor;
        $this->data = $data;
    }
}

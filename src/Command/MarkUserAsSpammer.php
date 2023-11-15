<?php

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
     * The user performing the action.
     *
     * @var User|null
     */
    public $actor;
    
    public function __construct(User $user, User $actor = null)
    {
        $this->actor = $actor;
        $this->user = $user;
    }
}

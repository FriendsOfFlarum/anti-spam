<?php

namespace FoF\AntiSpam\Command;

use Flarum\User\User;

class CreateChallengeQuestion
{
    /**
     * @var User
     */
    public $actor;

    /**
     * @var array
     */
    public $data;

    public function __construct(User $actor, array $data)
    {
        $this->actor = $actor;
        $this->data = $data;
    }
}

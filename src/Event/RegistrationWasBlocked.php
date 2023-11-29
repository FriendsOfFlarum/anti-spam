<?php

namespace FoF\AntiSpam\Event;

use FoF\AntiSpam\Model\BlockedRegistration;

class RegistrationWasBlocked
{
    public $blocked;
    
    public function __construct(BlockedRegistration $blocked)
    {
        $this->blocked = $blocked;
    }
}

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

use FoF\AntiSpam\Model\BlockedRegistration;

class RegistrationWasBlocked
{
    public function __construct(public BlockedRegistration $blocked)
    {
    }
}

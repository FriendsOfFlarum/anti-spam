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

/**
 * Registered only while fof/user-bio is enabled — see the Conditional in extend.php.
 *
 * The bio was already being cleared when a moderator marked someone as a spammer; this stops it
 * being worth writing in the first place.
 */
class CheckBioContent extends AbstractProfileFieldCheck
{
    protected function attribute(): string
    {
        return 'bio';
    }
}

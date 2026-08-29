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
 * Registered only while flarum/nicknames is enabled — see the Conditional in extend.php.
 *
 * A nickname is the display name shown on every post the user makes, which makes it the most
 * valuable field on the profile for a spammer.
 */
class CheckNicknameContent extends AbstractProfileFieldCheck
{
    protected function attribute(): string
    {
        return 'nickname';
    }
}

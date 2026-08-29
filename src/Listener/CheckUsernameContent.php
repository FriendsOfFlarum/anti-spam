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
 * Core field, so this one is always active.
 */
class CheckUsernameContent extends AbstractProfileFieldCheck
{
    protected function attribute(): string
    {
        return 'username';
    }
}

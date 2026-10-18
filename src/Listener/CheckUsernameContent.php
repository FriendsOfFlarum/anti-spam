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

    /**
     * Usernames cannot contain spaces, so `_` is what separates their words. As a word character
     * it would keep a blocked word's boundaries from ever matching inside one. The name as typed
     * is kept alongside, so an admin's own entry written with `_` still matches it.
     */
    #[\Override]
    protected function textToAnalyze(string $value): string
    {
        return str_contains($value, '_') ? $value.' '.str_replace('_', ' ', $value) : $value;
    }
}

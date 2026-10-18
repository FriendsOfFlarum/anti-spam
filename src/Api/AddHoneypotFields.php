<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Api;

use Flarum\Api\Schema;
use FoF\AntiSpam\Honeypot;

/**
 * Lets the sign-up form send the honeypot and its token. The API refuses attributes it does not
 * know, and the registration check reads both before the user is ever created, so they are
 * accepted here and otherwise discarded.
 */
class AddHoneypotFields
{
    public function __invoke(): array
    {
        return [
            Schema\Str::make(Honeypot::FIELD)
                ->writableOnCreate()
                ->nullable()
                ->hidden()
                ->set(fn () => null),

            Schema\Str::make(Honeypot::TOKEN)
                ->writableOnCreate()
                ->nullable()
                ->hidden()
                ->set(fn () => null),
        ];
    }
}

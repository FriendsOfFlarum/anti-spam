<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Flarum\Settings\DatabaseSettingsRepository;
use Illuminate\Database\Schema\Builder;

// The honeypot signs its form tokens with this. Created here rather than on first use, so serving a
// page never writes to the database and two first visitors cannot race to create different ones.
return [
    'up' => function (Builder $schema) {
        $settings = new DatabaseSettingsRepository($schema->getConnection());

        if (! $settings->get('fof-anti-spam.honeypot.secret')) {
            $settings->set('fof-anti-spam.honeypot.secret', bin2hex(random_bytes(32)));
        }
    },
    'down' => function (Builder $schema) {
        (new DatabaseSettingsRepository($schema->getConnection()))->delete('fof-anti-spam.honeypot.secret');
    },
];

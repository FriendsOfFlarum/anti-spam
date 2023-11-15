<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        $db = $schema->getConnection();
        $keys = ['username', 'email', 'ip', 'frequency', 'api_key'];

        foreach ($keys as $key) {
            $db->table('settings')
                ->where('key', "fof-stopforumspam.$key")
                ->update(['key' => "fof-anti-spam.$key"]);
        }
    },
    'down' => function (Builder $schema) {
        // Do nothing
    },
];

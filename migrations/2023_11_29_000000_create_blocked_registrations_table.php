<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable(
    'blocked_registrations',
    function (Blueprint $table) {
        $table->increments('id');
        $table->string('ip', 40)->nullable();
        $table->string('email')->nullable();
        $table->string('username')->nullable();
        $table->mediumText('data')->nullable();
        $table->string('provider')->nullable();
        $table->mediumText('provider_data')->nullable();
        $table->timestamp('attempted_at')->nullable();
    }
);

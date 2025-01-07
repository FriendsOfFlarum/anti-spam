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
    'challenge_questions',
    function (Blueprint $table) {
        $table->increments('id');
        $table->string('question');
        $table->string('answer');
        $table->boolean('case_sensitive')->default(false);
        $table->boolean('is_active')->default(false);
        $table->timestamps();
    }
);

<?php

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

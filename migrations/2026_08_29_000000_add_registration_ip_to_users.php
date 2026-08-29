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

// 45 characters holds an IPv4-mapped IPv6 address in full notation, which is the longest form the
// StopForumSpam API accepts and therefore the longest we can be handed.
return Migration::addColumns('users', [
    'registration_ip' => ['string', 'length' => 45, 'nullable' => true],
]);

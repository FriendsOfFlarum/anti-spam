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

/**
 * Records which rules actually fired, at the moment the registration was blocked.
 *
 * The stored StopForumSpam response says what the service reported, but not which of the
 * admin's thresholds that tripped. Those thresholds change, so re-applying today's settings
 * to an old row can disagree with the decision that was really made — in a sample of live
 * data, 8% of blocked rows matched none of the current thresholds. Nullable because every
 * row recorded before this migration has no answer, and guessing one would be worse than
 * admitting we don't know.
 */
return Migration::addColumns('blocked_registrations', [
    'reasons' => ['type' => 'text', 'nullable' => true],
]);

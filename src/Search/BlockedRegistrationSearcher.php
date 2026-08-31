<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Search;

use Flarum\Search\Database\AbstractSearcher;
use Flarum\User\User;
use FoF\AntiSpam\Model\BlockedRegistration;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtering for blocked registrations.
 *
 * Flarum's resources deliberately refuse JSON:API filters — AbstractDatabaseResource::filters()
 * is final and throws — and route filtering through a searcher instead, so that is what this is.
 */
class BlockedRegistrationSearcher extends AbstractSearcher
{
    public function getQuery(User $actor): Builder
    {
        return BlockedRegistration::whereVisibleTo($actor);
    }
}

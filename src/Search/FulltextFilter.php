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

use Flarum\Search\AbstractFulltextFilter;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\SearchState;
use Illuminate\Database\Eloquent\Builder;

/**
 * Free-text search across the identity fields, so an admin can paste an address or a username
 * straight into the search box.
 *
 * @extends AbstractFulltextFilter<DatabaseSearchState>
 */
class FulltextFilter extends AbstractFulltextFilter
{
    public function search(SearchState $state, string $value): void
    {
        $like = '%'.addcslashes($value, '%_\\').'%';

        $state->getQuery()->where(function (Builder $query) use ($like): void {
            $query->where('ip', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('username', 'like', $like);
        });
    }
}

<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Search\Filter;

use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;
use Illuminate\Database\Eloquent\Builder;

/**
 * Matches the rules recorded when the block happened, e.g. `filter[reason]=blacklisted`.
 *
 * Reads the recorded decision rather than re-deriving one from the stored StopForumSpam
 * response: the thresholds are settings, so re-evaluating an old row can contradict the
 * decision that was actually taken. Rows recorded before reasons were captured match nothing
 * here, which is correct — we do not know why they were blocked.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class ReasonFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'reason';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $reasons = $this->asStringArray($value);

        $state->getQuery()->where(function (Builder $query) use ($reasons, $negate): void {
            foreach ($reasons as $reason) {
                // The column holds {"reasons":["blacklisted",...],"context":{...}}. Matching the
                // quoted name keeps `torExit` from matching a substring of some other rule, and
                // avoids depending on JSON functions the older supported databases may lack.
                $pattern = '%"'.addcslashes($reason, '%_\\').'"%';

                $negate
                    ? $query->where('reasons', 'not like', $pattern)
                    : $query->orWhere('reasons', 'like', $pattern);
            }
        });
    }
}

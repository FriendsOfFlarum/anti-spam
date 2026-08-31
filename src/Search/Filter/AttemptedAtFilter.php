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

use Carbon\Carbon;
use Flarum\Search\Database\DatabaseSearchState;
use Flarum\Search\Filter\FilterInterface;
use Flarum\Search\SearchState;
use Flarum\Search\ValidateFilterTrait;

/**
 * Narrows to a window of time, as `filter[attemptedAt]=<start>..<end>`.
 *
 * Either side may be omitted (`..2026-01-01`, `2026-01-01..`) to leave that end open. Accepts
 * unix timestamps as well as dates, since the dashboard widget works in timestamps while an
 * admin typing a filter by hand will not.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
class AttemptedAtFilter implements FilterInterface
{
    use ValidateFilterTrait;

    public function getFilterKey(): string
    {
        return 'attemptedAt';
    }

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        [$start, $end] = $this->range($this->asString($value));

        if ($start === null && $end === null) {
            return;
        }

        $query = $state->getQuery();

        // Negated, the window is what to exclude, so both bounds have to be escaped together
        // rather than applied as two independent conditions.
        if ($negate) {
            $query->where(function ($query) use ($start, $end): void {
                if ($start !== null) {
                    $query->orWhere('attempted_at', '<', $start);
                }

                if ($end !== null) {
                    $query->orWhere('attempted_at', '>', $end);
                }
            });

            return;
        }

        if ($start !== null) {
            $query->where('attempted_at', '>=', $start);
        }

        if ($end !== null) {
            $query->where('attempted_at', '<=', $end);
        }
    }

    /**
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    private function range(string $value): array
    {
        if (str_contains($value, '..')) {
            [$start, $end] = explode('..', $value, 2);

            return [$this->parse($start), $this->parse($end)];
        }

        // A bare value means that day, not that instant, so a date alone still matches the
        // attempts made during it.
        $date = $this->parse($value);

        return $date === null ? [null, null] : [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
    }

    private function parse(string $value): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return ctype_digit($value) ? Carbon::createFromTimestampUTC((int) $value) : Carbon::parse($value);
        } catch (\Throwable) {
            // An unparseable bound is treated as absent rather than fatal: a mistyped filter
            // should narrow nothing, not break the page.
            return null;
        }
    }
}

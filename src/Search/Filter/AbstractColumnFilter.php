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

/**
 * Matches one column against one or more exact values.
 *
 * Several fields on a blocked registration differ only in which column they read, so the
 * behaviour lives here rather than being repeated per filter.
 *
 * @implements FilterInterface<DatabaseSearchState>
 */
abstract class AbstractColumnFilter implements FilterInterface
{
    use ValidateFilterTrait;

    abstract protected function column(): string;

    public function filter(SearchState $state, string|array $value, bool $negate): void
    {
        $values = $this->asStringArray($value);

        $state->getQuery()->whereIn($this->column(), $values, 'and', $negate);
    }
}

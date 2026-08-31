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

class ProviderFilter extends AbstractColumnFilter
{
    public function getFilterKey(): string
    {
        return 'provider';
    }

    protected function column(): string
    {
        return 'provider';
    }
}

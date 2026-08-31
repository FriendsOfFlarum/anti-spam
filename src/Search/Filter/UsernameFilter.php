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

class UsernameFilter extends AbstractColumnFilter
{
    public function getFilterKey(): string
    {
        return 'username';
    }

    protected function column(): string
    {
        return 'username';
    }
}

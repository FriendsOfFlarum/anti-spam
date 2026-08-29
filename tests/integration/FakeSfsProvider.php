<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\integration;

use Flarum\Foundation\AbstractServiceProvider;
use FoF\AntiSpam\Api\SfsClient;

class FakeSfsProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(SfsClient::class, fn () => new FakeSfsClient());
    }
}

<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Providers;

use Flarum\Foundation\AbstractServiceProvider;
use FoF\AntiSpam\Api\SfsClient;
use Illuminate\Contracts\Container\Container;

class SfsProvider extends AbstractServiceProvider
{
    public function register()
    {
        $this->container->singleton(SfsClient::class, function (Container $container) {
            return new SfsClient($container->make('flarum.settings'));
        });
    }
}

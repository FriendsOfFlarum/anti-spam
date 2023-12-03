<?php

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

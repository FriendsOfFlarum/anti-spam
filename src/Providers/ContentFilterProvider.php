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
use Flarum\Foundation\Config;
use FoF\AntiSpam\ContentFilter\Analyzer;
use FoF\AntiSpam\ContentFilter\ConfigurationManager;
use FoF\AntiSpam\ContentFilter\Detectors\EmailDetector;
use FoF\AntiSpam\ContentFilter\Detectors\PatternDetector;
use FoF\AntiSpam\ContentFilter\Detectors\PhoneDetector;
use FoF\AntiSpam\ContentFilter\Detectors\UrlDetector;

/**
 * Service provider for content filtering system.
 */
class ContentFilterProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        // Register ConfigurationManager as singleton
        $this->container->singleton(ConfigurationManager::class, function ($container) {
            return new ConfigurationManager(
                $container->make('flarum.settings'),
                $container->make(Config::class)
            );
        });

        // Register Analyzer as singleton
        $this->container->singleton(Analyzer::class, function ($container) {
            $analyzer = new Analyzer(
                $container->make(ConfigurationManager::class),
                $container->make('log')
            );

            // Register all detectors
            $analyzer->addDetector($container->make(PhoneDetector::class));
            $analyzer->addDetector($container->make(EmailDetector::class));
            $analyzer->addDetector($container->make(UrlDetector::class));
            $analyzer->addDetector($container->make(PatternDetector::class));

            return $analyzer;
        });

        // Register individual detectors
        $this->container->singleton(PhoneDetector::class, function ($container) {
            return new PhoneDetector(
                $container->make(ConfigurationManager::class)
            );
        });

        $this->container->singleton(EmailDetector::class, function ($container) {
            return new EmailDetector(
                $container->make(ConfigurationManager::class)
            );
        });

        $this->container->singleton(UrlDetector::class, function ($container) {
            return new UrlDetector(
                $container->make(ConfigurationManager::class)
            );
        });

        $this->container->singleton(PatternDetector::class, function ($container) {
            return new PatternDetector(
                $container->make(ConfigurationManager::class),
                $container->make('log')
            );
        });
    }
}

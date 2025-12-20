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

use Flarum\Testing\integration\TestCase;
use FoF\AntiSpam\ContentFilter\ConfigurationManager;
use FoF\AntiSpam\Extend\ContentFilter;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for the hybrid configuration system (code + database).
 */
class ContentFilterConfigurationTest extends TestCase
{
    protected function setup(): void
    {
        parent::setup();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');
    }

    #[Test]
    public function database_configuration_works()
    {
        // Set via database (admin UI)
        $this->setting('fof-anti-spam.content-filter.monitor_post_count', 10);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 80);

        $this->app();

        /** @var ConfigurationManager $config */
        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        $this->assertEquals(10, $config->get('monitor_post_count'));
        $this->assertEquals(80, $config->get('spam_threshold'));
        $this->assertFalse($config->isCodeConfigured('monitor_post_count'));
        $this->assertFalse($config->isCodeConfigured('spam_threshold'));
    }

    #[Test]
    public function code_configuration_works()
    {
        $this->extend(
            (new ContentFilter())
                ->monitorUsersUpToPostCount(15)
                ->spamScoreThreshold(90)
        );

        $this->app();

        /** @var ConfigurationManager $config */
        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        $this->assertEquals(15, $config->get('monitor_post_count'));
        $this->assertEquals(90, $config->get('spam_threshold'));
        $this->assertTrue($config->isCodeConfigured('monitor_post_count'));
        $this->assertTrue($config->isCodeConfigured('spam_threshold'));
    }

    #[Test]
    public function code_configuration_overrides_database()
    {
        // Database says 10
        $this->setting('fof-anti-spam.content-filter.monitor_post_count', 10);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 80);

        // Code says 15 (should win)
        $this->extend(
            (new ContentFilter())
                ->monitorUsersUpToPostCount(15)
                ->spamScoreThreshold(90)
        );

        $this->app();

        /** @var ConfigurationManager $config */
        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        // Code configuration should override
        $this->assertEquals(15, $config->get('monitor_post_count'), 'Code config should override database');
        $this->assertEquals(90, $config->get('spam_threshold'), 'Code config should override database');
        $this->assertTrue($config->isCodeConfigured('monitor_post_count'));
        $this->assertTrue($config->isCodeConfigured('spam_threshold'));
    }

    #[Test]
    public function hybrid_configuration_merges_correctly()
    {
        // Some settings in database
        $this->setting('fof-anti-spam.content-filter.monitor_post_count', 10);
        $this->setting('fof-anti-spam.content-filter.detect_phones', false);

        // Other settings in code
        $this->extend(
            (new ContentFilter())
                ->spamScoreThreshold(90)
                ->blockEmailAddresses(false)
        );

        $this->app();

        /** @var ConfigurationManager $config */
        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        // Database settings (not in code)
        $this->assertEquals(10, $config->get('monitor_post_count'));
        $this->assertFalse($config->isCodeConfigured('monitor_post_count'));
        $this->assertFalse($config->get('detect_phones'));

        // Code settings (not in database)
        $this->assertEquals(90, $config->get('spam_threshold'));
        $this->assertTrue($config->isCodeConfigured('spam_threshold'));
        $this->assertFalse($config->get('detect_emails'));
        $this->assertTrue($config->isCodeConfigured('detect_emails'));
    }

    #[Test]
    public function domain_allowlist_merges_code_and_database()
    {
        // Database domains
        $this->setting('fof-anti-spam.content-filter.allowed_domains', json_encode([
            'example.com',
            'test.com',
        ]));

        // Code domains
        $this->extend(
            (new ContentFilter())
                ->allowDomains(['youtube.com', 'github.com'])
        );

        $this->app();

        /** @var ConfigurationManager $config */
        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        $allDomains = $config->getAllowedDomains();

        // Should contain all domains from both sources
        $this->assertContains('example.com', $allDomains, 'Should include database domain');
        $this->assertContains('test.com', $allDomains, 'Should include database domain');
        $this->assertContains('youtube.com', $allDomains, 'Should include code domain');
        $this->assertContains('github.com', $allDomains, 'Should include code domain');

        // Should have 4 unique domains
        $this->assertCount(4, array_unique($allDomains));
    }

    #[Test]
    public function block_patterns_merge_code_and_database()
    {
        // Database advanced patterns
        $this->setting('fof-anti-spam.content-filter.advanced_block_patterns', json_encode([
            ['pattern' => '/spam/', 'description' => 'DB spam'],
        ]));

        // Code patterns
        $this->extend(
            (new ContentFilter())
                ->blockPattern('/viagra/i', 'Code viagra')
        );

        $this->app();

        /** @var ConfigurationManager $config */
        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        $allPatterns = $config->getBlockPatterns();

        // Should contain patterns from both sources
        $this->assertCount(2, $allPatterns);

        $patterns = array_column($allPatterns, 'pattern');
        $this->assertContains('/spam/', $patterns, 'Should include database pattern');
        $this->assertContains('/viagra/i', $patterns, 'Should include code pattern');
    }

    #[Test]
    public function get_code_value_returns_only_code_configured_value()
    {
        $this->setting('fof-anti-spam.content-filter.monitor_post_count', 10);

        $this->extend(
            (new ContentFilter())
                ->spamScoreThreshold(90)
        );

        $this->app();

        /** @var ConfigurationManager $config */
        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        // Code-configured value
        $this->assertEquals(90, $config->getCodeValue('spam_threshold'));

        // Database-only value
        $this->assertNull($config->getCodeValue('monitor_post_count'));
    }

    #[Test]
    public function get_database_value_returns_only_database_configured_value()
    {
        $this->setting('fof-anti-spam.content-filter.monitor_post_count', 10);

        $this->extend(
            (new ContentFilter())
                ->spamScoreThreshold(90)
        );

        $this->app();

        /** @var ConfigurationManager $config */
        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        // Database-configured value
        $this->assertEquals(10, $config->getDatabaseValue('monitor_post_count'));

        // Code-only value returns database default (50), not null since extend.php sets defaults
        $this->assertEquals(50, $config->getDatabaseValue('spam_threshold'), 'Should return database default from extend.php');
    }

    #[Test]
    public function to_array_shows_configuration_sources()
    {
        $this->setting('fof-anti-spam.content-filter.monitor_post_count', 10);

        $this->extend(
            (new ContentFilter())
                ->spamScoreThreshold(90)
        );

        $this->app();

        /** @var ConfigurationManager $config */
        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        $array = $config->toArray();

        // Check structure
        $this->assertArrayHasKey('monitorPostCount', $array);
        $this->assertArrayHasKey('value', $array['monitorPostCount']);
        $this->assertArrayHasKey('isCodeConfigured', $array['monitorPostCount']);

        // Database-configured value
        $this->assertEquals(10, $array['monitorPostCount']['value']);
        $this->assertFalse($array['monitorPostCount']['isCodeConfigured']);

        // Code-configured value
        $this->assertEquals(90, $array['spamThreshold']['value']);
        $this->assertTrue($array['spamThreshold']['isCodeConfigured']);
    }

    #[Test]
    public function disabled_detectors_are_tracked()
    {
        $this->extend(
            (new ContentFilter())
                ->disableDetector('FoF\\AntiSpam\\ContentFilter\\Detectors\\PhoneDetector')
        );

        $this->app();

        /** @var ConfigurationManager $config */
        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        $disabled = $config->getDisabledDetectors();

        $this->assertContains('FoF\\AntiSpam\\ContentFilter\\Detectors\\PhoneDetector', $disabled);
        $this->assertTrue($config->isDetectorDisabled('FoF\\AntiSpam\\ContentFilter\\Detectors\\PhoneDetector'));
        $this->assertFalse($config->isDetectorDisabled('FoF\\AntiSpam\\ContentFilter\\Detectors\\EmailDetector'));
    }

    #[Test]
    public function domain_callbacks_are_stored()
    {
        $callback = function ($uri, $user) {
            return str_ends_with($uri->getHost(), '.trusted.com');
        };

        $this->extend(
            (new ContentFilter())
                ->allowDomainCallback($callback)
        );

        $this->app();

        /** @var ConfigurationManager $config */
        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        $callbacks = $config->getDomainCallbacks();

        $this->assertCount(1, $callbacks);
        $this->assertIsCallable($callbacks[0]);
    }

    #[Test]
    public function default_values_are_used_when_nothing_configured()
    {
        $this->app();

        /** @var ConfigurationManager $config */
        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        // Should use defaults from extend.php settings
        $this->assertEquals(5, $config->get('monitor_post_count', 5));
        $this->assertEquals(50, $config->get('spam_threshold', 50));
        $this->assertTrue($config->get('detect_phones', true));
    }

    #[Test]
    public function forum_domain_is_automatically_allowlisted()
    {
        $this->setting('forum_url', 'https://myforum.com/forum');

        $this->app();

        /** @var ConfigurationManager $config */
        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        $domains = $config->getAllowedDomains();

        $this->assertContains('myforum.com', $domains, 'Forum domain should be automatically allowlisted');
    }
}

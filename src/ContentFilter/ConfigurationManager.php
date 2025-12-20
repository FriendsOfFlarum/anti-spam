<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\ContentFilter;

use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Manages configuration from both code (extend.php) and database (admin UI).
 *
 * Priority: Code configuration overrides database settings
 */
class ConfigurationManager
{
    private const PREFIX = 'fof-anti-spam.content-filter.';

    /**
     * @var array<string, mixed>
     */
    private array $codeConfig = [];

    public function __construct(
        private SettingsRepositoryInterface $settings
    ) {
    }

    /**
     * Set configuration from code (extend.php).
     *
     * @param array<string, mixed> $config
     */
    public function setCodeConfig(array $config): void
    {
        $this->codeConfig = $config;
    }

    /**
     * Get a configuration value with priority: code > database > default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Code config takes precedence
        if (array_key_exists($key, $this->codeConfig)) {
            return $this->codeConfig[$key];
        }

        // Fall back to database setting
        return $this->settings->get(self::PREFIX.$key, $default);
    }

    /**
     * Check if a setting is configured via code.
     */
    public function isCodeConfigured(string $key): bool
    {
        return array_key_exists($key, $this->codeConfig);
    }

    /**
     * Get only code-configured value (or null).
     */
    public function getCodeValue(string $key): mixed
    {
        return $this->codeConfig[$key] ?? null;
    }

    /**
     * Get only database-configured value (or default).
     */
    public function getDatabaseValue(string $key, mixed $default = null): mixed
    {
        return $this->settings->get(self::PREFIX.$key, $default);
    }

    /**
     * Get merged list of allowed domains (code + database).
     *
     * @return array<string>
     */
    public function getAllowedDomains(): array
    {
        $codeDomains = $this->codeConfig['allowed_domains'] ?? [];
        $dbDomains = json_decode(
            $this->settings->get(self::PREFIX.'allowed_domains', '[]'),
            true
        );

        // Always include the forum's own domain
        $forumUrl = $this->settings->get('forum_url', '');
        $forumDomain = parse_url($forumUrl, PHP_URL_HOST);

        $allDomains = array_merge(
            $codeDomains,
            is_array($dbDomains) ? $dbDomains : [],
            $forumDomain ? [$forumDomain] : []
        );

        return array_values(array_unique(array_filter($allDomains)));
    }

    /**
     * Get only code-configured domains.
     *
     * @return array<string>
     */
    public function getCodeConfiguredDomains(): array
    {
        return $this->codeConfig['allowed_domains'] ?? [];
    }

    /**
     * Get only database-configured domains.
     *
     * @return array<string>
     */
    public function getDatabaseConfiguredDomains(): array
    {
        $domains = json_decode(
            $this->settings->get(self::PREFIX.'allowed_domains', '[]'),
            true
        );

        return is_array($domains) ? $domains : [];
    }

    /**
     * Get domain validation callbacks.
     *
     * @return array<callable>
     */
    public function getDomainCallbacks(): array
    {
        return $this->codeConfig['domain_callbacks'] ?? [];
    }

    /**
     * Get merged list of block patterns (code + database)
     * Includes both plain text words (converted to regex) and advanced patterns.
     *
     * @return array<array{pattern: string, description: ?string}>
     */
    public function getBlockPatterns(): array
    {
        $patterns = [];

        // Add code-configured patterns
        $patterns = array_merge($patterns, $this->codeConfig['block_patterns'] ?? []);

        // Add plain text blocked words (convert to regex)
        $blockedWords = $this->settings->get(self::PREFIX.'blocked_words', '');
        if (! empty($blockedWords)) {
            $words = array_filter(array_map('trim', explode("\n", $blockedWords)));
            foreach ($words as $word) {
                // Escape special regex characters
                $escaped = preg_quote($word, '/');

                // Replace spaces with \s+ to match any whitespace
                $escaped = preg_replace('/\s+/', '\\s+', $escaped);

                // Create word boundary pattern
                $patterns[] = [
                    'pattern' => '/\b'.$escaped.'\b/iu',
                    'description' => "Contains blocked word: {$word}",
                ];
            }
        }

        // Add advanced regex patterns from database
        $dbPatterns = json_decode(
            $this->settings->get(self::PREFIX.'advanced_block_patterns', '[]'),
            true
        );
        $patterns = array_merge($patterns, is_array($dbPatterns) ? $dbPatterns : []);

        return $patterns;
    }

    /**
     * Get only code-configured block patterns.
     *
     * @return array<array{pattern: string, description: ?string}>
     */
    public function getCodeConfiguredPatterns(): array
    {
        return $this->codeConfig['block_patterns'] ?? [];
    }

    /**
     * Get only database-configured advanced block patterns (regex).
     *
     * @return array<array{pattern: string, description: ?string}>
     */
    public function getDatabaseConfiguredPatterns(): array
    {
        $patterns = json_decode(
            $this->settings->get(self::PREFIX.'advanced_block_patterns', '[]'),
            true
        );

        return is_array($patterns) ? $patterns : [];
    }

    /**
     * Get disabled detector classes.
     *
     * @return array<class-string>
     */
    public function getDisabledDetectors(): array
    {
        return $this->codeConfig['disabled_detectors'] ?? [];
    }

    /**
     * Check if content filtering is enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->get('enabled', true);
    }

    /**
     * Check if a detector class is disabled.
     *
     * @param class-string $detectorClass
     */
    public function isDetectorDisabled(string $detectorClass): bool
    {
        return in_array($detectorClass, $this->getDisabledDetectors(), true);
    }

    /**
     * Get all configuration as array (for API exposure).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => [
                'value' => $this->isEnabled(),
                'isCodeConfigured' => $this->isCodeConfigured('enabled'),
            ],
            'monitorPostCount' => [
                'value' => $this->get('monitor_post_count', 5),
                'isCodeConfigured' => $this->isCodeConfigured('monitor_post_count'),
            ],
            'monitorHoursOld' => [
                'value' => $this->get('monitor_hours_old', 24),
                'isCodeConfigured' => $this->isCodeConfigured('monitor_hours_old'),
            ],
            'detectPhones' => [
                'value' => $this->get('detect_phones', true),
                'isCodeConfigured' => $this->isCodeConfigured('detect_phones'),
            ],
            'detectEmails' => [
                'value' => $this->get('detect_emails', true),
                'isCodeConfigured' => $this->isCodeConfigured('detect_emails'),
            ],
            'detectUrls' => [
                'value' => $this->get('detect_urls', true),
                'isCodeConfigured' => $this->isCodeConfigured('detect_urls'),
            ],
            'spamThreshold' => [
                'value' => $this->get('spam_threshold', 70),
                'isCodeConfigured' => $this->isCodeConfigured('spam_threshold'),
            ],
            'flagThreshold' => [
                'value' => $this->get('flag_threshold', 50),
                'isCodeConfigured' => $this->isCodeConfigured('flag_threshold'),
            ],
            'autoUnapprove' => [
                'value' => $this->get('auto_unapprove', true),
                'isCodeConfigured' => $this->isCodeConfigured('auto_unapprove'),
            ],
            'autoFlag' => [
                'value' => $this->get('auto_flag', true),
                'isCodeConfigured' => $this->isCodeConfigured('auto_flag'),
            ],
            'flagModeratorId' => [
                'value' => $this->get('flag_moderator_id'),
                'isCodeConfigured' => $this->isCodeConfigured('flag_moderator_id'),
            ],
            'allowedDomains' => [
                'code' => $this->getCodeConfiguredDomains(),
                'database' => $this->getDatabaseConfiguredDomains(),
                'merged' => $this->getAllowedDomains(),
            ],
            'blockPatterns' => [
                'code' => $this->getCodeConfiguredPatterns(),
                'database' => $this->getDatabaseConfiguredPatterns(),
                'merged' => $this->getBlockPatterns(),
            ],
            'disabledDetectors' => $this->getDisabledDetectors(),
        ];
    }
}

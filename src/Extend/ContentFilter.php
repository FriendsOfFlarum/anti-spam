<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Extend;

use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use FoF\AntiSpam\ContentFilter\ConfigurationManager;
use Illuminate\Contracts\Container\Container;

/**
 * Fluent API for configuring content filtering via extend.php.
 *
 * Example usage:
 * ```php
 * (new ContentFilter())
 *     ->monitorAllUsers()
 *     ->monitorUsersUpToPostCount(5)
 *     ->monitorUsersUpToHoursOld(24)
 *     ->allowDomain('youtube.com')
 *     ->blockPhoneNumbers(true)
 *     ->spamScoreThreshold(70)
 * ```
 */
class ContentFilter implements ExtenderInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $config = [];

    /**
     * Monitor every user's posts, not only those of new accounts.
     *
     * Staff stay exempt. This supersedes the post-count and account-age windows, which can
     * only ever exempt an account permanently once it clears them.
     */
    public function monitorAllUsers(bool $monitor = true): self
    {
        $this->config['monitor_all_users'] = $monitor;

        return $this;
    }

    /**
     * Monitor users up to their first N posts.
     */
    public function monitorUsersUpToPostCount(int $count): self
    {
        $this->config['monitor_post_count'] = $count;

        return $this;
    }

    /**
     * Monitor users up to N hours after registration.
     */
    public function monitorUsersUpToHoursOld(int $hours): self
    {
        $this->config['monitor_hours_old'] = $hours;

        return $this;
    }

    /**
     * Add a single allowed domain for URLs.
     */
    public function allowDomain(string $domain): self
    {
        if (! isset($this->config['allowed_domains'])) {
            $this->config['allowed_domains'] = [];
        }

        $this->config['allowed_domains'][] = $this->normalizeDomain($domain);

        return $this;
    }

    /**
     * Add multiple allowed domains for URLs.
     *
     * @param array<string> $domains
     */
    public function allowDomains(array $domains): self
    {
        foreach ($domains as $domain) {
            $this->allowDomain($domain);
        }

        return $this;
    }

    /**
     * Add a custom domain validation callback.
     *
     * @param callable $callback Receives (\Psr\Http\Message\UriInterface $uri, \Flarum\User\User $user): bool
     */
    public function allowDomainCallback(callable $callback): self
    {
        if (! isset($this->config['domain_callbacks'])) {
            $this->config['domain_callbacks'] = [];
        }

        $this->config['domain_callbacks'][] = $callback;

        return $this;
    }

    /**
     * Add a regex pattern to block (with optional description).
     */
    public function blockPattern(string $pattern, ?string $description = null): self
    {
        if (! isset($this->config['block_patterns'])) {
            $this->config['block_patterns'] = [];
        }

        $this->config['block_patterns'][] = [
            'pattern' => $pattern,
            'description' => $description,
        ];

        return $this;
    }

    /**
     * Add multiple patterns to block.
     *
     * @param array<string|array{pattern: string, description?: string}> $patterns
     */
    public function blockPatterns(array $patterns): self
    {
        foreach ($patterns as $pattern) {
            if (is_array($pattern)) {
                $this->blockPattern($pattern['pattern'], $pattern['description'] ?? null);
            } else {
                $this->blockPattern($pattern);
            }
        }

        return $this;
    }

    /**
     * Enable/disable phone number detection.
     */
    public function blockPhoneNumbers(bool $enabled = true): self
    {
        $this->config['detect_phones'] = $enabled;

        return $this;
    }

    /**
     * Enable/disable email address detection in post content.
     */
    public function blockEmailAddresses(bool $enabled = true): self
    {
        $this->config['detect_emails'] = $enabled;

        return $this;
    }

    /**
     * Enable/disable URL detection.
     */
    public function blockUrls(bool $enabled = true): self
    {
        $this->config['detect_urls'] = $enabled;

        return $this;
    }

    /**
     * Set spam score threshold for auto-unapproval (0-100).
     */
    public function spamScoreThreshold(int $threshold): self
    {
        $this->config['spam_threshold'] = max(0, min(100, $threshold));

        return $this;
    }

    /**
     * Set spam score threshold for flagging only (0-100).
     */
    public function flagScoreThreshold(int $threshold): self
    {
        $this->config['flag_threshold'] = max(0, min(100, $threshold));

        return $this;
    }

    /**
     * Enable/disable automatic content unapproval.
     */
    public function enableAutoUnapprove(bool $enabled = true): self
    {
        $this->config['auto_unapprove'] = $enabled;

        return $this;
    }

    /**
     * Enable/disable automatic flag creation.
     */
    public function enableAutoFlag(bool $enabled = true): self
    {
        $this->config['auto_flag'] = $enabled;

        return $this;
    }

    /**
     * Set which user ID should be assigned as moderator for auto-created flags.
     */
    public function assignFlagsToModerator(int $userId): self
    {
        $this->config['flag_moderator_id'] = $userId;

        return $this;
    }

    /**
     * Disable a specific detector class.
     *
     * @param class-string $detectorClass
     */
    public function disableDetector(string $detectorClass): self
    {
        if (! isset($this->config['disabled_detectors'])) {
            $this->config['disabled_detectors'] = [];
        }

        $this->config['disabled_detectors'][] = $detectorClass;

        return $this;
    }

    /**
     * Enable content filtering (can be disabled entirely).
     */
    public function enabled(bool $enabled = true): self
    {
        $this->config['enabled'] = $enabled;

        return $this;
    }

    /**
     * Normalize domain by removing protocol and path.
     */
    private function normalizeDomain(string $domain): string
    {
        return ConfigurationManager::normalizeDomain($domain);
    }

    public function extend(Container $container, ?Extension $extension = null): void
    {
        // Register the configuration in the container
        $container->extend(ConfigurationManager::class, function ($manager, $container) {
            if (! $manager) {
                $manager = $container->make(ConfigurationManager::class);
            }

            $manager->setCodeConfig($this->config);

            return $manager;
        });
    }
}

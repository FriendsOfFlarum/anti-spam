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

use Flarum\User\User;

/**
 * Base class for content spam detectors.
 */
abstract class AbstractDetector implements DetectorInterface
{
    public function __construct(
        protected ConfigurationManager $config
    ) {
    }

    /**
     * Check if user should be monitored based on their account age and post count.
     */
    protected function shouldMonitorUser(User $user): bool
    {
        // Never monitor staff
        if ($user->isAdmin() || $user->can('discussion.hide')) {
            return false;
        }

        // Always monitor guests (shouldn't post, but just in case)
        if ($user->isGuest()) {
            return true;
        }

        $monitorPostCount = $this->config->get('monitor_post_count', 5);
        $monitorHoursOld = $this->config->get('monitor_hours_old', 24);

        // Check post count threshold
        if ($user->comment_count <= $monitorPostCount) {
            return true;
        }

        // Check account age threshold
        if ($user->joined_at && $user->joined_at->diffInHours() <= $monitorHoursOld) {
            return true;
        }

        return false;
    }

    /**
     * Strip BBCode and HTML from content for analysis.
     */
    protected function stripFormatting(string $content): string
    {
        // Remove TextFormatter XML tags like <r>, <t>, <s>, etc.
        $content = preg_replace('/<[^>]+>/', ' ', $content);

        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        return trim($content);
    }

    public function isEnabled(): bool
    {
        // Check if content filtering is globally enabled
        if (! $this->config->isEnabled()) {
            return false;
        }

        // Check if this specific detector is disabled
        if ($this->config->isDetectorDisabled(static::class)) {
            return false;
        }

        return true;
    }
}

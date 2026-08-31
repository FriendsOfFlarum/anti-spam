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
     * Check if user should be monitored.
     *
     * By default only new accounts are — up to a post count or an age — unless the admin has
     * turned on monitoring for everyone.
     *
     * Administrators and anyone who can hide discussions are exempt either way. That is a
     * proxy for trust rather than a designation of it: the exemption follows whoever holds
     * `discussion.hide`, which a forum may have granted for unrelated reasons.
     */
    protected function shouldMonitorUser(User $user): bool
    {
        // Administrators, and whoever can hide discussions — moderators on a default forum,
        // though the permission can be granted more widely than that.
        if ($user->isAdmin() || $user->can('discussion.hide')) {
            return false;
        }

        // Always monitor guests (shouldn't post, but just in case)
        if ($user->isGuest()) {
            return true;
        }

        // The window below is one-way: an account that clears it is never examined again. That
        // is the wrong answer for a member whose credentials are stolen, or one that waits the
        // thresholds out before starting, so an admin can opt into checking everyone. The
        // exemption above still applies.
        if ($this->config->get('monitor_all_users')) {
            return true;
        }

        $monitorPostCount = $this->config->get('monitor_post_count');
        $monitorHoursOld = $this->config->get('monitor_hours_old');

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
     * Markup tags: `<r>`, `</p>`, `<br/>`, `<URL url="…">`, `<UPL-IMAGE-PREVIEW …>`.
     *
     * The tag name must start with a letter and run straight into whitespace, `/` or `>`. That is
     * what separates a tag from a CommonMark autolink — `<https://spam.com>` breaks at the `:`,
     * `<www.spam.com>` and `<spam.com>` at the `.`, `<user@spam.com>` at the `@` — so none of them
     * match. A blanket `<[^>]+>` swallowed all of those, which deleted the payload before any
     * detector saw it and let a live link through with a score of zero.
     */
    private const MARKUP_TAG = '~</?[A-Za-z][A-Za-z0-9_-]*(?:\s[^<>]*)?/?>~';

    /**
     * Strip BBCode and HTML from content for analysis.
     */
    protected function stripFormatting(string $content): string
    {
        // Remove TextFormatter XML tags like <r>, <t>, <s>, etc.
        $content = preg_replace(self::MARKUP_TAG, ' ', $content);

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

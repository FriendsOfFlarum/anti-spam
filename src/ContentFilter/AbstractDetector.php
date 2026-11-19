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
     * Check if user should be monitored. See MonitoredUsers.
     *
     * Built here rather than injected, so a detector written against this class keeps its
     * constructor.
     */
    protected function shouldMonitorUser(User $user): bool
    {
        return (new MonitoredUsers($this->config))->shouldMonitor($user);
    }

    /**
     * Whether a user is a new account: inside the post count or age window, and not exempt.
     * Unlike shouldMonitorUser(), monitoring everyone does not widen it. For detectors that
     * judge how an account behaves rather than what it says, where a regular doing the same is
     * just being a regular.
     */
    protected function isNewAccount(User $user): bool
    {
        $users = new MonitoredUsers($this->config);

        return ! $users->isExempt($user) && $users->isNew($user);
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

    /**
     * How many matches a flag names before it summarises the rest.
     */
    protected const int LISTED_MATCHES = 5;

    /**
     * The matches a flag names, so a moderator can see what tripped it after the author has
     * edited it away: `a, b and 3 more`.
     *
     * @param array<string> $matches
     */
    protected static function listMatches(array $matches): string
    {
        $matches = array_values(array_unique(array_map([static::class, 'quotable'], $matches)));
        $listed = implode(', ', array_slice($matches, 0, static::LISTED_MATCHES));
        $rest = count($matches) - static::LISTED_MATCHES;

        return $rest > 0 ? "{$listed} and {$rest} more" : $listed;
    }

    /**
     * Matched text made safe to store and show: valid UTF-8, since a pattern without the `u`
     * modifier can end a match inside a character, and short, since a post can hold a very long
     * token and a flag's detail has a size limit.
     */
    protected static function quotable(string $matched): string
    {
        // Line and paragraph separators too: a flag shows one reason per line, and a match must
        // not be able to start a line of its own.
        $matched = trim(preg_replace('/[\p{Z}\p{Cc}]+/u', ' ', mb_scrub($matched, 'UTF-8')));

        return mb_strlen($matched) > 100 ? mb_substr($matched, 0, 100).'…' : $matched;
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

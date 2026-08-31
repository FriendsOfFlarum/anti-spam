<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Search;

use FoF\AntiSpam\BlockReasons;

/**
 * Registry of the search filters available on the blocked registrations list.
 *
 * Exposed to the frontend so the list can show clickable examples and a help panel, making the
 * otherwise-undiscoverable `key:value` syntax self-documenting. This mirrors flarum/audit's
 * AuditGambits, so the two admin log browsers work the same way.
 *
 * @see \Flarum\Audit\Search\AuditGambits
 */
class BlockedRegistrationGambits
{
    /**
     * @var array<array{key: string, example: string, description: string|null, values: string[], extension: string|null}>
     */
    public static array $filters = [];

    /**
     * Register a filter for display in the list's search help.
     *
     * @param string $key The gambit key, e.g. "reason".
     * @param string $example A complete example query, e.g. "reason:blacklisted".
     * @param string|null $description A translation key describing what the filter matches.
     * @param string[] $values Known accepted values, exposed as ready-to-use chips.
     * @param string|null $extension Extension providing the gambit, for grouping. Null means this one.
     */
    public static function register(string $key, string $example, ?string $description = null, array $values = [], ?string $extension = null): void
    {
        foreach (self::$filters as $filter) {
            if ($filter['key'] === $key) {
                return;
            }
        }

        self::$filters[] = [
            'key' => $key,
            'example' => $example,
            'description' => $description,
            'values' => $values,
            'extension' => $extension,
        ];
    }

    /**
     * The filters this extension provides.
     *
     * Registered lazily so the reason values stay in step with the rules BlockReasons defines,
     * rather than being repeated as a literal list that can drift.
     */
    public static function registerDefaults(): void
    {
        // A bare `key:` where the value is whatever the admin types — a made-up sample address
        // would only ever match nothing. Concrete values are given below, where the set is
        // known and every one of them is a filter that can actually return something.
        self::register('ip', 'ip:', 'fof-anti-spam.admin.blocked_registrations.filters.help.ip');
        self::register('email', 'email:', 'fof-anti-spam.admin.blocked_registrations.filters.help.email');
        self::register('username', 'username:', 'fof-anti-spam.admin.blocked_registrations.filters.help.username');
        // Providers are a known, short set, so every chip here is a filter that can return
        // something on a forum using them.
        self::register(
            'provider',
            'provider:',
            'fof-anti-spam.admin.blocked_registrations.filters.help.provider',
            ['flarum', 'github', 'forum']
        );
        self::register(
            'reason',
            'reason:',
            'fof-anti-spam.admin.blocked_registrations.filters.help.reason',
            [
                BlockReasons::BLACKLISTED,
                BlockReasons::TOR_EXIT,
                BlockReasons::DENIED_ASN,
                BlockReasons::CONFIDENCE,
                BlockReasons::FREQUENCY,
            ]
        );
        self::register('attemptedAt', 'attemptedAt:', 'fof-anti-spam.admin.blocked_registrations.filters.help.attemptedAt');
    }
}

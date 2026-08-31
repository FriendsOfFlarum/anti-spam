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
        self::register('ip', 'ip:192.0.2.1', 'fof-anti-spam.admin.blocked_registrations.filters.help.ip');
        self::register('email', 'email:someone@example.com', 'fof-anti-spam.admin.blocked_registrations.filters.help.email');
        self::register('username', 'username:spammer', 'fof-anti-spam.admin.blocked_registrations.filters.help.username');
        self::register(
            'provider',
            'provider:flarum',
            'fof-anti-spam.admin.blocked_registrations.filters.help.provider',
            ['flarum', 'github', 'forum']
        );
        self::register(
            'reason',
            'reason:blacklisted',
            'fof-anti-spam.admin.blocked_registrations.filters.help.reason',
            [
                \FoF\AntiSpam\BlockReasons::BLACKLISTED,
                \FoF\AntiSpam\BlockReasons::TOR_EXIT,
                \FoF\AntiSpam\BlockReasons::DENIED_ASN,
                \FoF\AntiSpam\BlockReasons::CONFIDENCE,
                \FoF\AntiSpam\BlockReasons::FREQUENCY,
            ]
        );
        self::register(
            'attemptedAt',
            'attemptedAt:2026-01-01..2026-02-01',
            'fof-anti-spam.admin.blocked_registrations.filters.help.attemptedAt'
        );
    }
}

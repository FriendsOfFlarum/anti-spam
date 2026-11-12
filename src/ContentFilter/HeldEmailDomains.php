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
 * Email domains whose new accounts have every post held for approval.
 *
 * A listed domain covers its subdomains too, so a provider that hands out addresses on several
 * of them needs listing once. A provider with several unrelated domains needs each one listed.
 */
class HeldEmailDomains
{
    public function __construct(
        protected ConfigurationManager $config
    ) {
    }

    /**
     * The listed domain the user's email address is on, or null.
     */
    public function matching(User $user): ?string
    {
        return self::match((string) $user->email, $this->config->getHeldEmailDomains());
    }

    /**
     * The listed domain an email address is on, or null. The most specific wins, so the flag
     * names the entry an admin would recognise.
     *
     * @param array<string> $domains Normalized, as by normalize()
     */
    public static function match(string $email, array $domains): ?string
    {
        $at = strrpos($email, '@');

        if ($at === false) {
            return null;
        }

        $host = rtrim(strtolower(substr($email, $at + 1)), '.');

        if ($host === '') {
            return null;
        }

        $match = null;

        foreach ($domains as $domain) {
            if ($domain === '') {
                continue;
            }

            if (($host === $domain || str_ends_with($host, '.'.$domain)) && strlen($domain) > strlen((string) $match)) {
                $match = $domain;
            }
        }

        return $match;
    }

    /**
     * Reduce an entry to a bare lowercase domain. Admins paste `@example.com`, a whole address,
     * or `*.example.com` as readily as the domain itself, and all of them mean the same thing.
     */
    public static function normalize(string $entry): string
    {
        $entry = strtolower(trim($entry));

        if (($at = strrpos($entry, '@')) !== false) {
            $entry = substr($entry, $at + 1);
        }

        $entry = preg_replace('/^\*?\.+/', '', $entry);

        return rtrim($entry, '.');
    }
}

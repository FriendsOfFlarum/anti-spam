<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam;

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Cache\Repository;

/**
 * A trap for form-filling bots StopForumSpam has not seen yet.
 *
 * The sign-up form carries a field people never see, which a bot filling in every input does
 * fill, and a signed timestamp from when the page was served, which tells a person from a script
 * by how fast the form came back. Neither costs a real person anything or sends anything off-site.
 */
class Honeypot
{
    public const string FIELD = 'fof_as_comment';
    public const string TOKEN = 'fof_as_token';

    public const string ENABLED = 'fof-anti-spam.honeypot.enabled';
    public const string STRICT = 'fof-anti-spam.honeypot.strict';
    public const string MIN_SECONDS = 'fof-anti-spam.honeypot.minSeconds';

    /**
     * Created by a migration and never sent to the forum: it is what makes a token unforgeable.
     */
    protected const string SECRET = 'fof-anti-spam.honeypot.secret';

    /**
     * Uses one token allows: enough for a person retrying after a taken username or a typo, not
     * for a bot that fetched a page once and replays the token.
     */
    protected const int MAX_USES = 5;

    /**
     * A token older than this no longer vouches for the form, so a bot cannot fetch one and
     * replay it indefinitely. A day comfortably outlasts a page left open.
     */
    protected const int MAX_AGE = 86400;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Repository $cache
    ) {
    }

    public function enabled(): bool
    {
        return (bool) $this->settings->get(self::ENABLED);
    }

    /**
     * The token the sign-up form is given: the time it was served and a nonce, signed. The nonce
     * gives every page view a token of its own, so its uses can be counted.
     */
    public function issue(): string
    {
        $time = Carbon::now()->timestamp;
        $nonce = bin2hex(random_bytes(8));

        return $time.'.'.$nonce.'.'.$this->sign($time, $nonce);
    }

    /**
     * Why a sign-up is refused, as a BlockReasons constant, or null when it passes.
     *
     * @param array<string, mixed> $attributes
     */
    public function verdict(array $attributes): ?string
    {
        $field = $attributes[self::FIELD] ?? null;

        if ($field !== null && $field !== '' && ! (is_string($field) && trim($field) === '')) {
            return BlockReasons::HONEYPOT;
        }

        $token = $attributes[self::TOKEN] ?? null;

        // Without the form: an app or an SSO bridge in lenient mode, a script in strict mode.
        if (! is_string($token) || $token === '') {
            return $this->strict() ? BlockReasons::NO_FORM : null;
        }

        [$time, $nonce, $signature] = explode('.', $token, 3) + [null, null, null];

        // A real form never sends a token it was not given, in either mode.
        if (! ctype_digit((string) $time) || ! ctype_xdigit((string) $nonce)
            || ! hash_equals($this->sign((int) $time, (string) $nonce), (string) $signature)) {
            return BlockReasons::FORM_TOKEN;
        }

        $age = Carbon::now()->timestamp - (int) $time;

        // An OAuth provider sends people back to a pre-filled form they can submit at once.
        $viaProvider = is_string($attributes['token'] ?? null) && $attributes['token'] !== '';

        if (! $viaProvider && $age < (int) $this->settings->get(self::MIN_SECONDS)) {
            return BlockReasons::TOO_FAST;
        }

        if ($age > self::MAX_AGE) {
            return $this->strict() ? BlockReasons::NO_FORM : null;
        }

        return $this->useUp((int) $time, (string) $nonce) ? null : BlockReasons::FORM_TOKEN;
    }

    /**
     * Count one use of a token, and say whether it is still within its allowance. Keyed on the
     * parsed parts, since `0001792…` and `1792…` are the same timestamp.
     */
    protected function useUp(int $time, string $nonce): bool
    {
        $key = 'fof-anti-spam.honeypot.used.'.hash('sha256', $time.'.'.strtolower($nonce));

        $this->cache->add($key, 0, self::MAX_AGE);

        return (int) $this->cache->increment($key) <= self::MAX_USES;
    }

    protected function strict(): bool
    {
        return (bool) $this->settings->get(self::STRICT);
    }

    protected function sign(int $time, string $nonce): string
    {
        return hash_hmac('sha256', $time.'.'.strtolower($nonce), $this->secret());
    }

    protected function secret(): string
    {
        $secret = (string) $this->settings->get(self::SECRET);

        // Only if the migration's secret was removed. Read back after writing, so that of two
        // requests racing here both sign with whichever was stored.
        if ($secret === '') {
            $this->settings->set(self::SECRET, bin2hex(random_bytes(32)));
            $secret = (string) $this->settings->get(self::SECRET);
        }

        return $secret;
    }
}

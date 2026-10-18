<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\ContentFilter\Detectors;

use Flarum\User\User;
use FoF\AntiSpam\ContentFilter\AbstractDetector;
use FoF\AntiSpam\ContentFilter\SpamScore;

/**
 * Detects international phone numbers: a `+` or `00` prefix and 9 to 15 digits.
 */
class PhoneDetector extends AbstractDetector
{
    /**
     * A `+` or `00` prefix, then digit groups joined by short runs of separators.
     *
     * Deliberately permissive: anything number-shaped after a prefix is a candidate, and
     * extractPhoneNumbers() decides. Every repetition is possessive, so a run of any length is
     * read once, without the backtracking that exhausts the regex engine's stack.
     *
     * - `+` may not follow a digit or another `+` (`3+4`, `c++`), but may follow a letter or a
     *   full stop: `call+91…` and `tel.+91…` are a spammer's spelling, not arithmetic.
     * - `00` may not follow a letter, digit or `_ + . : / # -`, which is how zero-padded IDs,
     *   times and paths are written (`INV-00123…`, `12:00 123…`, `/api/00123…`).
     * - A country code never begins with 0, which rules out every run of zeros in a large number.
     * - Digits are any script's, and spaces any width: spammers reach for fullwidth digits and
     *   no-break spaces to slip past an ASCII pattern. Combining marks and zero-width characters
     *   inside a group are skipped for the same reason.
     */
    protected const string PHONE_PATTERN = '~
        (?<prefix>
            (?<! [\p{N}+\x{FF0B}] ) [+\x{FF0B}] \p{Zs}?+ \(?+
          | (?<! [\p{L}\p{N}_+\x{FF0B}.:/\#\-] ) 00 \p{Zs}?+
        )
        (?! [0\x{FF10}\x{0660}\x{06F0}\x{0966}] ) (?= \p{Nd} )
        (?<body> GROUP (?: [\p{Zs}\-\p{Pd}.()]{1,4}+ GROUP )*+ )
    ~xu';

    /**
     * One run of digits, with any marks or zero-width characters hidden inside it.
     */
    protected const string GROUP = '(?: \p{Nd} [\p{M}\x{200B}-\x{200D}\x{2060}\x{FEFF}]*+ )++';

    protected const int MIN_DIGITS = 9;

    /**
     * The E.164 maximum. Anything longer is a hash or an identifier, not a number anyone can ring.
     */
    protected const int MAX_DIGITS = 15;

    /**
     * More single-digit groups than this is a list or a diff line (`+ 1 2 3 4 …`), not a number.
     * A real one has at most a one-digit country code and a bracketed trunk zero.
     */
    protected const int MAX_SINGLE_DIGIT_GROUPS = 2;

    /**
     * Extract every phone number from content that has already had its formatting stripped.
     *
     * @return array<string> The matched numbers, in the order they appear.
     */
    public static function extractPhoneNumbers(string $content): array
    {
        $pattern = str_replace('GROUP', self::GROUP, self::PHONE_PATTERN);

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER) === false) {
            throw new \RuntimeException('Phone number pattern failed: '.preg_last_error_msg());
        }

        $numbers = [];

        foreach ($matches as $match) {
            $number = self::trimToNumber($match['prefix'], $match['body']);

            if ($number !== null) {
                $numbers[] = $number;
            }
        }

        return $numbers;
    }

    /**
     * Keep the leading groups that fit in a phone number, or reject the candidate.
     *
     * Trailing groups past the E.164 limit are dropped rather than sinking the match: `+91…
     * 2024` and two numbers in a row would otherwise both escape. A single group that is too
     * long on its own is a hash or an identifier.
     */
    protected static function trimToNumber(string $prefix, string $body): ?string
    {
        preg_match_all('~'.self::GROUP.'~xu', $body, $groups, PREG_OFFSET_CAPTURE);

        $digits = 0;
        $singles = 0;
        $end = 0;

        foreach ($groups[0] as [$group, $offset]) {
            $count = (int) preg_match_all('~\p{Nd}~u', $group);

            if ($digits + $count > self::MAX_DIGITS) {
                break;
            }

            $digits += $count;
            $singles += $count === 1 ? 1 : 0;
            $end = $offset + strlen($group);
        }

        if ($digits < self::MIN_DIGITS || $singles > self::MAX_SINGLE_DIGIT_GROUPS) {
            return null;
        }

        return $prefix.substr($body, 0, $end);
    }

    public function analyze(string $content, User $user, array $context = []): SpamScore
    {
        // Skip if detector is disabled
        if (! $this->isEnabled()) {
            return new SpamScore();
        }

        // Skip if this setting is disabled
        if (! $this->config->get('detect_phones')) {
            return new SpamScore();
        }

        // Only check fresh users
        if (! $this->shouldMonitorUser($user)) {
            return new SpamScore();
        }

        // Strip formatting but preserve numbers
        $cleanContent = $this->stripFormatting($content);

        $phoneNumbers = self::extractPhoneNumbers($cleanContent);
        $count = count($phoneNumbers);

        if ($count === 0) {
            return new SpamScore();
        }

        // Found phone numbers - calculate score based on count
        // Each phone number adds to the score
        $score = min(80, $count * 50); // 50 points per phone, cap at 80

        $reasons = [];
        $phoneNumbers = array_unique($phoneNumbers);

        if ($count === 1) {
            $reasons[] = 'Contains a phone number';
        } else {
            $reasons[] = "Contains {$count} phone numbers";
        }

        return new SpamScore(
            score: $score,
            reasons: $reasons,
            metadata: [
                'detector' => 'phone',
                'count' => $count,
                'phones' => array_values($phoneNumbers),
            ]
        );
    }

    public function getName(): string
    {
        return 'Phone Number Detector';
    }

    public function getDescription(): string
    {
        return 'Detects international phone numbers in content (format: +/00 followed by 9 to 15 digits)';
    }
}

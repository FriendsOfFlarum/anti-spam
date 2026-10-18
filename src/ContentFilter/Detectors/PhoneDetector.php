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
     * A `+` or `00` prefix, then digit groups joined by single separators.
     *
     * - The prefix may not follow a letter, digit, `+` or `.`, so the `00` inside `1000` or
     *   `2008-12-01`, and the `+` in `c++` or `3 + 4`, never start a match.
     * - A country code never begins with 0, which rules out every run of zeros in a large number.
     * - Digits are any script's: spammers reach for fullwidth and Arabic-Indic digits to slip past
     *   an ASCII-only pattern. Combining marks and zero-width characters between them are skipped
     *   for the same reason.
     */
    protected const string PHONE_PATTERN = '~
        (?<! [\p{L}\p{N}_+\x{FF0B}.] )
        (?: [+\x{FF0B}] | 00 \x20? )
        (?! [0\x{FF10}\x{0660}\x{06F0}\x{0966}] ) (?= \p{Nd} )
        (?:
            \p{Nd}
          | [\p{M}\x{200B}-\x{200D}\x{2060}\x{FEFF}]
          | [\x20\-.] (?= [\p{Nd}(] )
          | \( (?= \p{Nd} )
          | \) (?= [\x20\-.]? \p{Nd} )
        )+
        (?! [\p{Nd}\p{L}_] )
    ~xu';

    protected const int MIN_DIGITS = 9;

    /**
     * The E.164 maximum. Anything longer is a hash or an identifier, not a number anyone can ring.
     */
    protected const int MAX_DIGITS = 15;

    /**
     * Extract every phone number from content that has already had its formatting stripped.
     *
     * @return array<string> The matched numbers, in the order they appear.
     */
    public static function extractPhoneNumbers(string $content): array
    {
        if (! preg_match_all(self::PHONE_PATTERN, $content, $matches)) {
            return [];
        }

        return array_values(array_filter($matches[0], function (string $candidate): bool {
            $digits = preg_match_all('~\p{Nd}~u', $candidate);

            // The digits of a `00` prefix are not part of the number.
            if (str_starts_with($candidate, '00')) {
                $digits -= 2;
            }

            return $digits >= self::MIN_DIGITS && $digits <= self::MAX_DIGITS;
        }));
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

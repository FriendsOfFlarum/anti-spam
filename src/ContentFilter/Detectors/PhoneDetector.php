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
 * Detects international phone numbers: a `+` or `00` prefix and 9 to 15 digits. Local ones, a
 * trunk `0` and 10 to 13 digits, when the admin turns them on.
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
     * A zero, for the trunk prefix every local number starts with: ASCII, fullwidth, Arabic-Indic,
     * Persian and Devanagari, the scripts spammers reach for to slip past an ASCII pattern.
     */
    protected const string ZERO = '[0\x{FF10}\x{0660}\x{06F0}\x{0966}]';

    /**
     * A number written for callers inside one country: the trunk `0`, then digit groups joined
     * by short runs of separators, the first group optionally bracketed (`(021) 5555 1234`).
     *
     * The same characters as the international pattern's `00` may not come before it, for the
     * same reason, and neither may `,` (`3,0811…` is a decimal), `=` (`?ref=0811…`) or `(`, which
     * only a candidate starting at the bracket may have (`Invoice:(0811…)`).
     *
     * A letter, digit or `_` straight after the last group is captured as `glued`, not refused
     * here: a check that could fail after the run would make the engine read the rest of it
     * again from every later `0`, which is quadratic in a crafted post. trimToLocalNumber() drops
     * the glued group instead (`08123456789AB` is a reference, `… 24h` is not part of a number).
     */
    protected const string LOCAL_PATTERN = '~
        (?<! [\p{L}\p{N}_+\x{FF0B}.,:=/\#\-(] )
        (?<body>
            (?: \( (?= ZERO ) )?+
            ZERO (?! ZERO ) (?= \p{Nd} )
            GROUP (?: [\p{Zs}\-\p{Pd}.()]{1,3}+ GROUP )*+
        )
        (?<glued> [\p{L}\p{N}_] )?+
    ~xu';

    /**
     * The shortest local number counted. Ten digits with the trunk zero is the shortest mobile
     * number in the countries that write them this way; anything shorter is more often a date,
     * a code or an amount.
     */
    protected const int MIN_LOCAL_DIGITS = 10;

    /**
     * E.164's fifteen, less a country code of at least two digits.
     */
    protected const int MAX_LOCAL_DIGITS = 13;

    /**
     * Extract every phone number from content that has already had its formatting stripped.
     *
     * @return array<string> The matched numbers, in the order they appear.
     */
    public static function extractPhoneNumbers(string $content): array
    {
        return array_column(self::matchInternational($content), 0);
    }

    /**
     * Every international number with its byte offset in the content.
     *
     * @return array<array{0: string, 1: int}>
     */
    protected static function matchInternational(string $content): array
    {
        $pattern = str_replace('GROUP', self::GROUP, self::PHONE_PATTERN);

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            throw new \RuntimeException('Phone number pattern failed: '.preg_last_error_msg());
        }

        $numbers = [];

        foreach ($matches as $match) {
            $number = self::trimToNumber($match['prefix'][0], $match['body'][0]);

            if ($number !== null) {
                $numbers[] = [$number, $match['prefix'][1]];
            }
        }

        return $numbers;
    }

    /**
     * Extract every local-format phone number (`0811-2506-6288`, `020 7946 0958`) from content
     * that has already had its formatting stripped.
     *
     * Every international number is blanked first, so `+62 0811-2506-6288` is one international
     * number and not a local one as well. Only what was accepted as one: a candidate rejected as
     * too long would otherwise take the local number after it down with it.
     *
     * @return array<string> The matched numbers, in the order they appear.
     */
    public static function extractLocalPhoneNumbers(string $content): array
    {
        $masked = '';
        $from = 0;

        foreach (self::matchInternational($content) as [$number, $offset]) {
            $masked .= substr($content, $from, $offset - $from).str_repeat(' ', strlen($number));
            $from = $offset + strlen($number);
        }

        $content = $masked.substr($content, $from);

        $pattern = str_replace(['GROUP', 'ZERO'], [self::GROUP, self::ZERO], self::LOCAL_PATTERN);

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER) === false) {
            throw new \RuntimeException('Phone number pattern failed: '.preg_last_error_msg());
        }

        $numbers = [];

        foreach ($matches as $match) {
            $number = self::trimToLocalNumber($match['body'], ($match['glued'] ?? '') !== '');

            if ($number !== null) {
                $numbers[] = $number;
            }
        }

        return $numbers;
    }

    /**
     * Keep the leading groups that fit in a local number, or reject the candidate.
     *
     * Without a country code there is less to go on, so the shape has to look like a number:
     * - the area or operator code with its trunk zero is three to five digits (`020`, `0811`,
     *   `07700`), which is what tells it apart from a day-first date (`01-02-2024 10:00`);
     * - except that five pairs (`01 23 45 67 89`) is how France writes its numbers;
     * - or the whole number is one unbroken run (`081125066288`);
     * - no group is a single digit, and four short dotted groups are an IP address.
     *
     * A last group glued to a word is not part of the number and is dropped first.
     */
    protected static function trimToLocalNumber(string $body, bool $glued = false): ?string
    {
        preg_match_all('~'.self::GROUP.'~xu', $body, $groups, PREG_OFFSET_CAPTURE);

        if ($glued) {
            array_pop($groups[0]);
        }

        $digits = 0;
        $counts = [];
        $end = 0;

        foreach ($groups[0] as [$group, $offset]) {
            $count = (int) preg_match_all('~\p{Nd}~u', $group);

            if ($digits + $count > self::MAX_LOCAL_DIGITS) {
                break;
            }

            $digits += $count;
            $counts[] = $count;
            $end = $offset + strlen($group);
        }

        if ($digits < self::MIN_LOCAL_DIGITS || min($counts) < 2) {
            return null;
        }

        $number = substr($body, 0, $end);

        if (count($counts) > 1) {
            $pairs = $counts === [2, 2, 2, 2, 2];

            if (! $pairs && ($counts[0] < 3 || $counts[0] > 5)) {
                return null;
            }

            if (preg_match('~^\p{Nd}{1,3}(?:\.\p{Nd}{1,3}){3}$~u', $number)) {
                return null;
            }
        }

        return $number;
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

        if ($this->config->get('detect_local_phones')) {
            $phoneNumbers = array_merge($phoneNumbers, self::extractLocalPhoneNumbers($cleanContent));
        }
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
            evidence: [self::listMatches($phoneNumbers)],
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
        return 'Detects international phone numbers in content (format: +/00 followed by 9 to 15 digits), and optionally local ones (a leading 0 and 10 to 13 digits)';
    }
}

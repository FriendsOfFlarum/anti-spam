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
 * Detects email addresses in post content.
 *
 * Email addresses in posts are often spam (contact info, phishing, etc.)
 */
class EmailDetector extends AbstractDetector
{
    /**
     * An address whose domain ends in a TLD of letters.
     *
     * - The local part may not follow a word character, `/` or `@`, so `flarum/core@2.0.2` and
     *   `@scope/pkg@1.0` never start a match, and a version after the `@` has no TLD to end in.
     * - A `:path` straight after the domain is a git remote such as `git@github.com:org/repo`.
     * - Letters are any script's: a spammer is as happy with `пример.рф` as with `example.com`.
     */
    protected const string EMAIL_PATTERN = '~
        (?<! [\p{L}\p{N}_.%+\-/@] )
        [\p{L}\p{N}_.%+\-]+
        @
        (?: [\p{L}\p{N}] (?: [\p{L}\p{N}\-]* [\p{L}\p{N}] )? \. )+
        \p{L}{2,63}
        (?! [\p{L}\p{N}_\-@] | :\S )
    ~xu';

    /**
     * Extract every email address from content that has already had its formatting stripped.
     *
     * @return array<string> The matched addresses, in the order they appear.
     */
    public static function extractEmails(string $content): array
    {
        return preg_match_all(self::EMAIL_PATTERN, $content, $matches) ? $matches[0] : [];
    }

    public function analyze(string $content, User $user, array $context = []): SpamScore
    {
        // Skip if detector is disabled
        if (! $this->isEnabled()) {
            return new SpamScore();
        }

        // Skip if this setting is disabled
        if (! $this->config->get('detect_emails')) {
            return new SpamScore();
        }

        // Only check fresh users
        if (! $this->shouldMonitorUser($user)) {
            return new SpamScore();
        }

        // Strip formatting
        $cleanContent = $this->stripFormatting($content);

        $allowed = $this->config->getAllowedDomains();

        // An address on an allowed domain is one this forum already trusts, and one on a reserved
        // example domain cannot reach anybody.
        $emails = array_values(array_filter(
            self::extractEmails($cleanContent),
            fn (string $email) => ! self::domainIsTrusted(substr($email, strrpos($email, '@') + 1), $allowed)
        ));
        $count = count($emails);

        if ($count === 0) {
            return new SpamScore();
        }

        // Found email addresses - calculate score
        $score = min(80, $count * 50); // 50 points per email, cap at 80

        $reasons = [];
        $emails = array_unique($emails);

        if ($count === 1) {
            $reasons[] = 'Contains an email address';
        } else {
            $reasons[] = "Contains {$count} email addresses";
        }

        return new SpamScore(
            score: $score,
            reasons: $reasons,
            metadata: [
                'detector' => 'email',
                'count' => $count,
                'emails' => array_values($emails),
            ]
        );
    }

    /**
     * @param array<string> $allowedDomains
     */
    protected static function domainIsTrusted(string $domain, array $allowedDomains): bool
    {
        $domain = mb_strtolower($domain);

        if (UrlDetector::isLocalOrReserved($domain)) {
            return true;
        }

        foreach ($allowedDomains as $allowed) {
            if ($domain === $allowed || str_ends_with($domain, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    public function getName(): string
    {
        return 'Email Address Detector';
    }

    public function getDescription(): string
    {
        return 'Detects email addresses in post content';
    }
}

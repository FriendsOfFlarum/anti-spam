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
 * Detects email addresses in post content
 *
 * Email addresses in posts are often spam (contact info, phishing, etc.)
 */
class EmailDetector extends AbstractDetector
{
    /**
     * Regex pattern for detecting email addresses
     */
    private const EMAIL_PATTERN = '~\S+@\S+\.\S+~';

    public function analyze(string $content, User $user, array $context = []): SpamScore
    {
        // Skip if detector is disabled
        if (! $this->isEnabled()) {
            return new SpamScore();
        }

        // Skip if this setting is disabled
        if (! $this->config->get('detect_emails', true)) {
            return new SpamScore();
        }

        // Only check fresh users
        if (! $this->shouldMonitorUser($user)) {
            return new SpamScore();
        }

        // Strip formatting
        $cleanContent = $this->stripFormatting($content);

        // Detect email addresses
        $matches = [];
        $count = preg_match_all(self::EMAIL_PATTERN, $cleanContent, $matches);

        if ($count === false || $count === 0) {
            return new SpamScore();
        }

        // Found email addresses - calculate score
        $score = min(50, $count * 30); // Cap at 50 points

        $reasons = [];
        $emails = array_unique($matches[0]);

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

    public function getName(): string
    {
        return 'Email Address Detector';
    }

    public function getDescription(): string
    {
        return 'Detects email addresses in post content';
    }
}

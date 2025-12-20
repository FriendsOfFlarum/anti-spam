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
 * Detects phone numbers in content
 *
 * Matches international phone numbers with + or 00 prefix and at least 9 digits
 */
class PhoneDetector extends AbstractDetector
{
    /**
     * Regex pattern for detecting phone numbers
     * Matches: +1234567890, 00123456789, +1-234-567-8900, etc.
     */
    private const PHONE_PATTERN = '/(\\+|00)([0-9-\p{No}\p{Nd}\p{M}\s]){9,}/u';

    public function analyze(string $content, User $user, array $context = []): SpamScore
    {
        // Skip if detector is disabled
        if (! $this->isEnabled()) {
            return new SpamScore();
        }

        // Skip if this setting is disabled
        if (! $this->config->get('detect_phones', true)) {
            return new SpamScore();
        }

        // Only check fresh users
        if (! $this->shouldMonitorUser($user)) {
            return new SpamScore();
        }

        // Strip formatting but preserve numbers
        $cleanContent = $this->stripFormatting($content);

        // Detect phone numbers
        $matches = [];
        $count = preg_match_all(self::PHONE_PATTERN, $cleanContent, $matches);

        if ($count === false || $count === 0) {
            return new SpamScore();
        }

        // Found phone numbers - calculate score based on count
        // Each phone number adds to the score
        $score = min(50, $count * 25); // Cap at 50 points (2+ phones)

        $reasons = [];
        $phoneNumbers = array_unique($matches[0]);

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
        return 'Detects international phone numbers in content (format: +/00 followed by 9+ digits)';
    }
}

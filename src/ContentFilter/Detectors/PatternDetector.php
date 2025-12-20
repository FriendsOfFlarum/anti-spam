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
use FoF\AntiSpam\ContentFilter\ConfigurationManager;
use FoF\AntiSpam\ContentFilter\SpamScore;
use Psr\Log\LoggerInterface;

/**
 * Detects custom regex patterns in content.
 *
 * Patterns can be configured via admin UI or extend.php
 */
class PatternDetector extends AbstractDetector
{
    public function __construct(
        ConfigurationManager $config,
        private LoggerInterface $log
    ) {
        parent::__construct($config);
    }

    public function analyze(string $content, User $user, array $context = []): SpamScore
    {
        // Skip if detector is disabled
        if (! $this->isEnabled()) {
            return new SpamScore();
        }

        // Only check fresh users
        if (! $this->shouldMonitorUser($user)) {
            return new SpamScore();
        }

        // Get patterns from configuration
        $patterns = $this->config->getBlockPatterns();

        if (empty($patterns)) {
            return new SpamScore();
        }

        // Strip formatting
        $cleanContent = $this->stripFormatting($content);

        $totalScore = 0;
        $matchedPatterns = [];
        $reasons = [];

        // Test each pattern
        foreach ($patterns as $patternData) {
            $pattern = $patternData['pattern'];
            $description = $patternData['description'] ?? null;

            if (empty($pattern)) {
                continue;
            }

            try {
                // Test pattern
                if (preg_match($pattern, $cleanContent)) {
                    $matchedPatterns[] = [
                        'pattern' => $pattern,
                        'description' => $description,
                    ];

                    // Each pattern match adds 50 points
                    $totalScore += 50;

                    $reasons[] = $description ?: "Matched pattern: {$pattern}";
                }
            } catch (\Throwable $e) {
                // Invalid regex pattern, log and skip
                $this->log->warning(
                    "[FoF Anti Spam] Invalid regex pattern in PatternDetector: {$pattern}",
                    ['error' => $e->getMessage()]
                );
                continue;
            }
        }

        if (empty($matchedPatterns)) {
            return new SpamScore();
        }

        // Cap total score at 100
        $totalScore = min(100, $totalScore);

        return new SpamScore(
            score: $totalScore,
            reasons: $reasons,
            metadata: [
                'detector' => 'pattern',
                'matched_patterns' => $matchedPatterns,
                'match_count' => count($matchedPatterns),
            ]
        );
    }

    public function getName(): string
    {
        return 'Pattern Detector';
    }

    public function getDescription(): string
    {
        return 'Detects custom regex patterns in content';
    }
}

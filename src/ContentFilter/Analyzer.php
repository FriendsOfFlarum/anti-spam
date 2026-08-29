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
use Psr\Log\LoggerInterface;

/**
 * Main content analyzer that coordinates all detectors.
 */
class Analyzer
{
    /**
     * @var array<DetectorInterface>
     */
    private array $detectors = [];

    public function __construct(
        private ConfigurationManager $config,
        private LoggerInterface $log
    ) {
    }

    /**
     * Register a detector.
     */
    public function addDetector(DetectorInterface $detector): self
    {
        $this->detectors[] = $detector;

        return $this;
    }

    /**
     * Analyze content and return combined spam score.
     *
     * @param string $content The content to analyze
     * @param User $user The user who created the content
     * @param array<string, mixed> $context Additional context
     * @return AnalysisResult
     */
    public function analyze(string $content, User $user, array $context = []): AnalysisResult
    {
        // Check if content filtering is globally enabled
        if (! $this->config->isEnabled()) {
            return new AnalysisResult(
                totalScore: 0,
                detectorScores: [],
                isSpam: false,
                shouldFlag: false,
                shouldUnapprove: false
            );
        }

        $detectorScores = [];
        $totalScore = 0;

        // Run all detectors
        foreach ($this->detectors as $detector) {
            try {
                if (! $detector->isEnabled()) {
                    continue;
                }

                $score = $detector->analyze($content, $user, $context);

                if ($score->isSpam()) {
                    $detectorScores[$detector->getName()] = $score;
                    $totalScore += $score->getScore();
                }
            } catch (\Throwable $e) {
                $this->log->error(
                    "[FoF Anti Spam] Error in detector {$detector->getName()}: {$e->getMessage()}",
                    [
                        'detector' => get_class($detector),
                        'exception' => $e,
                    ]
                );
            }
        }

        // Cap total score at 100
        $totalScore = min(100, $totalScore);

        // Determine actions based on thresholds
        $spamThreshold = (int) $this->config->get('spam_threshold');
        $flagThreshold = (int) $this->config->get('flag_threshold');

        $isSpam = $totalScore >= $flagThreshold;
        $shouldFlag = $isSpam && (bool) $this->config->get('auto_flag');
        $shouldUnapprove = $totalScore >= $spamThreshold && (bool) $this->config->get('auto_unapprove');

        return new AnalysisResult(
            totalScore: $totalScore,
            detectorScores: $detectorScores,
            isSpam: $isSpam,
            shouldFlag: $shouldFlag,
            shouldUnapprove: $shouldUnapprove
        );
    }

    /**
     * Get all registered detectors.
     *
     * @return array<DetectorInterface>
     */
    public function getDetectors(): array
    {
        return $this->detectors;
    }
}

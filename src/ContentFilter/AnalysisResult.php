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

/**
 * Result of content analysis
 */
class AnalysisResult
{
    /**
     * @param int $totalScore Combined spam score from all detectors (0-100)
     * @param array<string, SpamScore> $detectorScores Individual detector results
     * @param bool $isSpam Whether content is considered spam
     * @param bool $shouldFlag Whether to create a moderation flag
     * @param bool $shouldUnapprove Whether to unapprove the content
     */
    public function __construct(
        private int $totalScore,
        private array $detectorScores,
        private bool $isSpam,
        private bool $shouldFlag,
        private bool $shouldUnapprove
    ) {
    }

    public function getTotalScore(): int
    {
        return $this->totalScore;
    }

    /**
     * @return array<string, SpamScore>
     */
    public function getDetectorScores(): array
    {
        return $this->detectorScores;
    }

    public function isSpam(): bool
    {
        return $this->isSpam;
    }

    public function shouldFlag(): bool
    {
        return $this->shouldFlag;
    }

    public function shouldUnapprove(): bool
    {
        return $this->shouldUnapprove;
    }

    /**
     * Get all reasons from all detectors
     *
     * @return array<string>
     */
    public function getAllReasons(): array
    {
        $reasons = [];

        foreach ($this->detectorScores as $detectorName => $score) {
            foreach ($score->getReasons() as $reason) {
                $reasons[] = "[{$detectorName}] {$reason}";
            }
        }

        return $reasons;
    }

    /**
     * Convert to array for JSON serialization
     *
     * @return array{totalScore: int, isSpam: bool, shouldFlag: bool, shouldUnapprove: bool, reasons: array<string>, detectors: array<string, array>}
     */
    public function toArray(): array
    {
        $detectors = [];

        foreach ($this->detectorScores as $detectorName => $score) {
            $detectors[$detectorName] = $score->toArray();
        }

        return [
            'totalScore' => $this->totalScore,
            'isSpam' => $this->isSpam,
            'shouldFlag' => $this->shouldFlag,
            'shouldUnapprove' => $this->shouldUnapprove,
            'reasons' => $this->getAllReasons(),
            'detectors' => $detectors,
        ];
    }
}

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
 * Represents a spam score contribution from a detector.
 */
class SpamScore
{
    /**
     * @param int $score Score contribution (0-100)
     * @param array<string> $reasons Human-readable reasons for the score
     * @param array<string, mixed> $metadata Additional metadata about the detection
     */
    public function __construct(
        private int $score = 0,
        private array $reasons = [],
        private array $metadata = []
    ) {
        // Clamp score to 0-100 range
        $this->score = max(0, min(100, $score));
    }

    /**
     * Get the spam score (0-100).
     */
    public function getScore(): int
    {
        return $this->score;
    }

    /**
     * Get human-readable reasons for the score.
     *
     * @return array<string>
     */
    public function getReasons(): array
    {
        return $this->reasons;
    }

    /**
     * Get additional metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Check if this score indicates spam was detected.
     */
    public function isSpam(): bool
    {
        return $this->score > 0;
    }

    /**
     * Add a reason to the score.
     */
    public function addReason(string $reason): self
    {
        $this->reasons[] = $reason;

        return $this;
    }

    /**
     * Add metadata.
     */
    public function addMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Merge another spam score into this one.
     */
    public function merge(SpamScore $other): self
    {
        $this->score = min(100, $this->score + $other->getScore());
        $this->reasons = array_merge($this->reasons, $other->getReasons());
        $this->metadata = array_merge($this->metadata, $other->getMetadata());

        return $this;
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array{score: int, reasons: array<string>, metadata: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'reasons' => $this->reasons,
            'metadata' => $this->metadata,
        ];
    }
}

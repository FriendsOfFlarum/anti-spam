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

/**
 * Interface for content spam detectors.
 */
interface DetectorInterface
{
    /**
     * Analyze content and return spam score contribution.
     *
     * @param string $content The content to analyze
     * @param User $user The user who created the content
     * @param array<string, mixed> $context Additional context (e.g., 'type' => 'discussion_title')
     * @return SpamScore The spam score with details
     */
    public function analyze(string $content, User $user, array $context = []): SpamScore;

    /**
     * Get the detector name for display.
     */
    public function getName(): string;

    /**
     * Get the detector description.
     */
    public function getDescription(): string;

    /**
     * Check if this detector is enabled based on configuration.
     */
    public function isEnabled(): bool;
}

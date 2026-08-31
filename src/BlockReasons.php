<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam;

/**
 * The rules that fired when a registration was blocked.
 *
 * Recorded at block time rather than derived later: the admin's thresholds change, so
 * re-evaluating a stored StopForumSpam response against current settings can contradict the
 * decision that was actually taken.
 */
class BlockReasons
{
    public const CONFIDENCE = 'confidence';
    public const FREQUENCY = 'frequency';
    public const BLACKLISTED = 'blacklisted';
    public const TOR_EXIT = 'torExit';
    public const DENIED_ASN = 'deniedAsn';

    /**
     * @param list<string> $reasons Rule identifiers, in the order they are evaluated.
     * @param array<string, mixed> $context The values that tripped them, for display.
     */
    public function __construct(
        public array $reasons = [],
        public array $context = []
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->reasons === [];
    }

    /**
     * @return array{reasons: list<string>, context: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'reasons' => $this->reasons,
            'context' => $this->context,
        ];
    }
}

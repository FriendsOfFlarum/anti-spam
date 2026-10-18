<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Listener;

use Flarum\Settings\SettingsRepositoryInterface;
use FoF\AntiSpam\BlockReasons;
use FoF\AntiSpam\Event\RegistrationWasBlocked;
use FoF\AntiSpam\Job\ReportSpammerJob;
use FoF\AntiSpam\Model\BlockedRegistration;
use FoF\AntiSpam\StopForumSpam;
use Illuminate\Contracts\Queue\Queue;

class ReportBlockedRegistration
{
    /**
     * Reasons that rest on StopForumSpam's own data. Tor and denied networks are this forum's
     * policy, and reporting on them would list an innocent address for every other forum.
     */
    protected const EVIDENCE = [BlockReasons::CONFIDENCE, BlockReasons::FREQUENCY, BlockReasons::BLACKLISTED];

    /**
     * What BlockedRegistration::create() stores for a field the attempt did not carry.
     */
    protected const MISSING = 'unknown';

    public function __construct(protected SettingsRepositoryInterface $settings, protected StopForumSpam $sfs, protected Queue $queue)
    {
    }

    public function handle(RegistrationWasBlocked $event): void
    {
        $blocked = $event->blocked;

        if (! $this->settings->get('fof-anti-spam.report_blocked_registrations') || ! $this->sfs->canReport()) {
            return;
        }

        if (! $this->restsOnEvidence($blocked) || in_array(self::MISSING, [$blocked->username, $blocked->email, $blocked->ip], true)) {
            return;
        }

        $this->queue->push(new ReportSpammerJob($blocked->username, $blocked->email, $blocked->ip));
    }

    protected function restsOnEvidence(BlockedRegistration $blocked): bool
    {
        $reasons = json_decode((string) $blocked->reasons, true)['reasons'] ?? [];

        return is_array($reasons) && array_intersect($reasons, self::EVIDENCE) !== [];
    }
}

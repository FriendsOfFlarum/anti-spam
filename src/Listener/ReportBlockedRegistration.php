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
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\Queue;
use Psr\Log\LoggerInterface;

class ReportBlockedRegistration
{
    /**
     * Seconds during which the same identity is not reported again. The lookup is cached, so a
     * bot retrying one identity is blocked on every attempt.
     */
    protected const int REPORTED_FOR = 86400;

    /**
     * Reasons that rest on StopForumSpam's own data, as opposed to this forum's network policy.
     */
    protected const array EVIDENCE = [BlockReasons::CONFIDENCE, BlockReasons::FREQUENCY, BlockReasons::BLACKLISTED];

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected StopForumSpam $sfs,
        protected Queue $queue,
        protected Repository $cache,
        protected LoggerInterface $log
    ) {
    }

    public function handle(RegistrationWasBlocked $event): void
    {
        $blocked = $event->blocked;

        if (! $this->settings->get('fof-anti-spam.report_blocked_registrations') || ! $this->sfs->canReport()) {
            return;
        }

        if (! $this->restsOnEvidence($blocked) || ! $this->personIsListed($blocked) || in_array(BlockedRegistration::MISSING, [$blocked->username, $blocked->email, $blocked->ip], true)) {
            return;
        }

        if (! $this->cache->add('fof-anti-spam.reported.'.md5($blocked->ip.'|'.$blocked->email.'|'.$blocked->username), 1, self::REPORTED_FOR)) {
            return;
        }

        // Reporting is a courtesy to StopForumSpam. A queue that cannot take the job must not turn
        // the block the visitor is owed into a server error.
        try {
            $this->queue->push(new ReportSpammerJob($blocked->username, $blocked->email, $blocked->ip));
        } catch (\Throwable $e) {
            $this->log->error("[FoF Anti Spam] Could not queue a report for a blocked registration: {$e->getMessage()}");
        }
    }

    protected function restsOnEvidence(BlockedRegistration $blocked): bool
    {
        $reasons = json_decode((string) $blocked->reasons, true)['reasons'] ?? [];

        return is_array($reasons) && array_intersect($reasons, self::EVIDENCE) !== [];
    }

    /**
     * Whether StopForumSpam already knows the email or username, rather than only the address.
     *
     * Tor, a denied network, or a score against a VPN or carrier-grade NAT address says nothing
     * about the person behind it. Reporting on those would list an innocent email and name for
     * every other forum that consults the database.
     */
    protected function personIsListed(BlockedRegistration $blocked): bool
    {
        $response = json_decode((string) $blocked->data, true);

        foreach (['email', 'username'] as $field) {
            if (! empty($response[$field]['appears']) || ! empty($response[$field]['blacklisted'])) {
                return true;
            }
        }

        return false;
    }
}

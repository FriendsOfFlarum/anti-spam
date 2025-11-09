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
use FoF\AntiSpam\Event\RegistrationWasBlocked;
use FoF\AntiSpam\Job\ReportSpammerJob;
use FoF\AntiSpam\StopForumSpam;
use Illuminate\Contracts\Queue\Queue;

class ReportBlockedRegistration
{
    public function __construct(protected SettingsRepositoryInterface $settings, protected StopForumSpam $sfs, protected Queue $queue)
    {
    }

    public function handle(RegistrationWasBlocked $event): void
    {
        if ($this->settings->get('fof-anti-spam.report_blocked_registrations') && $this->sfs->isEnabled()) {
            $blocked = $event->blocked;
            $this->queue->push(
                new ReportSpammerJob(
                    $blocked->username,
                    $blocked->email,
                    $blocked->ip
                )
            );
        }
    }
}

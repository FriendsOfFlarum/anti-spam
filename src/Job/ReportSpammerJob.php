<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Job;

use Flarum\Queue\AbstractJob;
use FoF\AntiSpam\StopForumSpam;
use Psr\Log\LoggerInterface;

class ReportSpammerJob extends AbstractJob
{
    public $username;
    public $email;
    public $ipAddress;

    public function __construct(string $username, string $email, string $ipAddress)
    {
        $this->username = $username;
        $this->email = $email;
        $this->ipAddress = $ipAddress;
    }

    public function handle(StopForumSpam $sfs, LoggerInterface $log)
    {
        if (! $sfs->isEnabled()) {
            $log->info('[FoF Anti Spam] Tried to report spammer to StopForumSpam, but not API key was configured.');
            return;
        }

        try {
            $sfs->report([
                'ip_addr'  => $this->ipAddress,
                'username' => $this->username,
                'email'    => $this->email,
            ]);
        } catch (\Throwable $e) {
            $log->error("[FoF Anti Spam] Failed to report spammer to StopForumSpam: {$e->getMessage()}");
        }
    }
}

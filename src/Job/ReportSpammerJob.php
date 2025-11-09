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
use FoF\AntiSpam\Api\SfsClient;
use FoF\AntiSpam\StopForumSpam;
use Psr\Log\LoggerInterface;

class ReportSpammerJob extends AbstractJob
{
    public function __construct(public string $username, public string $email, public string $ipAddress)
    {
    }

    public function handle(StopForumSpam $sfs, SfsClient $client, LoggerInterface $log): void
    {
        if (! $sfs->isEnabled()) {
            return;
        }

        try {
            $client->report([
                'ip_addr' => $this->ipAddress,
                'username' => $this->username,
                'email' => $this->email,
            ]);
        } catch (\Throwable $e) {
            $log->error("[FoF Anti Spam] Failed to report spammer to StopForumSpam: {$e->getMessage()}");
        }
    }
}

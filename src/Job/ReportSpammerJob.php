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
    public $username;
    public $email;
    public $ipAddress;

    public function __construct(string $username, string $email, string $ipAddress)
    {
        $this->username = $username;
        $this->email = $email;
        $this->ipAddress = $ipAddress;
    }

    public function handle(StopForumSpam $sfs, SfsClient $client, LoggerInterface $log)
    {
        if (! $sfs->isEnabled()) {
            return;
        }

        try {
            $client->report([
                'ip_addr'  => $this->ipAddress,
                'username' => $this->username,
                'email'    => $this->email,
            ]);
        } catch (\Throwable $e) {
            $log->error("[FoF Anti Spam] Failed to report spammer to StopForumSpam: {$e->getMessage()}");
        }
    }
}

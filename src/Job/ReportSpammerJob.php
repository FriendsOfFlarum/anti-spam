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
use Flarum\User\User;
use FoF\AntiSpam\StopForumSpam;
use Psr\Log\LoggerInterface;

class ReportSpammerJob extends AbstractJob
{
    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle(StopForumSpam $sfs, LoggerInterface $log)
    {
        if (! $sfs->isEnabled()) {
            return;
        }

        $post = $this->user->posts()->first();

        $ipAddress = '8.8.8.8';

        if ($post && filter_var($post->ip_address, FILTER_VALIDATE_IP, [FILTER_FLAG_NO_PRIV_RANGE])) {
            $ipAddress = $post->ip_address;
        }

        try {
            $sfs->report([
                'ip_addr'  => $ipAddress,
                'username' => $this->user->username,
                'email'    => $this->user->email,
            ]);
        } catch (\Throwable $e) {
            $log->error("Failed to report spammer to StopForumSpam: {$e->getMessage()}");
        }
    }
}

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

use FoF\AntiSpam\Event\MarkedUserAsSpammer;
use FoF\AntiSpam\Job\ReportSpammerJob;
use Illuminate\Contracts\Queue\Queue;

class ReportSpammer
{
    protected $queue;

    public function __construct(Queue $queue)
    {
        $this->queue = $queue;
    }

    public function handle(MarkedUserAsSpammer $event)
    {
        $this->queue->push(new ReportSpammerJob($event->user));
    }
}

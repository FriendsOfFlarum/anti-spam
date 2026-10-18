<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\integration;

use Flarum\Foundation\AbstractServiceProvider;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\SyncQueue;

/**
 * A queue whose backend is down: every push throws, as a database or Redis queue does when its
 * connection is gone.
 */
class FailingQueueProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->extend(Queue::class, fn () => new class(0) extends SyncQueue {
            public function push($job, $data = '', $queue = null)
            {
                throw new \RuntimeException('Queue backend unavailable');
            }
        });
    }
}

<?php

/*
 * This file is part of fof/stopforumspam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Listener;

use Flarum\Foundation\ValidationException;
use FoF\AntiSpam\Event\MarkedUserAsSpammer;
use FoF\AntiSpam\StopForumSpam;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Message;

class ReportSpammer
{
    /**
     * @var StopForumSpam
     */
    private $sfs;

    public function __construct(StopForumSpam $sfs)
    {
        $this->sfs = $sfs;
    }

    public function handle(MarkedUserAsSpammer $event)
    {
        if (!$this->sfs->isEnabled()) {
            return;
        }

        $user = $event->user;
        $post = $user->posts()->first();

        $ipAddress = '8.8.8.8';

        if ($post && filter_var($post->ip_address, FILTER_VALIDATE_IP, [FILTER_FLAG_NO_PRIV_RANGE])) {
            $ipAddress = $post->ip_address;
        }

        try {
            $this->sfs->report([
                'ip_addr'  => $ipAddress,
                'username' => $user->username,
                'email'    => $user->email,
            ]);
        } catch (RequestException $e) {
            throw new ValidationException([
                'sfs' => strip_tags(Message::bodySummary($e->getResponse())),
            ]);
        } catch (\Throwable $e) {
            throw new ValidationException([
                'sfs' => resolve('translator')->trans('fof-stopforumspam.api.error.unknown'),
            ]);
        }
    }
}

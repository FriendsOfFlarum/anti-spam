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

use Flarum\Foundation\ValidationException;
use Flarum\User\Event\RegisteringFromProvider;
use FoF\AntiSpam\StopForumSpam;
use Illuminate\Support\Arr;

class ProviderRegistration
{
    protected $sfs;

    public function __construct(StopForumSpam $sfs)
    {
        $this->sfs = $sfs;
    }

    public function handle(RegisteringFromProvider $event)
    {
        $check = $this->sfs->shouldPreventLogin([
            'ip'       => $this->getIpAddress(),
            'email'    => $event->user->email,
            'username' => $event->user->username,
        ], $event->provider, $event->payload);

        if ($check) {
            throw new ValidationException([
                'username' => resolve('translator')->trans('fof-anti-spam.forum.message.spam'),
            ]);
        }
    }

    protected function getIpAddress(): ?string
    {
        $serverParams = $_SERVER;

        return Arr::get($serverParams, 'HTTP_CLIENT_IP')
            ?? Arr::get($serverParams, 'HTTP_CF_CONNECTING_IP')
            ?? Arr::get($serverParams, 'HTTP_X_FORWARDED_FOR')
            ?? Arr::get($serverParams, 'REMOTE_ADDR');
    }
}

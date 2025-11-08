<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Event;

/**
 * @deprecated Use `\FoF\AntiSpam\Event\RegistrationWasBlocked` instead. Will be removed in Flarum 2.0
 */
class RegistrationBlocked
{
    public function __construct(public string $username, public ?string $ipAddress, public string $email, public array $data = [], public ?string $provider = null, public ?array $providerData = null)
    {
    }
}

<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Api\Serializers;

use Flarum\Api\Serializer\AbstractSerializer;
use FoF\AntiSpam\Model\BlockedRegistration;

class BlockedRegistrationSerializer extends AbstractSerializer
{
    public $type = 'blocked-registrations';

    /**
     * @param BlockedRegistration $blocked
     * @return array
     */
    public function getDefaultAttributes($blocked): array
    {
        return [
            'ip' => $blocked->ip,
            'email' => $blocked->email,
            'username' => $blocked->username,
            'sfsData' => $blocked->data,
            'provider' => $blocked->provider,
            'providerData' => $blocked->provider_data,
            'attemptedAt' => $this->formatDate($blocked->attempted_at),
        ];
    }
}

<?php

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
            'data' => $blocked->data,
            'provider' => $blocked->provider,
            'providerData' => $blocked->provider_data,
            'attemptedAt' => $this->formatDate($blocked->attempted_at),
        ];
    }
}

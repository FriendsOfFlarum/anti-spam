<?php

namespace FoF\AntiSpam\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;

/**
 * @property int    $id
 * @property string $ip
 * @property string $email
 * @property string $username
 * @property array $data
 * @property string|null $provider
 * @property array|null $provider_data
 * @property Carbon $attempted_at
 */
class BlockedRegistration extends AbstractModel
{
    public $table = 'blocked_registrations';

    public $casts = [
        'attempted_at' => 'datetime',
        'data' => 'array'
    ];

    public static function create(string $ip, string $email, string $username, array $data, ?string $provider = null, ?array $providerData = null): self
    {
        $blocked = new static();

        $blocked->ip = $ip;
        $blocked->email = $email;
        $blocked->username = $username;
        $blocked->data = $data;
        $blocked->provider = $provider;
        $blocked->provider_data = $providerData;
        $blocked->attempted_at = Carbon::now();

        $blocked->save();

        return $blocked;
    }
}

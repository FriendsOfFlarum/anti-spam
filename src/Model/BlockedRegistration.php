<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\Database\ScopeVisibilityTrait;
use FoF\AntiSpam\BlockReasons;

/**
 * @property int    $id
 * @property string $ip
 * @property string $email
 * @property string $username
 * @property string $data
 * @property string|null $provider
 * @property string|false $provider_data
 * @property string|null $reasons
 * @property Carbon $attempted_at
 */
class BlockedRegistration extends AbstractModel
{
    use ScopeVisibilityTrait;

    public $table = 'blocked_registrations';

    public $casts = [
        'attempted_at' => 'datetime',
    ];

    public static function create(string $ip, string $email, string $username, string $data, ?string $provider = null, ?array $providerData = null, ?BlockReasons $reasons = null): self
    {
        $blocked = new static();

        $blocked->ip = $ip;
        $blocked->email = $email;
        $blocked->username = $username;
        $blocked->data = $data;
        $blocked->provider = $provider;
        $blocked->provider_data = json_encode($providerData);
        // Null rather than an empty structure when nothing was recorded, so the frontend can
        // tell "blocked before we recorded reasons" from "blocked for no recorded reason".
        $blocked->reasons = $reasons !== null && ! $reasons->isEmpty() ? json_encode($reasons->toArray()) : null;
        $blocked->attempted_at = Carbon::now();

        $blocked->save();

        return $blocked;
    }
}

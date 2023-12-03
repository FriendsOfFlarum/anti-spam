<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Api;

use Illuminate\Support\Arr;

class SfsResponse
{
    /**
     * @var bool
     */
    public $success;
    public ?IpFieldData $ip;
    public ?BasicFieldData $username;
    public ?BasicFieldData $email;

    public function __construct(array $data)
    {
        $this->success = (bool) Arr::get($data, 'success', false);
        $this->ip = isset($data['ip']) ? new IpFieldData($data['ip']) : null;
        $this->username = isset($data['username']) ? new BasicFieldData($data['username']) : null;
        $this->email = isset($data['email']) ? new BasicFieldData($data['email']) : (isset($data['emailhash']) ? new BasicFieldData($data['emailhash']) : null);
    }
}

class BasicFieldData
{
    public string $value;
    public bool $appears;
    public ?int $frequency = null;
    public ?string $lastseen = null;
    public ?float $confidence = null;
    public bool $blacklisted;

    public function __construct(array $fieldData)
    {
        $this->value = Arr::get($fieldData, 'value');
        $this->appears = Arr::get($fieldData, 'appears') !== null ? (bool) Arr::get($fieldData, 'appears') : false;
        $this->frequency = Arr::get($fieldData, 'frequency') !== null ? (int) Arr::get($fieldData, 'frequency') : null;
        $this->lastseen = Arr::get($fieldData, 'lastseen');
        $this->confidence = Arr::get($fieldData, 'confidence') !== null ? (float) Arr::get($fieldData, 'confidence') : null;
        $this->blacklisted = Arr::get($fieldData, 'blacklisted') !== null ? (bool) Arr::get($fieldData, 'blacklisted') : false;
    }
}

class IpFieldData extends BasicFieldData
{
    public ?int $asn = null;
    public ?string $country = null;

    public function __construct(array $fieldData)
    {
        parent::__construct($fieldData);
        $this->asn = Arr::get($fieldData, 'asn') !== null ? (int) Arr::get($fieldData, 'asn') : null;
        $this->country = Arr::get($fieldData, 'country');
    }
}

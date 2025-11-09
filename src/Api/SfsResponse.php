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
    public bool $success;
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
    public ?int $frequency;
    public ?string $lastseen;
    public ?float $confidence;
    public bool $blacklisted;

    public function __construct(array $fieldData)
    {
        $this->value = (string) Arr::get($fieldData, 'value', '');
        $this->appears = Arr::get($fieldData, 'appears') !== null ? (bool) Arr::get($fieldData, 'appears') : false;
        $this->frequency = Arr::get($fieldData, 'frequency') !== null ? (int) Arr::get($fieldData, 'frequency') : null;
        $this->lastseen = Arr::get($fieldData, 'lastseen');
        $this->confidence = Arr::get($fieldData, 'confidence') !== null ? (float) Arr::get($fieldData, 'confidence') : null;
        $this->blacklisted = Arr::get($fieldData, 'blacklisted') !== null ? (bool) Arr::get($fieldData, 'blacklisted') : false;
    }
}

class IpFieldData extends BasicFieldData
{
    public ?int $asn;
    public ?string $country;
    public ?bool $torexit;
    public ?string $delegated;

    public function __construct(array $fieldData)
    {
        parent::__construct($fieldData);
        $this->asn = Arr::get($fieldData, 'asn') !== null ? (int) Arr::get($fieldData, 'asn') : null;
        $this->country = Arr::get($fieldData, 'country');
        $this->torexit = Arr::get($fieldData, 'torexit') !== null ? (bool) Arr::get($fieldData, 'torexit') : null;
        $this->delegated = Arr::get($fieldData, 'delegated');
    }
}

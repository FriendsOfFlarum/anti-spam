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

    /**
     * @var IpFieldData|null
     */
    public $ip;

    /**
     * @var BasicFieldData|null
     */
    public $username;

    /**
     * @var BasicFieldData|null
     */
    public $email;

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
    /**
     * @var string
     */
    public $value;

    /**
     * @var bool
     */
    public $appears;

    /**
     * @var int|null
     */
    public $frequency = null;

    /**
     * @var string|null
     */
    public $lastseen = null;

    /**
     * @var float|null
     */
    public $confidence = null;

    /**
     * @var bool|null
     */
    public $blacklisted;

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
    /**
     * @var int|null
     */
    public $asn = null;

    /**
     * @var string|null
     */
    public $country = null;

    public function __construct(array $fieldData)
    {
        parent::__construct($fieldData);
        $this->asn = Arr::get($fieldData, 'asn') !== null ? (int) Arr::get($fieldData, 'asn') : null;
        $this->country = Arr::get($fieldData, 'country');
    }
}

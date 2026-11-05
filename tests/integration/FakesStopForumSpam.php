<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\integration;

use FoF\AntiSpam\Api\SfsClient;
use FoF\AntiSpam\Api\SfsResponse;

/**
 * Stands in for StopForumSpam: records what it is asked about, and lists one value per field.
 */
trait FakesStopForumSpam
{
    /**
     * @var SfsClient|null
     */
    protected $sfs;

    public static $listed = [
        'ip' => '10.0.0.66',
        'email' => 'listed@machine.local',
        'username' => '123456',
    ];

    protected function fakeSfs(): void
    {
        $this->sfs = new class($this->app()->getContainer()->make('flarum.settings'), self::$listed) extends SfsClient {
            public $checked = [];

            private $listed;

            public function __construct($settings, array $listed)
            {
                parent::__construct($settings);

                $this->listed = $listed;
            }

            public function check(?string $ip, ?string $email, ?string $username): SfsResponse
            {
                $this->checked[] = compact('ip', 'email', 'username');

                $field = function (string $name, ?string $value) {
                    $listed = $value !== null && $value === $this->listed[$name];

                    return [
                        'value' => $value,
                        'appears' => (int) $listed,
                        'frequency' => $listed ? 255 : 0,
                        'confidence' => $listed ? 99.0 : 0.0,
                    ];
                };

                return new SfsResponse([
                    'success' => 1,
                    'ip' => $field('ip', $ip),
                    'email' => $field('email', $email),
                    'username' => $field('username', $username),
                ]);
            }
        };

        $this->app()->getContainer()->instance(SfsClient::class, $this->sfs);
    }

    /**
     * The addresses StopForumSpam was asked about, in order.
     */
    protected function checkedIps(): array
    {
        return array_column($this->sfs->checked, 'ip');
    }
}

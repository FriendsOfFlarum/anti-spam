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

use Laminas\Diactoros\ServerRequest;

trait MakesClientRequests
{
    /**
     * A request as PHP delivers one: from a connection address, with every header also in the
     * server params as HTTP_*. Code reading either place sees what a real client sent.
     */
    protected function clientRequest(string $method, string $path, string $remoteAddr, array $headers = []): ServerRequest
    {
        $server = ['REMOTE_ADDR' => $remoteAddr];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return new ServerRequest($server, [], $path, $method, 'php://input', $headers);
    }
}

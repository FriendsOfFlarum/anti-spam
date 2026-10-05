<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Api\Controllers;

use Flarum\Api\Client;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use FoF\AntiSpam\Command\MarkUserAsSpammer;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MarkAsSpammerController implements RequestHandlerInterface
{
    public function __construct(protected Dispatcher $bus, protected Client $api)
    {
    }

    /**
     * Handle the request and return a response.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        $userId = Arr::get($request->getQueryParams(), 'id');
        $user = User::findOrFail($userId);

        $actor->assertCan('spamblock', $user);

        $options = Arr::get($request->getParsedBody(), 'options', []);

        $user = $this->bus->dispatch(new MarkUserAsSpammer($user, $options, $actor));

        if (! $user->exists) {
            return new EmptyResponse();
        }

        return $this->api->withParentRequest($request)->get('/users/'.$user->id);
    }
}

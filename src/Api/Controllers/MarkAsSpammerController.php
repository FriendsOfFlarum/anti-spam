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

use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
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
    /**
     * @var Dispatcher
     */
    protected $bus;

    /**
     * @var UrlGenerator
     */
    protected $url;

    /**
     * @param Dispatcher       $bus
     */
    public function __construct(Dispatcher $bus, UrlGenerator $url)
    {
        $this->bus = $bus;
        $this->url = $url;
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

        $this->bus->dispatch(new MarkUserAsSpammer($user, $options, $actor));

        return new EmptyResponse();
    }
}

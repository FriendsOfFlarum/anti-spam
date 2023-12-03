<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Middleware;

use Flarum\Foundation\ErrorHandling\JsonApiFormatter;
use Flarum\Foundation\ErrorHandling\Registry;
use Flarum\Foundation\ValidationException;
use Flarum\Http\UrlGenerator;
use FoF\AntiSpam\StopForumSpam;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CheckLoginMiddleware implements MiddlewareInterface
{
    /**
     * @var StopForumSpam
     */
    private $sfs;

    /**
     * @var UrlGenerator
     */
    private $url;

    public function __construct(StopForumSpam $sfs, UrlGenerator $url)
    {
        $this->sfs = $sfs;
        $this->url = $url;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $registerPath = Str::replaceFirst($this->url->to('forum')->base(), '', $this->url->to('forum')->path('register'));

        if ($request->getUri()->getPath() === $registerPath) {
            $data = $request->getParsedBody();

            //try {
                $shouldPrevent = $this->sfs->shouldPreventLogin(
                    $this->getIpAddress($request),
                    $data['email'],
                    $data['username'],
                    'forum',
                    $data
                );
            // } catch (\Throwable $e) {
            //     return (new JsonApiFormatter())->format(
            //         resolve(Registry::class)->handle($e),
            //         $request
            //     );
            // }

            if ($shouldPrevent) {
                return (new JsonApiFormatter())
                    ->format(
                        resolve(Registry::class)
                            ->handle(new ValidationException([
                                'username' => resolve('translator')->trans('fof-stopforumspam.forum.message.spam'),
                            ])),
                        $request
                    );
            }
        }

        return $handler->handle($request);
    }

    protected function getIpAddress(ServerRequestInterface $request): ?string
    {
        $serverParams = $request->getServerParams();

        return Arr::get($serverParams, 'HTTP_CLIENT_IP')
            ?? Arr::get($serverParams, 'HTTP_CF_CONNECTING_IP')
            ?? Arr::get($serverParams, 'HTTP_X_FORWARDED_FOR')
            ?? Arr::get($serverParams, 'REMOTE_ADDR');
    }
}

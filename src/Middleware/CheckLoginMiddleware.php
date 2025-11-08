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
use Flarum\User\RegistrationToken;
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
     * @var string
     */
    private $provider = 'flarum';

    /**
     * @var array
     */
    private $providerData = [];

    public function __construct(private StopForumSpam $sfs, private UrlGenerator $url)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $registerPath = Str::replaceFirst($this->url->to('forum')->base(), '', $this->url->to('forum')->path('register'));

        if ($request->getUri()->getPath() === $registerPath) {
            $data = $request->getParsedBody();

            if (! $this->isOAuthRegistration($data)) {
                $this->providerData = $data;
            }

            $shouldPrevent = $this->sfs->shouldPreventLogin(
                $this->getIpAddress($request),
                $data['email'],
                $data['username'],
                $this->provider,
                $this->providerData
            );

            if ($shouldPrevent) {
                return (new JsonApiFormatter())
                    ->format(
                        resolve(Registry::class)
                            ->handle(new ValidationException([
                                'username' => resolve('translator')->trans('fof-anti-spam.forum.message.stopforumspam.blocked'),
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

    protected function isOAuthRegistration(array $data): bool
    {
        if (Arr::has($data, 'token') && $registration = RegistrationToken::find(Arr::get($data, 'token'))) {
            $this->provider = $registration->provider;
            $this->providerData = $registration->payload;

            return true;
        }

        return false;
    }
}

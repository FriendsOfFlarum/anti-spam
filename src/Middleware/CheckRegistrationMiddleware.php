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

class CheckRegistrationMiddleware implements MiddlewareInterface
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

            // Ensure data is an array and has required fields
            if (! is_array($data)) {
                return $handler->handle($request);
            }

            $email = Arr::get($data, 'email');
            $username = Arr::get($data, 'username');

            // Skip spam check if essential data is missing (let normal validation handle it)
            if (empty($email) && empty($username)) {
                return $handler->handle($request);
            }

            if (! $this->isOAuthRegistration($data)) {
                $this->providerData = $data;
            }

            $shouldPrevent = $this->sfs->shouldPreventRegistration(
                $this->getIpAddress($request),
                $email,
                $username,
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

        // Priority order: CF (trusted CDN) > X-Forwarded-For > Client-IP > Remote
        $ip = Arr::get($serverParams, 'HTTP_CF_CONNECTING_IP')
            ?? Arr::get($serverParams, 'HTTP_CLIENT_IP')
            ?? Arr::get($serverParams, 'HTTP_X_FORWARDED_FOR')
            ?? Arr::get($serverParams, 'REMOTE_ADDR');

        if ($ip === null) {
            return null;
        }

        // X-Forwarded-For can contain multiple IPs: "client, proxy1, proxy2"
        // We want the first (original client) IP only
        if (str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }

        // Validate IP address format
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $ip;
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

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
use Flarum\Http\RequestUtil;
use Flarum\User\RegistrationToken;
use FoF\AntiSpam\StopForumSpam;
use Illuminate\Support\Arr;
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

    /**
     * @var array
     */
    private $userAttributes = [];

    /**
     * @var StopForumSpam
     */
    private $sfs;

    public function __construct(StopForumSpam $sfs)
    {
        $this->sfs = $sfs;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // The endpoint that creates users, on the API stack: the forum's /register route only
        // proxies to it, so this covers both it and a direct `POST /api/users`, each once.
        if ($request->getAttribute('routeName') === 'users.create' && ! RequestUtil::getActor($request)->isAdmin()) {
            // Read exactly as CreateUserController and RegisterUserHandler do. Arr::get prefers a
            // literal dotted key, so any other lookup can be shown values other than those core
            // registers.
            $data = Arr::get($request->getParsedBody(), 'data', []);

            if (! is_array($data)) {
                return $handler->handle($request);
            }

            $attributes = is_array($data['attributes'] ?? null) ? $data['attributes'] : [];

            // The instance outlives a request inside core's API client, so nothing carries over.
            $this->provider = 'flarum';
            $this->userAttributes = [];

            if (! $this->isOAuthRegistration($attributes)) {
                $this->providerData = $attributes;
            }

            $shouldPrevent = $this->sfs->shouldPreventLogin(
                $this->getIpAddress($request),
                // Core applies a token's attributes over the body's, so those are what the
                // account will carry.
                $this->scalar(Arr::get($this->userAttributes, 'email', Arr::get($data, 'attributes.email'))),
                $this->scalar(Arr::get($this->userAttributes, 'username', Arr::get($data, 'attributes.username'))),
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
        // The address core resolved from the connection. Forwarding headers are sent by the client,
        // so trusting them let a spammer choose the address that was checked.
        return $request->getAttribute('ipAddress');
    }

    /**
     * A JSON number is a valid username, so it is checked as the string core will store.
     */
    private function scalar($value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    protected function isOAuthRegistration(array $data): bool
    {
        if (Arr::has($data, 'token') && $registration = RegistrationToken::find(Arr::get($data, 'token'))) {
            $this->provider = $registration->provider;
            $this->providerData = $registration->payload;
            $this->userAttributes = $registration->user_attributes;

            return true;
        }

        return false;
    }
}

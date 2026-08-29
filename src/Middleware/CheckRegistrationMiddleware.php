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
use Flarum\User\RegistrationToken;
use FoF\AntiSpam\Http\ClientIp;
use FoF\AntiSpam\StopForumSpam;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CheckRegistrationMiddleware implements MiddlewareInterface
{
    /**
     * The name of the JSON:API endpoint that creates a user.
     *
     * Registration is checked here rather than at the forum's `/register` path because that route
     * is only a wrapper: RegisterController proxies to this endpoint and additionally logs the new
     * user in. Matching the path left `POST /api/users` — open to guests whenever sign-up is
     * enabled — completely unchecked. Both routes reach this endpoint, so both are covered, and
     * neither is checked twice.
     */
    private const REGISTER_ROUTE = 'users.create';

    /**
     * @var string
     */
    private $provider = 'flarum';

    /**
     * @var array
     */
    private $providerData = [];

    public function __construct(private StopForumSpam $sfs)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute('routeName') === self::REGISTER_ROUTE && ! $this->actorIsAdmin($request)) {
            // The body is JSON:API shaped on both routes: RegisterController wraps the form fields
            // into `data.attributes` before handing them on.
            $attributes = Arr::get($request->getParsedBody() ?? [], 'data.attributes');

            if (! is_array($attributes)) {
                return $handler->handle($request);
            }

            $email = Arr::get($attributes, 'email');
            $username = Arr::get($attributes, 'username');

            // Skip spam check if essential data is missing (let normal validation handle it)
            if (empty($email) && empty($username)) {
                return $handler->handle($request);
            }

            if (! $this->isOAuthRegistration($attributes)) {
                $this->providerData = $attributes;
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

    /**
     * An admin deliberately creating an account is not a registration attempt, and must not be
     * turned away because StopForumSpam recognises the address.
     */
    protected function actorIsAdmin(ServerRequestInterface $request): bool
    {
        $actor = $request->getAttribute('actorReference')?->getActor();

        return $actor !== null && $actor->isAdmin();
    }

    protected function getIpAddress(ServerRequestInterface $request): ?string
    {
        return ClientIp::fromRequest($request);
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

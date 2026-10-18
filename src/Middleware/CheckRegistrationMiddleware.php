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
use FoF\AntiSpam\BlockReasons;
use FoF\AntiSpam\Honeypot;
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
    protected const string REGISTER_ROUTE = 'users.create';

    public function __construct(protected StopForumSpam $sfs, protected Honeypot $honeypot)
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

            // Anything but a string is junk for this check, and the lookup is typed to say so.
            $email = self::text(Arr::get($attributes, 'email'));
            $username = self::text(Arr::get($attributes, 'username'));
            $provider = 'flarum';
            $providerData = $attributes;

            if ($token = $this->registrationToken($attributes)) {
                $provider = $token->provider;
                $providerData = $token->payload;

                // Core applies the token's attributes over the body's after validation, so these
                // are what the account will really carry. Checking the body alone let a request
                // send clean values, or none at all, beside a token holding the spammer's.
                $email = self::text(Arr::get($token->user_attributes, 'email', $email));
                $username = self::text(Arr::get($token->user_attributes, 'username', $username));
            }

            // First, and before anything leaves the server: a bot that springs the trap needs no
            // StopForumSpam lookup to be turned away.
            if ($this->honeypot->enabled() && ($reason = $this->honeypot->verdict($attributes)) !== null) {
                $this->sfs->recordBlock(
                    ['ip' => $this->getIpAddress($request), 'email' => $email, 'username' => $username],
                    '{}',
                    $provider,
                    // What a bot typed into the trap says nothing useful, and may be long.
                    Arr::except($providerData, [Honeypot::FIELD, Honeypot::TOKEN]),
                    new BlockReasons([$reason])
                );

                // A form the server cannot vouch for is most often a page left open, or loaded
                // before strict mode was turned on: worth a reload, not a refusal. The trap itself
                // answers neutrally, since a person whose password manager filled it was never
                // found in any spam database.
                return in_array($reason, [BlockReasons::NO_FORM, BlockReasons::FORM_TOKEN], true)
                    ? $this->refuse($request, 'fof-anti-spam.forum.message.honeypot.reload')
                    : $this->refuse($request, 'fof-anti-spam.forum.message.honeypot.refused');
            }

            // Skip spam check if essential data is missing (let normal validation handle it)
            if (empty($email) && empty($username)) {
                return $handler->handle($request);
            }

            $shouldPrevent = $this->sfs->shouldPreventRegistration(
                $this->getIpAddress($request),
                $email,
                $username,
                $provider,
                $providerData
            );

            if ($shouldPrevent) {
                return $this->refuse($request);
            }
        }

        return $handler->handle($request);
    }

    /**
     * The same answer whichever check refused it, so a bot learns nothing about which one it hit.
     */
    protected function refuse(ServerRequestInterface $request, string $message = 'fof-anti-spam.forum.message.stopforumspam.blocked'): ResponseInterface
    {
        return (new JsonApiFormatter())
            ->format(
                resolve(Registry::class)
                    ->handle(new ValidationException([
                        'username' => resolve('translator')->trans($message),
                    ])),
                $request
            );
    }

    /**
     * A non-empty string, truncated to what the record can hold, or null.
     */
    protected static function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? mb_substr($value, 0, 255) : null;
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

    protected function registrationToken(array $attributes): ?RegistrationToken
    {
        $token = Arr::get($attributes, 'token');

        return is_string($token) && $token !== '' ? RegistrationToken::find($token) : null;
    }
}

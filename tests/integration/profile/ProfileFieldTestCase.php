<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\integration\profile;

use Carbon\Carbon;
use Flarum\Extend;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use Psr\Http\Message\ResponseInterface;

/**
 * Shared setup for the profile fields a spammer can write into.
 *
 * Bio was only ever cleared when a moderator spamblocked someone; nothing looked at it while it
 * was live. Nicknames were never looked at either, and a display name is the one piece of a
 * spammer's profile that follows them onto every post they make.
 */
abstract class ProfileFieldTestCase extends TestCase
{
    /**
     * The extension that owns the field under test, if any.
     */
    abstract protected function owningExtension(): ?string;

    /**
     * Extra group permissions the field under test needs before anyone can write it.
     *
     * @return array<int, array{group_id: int, permission: string}>
     */
    protected function permissions(): array
    {
        return [];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $extensions = ['flarum-flags', 'flarum-approval', 'fof-anti-spam'];

        if ($owner = $this->owningExtension()) {
            $extensions[] = $owner;
        }

        $this->extension(...$extensions);

        $this->extend((new Extend\Csrf())->exemptRoute('register')->exemptRoute('users.create'));

        $this->prepareDatabase([
            User::class => [
                ['id' => 3, 'username' => 'new_user', 'email' => 'new@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()],
                ['id' => 4, 'username' => 'old_hand', 'email' => 'old@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()->subYear(), 'comment_count' => 500],
            ],
            'group_permission' => $this->permissions(),
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function patchUser(int $id, array $attributes, int $actor): ResponseInterface
    {
        return $this->send(
            $this->request('PATCH', '/api/users/'.$id, [
                'authenticatedAs' => $actor,
                'json' => [
                    'data' => [
                        'type' => 'users',
                        'id' => (string) $id,
                        'attributes' => $attributes,
                    ],
                ],
            ])
        );
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function registerWith(array $attributes): ResponseInterface
    {
        return $this->send(
            $this->request('POST', '/api/users', [
                'json' => [
                    'data' => [
                        'type' => 'users',
                        'attributes' => $attributes + [
                            'username' => 'fresh_signup',
                            'password' => 'too-obscure',
                            'email' => 'fresh@example.com',
                        ],
                    ],
                ],
            ])
        );
    }
}

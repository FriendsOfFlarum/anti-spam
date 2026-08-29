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

use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class UsernameSpamTest extends ProfileFieldTestCase
{
    protected function owningExtension(): ?string
    {
        return null;
    }

    #[Test]
    public function a_spam_username_is_rejected_at_registration()
    {
        $this->setting('fof-anti-spam.content-filter.blocked_words', "viagra\ncialis");

        $response = $this->send(
            $this->request('POST', '/api/users', [
                'json' => [
                    'data' => [
                        'type' => 'users',
                        'attributes' => [
                            'username' => 'viagra-deals',
                            'password' => 'too-obscure',
                            'email' => 'fresh@example.com',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertNull(User::where('username', 'viagra-deals')->first());
    }

    #[Test]
    public function a_blocked_word_buried_inside_a_username_is_not_caught()
    {
        // Documented limit, not an aspiration: blocked words are matched on word boundaries and
        // `_` is a word character, so `\bviagra\b` never fires inside `cheap_viagra_deals`.
        $this->setting('fof-anti-spam.content-filter.blocked_words', "viagra\ncialis");

        $response = $this->send(
            $this->request('POST', '/api/users', [
                'json' => [
                    'data' => [
                        'type' => 'users',
                        'attributes' => [
                            'username' => 'cheap_viagra_deals',
                            'password' => 'too-obscure',
                            'email' => 'buried@example.com',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function an_ordinary_username_is_accepted()
    {
        $this->setting('fof-anti-spam.content-filter.blocked_words', "viagra\ncialis");

        $response = $this->send(
            $this->request('POST', '/api/users', [
                'json' => [
                    'data' => [
                        'type' => 'users',
                        'attributes' => [
                            'username' => 'perfectly_normal',
                            'password' => 'too-obscure',
                            'email' => 'fresh@example.com',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function the_profile_check_is_inert_when_the_owning_extensions_are_absent()
    {
        // Neither flarum-nicknames nor fof-user-bio is enabled here. Registration and ordinary
        // profile edits must behave exactly as they did before.
        $response = $this->patchUser(3, ['username' => 'renamed_user'], 1);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertEquals('renamed_user', User::find(3)->username);
    }
}

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
    public function a_blocked_word_joined_to_others_by_underscores_is_caught()
    {
        // Usernames cannot contain spaces, so `_` is how words are separated in them. As a word
        // character it would otherwise keep `\bviagra\b` from ever firing inside one.
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

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame('/data/attributes/username', json_decode((string) $response->getBody(), true)['errors'][0]['source']['pointer']);
    }

    #[Test]
    public function a_blocked_word_inside_a_longer_word_is_still_left_alone()
    {
        // Treating `_` as a separator must not turn the match into a substring search, which
        // would refuse `classic_cars` over a word list containing "ass".
        $this->setting('fof-anti-spam.content-filter.blocked_words', "ass");

        $response = $this->send(
            $this->request('POST', '/api/users', [
                'json' => [
                    'data' => [
                        'type' => 'users',
                        'attributes' => [
                            'username' => 'classic_cars',
                            'password' => 'too-obscure',
                            'email' => 'cars@example.com',
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

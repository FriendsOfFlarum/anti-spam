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

class NicknameSpamTest extends ProfileFieldTestCase
{
    protected function owningExtension(): ?string
    {
        return 'flarum-nicknames';
    }

    #[Test]
    public function a_spam_nickname_is_rejected()
    {
        $response = $this->patchUser(3, ['nickname' => 'Cheap pills at suspicious-site.com'], 3);

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());
        $this->assertNull(User::find(3)->nickname, 'A rejected nickname must not be stored');
    }

    #[Test]
    public function the_rejection_points_at_the_nickname_field()
    {
        $response = $this->patchUser(3, ['nickname' => 'Call me on +1234567890'], 3);

        $body = json_decode((string) $response->getBody(), true);

        $this->assertEquals('validation_error', $body['errors'][0]['code']);
        $this->assertEquals('/data/attributes/nickname', $body['errors'][0]['source']['pointer']);
    }

    #[Test]
    public function an_ordinary_nickname_is_accepted()
    {
        $response = $this->patchUser(3, ['nickname' => 'Ian from Accounts'], 3);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertEquals('Ian from Accounts', User::find(3)->nickname);
    }

    #[Test]
    public function a_spam_nickname_is_rejected_at_registration()
    {
        $response = $this->registerWith(['nickname' => 'Visit suspicious-site.com now']);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertNull(User::where('username', 'fresh_signup')->first(), 'The account must not be created');
    }

    #[Test]
    public function an_established_user_is_not_checked()
    {
        // Same monitoring window the content filter uses: past it, the detectors stand down.
        $response = $this->patchUser(4, ['nickname' => 'Cheap pills at suspicious-site.com'], 4);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function staff_are_not_checked()
    {
        $response = $this->patchUser(3, ['nickname' => 'Cheap pills at suspicious-site.com'], 1);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function an_untouched_nickname_does_not_block_other_edits()
    {
        $this->app();

        User::find(3)->forceFill(['nickname' => 'Grandfathered suspicious-site.com'])->save();

        // Only a field the request actually changes should be analysed, otherwise a user carrying
        // an old bad value could never save their profile again.
        $response = $this->patchUser(3, ['nickname' => 'Grandfathered suspicious-site.com'], 3);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
    }
}

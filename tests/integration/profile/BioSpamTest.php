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

use Flarum\Group\Group;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

class BioSpamTest extends ProfileFieldTestCase
{
    protected function owningExtension(): ?string
    {
        return 'fof-user-bio';
    }

    protected function permissions(): array
    {
        // Members can write their own bio; without this nothing is writable and the tests would
        // pass on a 403 that has nothing to do with spam.
        return [
            ['group_id' => Group::MEMBER_ID, 'permission' => 'fof-user-bio.editOwn'],
            ['group_id' => Group::MEMBER_ID, 'permission' => 'fof-user-bio.view'],
        ];
    }

    #[Test]
    public function a_spam_bio_is_rejected()
    {
        $response = $this->patchUser(3, ['bio' => 'Best deals at https://suspicious-site.com, mail me'], 3);

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());
        $this->assertNull(User::find(3)->bio, 'A rejected bio must not be stored');
    }

    #[Test]
    public function the_rejection_points_at_the_bio_field()
    {
        $response = $this->patchUser(3, ['bio' => 'Reach me on spammer@suspicious-site.com'], 3);

        $body = json_decode((string) $response->getBody(), true);

        $this->assertEquals('validation_error', $body['errors'][0]['code']);
        $this->assertEquals('/data/attributes/bio', $body['errors'][0]['source']['pointer']);
    }

    #[Test]
    public function an_ordinary_bio_is_accepted()
    {
        $response = $this->patchUser(3, ['bio' => 'Forum regular, mostly here for the tags discussion.'], 3);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertEquals('Forum regular, mostly here for the tags discussion.', User::find(3)->bio);
    }

    #[Test]
    public function an_established_user_is_not_checked()
    {
        $response = $this->patchUser(4, ['bio' => 'Best deals at https://suspicious-site.com'], 4);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function staff_are_not_checked()
    {
        $response = $this->patchUser(3, ['bio' => 'Best deals at https://suspicious-site.com'], 1);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function clearing_a_bio_is_always_allowed()
    {
        $response = $this->patchUser(3, ['bio' => ''], 3);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
    }
}

<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\integration;

/**
 * ianm/twofactor replaces the login and token routes, but not registration: every registration
 * check must hold with it enabled.
 *
 * It is not a dev dependency because no 1.x release installs on PHP 7.3 or 7.4, which CI still
 * tests. Install it to run these (`composer require --dev ianm/twofactor:^1.2`, without committing).
 */
class TwoFactorRegistrationCheckTest extends RegistrationCheckTest
{
    public function setUp(): void
    {
        if (! class_exists(\IanM\TwoFactor\Model\TwoFactor::class)) {
            $this->markTestSkipped('ianm/twofactor is not installed.');
        }

        parent::setUp();
    }

    protected function extensions(): array
    {
        return ['ianm-twofactor', 'fof-anti-spam'];
    }
}

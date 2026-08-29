<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\integration\content;

class EvasionWithMarkdownTest extends EvasionTestCase
{
    protected function markdownEnabled(): bool
    {
        return true;
    }
}

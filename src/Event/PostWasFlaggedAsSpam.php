<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Event;

use Flarum\Post\Post;

/**
 * The content filter flagged a post as spam.
 *
 * Dispatched so the flagging leaves a durable record. The flag row itself is not one:
 * flarum/flags hard-deletes flags when they are dismissed, and again when the post is deleted —
 * which is what marking the author as a spammer does — so counting that table measures the
 * open queue rather than the work the filter has done.
 */
class PostWasFlaggedAsSpam
{
    public function __construct(
        public Post $post,
        public int $score,
        public string $reasonDetail
    ) {
    }
}

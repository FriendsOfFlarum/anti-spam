<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\ContentFilter;

use Flarum\User\User;

/**
 * Who the content filter treats as new, and who it leaves alone.
 */
class MonitoredUsers
{
    public function __construct(
        protected ConfigurationManager $config
    ) {
    }

    /**
     * Administrators, and whoever can hide discussions — moderators on a default forum, though
     * the permission can be granted more widely than that. That is a proxy for trust rather than
     * a designation of it.
     */
    public function isExempt(User $user): bool
    {
        return $user->isAdmin() || $user->can('discussion.hide');
    }

    /**
     * Whether a user's content is checked: new accounts only — up to a post count or an age —
     * unless the admin has turned on monitoring for everyone. The exemption applies either way.
     */
    public function shouldMonitor(User $user): bool
    {
        if ($this->isExempt($user)) {
            return false;
        }

        // Always monitor guests (shouldn't post, but just in case)
        if ($user->isGuest()) {
            return true;
        }

        // The window is one-way: an account that clears it is never examined again. That is the
        // wrong answer for a member whose credentials are stolen, or one that waits the
        // thresholds out before starting, so an admin can opt into checking everyone.
        if ($this->config->get('monitor_all_users')) {
            return true;
        }

        return $this->isNew($user);
    }

    /**
     * Whether an account is still inside the window: up to the post count, or up to the age.
     * Says nothing about the exemption or about monitoring everyone.
     */
    public function isNew(User $user): bool
    {
        if ($user->comment_count <= $this->config->get('monitor_post_count')) {
            return true;
        }

        return $user->joined_at && $user->joined_at->diffInHours() <= $this->config->get('monitor_hours_old');
    }
}

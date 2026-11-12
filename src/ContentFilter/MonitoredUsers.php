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

use Carbon\Carbon;
use Flarum\User\User;

/**
 * Who the content filter treats as new, and who it leaves alone.
 */
class MonitoredUsers
{
    /**
     * Each user's first public post time, once looked up. Static because every detector builds
     * its own instance, and a post is judged by several of them; weak so it goes with the user.
     *
     * Wrapped in an array: WeakMap's offsetExists() is isset(), so a bare null would never count
     * as looked up. Fine for a request's actor; a User object kept across several posts (an
     * import, a long-running job) keeps the answer it got first.
     *
     * @var \WeakMap<User, array{0: Carbon|null}>|null
     */
    private static ?\WeakMap $firstPostedAt = null;

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
        // wrong answer for a member whose credentials are stolen, or one that posts harmlessly
        // until it is through before starting, so an admin can opt into checking everyone.
        if ($this->config->get('monitor_all_users')) {
            return true;
        }

        return $this->isNew($user);
    }

    /**
     * Whether an account is still inside the window: up to the post count, or up to the age.
     * Says nothing about the exemption or about monitoring everyone.
     *
     * The age runs from when the account's first public post was written, not from registration: an account that
     * sits unused would otherwise have used the age up before it ever posts, leaving only the
     * post count, which a few quick, harmless posts clear.
     */
    public function isNew(User $user): bool
    {
        if ($user->comment_count <= $this->config->get('monitor_post_count')) {
            return true;
        }

        $hours = $this->config->get('monitor_hours_old');

        // No post comes before registration, so an account still inside the age from joining is
        // inside it from its first post too, and the query can be skipped.
        if ($user->joined_at && $user->joined_at->diffInHours() <= $hours) {
            return true;
        }

        // Every past post is more than 0 hours old, so there is nothing to look up.
        if ($hours <= 0) {
            return false;
        }

        $since = $this->firstPostedAt($user);

        return $since && $since->diffInHours() <= $hours;
    }

    /**
     * When the user's earliest post counted by comment_count was made, so the same posts decide
     * both halves of the window.
     */
    protected function firstPostedAt(User $user): ?Carbon
    {
        self::$firstPostedAt ??= new \WeakMap();

        if (! self::$firstPostedAt->offsetExists($user)) {
            $first = $user->posts()
                ->where('type', 'comment')
                ->where('is_private', false)
                ->oldest()
                ->value('created_at');

            self::$firstPostedAt[$user] = [$first ? Carbon::parse($first) : null];
        }

        return self::$firstPostedAt[$user][0];
    }
}

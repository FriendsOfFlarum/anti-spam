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

use Carbon\Carbon;
use Flarum\Flags\Flag;
use Flarum\Group\Group;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * Monitoring every post, not just those from new accounts.
 *
 * The default window — a user's first few posts, or their first day — is one-way: once an
 * account is past it, nothing it posts is ever examined again. That misses the cases that
 * matter most, because they all involve accounts that have already earned the exemption: a
 * long-standing member whose credentials are stolen, an account that waits out the thresholds
 * before starting, or a member who simply turns.
 */
class MonitorAllUsersTest extends TestCase
{
    protected function setup(): void
    {
        parent::setup();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $this->prepareDatabase([
            User::class => [
                // Well past both thresholds: plenty of posts, joined a year ago.
                [
                    'id' => 3, 'username' => 'established', 'email' => 'established@machine.local',
                    'is_email_confirmed' => 1, 'joined_at' => Carbon::now()->subYear(), 'comment_count' => 500,
                ],
                // Inside the default window.
                [
                    'id' => 4, 'username' => 'newcomer', 'email' => 'newcomer@machine.local',
                    'is_email_confirmed' => 1, 'joined_at' => Carbon::now(), 'comment_count' => 0,
                ],
                // Staff, equally established.
                [
                    'id' => 5, 'username' => 'a_moderator', 'email' => 'a_mod@machine.local',
                    'is_email_confirmed' => 1, 'joined_at' => Carbon::now()->subYear(), 'comment_count' => 500,
                ],
            ],
            'group_user' => [
                ['user_id' => 5, 'group_id' => Group::MODERATOR_ID],
            ],
            'group_permission' => [
                ['group_id' => Group::MODERATOR_ID, 'permission' => 'discussion.hide'],
            ],
        ]);
    }

    private function lowerThresholds(): void
    {
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 20);
    }

    private function postSpamAs(int $userId): int
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => $userId,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Cheap deals',
                            'content' => 'Call me on 07700 900123 or visit https://suspicious-site.example for cheap deals',
                        ],
                    ],
                ],
            ])
        );

        return $response->getStatusCode();
    }

    private function spamFlags(): int
    {
        return Flag::where('type', 'spam')->count();
    }

    #[Test]
    public function by_default_an_established_user_is_not_monitored()
    {
        $this->lowerThresholds();
        $this->app();

        $this->postSpamAs(3);

        // The existing behaviour, stated so a change to it cannot go unnoticed.
        $this->assertSame(0, $this->spamFlags());
    }

    #[Test]
    public function by_default_a_new_user_is_monitored()
    {
        $this->lowerThresholds();
        $this->app();

        $this->postSpamAs(4);

        $this->assertGreaterThan(0, $this->spamFlags());
    }

    #[Test]
    public function with_monitor_all_enabled_an_established_user_is_monitored()
    {
        $this->lowerThresholds();
        $this->setting('fof-anti-spam.content-filter.monitor_all_users', true);
        $this->app();

        $this->postSpamAs(3);

        // The compromised-account case: an account well past both thresholds, posting spam.
        $this->assertGreaterThan(0, $this->spamFlags(), 'An established user should be monitored when the option is on');
    }

    #[Test]
    public function with_monitor_all_enabled_staff_are_still_exempt()
    {
        $this->lowerThresholds();
        $this->setting('fof-anti-spam.content-filter.monitor_all_users', true);
        $this->app();

        $this->postSpamAs(5);

        // Staff exemption is absolute: a moderator quoting a spam post to discuss it must not
        // have their own post flagged.
        $this->assertSame(0, $this->spamFlags(), 'Staff must stay exempt however the option is set');
    }

    #[Test]
    public function with_monitor_all_enabled_a_new_user_is_still_monitored()
    {
        $this->lowerThresholds();
        $this->setting('fof-anti-spam.content-filter.monitor_all_users', true);
        $this->app();

        $this->postSpamAs(4);

        $this->assertGreaterThan(0, $this->spamFlags());
    }

    #[Test]
    public function an_ordinary_post_from_an_established_user_is_not_flagged()
    {
        $this->lowerThresholds();
        $this->setting('fof-anti-spam.content-filter.monitor_all_users', true);
        $this->app();

        $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'A normal discussion',
                            'content' => 'Has anyone tried the new release? Curious what people think of it.',
                        ],
                    ],
                ],
            ])
        );

        // Monitoring everyone is only tolerable if ordinary posts stay untouched.
        $this->assertSame(0, $this->spamFlags());
    }
}

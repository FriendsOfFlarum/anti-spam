<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\AntiSpam\Model\BlockedRegistration;
use PHPUnit\Framework\Attributes\Test;

/**
 * The statistics endpoint behind the dashboard widget.
 *
 * Served by this extension rather than through flarum/statistics, whose entity list is a
 * hardcoded private array that rejects any model it does not already know.
 */
class BlockedRegistrationStatsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'moderator', 'email' => 'moderator@machine.local', 'is_email_confirmed' => true],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => Group::MODERATOR_ID],
            ],
            'group_permission' => [
                ['permission' => 'fof-anti-spam.viewBlockedRegistrations', 'group_id' => Group::MODERATOR_ID],
            ],
            BlockedRegistration::class => [
                [
                    'id' => 1, 'ip' => '10.0.0.1', 'email' => 'a@spam.example', 'username' => 'a',
                    'provider' => 'flarum', 'attempted_at' => Carbon::now()->subDays(3),
                    'reasons' => '{"reasons":["blacklisted"],"context":{}}',
                ],
                [
                    'id' => 2, 'ip' => '10.0.0.2', 'email' => 'b@spam.example', 'username' => 'b',
                    'provider' => 'flarum', 'attempted_at' => Carbon::now()->subDays(2),
                    'reasons' => '{"reasons":["blacklisted","frequency"],"context":{}}',
                ],
                [
                    'id' => 3, 'ip' => '10.0.0.3', 'email' => 'c@spam.example', 'username' => 'c',
                    'provider' => 'github', 'attempted_at' => Carbon::now()->subDay(),
                    'reasons' => null,
                ],
            ],
        ]);
    }

    private function stats(array $params = [], int $as = 1): array
    {
        $response = $this->send(
            $this->request('GET', '/api/fof/anti-spam/statistics', ['authenticatedAs' => $as])
                ->withQueryParams($params)
        );

        $this->assertEquals(200, $response->getStatusCode());

        return json_decode($response->getBody()->getContents(), true);
    }

    #[Test]
    public function a_guest_cannot_read_statistics()
    {
        $response = $this->send($this->request('GET', '/api/fof/anti-spam/statistics'));

        // assertAdmin() denies rather than challenges, so a guest gets 403 like any other
        // non-admin. Either way the data is not served.
        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function a_non_admin_cannot_read_statistics()
    {
        // Being able to view the blocked list is not the same as reading forum-wide statistics,
        // which live on the admin dashboard.
        $response = $this->send(
            $this->request('GET', '/api/fof/anti-spam/statistics', ['authenticatedAs' => 3])
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    #[Test]
    public function lifetime_statistics_report_the_total()
    {
        $this->assertSame(3, $this->stats(['period' => 'lifetime'])['total']);
    }

    #[Test]
    public function lifetime_statistics_count_each_rule_that_fired()
    {
        $byReason = $this->stats(['period' => 'lifetime'])['byReason'];

        // Row 2 tripped two rules, so it counts once against each.
        $this->assertSame(2, $byReason['blacklisted']);
        $this->assertSame(1, $byReason['frequency']);
    }

    #[Test]
    public function rows_without_recorded_reasons_are_counted_separately()
    {
        $byReason = $this->stats(['period' => 'lifetime'])['byReason'];

        // Row 3 predates the reasons column. Attributing it to a rule would be a guess, so it
        // is reported as what it is: unexplained.
        $this->assertSame(1, $byReason['unrecorded']);
    }

    #[Test]
    public function lifetime_statistics_break_down_by_provider()
    {
        $byProvider = $this->stats(['period' => 'lifetime'])['byProvider'];

        $this->assertSame(2, $byProvider['flarum']);
        $this->assertSame(1, $byProvider['github']);
    }

    #[Test]
    public function timed_statistics_are_keyed_by_timestamp()
    {
        $timed = $this->stats();

        $this->assertNotEmpty($timed);
        $this->assertSame(3, array_sum($timed));

        foreach (array_keys($timed) as $key) {
            $this->assertTrue(ctype_digit((string) $key), 'Buckets should be keyed by unix timestamp');
        }
    }
}

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

use Flarum\Group\Group;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\AntiSpam\Model\BlockedRegistration;
use PHPUnit\Framework\Attributes\Test;

/**
 * Filtering and sorting on the blocked registrations endpoint.
 *
 * Filters go through a searcher rather than JSON:API filters, because Flarum makes
 * AbstractDatabaseResource::filters() final and throws from it, pointing at searchers instead.
 */
class BlockedRegistrationFiltersTest extends TestCase
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
                    'id' => 1, 'ip' => '10.0.0.1', 'email' => 'first@spam.example', 'username' => 'alpha',
                    'provider' => 'flarum', 'attempted_at' => '2026-01-10 09:00:00',
                    'reasons' => '{"reasons":["blacklisted"],"context":{}}',
                ],
                [
                    'id' => 2, 'ip' => '10.0.0.2', 'email' => 'second@spam.example', 'username' => 'beta',
                    'provider' => 'github', 'attempted_at' => '2026-02-20 09:00:00',
                    'reasons' => '{"reasons":["torExit","frequency"],"context":{}}',
                ],
                [
                    'id' => 3, 'ip' => '10.0.0.3', 'email' => 'third@spam.example', 'username' => 'gamma',
                    'provider' => 'flarum', 'attempted_at' => '2026-03-30 09:00:00',
                    // No reasons: recorded before the column existed.
                    'reasons' => null,
                ],
            ],
        ]);
    }

    /**
     * @return list<string>
     */
    private function ids(array $params): array
    {
        $response = $this->send(
            $this->request('GET', '/api/blocked-registrations', ['authenticatedAs' => 3])
                ->withQueryParams($params)
        );

        $this->assertEquals(200, $response->getStatusCode());

        return array_column(json_decode($response->getBody()->getContents(), true)['data'], 'id');
    }

    #[Test]
    public function results_can_be_filtered_by_ip()
    {
        $this->assertSame(['2'], $this->ids(['filter' => ['ip' => '10.0.0.2']]));
    }

    #[Test]
    public function results_can_be_filtered_by_email()
    {
        $this->assertSame(['1'], $this->ids(['filter' => ['email' => 'first@spam.example']]));
    }

    #[Test]
    public function results_can_be_filtered_by_username()
    {
        $this->assertSame(['3'], $this->ids(['filter' => ['username' => 'gamma']]));
    }

    #[Test]
    public function results_can_be_filtered_by_provider()
    {
        // Newest first, per the endpoint's default sort.
        $this->assertSame(['3', '1'], $this->ids(['filter' => ['provider' => 'flarum']]));
    }

    #[Test]
    public function a_date_range_narrows_to_the_window()
    {
        $this->assertSame(
            ['2'],
            $this->ids(['filter' => ['attemptedAt' => '2026-02-01..2026-02-28']])
        );
    }

    #[Test]
    public function an_open_ended_range_leaves_that_side_unbounded()
    {
        $this->assertSame(['3', '2'], $this->ids(['filter' => ['attemptedAt' => '2026-02-01..']]));
        $this->assertSame(['2', '1'], $this->ids(['filter' => ['attemptedAt' => '..2026-02-28']]));
    }

    #[Test]
    public function a_range_accepts_unix_timestamps()
    {
        // The dashboard widget works in timestamps, so they have to be understood as well as dates.
        $start = (new \DateTimeImmutable('2026-02-01', new \DateTimeZone('UTC')))->getTimestamp();
        $end = (new \DateTimeImmutable('2026-02-28', new \DateTimeZone('UTC')))->getTimestamp();

        $this->assertSame(['2'], $this->ids(['filter' => ['attemptedAt' => "$start..$end"]]));
    }

    #[Test]
    public function results_can_be_filtered_by_recorded_reason()
    {
        $this->assertSame(['1'], $this->ids(['filter' => ['reason' => 'blacklisted']]));
        $this->assertSame(['2'], $this->ids(['filter' => ['reason' => 'torExit']]));
    }

    #[Test]
    public function a_reason_filter_does_not_match_rows_with_no_recorded_reasons()
    {
        // Row 3 predates the reasons column. We do not know why it was blocked, so it must not
        // be swept into a filter for a specific rule.
        $this->assertNotContains('3', $this->ids(['filter' => ['reason' => 'blacklisted']]));
    }

    #[Test]
    public function a_reason_filter_matches_whole_names_only()
    {
        // 'tor' must not match 'torExit' by substring.
        $this->assertSame([], $this->ids(['filter' => ['reason' => 'tor']]));
    }

    #[Test]
    public function free_text_search_covers_the_identity_fields()
    {
        $this->assertSame(['2'], $this->ids(['filter' => ['q' => 'second@spam']]));
        $this->assertSame(['3'], $this->ids(['filter' => ['q' => 'gamma']]));
        $this->assertSame(['1'], $this->ids(['filter' => ['q' => '10.0.0.1']]));
    }

    #[Test]
    public function results_can_be_sorted_by_oldest_first()
    {
        $this->assertSame(['1', '2', '3'], $this->ids(['sort' => 'attemptedAt']));
    }

    #[Test]
    public function results_can_be_sorted_by_username()
    {
        $this->assertSame(['1', '2', '3'], $this->ids(['sort' => 'username']));
        $this->assertSame(['3', '2', '1'], $this->ids(['sort' => '-username']));
    }

    #[Test]
    public function filters_can_be_combined()
    {
        $this->assertSame(
            ['1'],
            $this->ids(['filter' => ['provider' => 'flarum', 'attemptedAt' => '..2026-02-01']])
        );
    }
}

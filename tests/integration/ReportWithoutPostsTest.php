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
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\AntiSpam\Api\SfsClient;
use PHPUnit\Framework\Attributes\Test;

/**
 * Reporting a spammer used to scrape the address off their first post, so an account caught before
 * it posted anything could never be reported — exactly the accounts worth reporting soonest.
 */
class ReportWithoutPostsTest extends TestCase
{
    use FakesStopForumSpam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeStopForumSpam();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $this->prepareDatabase([
            User::class => [
                [
                    'id' => 5, 'username' => 'never_posted', 'email' => 'never@machine.local',
                    'is_email_confirmed' => 1, 'joined_at' => Carbon::now(), 'registration_ip' => '203.0.113.9',
                ],
            ],
        ]);
    }

    private function spamblock(int $id): int
    {
        return $this->send(
            $this->request('POST', '/api/users/'.$id.'/spamblock', [
                'authenticatedAs' => 1,
                'json' => ['options' => ['reportToSfs' => true]],
            ])
        )->getStatusCode();
    }

    #[Test]
    public function a_spammer_who_never_posted_can_still_be_reported()
    {
        $this->app()->getContainer()->make('flarum.settings')->set(SfsClient::KEY, 'a-real-key');

        $this->assertEquals(204, $this->spamblock(5));

        $reports = $this->sfsReports();

        $this->assertCount(1, $reports, 'The account should have been reported using its registration address');
        $this->assertEquals('203.0.113.9', $reports[0]['ip_addr']);
        $this->assertEquals('never_posted', $reports[0]['username']);
    }

    #[Test]
    public function nothing_is_reported_without_an_api_key()
    {
        $this->assertEquals(204, $this->spamblock(5));

        $this->assertCount(0, $this->sfsReports(), 'Submissions need a key; lookups never did');
    }

    #[Test]
    public function an_account_with_no_address_at_all_is_not_reported()
    {
        $this->app();

        $settings = $this->app()->getContainer()->make('flarum.settings');
        $settings->set(SfsClient::KEY, 'a-real-key');

        User::find(5)->forceFill(['registration_ip' => null])->save();

        $this->assertEquals(204, $this->spamblock(5));

        // Never invent an address: reporting the wrong one gets an innocent party listed.
        $this->assertCount(0, $this->sfsReports());
    }
}

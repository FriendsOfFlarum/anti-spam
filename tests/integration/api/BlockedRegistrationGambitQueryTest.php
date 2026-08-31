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

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\AntiSpam\Model\BlockedRegistration;
use PHPUnit\Framework\Attributes\Test;

/**
 * The requests the search box actually produces.
 *
 * The frontend parses `key:value` tokens into discrete filter params — the backend does not
 * read gambits out of `filter[q]`. These cases mirror that parser's output for realistic
 * queries, against data shaped like production, so a filter that returns nothing in the browser
 * cannot pass here.
 */
class BlockedRegistrationGambitQueryTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $rows = [];

        // Most rows predate the reasons column, as on a real forum that has been running a while.
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = [
                'id' => $i,
                'ip' => '103.150.206.'.$i,
                'email' => "spammer$i@gmail.com",
                'username' => "spammer$i",
                'provider' => $i % 2 === 0 ? 'github' : 'flarum',
                'attempted_at' => '2026-01-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).' 09:00:00',
                'reasons' => null,
            ];
        }

        // A couple of recent ones that do carry recorded reasons.
        $rows[] = [
            'id' => 11, 'ip' => '10.0.0.1', 'email' => 'recent@spam.example', 'username' => 'recent',
            'provider' => 'flarum', 'attempted_at' => '2026-03-01 09:00:00',
            'reasons' => '{"reasons":["blacklisted"],"context":{}}',
        ];
        $rows[] = [
            'id' => 12, 'ip' => '10.0.0.2', 'email' => 'tor@spam.example', 'username' => 'toruser',
            'provider' => 'github', 'attempted_at' => '2026-03-02 09:00:00',
            'reasons' => '{"reasons":["torExit","frequency"],"context":{}}',
        ];

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
            BlockedRegistration::class => $rows,
        ]);
    }

    /**
     * @return list<string>
     */
    private function ids(array $filter): array
    {
        $params = ['filter' => $filter];
        $queryString = http_build_query($params);

        parse_str($queryString, $parsed);

        $response = $this->send(
            $this->request('GET', '/api/blocked-registrations?'.$queryString, ['authenticatedAs' => 1])
                ->withQueryParams($parsed)
        );

        $this->assertEquals(200, $response->getStatusCode());

        return array_column(json_decode($response->getBody()->getContents(), true)['data'], 'id');
    }

    #[Test]
    public function a_bare_term_searches_the_identity_fields()
    {
        // "gmail" — the parser sends this as filter[q].
        $this->assertCount(10, $this->ids(['q' => 'gmail']));
    }

    #[Test]
    public function a_provider_gambit_narrows_by_provider()
    {
        // "provider:github"
        $this->assertCount(6, $this->ids(['provider' => 'github']));
    }

    #[Test]
    public function a_reason_gambit_narrows_to_rows_that_recorded_it()
    {
        // "reason:blacklisted"
        $this->assertSame(['11'], $this->ids(['reason' => 'blacklisted']));
    }

    #[Test]
    public function a_reason_gambit_returns_nothing_when_no_row_recorded_one()
    {
        // Worth stating plainly: on a forum whose rows all predate the reasons column this
        // returns nothing, which is why the value chips come from the backend rather than
        // being a fixed list the UI always offers.
        $this->assertSame([], $this->ids(['reason' => 'deniedAsn']));
    }

    #[Test]
    public function gambits_combine()
    {
        // "provider:github reason:torExit"
        $this->assertSame(['12'], $this->ids(['provider' => 'github', 'reason' => 'torExit']));
    }

    #[Test]
    public function a_gambit_and_free_text_combine()
    {
        // "provider:flarum gmail" — the parser splits these into two params.
        $this->assertCount(5, $this->ids(['provider' => 'flarum', 'q' => 'gmail']));
    }

    #[Test]
    public function repeated_values_are_comma_joined_as_the_parser_sends_them()
    {
        // "reason:blacklisted reason:torExit" becomes filter[reason]=blacklisted,torExit
        $this->assertSame(['12', '11'], $this->ids(['reason' => 'blacklisted,torExit']));
    }

    #[Test]
    public function a_date_range_gambit_narrows_to_the_window()
    {
        // "attemptedAt:2026-03-01..2026-03-31"
        $this->assertSame(['12', '11'], $this->ids(['attemptedAt' => '2026-03-01..2026-03-31']));
    }
}

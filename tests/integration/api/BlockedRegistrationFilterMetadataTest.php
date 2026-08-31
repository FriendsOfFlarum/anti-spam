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
use PHPUnit\Framework\Attributes\Test;

/**
 * The filter metadata the list's search box builds its help from.
 *
 * Served as a forum attribute, the way flarum/audit serves auditFilters, so the frontend can
 * render clickable examples instead of expecting an admin to know the syntax.
 */
class BlockedRegistrationFilterMetadataTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
        ]);
    }

    private function forumAttributes(?int $as): array
    {
        $response = $this->send(
            $this->request('GET', '/api', $as === null ? [] : ['authenticatedAs' => $as])
        );

        $body = json_decode($response->getBody()->getContents(), true);

        return $body['data']['attributes'] ?? [];
    }

    #[Test]
    public function an_admin_receives_the_filter_metadata()
    {
        $filters = $this->forumAttributes(1)['fof-anti-spam.filters'] ?? null;

        $this->assertIsArray($filters, 'Admins should receive the filter metadata');
        $this->assertNotEmpty($filters);

        $keys = array_column($filters, 'key');

        foreach (['ip', 'email', 'username', 'provider', 'reason', 'attemptedAt'] as $expected) {
            $this->assertContains($expected, $keys);
        }
    }

    #[Test]
    public function every_filter_carries_what_the_help_panel_needs()
    {
        foreach ($this->forumAttributes(1)['fof-anti-spam.filters'] as $filter) {
            $this->assertArrayHasKey('key', $filter);
            $this->assertArrayHasKey('example', $filter);
            $this->assertArrayHasKey('description', $filter);
            $this->assertArrayHasKey('values', $filter);

            // The example has to be a usable query, not just a key name, since clicking a chip
            // drops it straight into the search box.
            $this->assertStringContainsString(':', $filter['example']);

            // And where it carries a value, that value has to be one the filter accepts.
            // A made-up sample (ip:192.0.2.1) is worse than a bare prefix: clicking it runs a
            // search that is guaranteed to return nothing.
            [$key, $value] = explode(':', $filter['example'], 2);

            $this->assertSame($filter['key'], $key, 'An example must use its own filter key');

            if ($value !== '') {
                $this->assertContains(
                    $value,
                    $filter['values'],
                    "The example for '{$filter['key']}' invents a value; use a bare prefix unless the value set is known"
                );
            }
        }
    }

    #[Test]
    public function the_reason_filter_offers_the_rules_the_extension_records()
    {
        $filters = $this->forumAttributes(1)['fof-anti-spam.filters'];
        $reason = collect($filters)->firstWhere('key', 'reason');

        // Sourced from BlockReasons so the chips cannot drift from the rules actually recorded.
        $this->assertSame(['blacklisted', 'torExit', 'deniedAsn', 'confidence', 'frequency'], $reason['values']);
    }

    #[Test]
    public function a_non_admin_does_not_receive_the_filter_metadata()
    {
        // The list is an admin panel page; the values name the rules this forum blocks on.
        $this->assertArrayNotHasKey('fof-anti-spam.filters', $this->forumAttributes(2));
    }

    #[Test]
    public function a_guest_does_not_receive_the_filter_metadata()
    {
        $this->assertArrayNotHasKey('fof-anti-spam.filters', $this->forumAttributes(null));
    }
}

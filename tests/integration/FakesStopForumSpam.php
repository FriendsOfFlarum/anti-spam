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

use Flarum\Extend;
use FoF\AntiSpam\Api\SfsClient;

/**
 * Swaps the StopForumSpam client for {@see FakeSfsClient} before the container is built.
 *
 * The binding has to be registered as an extender rather than poked into the container after
 * boot: the middleware pipe is assembled once, and by the time a request is sent it already holds
 * a StopForumSpam instance wired to whatever SfsClient existed at that moment.
 */
trait FakesStopForumSpam
{
    protected function fakeStopForumSpam(): void
    {
        FakeSfsClient::reset();

        $this->extend(
            (new Extend\ServiceProvider())->register(FakeSfsProvider::class)
        );
    }

    /**
     * Mark a username as a known spammer for this test.
     *
     * @param array<string, mixed> $overrides
     */
    protected function knownSpammer(string $username, array $overrides = []): void
    {
        FakeSfsClient::willReport($username, $overrides);
    }

    /**
     * The arguments the extension passed to the SFS lookup.
     *
     * @return array<int, array{ip: ?string, email: ?string, username: ?string}>
     */
    protected function sfsLookups(): array
    {
        return FakeSfsClient::$checked;
    }

    /**
     * Payloads the extension would have sent to StopForumSpam's report endpoint.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sfsReports(): array
    {
        return FakeSfsClient::$reported;
    }

    /**
     * Guard against a test silently going back to the live API.
     */
    protected function assertSfsClientIsFaked(): void
    {
        $this->assertInstanceOf(
            FakeSfsClient::class,
            $this->app()->getContainer()->make(SfsClient::class),
            'This test must not reach the real StopForumSpam API'
        );
    }
}

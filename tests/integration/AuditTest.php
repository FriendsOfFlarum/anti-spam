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

use Flarum\Audit\AuditLog;
use Flarum\Audit\AuditLogger;
use Flarum\Extend;
use Flarum\Group\Group;
use Flarum\Testing\integration\TestCase;
use FoF\AntiSpam\Event\RegistrationWasBlocked;
use FoF\AntiSpam\Model\BlockedRegistration;
use Illuminate\Contracts\Events\Dispatcher;

class AuditTest extends TestCase
{
    use FakesStopForumSpam;
    use MakesClientRequests;

    protected function setup(): void
    {
        parent::setup();

        // Log lifecycle events fired outside the test transaction shouldn't create stray entries.
        AuditLogger::$testMode = true;

        $this->extension('flarum-audit', 'fof-anti-spam');

        $this->prepareDatabase([
            'audit_log' => [],
            'users' => [
                ['id' => 3, 'username' => 'a_moderator', 'email' => 'a_mod@machine.local', 'is_email_confirmed' => 1],
                ['id' => 5, 'username' => 'bad_user', 'email' => 'bad_user@machine.local', 'is_email_confirmed' => 1],
            ],
            'group_user' => [
                ['user_id' => 3, 'group_id' => Group::MODERATOR_ID],
            ],
            'group_permission' => [
                ['group_id' => Group::MODERATOR_ID, 'permission' => 'user.spamblock'],
            ],
        ]);
    }

    /**
     * @test
     */
    public function marking_user_as_spammer_is_logged()
    {
        $response = $this->send(
            $this->request('POST', 'api/users/5/spamblock', [
                'authenticatedAs' => 3,
            ])
        );

        $this->assertEquals(204, $response->getStatusCode());

        $log = AuditLog::query()->where('action', 'user.marked_as_spammer')->first();

        $this->assertNotNull($log, 'Marking a user as a spammer should be audit logged');
        $this->assertEquals(3, $log->actor_id, 'The acting moderator should be recorded');
        $this->assertEquals(['user_id' => 5], $log->payload);
    }

    /**
     * @test
     */
    public function blocked_registration_is_logged()
    {
        $this->app();

        $blocked = BlockedRegistration::create('1.2.3.4', 'spammer@example.com', 'spammer', '{}');

        $this->app()->getContainer()->make(Dispatcher::class)->dispatch(
            new RegistrationWasBlocked($blocked)
        );

        $log = AuditLog::query()->where('action', 'registration.blocked')->first();

        $this->assertNotNull($log, 'A blocked registration should be audit logged');
        $this->assertEquals([
            'ip' => '1.2.3.4',
            'email' => 'spammer@example.com',
            'username' => 'spammer',
        ], $log->payload);
    }

    public function registrationRoutes(): array
    {
        return [
            'form' => ['/register', ['username' => 'spammer', 'email' => 'listed@machine.local', 'password' => 'too-obscure']],
            'api' => ['/api/users', ['data' => ['attributes' => ['username' => 'spammer', 'email' => 'listed@machine.local', 'password' => 'too-obscure']]]],
        ];
    }

    /**
     * @test
     * @dataProvider registrationRoutes
     */
    public function a_refused_registration_is_logged_with_the_address_it_came_from(string $path, array $body)
    {
        $this->extend(
            (new Extend\Csrf())
                ->exemptRoute('register')
                ->exemptRoute('users.create')
        );

        $this->fakeSfs();

        $request = $this->requestWithJsonBody(
            $this->clientRequest('POST', $path, '10.0.0.5', ['Client-IP' => '1.1.1.1', 'X-Forwarded-For' => '1.1.1.1']),
            $body
        );

        $response = $this->send($request);

        $this->assertEquals(422, $response->getStatusCode());

        $log = AuditLog::query()->where('action', 'registration.blocked')->first();

        $this->assertNotNull($log, 'A refused registration should be audit logged');
        $this->assertNull($log->actor_id, 'A guest registered');
        $this->assertEquals('10.0.0.5', $log->ip_address);
        $this->assertEquals([
            'ip' => '10.0.0.5',
            'email' => 'listed@machine.local',
            'username' => 'spammer',
        ], $log->payload, 'The payload should name the address the entry was logged from');
    }
}

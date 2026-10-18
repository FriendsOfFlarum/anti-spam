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
use Flarum\Testing\integration\TestCase;
use FoF\AntiSpam\Api\SfsClient;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * A registration StopForumSpam turned away can be submitted back to it, so the database learns
 * the attempt was seen again.
 */
class ReportBlockedRegistrationTest extends TestCase
{
    use FakesStopForumSpam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeStopForumSpam();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $this->extend((new Extend\Csrf())->exemptRoute('users.create'));

        $this->setting(SfsClient::KEY, 'a-real-key');
    }

    private function register(string $username, string $email): ResponseInterface
    {
        return $this->send(
            $this->request('POST', '/api/users', [
                'json' => [
                    'data' => [
                        'type' => 'users',
                        'attributes' => [
                            'username' => $username,
                            'password' => 'too-obscure',
                            'email' => $email,
                        ],
                    ],
                ],
            ])
        );
    }

    #[Test]
    public function a_registration_blocked_on_stopforumspam_evidence_is_reported()
    {
        $this->knownSpammer('xrumer');

        $this->assertEquals(422, $this->register('xrumer', 'testing@xrumer.ru')->getStatusCode());

        $this->assertCount(1, $this->sfsReports());
        $this->assertSame('xrumer', $this->sfsReports()[0]['username']);
        $this->assertSame('testing@xrumer.ru', $this->sfsReports()[0]['email']);
        $this->assertSame('127.0.0.1', $this->sfsReports()[0]['ip_addr']);
    }

    #[Test]
    public function a_block_from_the_forums_own_network_rules_is_not_reported()
    {
        // Denying a hosting network or Tor is this forum's policy, not evidence that the person
        // spams. Submitting them would put an innocent address into a database other forums
        // block on.
        $this->setting('fof-anti-spam.blockedAsns', '64500');
        $this->knownSpammer('hosted_visitor', [
            'ip' => ['appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'blacklisted' => 0, 'asn' => 64500],
            'email' => ['appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'blacklisted' => 0],
            'username' => ['appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'blacklisted' => 0],
        ]);

        $this->assertEquals(422, $this->register('hosted_visitor', 'visitor@example.com')->getStatusCode());

        $this->assertSame([], $this->sfsReports());
    }

    #[Test]
    public function an_attempt_missing_a_field_is_not_reported_with_a_placeholder()
    {
        // StopForumSpam wants all three. Filling the gap with "unknown" would submit a value
        // nobody sent, shared by every such report.
        $this->knownSpammer('xrumer');

        $response = $this->send(
            $this->request('POST', '/api/users', [
                'json' => ['data' => ['type' => 'users', 'attributes' => ['username' => 'xrumer', 'password' => 'too-obscure']]],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertSame([], $this->sfsReports());
    }
}

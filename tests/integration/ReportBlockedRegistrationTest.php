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
    public function evidence_against_the_address_alone_does_not_report_the_person()
    {
        // A VPN or carrier-grade NAT address can carry a high score while the person behind it
        // is innocent. Reporting would list their clean email and name for every other forum.
        $this->knownSpammer('vpn_user', [
            'ip' => ['appears' => 1, 'frequency' => 40, 'confidence' => 95.0, 'blacklisted' => 0],
            'email' => ['appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'blacklisted' => 0],
            'username' => ['appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'blacklisted' => 0],
        ]);

        $this->assertEquals(422, $this->register('vpn_user', 'vpn_user@example.com')->getStatusCode());

        $this->assertSame([], $this->sfsReports());
    }

    #[Test]
    public function a_tor_exit_block_is_not_reported()
    {
        $this->setting('fof-anti-spam.blockTorExitNodes', true);
        $this->knownSpammer('tor_visitor', [
            'ip' => ['appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'blacklisted' => 0, 'torexit' => 1],
            'email' => ['appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'blacklisted' => 0],
            'username' => ['appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'blacklisted' => 0],
        ]);

        $this->assertEquals(422, $this->register('tor_visitor', 'tor_visitor@example.com')->getStatusCode());

        $this->assertSame([], $this->sfsReports());
    }

    #[Test]
    public function a_listed_email_is_reported_even_when_a_network_rule_also_fired()
    {
        $this->setting('fof-anti-spam.blockedAsns', '64500');
        $this->knownSpammer('listed_mail', [
            'ip' => ['appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'blacklisted' => 0, 'asn' => 64500],
            'email' => ['appears' => 1, 'frequency' => 30, 'confidence' => 90.0, 'blacklisted' => 0],
            'username' => ['appears' => 0, 'frequency' => 0, 'confidence' => 0.0, 'blacklisted' => 0],
        ]);

        $this->assertEquals(422, $this->register('listed_mail', 'listed@example.com')->getStatusCode());

        $this->assertCount(1, $this->sfsReports());
    }

    #[Test]
    public function nothing_is_reported_when_the_setting_is_off()
    {
        $this->setting('fof-anti-spam.report_blocked_registrations', false);
        $this->knownSpammer('xrumer');

        $this->register('xrumer', 'testing@xrumer.ru');

        $this->assertSame([], $this->sfsReports());
    }

    #[Test]
    public function nothing_is_reported_without_an_api_key()
    {
        $this->setting(SfsClient::KEY, '');
        $this->knownSpammer('xrumer');

        $this->register('xrumer', 'testing@xrumer.ru');

        $this->assertSame([], $this->sfsReports());
    }

    #[Test]
    public function a_bot_retrying_the_same_identity_is_reported_once()
    {
        $this->setting('fof-anti-spam.registrationThrottleSeconds', 0);
        $this->knownSpammer('xrumer');

        $this->register('xrumer', 'testing@xrumer.ru');
        $this->register('xrumer', 'testing@xrumer.ru');
        $this->register('xrumer', 'testing@xrumer.ru');

        $this->assertCount(1, $this->sfsReports());
    }

    #[Test]
    public function a_queue_that_cannot_take_the_report_does_not_turn_the_block_into_an_error()
    {
        $this->extend((new Extend\ServiceProvider())->register(FailingQueueProvider::class));
        $this->knownSpammer('xrumer');

        $this->assertEquals(422, $this->register('xrumer', 'testing@xrumer.ru')->getStatusCode());
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

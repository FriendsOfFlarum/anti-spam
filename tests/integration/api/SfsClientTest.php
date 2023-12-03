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

use Flarum\Testing\integration\TestCase;
use FoF\AntiSpam\Api\SfsClient;
use FoF\AntiSpam\Api\SfsResponse;

class SfsClientTest extends TestCase
{
    /**
     * @var SfsClient
     */
    protected $sfsClient;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-anti-spam');
    }

    protected function setUpClient(): void
    {
        $this->sfsClient = $this->app()->getContainer()->make(SfsClient::class);
    }

    /**
     * @test
     */
    public function it_can_check_data_and_parse_it()
    {
        $this->setUpClient();

        $testIp = '109.104.183.88';
        $testEmail = 'testing@xrumer.ru';
        $testUsername = 'xrumer';

        $response = $this->sfsClient->check(
            $testIp,
            $testEmail,
            $testUsername
        );

        $this->assertEquals(SfsResponse::class, get_class($response));

        $this->assertTrue($response->success);

        $this->assertNotNull($response->ip);
        $this->assertEquals($testIp, $response->ip->value);
        $this->assertObjectHasProperty('confidence', $response->ip);
        $this->assertObjectHasProperty('blacklisted', $response->ip);
        $this->assertObjectHasProperty('asn', $response->ip);
        $this->assertObjectHasProperty('country', $response->ip);

        $this->assertNotNull($response->email);
        $this->assertEquals($testEmail, $response->email->value);
        $this->assertObjectHasProperty('confidence', $response->email);
        $this->assertObjectHasProperty('blacklisted', $response->email);

        $this->assertNotNull($response->username);
        $this->assertEquals($testUsername, $response->username->value);
        $this->assertObjectHasProperty('confidence', $response->username);
        $this->assertObjectHasProperty('blacklisted', $response->username);
    }

    /**
     * @test
     */
    public function email_is_hashed_when_setting_enabled()
    {
        $this->setting('fof-anti-spam.emailhash', true);

        $this->setUpClient();

        $testIp = '109.104.183.88';
        $testEmail = 'testing@xrumer.ru';
        $testUsername = 'xrumer';

        $response = $this->sfsClient->check(
            $testIp,
            $testEmail,
            $testUsername
        );
        $this->assertEquals(SfsResponse::class, get_class($response));

        $this->assertTrue($response->success);

        $this->assertNotNull($response->email);
        $this->assertEquals($testEmail, $response->email->value);
    }
}

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
use FoF\AntiSpam\Api\BasicFieldData;
use FoF\AntiSpam\Api\IpFieldData;
use FoF\AntiSpam\Api\SfsClient;
use FoF\AntiSpam\Api\SfsResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Cache\Store;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;

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

    #[Test]
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
        $this->assertObjectHasProperty('torexit', $response->ip);
        $this->assertObjectHasProperty('delegated', $response->ip);

        $this->assertNotNull($response->email);
        $this->assertEquals($testEmail, $response->email->value);
        $this->assertObjectHasProperty('confidence', $response->email);
        $this->assertObjectHasProperty('blacklisted', $response->email);

        $this->assertNotNull($response->username);
        $this->assertEquals($testUsername, $response->username->value);
        $this->assertObjectHasProperty('confidence', $response->username);
        $this->assertObjectHasProperty('blacklisted', $response->username);
    }

    #[Test]
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

    #[Test]
    public function caching_works_correctly()
    {
        $this->setUpClient();

        $testIp = '109.104.183.88';
        $testEmail = 'testing@xrumer.ru';
        $testUsername = 'xrumer';

        // First call - should hit API
        $response1 = $this->sfsClient->check($testIp, $testEmail, $testUsername);
        $this->assertTrue($response1->success);

        // Get cache to verify it was stored
        $cache = $this->app()->getContainer()->make(Store::class);
        $cacheKey = 'sfs_check_'.md5(($testIp ?? '').'|'.($testEmail ?? '').'|'.($testUsername ?? ''));
        $cachedData = $cache->get($cacheKey);

        $this->assertNotNull($cachedData, 'Response should be cached');

        // Second call with same parameters - should use cache
        $response2 = $this->sfsClient->check($testIp, $testEmail, $testUsername);
        $this->assertTrue($response2->success);
        $this->assertEquals($response1->ip->value, $response2->ip->value);
    }

    #[Test]
    public function different_parameters_use_different_cache_keys()
    {
        $this->setUpClient();

        // Check first set of parameters
        $this->sfsClient->check('1.2.3.4', 'test1@example.com', 'user1');

        // Check second set of parameters
        $this->sfsClient->check('5.6.7.8', 'test2@example.com', 'user2');

        // Verify different cache keys exist
        $cache = $this->app()->getContainer()->make(Store::class);
        $cacheKey1 = 'sfs_check_'.md5('1.2.3.4|test1@example.com|user1');
        $cacheKey2 = 'sfs_check_'.md5('5.6.7.8|test2@example.com|user2');

        $this->assertNotEquals($cacheKey1, $cacheKey2, 'Cache keys should be different');

        // Verify both responses were cached
        $this->assertNotNull($cache->get($cacheKey1), 'First response should be cached');
        $this->assertNotNull($cache->get($cacheKey2), 'Second response should be cached');
    }

    #[Test]
    public function api_failure_returns_unsuccessful_response()
    {
        // Create a mock handler that throws an exception
        $mock = new MockHandler([
            new ConnectException('Connection timeout', new Request('POST', 'api'))
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockClient = new Client(['handler' => $handlerStack]);

        // Create SfsClient with mocked Guzzle client
        $settings = $this->app()->getContainer()->make('flarum.settings');
        $cache = $this->app()->getContainer()->make(Store::class);
        $log = $this->app()->getContainer()->make(LoggerInterface::class);

        $sfsClient = new SfsClient($settings, $cache, $log);

        // Use reflection to inject mock client
        $reflection = new \ReflectionClass($sfsClient);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($sfsClient, $mockClient);

        // Should return unsuccessful response without throwing
        $response = $sfsClient->check('1.2.3.4', 'test@example.com', 'username');

        $this->assertFalse($response->success, 'Response should be unsuccessful on API failure');
        $this->assertNull($response->ip);
        $this->assertNull($response->email);
        $this->assertNull($response->username);
    }

    #[Test]
    public function api_failure_does_not_cache_failed_response()
    {
        // Create a mock handler that throws an exception
        $mock = new MockHandler([
            new ConnectException('Connection timeout', new Request('POST', 'api'))
        ]);
        $handlerStack = HandlerStack::create($mock);
        $mockClient = new Client(['handler' => $handlerStack]);

        $settings = $this->app()->getContainer()->make('flarum.settings');
        $cache = $this->app()->getContainer()->make(Store::class);
        $log = $this->app()->getContainer()->make(LoggerInterface::class);

        $sfsClient = new SfsClient($settings, $cache, $log);

        // Use reflection to inject mock client
        $reflection = new \ReflectionClass($sfsClient);
        $clientProperty = $reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($sfsClient, $mockClient);

        // Make failed request
        $response = $sfsClient->check('1.2.3.4', 'test@example.com', 'username');
        $this->assertFalse($response->success);

        // Verify failed response was not cached
        $cacheKey = 'sfs_check_'.md5('1.2.3.4|test@example.com|username');
        $cachedData = $cache->get($cacheKey);

        $this->assertNull($cachedData, 'Failed responses should not be cached');
    }
}

class SfsResponseTest extends TestCase
{
    #[Test]
    public function it_parses_successful_response_with_all_fields()
    {
        $data = [
            'success' => 1,
            'ip' => [
                'value' => '1.2.3.4',
                'appears' => 1,
                'frequency' => 50,
                'lastseen' => '2024-01-01 12:00:00',
                'confidence' => 95.5,
                'blacklisted' => 1,
                'asn' => 12345,
                'country' => 'US'
            ],
            'email' => [
                'value' => 'spam@example.com',
                'appears' => 1,
                'frequency' => 25,
                'lastseen' => '2024-01-02 12:00:00',
                'confidence' => 85.0,
                'blacklisted' => 0
            ],
            'username' => [
                'value' => 'spammer',
                'appears' => 1,
                'frequency' => 10,
                'lastseen' => '2024-01-03 12:00:00',
                'confidence' => 75.0,
                'blacklisted' => 0
            ]
        ];

        $response = new SfsResponse($data);

        $this->assertTrue($response->success);

        // Test IP field
        $this->assertInstanceOf(IpFieldData::class, $response->ip);
        $this->assertEquals('1.2.3.4', $response->ip->value);
        $this->assertTrue($response->ip->appears);
        $this->assertEquals(50, $response->ip->frequency);
        $this->assertEquals('2024-01-01 12:00:00', $response->ip->lastseen);
        $this->assertEquals(95.5, $response->ip->confidence);
        $this->assertTrue($response->ip->blacklisted);
        $this->assertEquals(12345, $response->ip->asn);
        $this->assertEquals('US', $response->ip->country);

        // Test email field
        $this->assertInstanceOf(BasicFieldData::class, $response->email);
        $this->assertEquals('spam@example.com', $response->email->value);
        $this->assertTrue($response->email->appears);
        $this->assertEquals(25, $response->email->frequency);
        $this->assertEquals('2024-01-02 12:00:00', $response->email->lastseen);
        $this->assertEquals(85.0, $response->email->confidence);
        $this->assertFalse($response->email->blacklisted);

        // Test username field
        $this->assertInstanceOf(BasicFieldData::class, $response->username);
        $this->assertEquals('spammer', $response->username->value);
        $this->assertTrue($response->username->appears);
        $this->assertEquals(10, $response->username->frequency);
        $this->assertEquals('2024-01-03 12:00:00', $response->username->lastseen);
        $this->assertEquals(75.0, $response->username->confidence);
        $this->assertFalse($response->username->blacklisted);
    }

    #[Test]
    public function it_handles_missing_fields_gracefully()
    {
        $data = [
            'success' => 1,
            'ip' => [
                'value' => '1.2.3.4',
                'appears' => 0
            ]
        ];

        $response = new SfsResponse($data);

        $this->assertTrue($response->success);
        $this->assertNotNull($response->ip);
        $this->assertEquals('1.2.3.4', $response->ip->value);
        $this->assertFalse($response->ip->appears);
        $this->assertNull($response->ip->frequency);
        $this->assertNull($response->ip->lastseen);
        $this->assertNull($response->ip->confidence);
        $this->assertFalse($response->ip->blacklisted);
        $this->assertNull($response->ip->asn);
        $this->assertNull($response->ip->country);

        // Email and username should be null
        $this->assertNull($response->email);
        $this->assertNull($response->username);
    }

    #[Test]
    public function it_handles_emailhash_response()
    {
        $data = [
            'success' => 1,
            'emailhash' => [
                'value' => 'spam@example.com',
                'appears' => 1,
                'frequency' => 5,
                'confidence' => 90.0,
                'blacklisted' => 1
            ]
        ];

        $response = new SfsResponse($data);

        $this->assertTrue($response->success);
        $this->assertNotNull($response->email, 'Email should be populated from emailhash');
        $this->assertEquals('spam@example.com', $response->email->value);
        $this->assertTrue($response->email->appears);
        $this->assertEquals(5, $response->email->frequency);
        $this->assertEquals(90.0, $response->email->confidence);
        $this->assertTrue($response->email->blacklisted);
    }

    #[Test]
    public function it_prefers_email_over_emailhash()
    {
        $data = [
            'success' => 1,
            'email' => [
                'value' => 'real@example.com',
                'appears' => 1
            ],
            'emailhash' => [
                'value' => 'hash@example.com',
                'appears' => 1
            ]
        ];

        $response = new SfsResponse($data);

        $this->assertEquals('real@example.com', $response->email->value, 'Should prefer email over emailhash');
    }

    #[Test]
    public function it_handles_unsuccessful_response()
    {
        $data = ['success' => 0];

        $response = new SfsResponse($data);

        $this->assertFalse($response->success);
        $this->assertNull($response->ip);
        $this->assertNull($response->email);
        $this->assertNull($response->username);
    }

    #[Test]
    public function it_handles_empty_data_array()
    {
        $data = [];

        $response = new SfsResponse($data);

        $this->assertFalse($response->success, 'Empty data should result in unsuccessful response');
        $this->assertNull($response->ip);
        $this->assertNull($response->email);
        $this->assertNull($response->username);
    }

    #[Test]
    public function basic_field_data_handles_null_values()
    {
        $fieldData = [
            'value' => 'test',
            'appears' => null,
            'frequency' => null,
            'lastseen' => null,
            'confidence' => null,
            'blacklisted' => null
        ];

        $field = new BasicFieldData($fieldData);

        $this->assertEquals('test', $field->value);
        $this->assertFalse($field->appears, 'Null appears should default to false');
        $this->assertNull($field->frequency);
        $this->assertNull($field->lastseen);
        $this->assertNull($field->confidence);
        $this->assertFalse($field->blacklisted, 'Null blacklisted should default to false');
    }

    #[Test]
    public function ip_field_data_inherits_basic_fields()
    {
        $fieldData = [
            'value' => '1.2.3.4',
            'appears' => 1,
            'frequency' => 10,
            'confidence' => 80.0,
            'asn' => 12345,
            'country' => 'US'
        ];

        $ipField = new IpFieldData($fieldData);

        // Test inherited basic fields
        $this->assertEquals('1.2.3.4', $ipField->value);
        $this->assertTrue($ipField->appears);
        $this->assertEquals(10, $ipField->frequency);
        $this->assertEquals(80.0, $ipField->confidence);

        // Test IP-specific fields
        $this->assertEquals(12345, $ipField->asn);
        $this->assertEquals('US', $ipField->country);
    }

    #[Test]
    public function ip_field_data_handles_null_asn_and_country()
    {
        $fieldData = [
            'value' => '1.2.3.4',
            'appears' => 1,
            'asn' => null,
            'country' => null
        ];

        $ipField = new IpFieldData($fieldData);

        $this->assertNull($ipField->asn);
        $this->assertNull($ipField->country);
    }

    #[Test]
    public function type_coercion_works_correctly()
    {
        $fieldData = [
            'value' => 123,  // Should be coerced to string
            'appears' => '1',  // Should be coerced to bool
            'frequency' => '50',  // Should be coerced to int
            'confidence' => '95.5',  // Should be coerced to float
            'blacklisted' => 1  // Should be coerced to bool
        ];

        $field = new BasicFieldData($fieldData);

        $this->assertIsString($field->value);
        $this->assertEquals('123', $field->value);
        $this->assertIsBool($field->appears);
        $this->assertTrue($field->appears);
        $this->assertIsInt($field->frequency);
        $this->assertEquals(50, $field->frequency);
        $this->assertIsFloat($field->confidence);
        $this->assertEquals(95.5, $field->confidence);
        $this->assertIsBool($field->blacklisted);
        $this->assertTrue($field->blacklisted);
    }

    #[Test]
    public function ip_field_data_includes_torexit_and_delegated()
    {
        $fieldData = [
            'value' => '1.2.3.4',
            'appears' => 1,
            'torexit' => 1,
            'delegated' => 'RU'
        ];

        $ipField = new IpFieldData($fieldData);

        $this->assertTrue($ipField->torexit, 'Tor exit node flag should be true');
        $this->assertEquals('RU', $ipField->delegated);
    }

    #[Test]
    public function ip_field_data_handles_missing_torexit_and_delegated()
    {
        $fieldData = [
            'value' => '1.2.3.4',
            'appears' => 1
        ];

        $ipField = new IpFieldData($fieldData);

        $this->assertNull($ipField->torexit, 'Tor exit node flag should be null when not present');
        $this->assertNull($ipField->delegated, 'Delegated country should be null when not present');
    }

    #[Test]
    public function response_with_tor_exit_node_is_parsed()
    {
        $data = [
            'success' => 1,
            'ip' => [
                'value' => '1.2.3.4',
                'appears' => 1,
                'frequency' => 10,
                'confidence' => 80.0,
                'blacklisted' => 0,
                'torexit' => 1,
                'asn' => 12345,
                'country' => 'US',
                'delegated' => 'RU'
            ]
        ];

        $response = new SfsResponse($data);

        $this->assertTrue($response->success);
        $this->assertNotNull($response->ip);
        $this->assertTrue($response->ip->torexit, 'Should identify as Tor exit node');
        $this->assertEquals('RU', $response->ip->delegated);
    }

    #[Test]
    public function response_without_tor_data_works()
    {
        $data = [
            'success' => 1,
            'ip' => [
                'value' => '5.6.7.8',
                'appears' => 1,
                'frequency' => 5,
                'torexit' => 0
            ]
        ];

        $response = new SfsResponse($data);

        $this->assertTrue($response->success);
        $this->assertNotNull($response->ip);
        $this->assertFalse($response->ip->torexit, 'Should not be a Tor exit node');
        $this->assertNull($response->ip->delegated);
    }
}

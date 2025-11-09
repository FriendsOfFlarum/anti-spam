<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Api;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use Illuminate\Contracts\Cache\Store;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class SfsClient
{
    public const KEY = 'fof-anti-spam.api_key';

    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * @var array<string, string>
     */
    protected array $endpoints = [
        'closest' => 'https://api.stopforumspam.org/',
        'europe' => 'https://europe.stopforumspam.org/',
        'us' => 'https://us.stopforumspam.org/'
    ];

    /**
     * @var Client
     */
    protected $client;

    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Store $cache,
        protected LoggerInterface $log
    ) {
        $this->client = new Client([
            'base_uri' => $this->endpoint(),
            'verify' => false,
            'timeout' => 5,
            'connect_timeout' => 3,
        ]);
    }

    private function endpoint(): string
    {
        return $this->endpoints[$this->settings->get('fof-anti-spam.regionalEndpoint')] ?? $this->endpoints['closest'];
    }

    public function check(?string $ip, ?string $email, ?string $username): SfsResponse
    {
        // Generate cache key based on checked fields
        $cacheKey = 'sfs_check_' . md5(($ip ?? '') . '|' . ($email ?? '') . '|' . ($username ?? ''));

        // Try to get from cache first
        $cachedResponse = $this->cache->get($cacheKey);
        if ($cachedResponse !== null) {
            return new SfsResponse(json_decode($cachedResponse, true));
        }

        try {
            $data = $this->buildDataArray($ip, $email, $username);
            $response = $this->call('api', $data);
            $sfsResponse = $this->parseResponse($response);

            // Cache the successful response
            $this->cache->put($cacheKey, json_encode($sfsResponse), self::CACHE_TTL);

            return $sfsResponse;
        } catch (\Throwable $e) {
            // Log the error but don't block registration on API failure
            $this->log->warning("[FoF Anti Spam] SFS API check failed: {$e->getMessage()}");

            // Return unsuccessful response (will not trigger spam blocking)
            return new SfsResponse(['success' => false]);
        }
    }

    private function buildDataArray(?string $ip, ?string $email, ?string $username): array
    {
        $data = [
            'ip' => $ip,
            'username' => $username,
            'json' => true,
            'confidence' => true,  // Request confidence scores from API
        ];

        if ((bool) $this->settings->get('fof-anti-spam.emailhash')) {
            $data['emailhash'] = md5($email);
        }

        $data['email'] = $email;

        return $data;
    }

    private function parseResponse(ResponseInterface $response): SfsResponse
    {
        $json = json_decode($response->getBody()->getContents(), true);

        return new SfsResponse($json);
    }

    public function report(array $data): ResponseInterface
    {
        $data['api_key'] = $this->settings->get(self::KEY);

        return $this->call('https://www.stopforumspam.com/add.php', $data);
    }

    private function call(string $url, array $data): ResponseInterface
    {
        return $this->client->post($url, [
            'form_params' => $data,
        ]);
    }
}

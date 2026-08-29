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

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Illuminate\Contracts\Cache\Store;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class SfsClient
{
    public const KEY = 'fof-anti-spam.api_key';

    /**
     * Records when a lookup last failed, so an admin can be told that registrations are currently
     * going unchecked. A failed lookup deliberately lets the registration through, and that must
     * not happen silently.
     *
     * Deliberately free of underscores: the settings repository deletes by SQL LIKE, where `_`
     * is a single-character wildcard.
     */
    public const FAILURE_KEY = 'fof-anti-spam.lookupFailedAt';

    /**
     * Cache TTL in seconds (1 hour).
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

    protected ClientInterface $client;

    /**
     * @param ClientInterface|null $client Injectable so tests can drive the API without a network
     *                                     call; the provider leaves it null for the real thing.
     */
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Store $cache,
        protected LoggerInterface $log,
        ?ClientInterface $client = null
    ) {
        $this->client = $client ?? new Client($this->clientConfig());
    }

    /**
     * Guzzle options for the real client.
     *
     * StopForumSpam's docs only call for certificate verification to be turned off where the
     * client cannot do SNI, which PHP has managed for well over a decade. Leaving it off matters
     * because report() sends the forum's API key over this same connection.
     *
     * @return array<string, mixed>
     */
    protected function clientConfig(): array
    {
        return [
            'base_uri' => $this->endpoint(),
            'verify' => true,
            'timeout' => 5,
            'connect_timeout' => 3,
        ];
    }

    private function endpoint(): string
    {
        return $this->endpoints[$this->settings->get('fof-anti-spam.regionalEndpoint')] ?? $this->endpoints['closest'];
    }

    public function check(?string $ip, ?string $email, ?string $username): SfsResponse
    {
        // Generate cache key based on checked fields
        $cacheKey = 'sfs_check_'.md5(($ip ?? '').'|'.($email ?? '').'|'.($username ?? ''));

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

            $this->recordLookupRecovered();

            return $sfsResponse;
        } catch (\Throwable $e) {
            // Log the error but don't block registration on API failure
            $this->log->warning("[FoF Anti Spam] SFS API check failed: {$e->getMessage()}");

            $this->recordLookupFailed();

            // Return unsuccessful response (will not trigger spam blocking)
            return new SfsResponse(['success' => false]);
        }
    }

    /**
     * Written only on the transition into failure, so an ordinary registration costs no write.
     */
    private function recordLookupFailed(): void
    {
        $this->settings->set(self::FAILURE_KEY, Carbon::now()->toIso8601String());
    }

    private function recordLookupRecovered(): void
    {
        if ($this->settings->get(self::FAILURE_KEY) !== null) {
            $this->settings->delete(self::FAILURE_KEY);
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
        return $this->client->request('POST', $url, [
            'form_params' => $data,
        ]);
    }
}

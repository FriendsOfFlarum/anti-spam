<?php

namespace FoF\AntiSpam\Api;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

class SfsClient
{
    public const KEY = 'fof-anti-spam.api_key';

    protected $endpoints = [
        'closest' => 'https://api.stopforumspam.org/',
        'europe'  => 'https://europe.stopforumspam.org/',
        'us'      => 'https://us.stopforumspam.org/',
    ];

    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @var Client
     */
    protected $client;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;

        $this->client = new Client([
            'base_uri' => $this->endpoint(),
            'verify'   => false,
        ]);
    }

    private function endpoint(): string
    {
        return $this->endpoints[$this->settings->get('fof-anti-spam.regionalEndpoint')] ?? $this->endpoints['closest'];
    }

    public function check(?string $ip, ?string $email, ?string $username): SfsResponse
    {
        $data = $this->buildDataArray($ip, $email, $username);
        $response = $this->call('api', $data);
        return $this->parseResponse($response);
    }

    private function buildDataArray(?string $ip, ?string $email, ?string $username): array
    {
        $data = [
            'ip' => $ip,
            'username' => $username,
            'json' => true
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

        return $this->call('https://www.stopforumspam.com/add', $data);
    }

    private function call(string $url, array $data): ResponseInterface
    {
        return $this->client->post($url, [
            'form_params' => $data,
        ]);
    }
}

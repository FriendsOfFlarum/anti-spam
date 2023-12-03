<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam;

use Flarum\Settings\SettingsRepositoryInterface;
use FoF\AntiSpam\Api\SfsClient;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;

class StopForumSpam
{
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @var Dispatcher
     */
    protected $bus;

    /**
     * @var SfsClient
     */
    protected $client;

    public function __construct(SettingsRepositoryInterface $settings, Dispatcher $bus, SfsClient $client)
    {
        $this->settings = $settings;
        $this->bus = $bus;
        $this->client = $client;
    }

    public function isEnabled(): bool
    {
        $key = $this->settings->get(SfsClient::KEY);

        return $key !== null && !empty($key);
    }

    /**
     * Validates against the StopForumSpam API. Returns a simple boolean indicating if based on the current 
     * extension settings this registration should be prevented or not.
     *
     * @return bool
     */
    public function shouldPreventLogin(?string $ip, ?string $email, ?string $username, ?string $provider = null, ?array $providerData = null): bool
    {
        // If we don't have sfs lookup enabled, we return false early.
        if (!(bool) $this->settings->get('fof-anti-spam.sfs-lookup')) {
            return false;
        }

        $sfsResponse = $this->client->check($ip, $email, $username);

        if ($sfsResponse->success) {
            $requiredFrequency = (int) $this->settings->get('fof-anti-spam.frequency');
            $requiredConfidence = (float) $this->settings->get('fof-anti-spam.confidence');
            $frequency = 0;
            $confidence = 0.0;
            $blacklisted = false;

            foreach (['ip' => $sfsResponse->ip, 'email' => $sfsResponse->email, 'username' => $sfsResponse->username] as $key => $value) {
                if ($value === null || !(bool) $this->settings->get("fof-anti-spam.$key")) {
                    continue;
                }

                if (isset($value->blacklisted) && $value->blacklisted) {
                    $blacklisted = true;
                }

                $frequency += $value->frequency ?? 0;
                $confidence += $value->confidence ?? 0.0;               
            }

            if ($confidence >= $requiredConfidence || $frequency >= $requiredFrequency || $blacklisted) {
                $this->buildAndDispatchEvents(['ip' => $ip, 'email' => $email, 'username' => $username], json_encode($sfsResponse), $provider, $providerData);
                return true;
            }
        }

        return false;
    }


    private function buildAndDispatchEvents(array $data, string $sfsData, string $provider = null, array $providerData = null): void
    {
        $ip = Arr::get($data, 'ip') ?? 'unknown';
        $email = Arr::get($data, 'email') ?? 'unknown';
        $username = Arr::get($data, 'username') ?? 'unknown';
        
        // If there's a password in the provider data, we remove it from the data we send to the event.
        Arr::pull($providerData, 'password');

        $this->bus->dispatch(new Event\RegistrationWasBlocked(
            Model\BlockedRegistration::create(
                $ip,
                $email,
                $username,
                $sfsData,
                $provider,
                $providerData
            )
        ));

        // Kept for backwards compatibility, remove for Flarum 2.0
        $this->bus->dispatch(new Event\RegistrationBlocked(
            $username,
            $ip,
            $email,
            $data,
            $provider,
            $providerData
        ));
    }
}

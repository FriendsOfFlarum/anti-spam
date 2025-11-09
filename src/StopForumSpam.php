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
    public function __construct(protected SettingsRepositoryInterface $settings, protected Dispatcher $bus, protected SfsClient $client)
    {
    }

    public function isEnabled(): bool
    {
        $key = $this->settings->get(SfsClient::KEY);

        return $key !== null && ! empty($key);
    }

    /**
     * Validates against the StopForumSpam API. Returns a simple boolean indicating if based on the current
     * extension settings this registration should be prevented or not.
     *
     * @return bool
     */
    public function shouldPreventRegistration(?string $ip, ?string $email, ?string $username, ?string $provider = null, ?array $providerData = null): bool
    {
        // If we don't have sfs lookup enabled, we return false early.
        if (! (bool) $this->settings->get('fof-anti-spam.sfs-lookup')) {
            return false;
        }

        $sfsResponse = $this->client->check($ip, $email, $username);

        if ($sfsResponse->success) {
            // Get frequency threshold (combined total across all enabled fields)
            // Default to 5 if not set or invalid
            $requiredFrequency = (int) $this->settings->get('fof-anti-spam.frequency');
            if ($requiredFrequency <= 0) {
                $requiredFrequency = 5;
            }

            // Get confidence threshold (percentage: 0-100)
            // Default to 50.0 if not set or invalid
            $requiredConfidence = (float) $this->settings->get('fof-anti-spam.confidence');
            if ($requiredConfidence <= 0.0) {
                $requiredConfidence = 50.0;
            }
            // Initialize tracking variables for spam indicators
            $frequency = 0;      // Total reports across all enabled fields (cumulative)
            $confidence = 0.0;   // Highest confidence score across all fields (not cumulative)
            $blacklisted = false;
            $isTorExit = false;

            /** @var array<string, \FoF\AntiSpam\Api\BasicFieldData|null> $fieldsToCheck */
            $fieldsToCheck = ['ip' => $sfsResponse->ip, 'email' => $sfsResponse->email, 'username' => $sfsResponse->username];

            // Check each enabled field and accumulate spam indicators
            foreach ($fieldsToCheck as $key => $value) {
                if ($value === null || ! (bool) $this->settings->get("fof-anti-spam.$key")) {
                    continue;
                }

                if ($value->blacklisted) {
                    $blacklisted = true;
                }

                // Frequency: sum across fields (e.g., IP:50 + email:50 = total:100)
                $frequency += $value->frequency ?? 0;

                // Confidence: use max, not sum (if ANY field has high confidence, that's significant)
                $confidence = max($confidence, $value->confidence ?? 0.0);
            }

            // Check for Tor exit node if enabled and IP data exists
            if ($sfsResponse->ip !== null && (bool) $this->settings->get('fof-anti-spam.blockTorExitNodes')) {
                $isTorExit = $sfsResponse->ip->torexit ?? false;
            }

            // Block registration if ANY of these conditions are met (OR logic):
            // 1. Confidence score meets/exceeds threshold (highest confidence from any field)
            // 2. Frequency count meets/exceeds threshold (cumulative across all fields)
            // 3. Any field is blacklisted (absolute block)
            // 4. IP is a Tor exit node (absolute block if feature enabled)
            if ($confidence >= $requiredConfidence || $frequency >= $requiredFrequency || $blacklisted || $isTorExit) {
                $this->buildAndDispatchEvents(['ip' => $ip, 'email' => $email, 'username' => $username], json_encode($sfsResponse), $provider, $providerData);

                return true;
            }
        }

        return false;
    }

    private function buildAndDispatchEvents(array $data, string $sfsData, ?string $provider = null, ?array $providerData = null): void
    {
        $ip = Arr::get($data, 'ip') ?? 'unknown';
        $email = Arr::get($data, 'email') ?? 'unknown';
        $username = Arr::get($data, 'username') ?? 'unknown';

        // If there's a password in the provider data, we remove it from the data we send to the event.
        if ($providerData !== null) {
            Arr::pull($providerData, 'password');
        }

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
    }
}

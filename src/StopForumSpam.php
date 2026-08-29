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

use Carbon\Carbon;
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\AntiSpam\Api\BasicFieldData;
use FoF\AntiSpam\Api\SfsClient;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;

class StopForumSpam
{
    public function __construct(protected SettingsRepositoryInterface $settings, protected Dispatcher $bus, protected SfsClient $client)
    {
    }

    /**
     * Whether this forum can submit spammers back to StopForumSpam.
     *
     * Only submissions need an API key. Lookups — listings, blacklists, toxic domain and network
     * wildcards, Tor detection, confidence scoring — all work without one, and are governed by the
     * separate `sfs-lookup` setting. A forum with no key is still fully protected; it just cannot
     * give anything back.
     */
    public function canReport(): bool
    {
        $key = $this->settings->get(SfsClient::KEY);

        return $key !== null && ! empty($key);
    }

    /**
     * @deprecated Misleading name — it never meant "StopForumSpam is switched on". Use canReport().
     */
    public function isEnabled(): bool
    {
        return $this->canReport();
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
            $maxListingAgeDays = (int) $this->settings->get('fof-anti-spam.maxListingAgeDays');

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

                // A sighting from years ago says little about whoever is registering today. A
                // blacklisting is exempt: it describes a domain, username or network that only
                // ever exists to abuse, and StopForumSpam restamps those with the current time.
                if (! $value->blacklisted && $this->listingIsStale($value, $maxListingAgeDays)) {
                    continue;
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

            // The ASN comes back on every lookup, including for addresses the database has never
            // seen, so it says something about traffic no amount of reporting would catch: almost
            // nobody browses a forum from a hosting provider's network.
            $isDeniedAsn = $sfsResponse->ip !== null && $this->asnIsDenied($sfsResponse->ip->asn);

            // Block registration if ANY of these conditions are met (OR logic):
            // 1. Confidence score meets/exceeds threshold (highest confidence from any field)
            // 2. Frequency count meets/exceeds threshold (cumulative across all fields)
            // 3. Any field is blacklisted (absolute block)
            // 4. IP is a Tor exit node (absolute block if feature enabled)
            // 5. IP sits on an ASN the admin has denied (absolute block, opt in)
            if ($confidence >= $requiredConfidence || $frequency >= $requiredFrequency || $blacklisted || $isTorExit || $isDeniedAsn) {
                $this->buildAndDispatchEvents(['ip' => $ip, 'email' => $email, 'username' => $username], json_encode($sfsResponse), $provider, $providerData);

                return true;
            }
        }

        return false;
    }

    /**
     * Whether a field was last reported longer ago than the admin is willing to act on.
     */
    private function listingIsStale(BasicFieldData $value, int $maxListingAgeDays): bool
    {
        if ($maxListingAgeDays <= 0 || $value->lastseen === null) {
            return false;
        }

        try {
            $lastSeen = Carbon::parse($value->lastseen);
        } catch (\Throwable $e) {
            // An unparseable date is not grounds to discard a listing.
            return false;
        }

        return $lastSeen->lt(Carbon::now()->subDays($maxListingAgeDays));
    }

    /**
     * Whether the address sits on an ASN the admin has denied.
     *
     * Accepts the two ways operators write them, `31272` and `AS31272`, separated by commas,
     * newlines or spaces.
     */
    private function asnIsDenied(?int $asn): bool
    {
        if ($asn === null) {
            return false;
        }

        $configured = (string) $this->settings->get('fof-anti-spam.blockedAsns');

        if (trim($configured) === '') {
            return false;
        }

        foreach (preg_split('/[\s,]+/', $configured) ?: [] as $entry) {
            $entry = ltrim(trim($entry), 'ASas');

            if ($entry === '' || ! ctype_digit($entry)) {
                continue;
            }

            if ((int) $entry === $asn) {
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

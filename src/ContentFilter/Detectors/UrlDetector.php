<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\ContentFilter\Detectors;

use Flarum\User\User;
use FoF\AntiSpam\ContentFilter\AbstractDetector;
use FoF\AntiSpam\ContentFilter\SpamScore;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;

/**
 * Detects URLs in content with domain allowlist
 *
 * URLs from non-allowlisted domains are flagged as potential spam
 */
class UrlDetector extends AbstractDetector
{
    /**
     * Regex pattern for detecting URLs
     */
    private const URL_PATTERN = '~(?<uri>(\w+)://(?<domain>[-\w.]+))~';

    public function analyze(string $content, User $user, array $context = []): SpamScore
    {
        // Skip if detector is disabled
        if (! $this->isEnabled()) {
            return new SpamScore();
        }

        // Skip if this setting is disabled
        if (! $this->config->get('detect_urls', true)) {
            return new SpamScore();
        }

        // Only check fresh users
        if (! $this->shouldMonitorUser($user)) {
            return new SpamScore();
        }

        // Strip formatting
        $cleanContent = $this->stripFormatting($content);

        // Detect URLs
        $matches = [];
        $count = preg_match_all(self::URL_PATTERN, $cleanContent, $matches);

        if ($count === false || $count === 0) {
            return new SpamScore();
        }

        // Get allowed domains
        $allowedDomains = $this->config->getAllowedDomains();
        $domainCallbacks = $this->config->getDomainCallbacks();

        // Filter URLs by allowlist
        $flaggedUrls = [];
        $allowedUrls = [];

        foreach ($matches['uri'] as $url) {
            try {
                $uri = new Uri($url);

                if ($this->isUrlAllowed($uri, $user, $allowedDomains, $domainCallbacks)) {
                    $allowedUrls[] = $url;
                } else {
                    $flaggedUrls[] = $url;
                }
            } catch (\Throwable $e) {
                // Invalid URL, flag it
                $flaggedUrls[] = $url;
            }
        }

        // No flagged URLs means all are allowed
        if (empty($flaggedUrls)) {
            return new SpamScore();
        }

        // Calculate score based on number of flagged URLs
        $flaggedCount = count($flaggedUrls);
        $score = min(60, $flaggedCount * 30); // Cap at 60 points

        $reasons = [];
        if ($flaggedCount === 1) {
            $reasons[] = 'Contains a URL to a non-allowlisted domain';
        } else {
            $reasons[] = "Contains {$flaggedCount} URLs to non-allowlisted domains";
        }

        return new SpamScore(
            score: $score,
            reasons: $reasons,
            metadata: [
                'detector' => 'url',
                'total_urls' => $count,
                'flagged_urls' => array_values(array_unique($flaggedUrls)),
                'allowed_urls' => array_values(array_unique($allowedUrls)),
            ]
        );
    }

    /**
     * Check if a URL is allowed based on domain allowlist and callbacks
     */
    private function isUrlAllowed(
        UriInterface $uri,
        User $user,
        array $allowedDomains,
        array $domainCallbacks
    ): bool {
        $host = strtolower($uri->getHost());

        // Check against allowlist
        foreach ($allowedDomains as $allowedDomain) {
            // Exact match or subdomain match
            if ($host === $allowedDomain || str_ends_with($host, '.'.$allowedDomain)) {
                return true;
            }
        }

        // Check custom callbacks
        foreach ($domainCallbacks as $callback) {
            try {
                if (call_user_func($callback, $uri, $user) === true) {
                    return true;
                }
            } catch (\Throwable $e) {
                // Callback failed, continue checking
                continue;
            }
        }

        return false;
    }

    public function getName(): string
    {
        return 'URL Detector';
    }

    public function getDescription(): string
    {
        return 'Detects URLs to non-allowlisted domains';
    }
}

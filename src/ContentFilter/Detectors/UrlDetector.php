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
 * Detects URLs in content with domain allowlist.
 *
 * URLs from non-allowlisted domains are flagged as potential spam
 */
class UrlDetector extends AbstractDetector
{
    /**
     * Regex for detecting URLs. `__TLDS__` is filled in from self::TLDS by self::pattern().
     *
     * A scheme is not required: dropping it costs a spammer nothing, so `www.example.com`,
     * `//example.com` and a bare `example.com` all have to count. The leading lookbehind keeps a
     * match from starting mid-token, which is also what stops an email address from being counted
     * here as well as by the EmailDetector.
     */
    private const URL_PATTERN = '~
        (?<! [\w@.\-] )
        (?:
            [a-z][a-z0-9+.\-]* :// [-\w]+ (?: \. [-\w]+ )*      # scheme://host
          | // [-\w]+ (?: \. [-\w]+ )*                          # protocol-relative //host
          | www \. [-\w]+ (?: \. [-\w]+ )*                      # www.host
          | (?: [a-z0-9] [-a-z0-9]* \. )+ (?: __TLDS__ ) (?! [-\w] )   # bare host.tld
        )
    ~ixu';

    /**
     * TLDs a bare, schemeless hostname is allowed to end in.
     *
     * A generic `[a-z]{2,}` rule would read `README.md`, `extend.php` or `main.rs` as links and
     * hold an ordinary question for approval, so bare hostnames are matched against a list
     * instead. Deliberately left out:
     *
     *  - TLDs that collide with source-file extensions (`md`, `sh`, `py`, `rs`, `so`, `zip`,
     *    `mov`). They are real, but on a forum about software `README.md` and `install.sh` come up
     *    constantly and spam almost never uses them.
     *  - New gTLDs that are ordinary English words (`app`, `info`, `name`, `link`, `click`,
     *    `style`, `support`, `news`, …). Each one turns a property access or a method call —
     *    `this.app`, `user.name`, `el.style`, `logger.info(…)` — into a "spam link".
     *
     * What is left is the classic gTLDs, the ccTLDs, and the new gTLDs spam actually leans on,
     * which between them cover the overwhelming majority of real spam domains. A false positive
     * costs a legitimate user far more than a missed bare hostname costs us.
     *
     * None of this limits detection of an explicit link: a URL carrying a scheme, `//` or `www.`
     * is matched whatever it ends in.
     *
     * @var array<string>
     */
    private const TLDS = [
        // Classic gTLDs
        'com', 'org', 'net', 'edu', 'gov', 'mil', 'int', 'biz', 'mobi', 'asia', 'travel', 'jobs',
        'coop', 'aero', 'museum', 'xxx',

        // New gTLDs disproportionately used by spam
        'top', 'xyz', 'icu', 'vip', 'buzz', 'cyou', 'sbs', 'cfd', 'bond', 'gdn', 'wtf', 'lol',
        'mom', 'monster', 'quest', 'online', 'site', 'website', 'space', 'store', 'shop', 'club',
        'cloud',

        // ccTLDs
        'ac', 'ad', 'ae', 'af', 'ag', 'ai', 'al', 'am', 'ao', 'ar', 'as', 'at', 'au', 'aw', 'ax',
        'az', 'ba', 'bb', 'bd', 'be', 'bf', 'bg', 'bh', 'bi', 'bj', 'bm', 'bn', 'bo', 'br', 'bs',
        'bt', 'bw', 'by', 'bz', 'ca', 'cd', 'cf', 'cg', 'ch', 'ci', 'ck', 'cl', 'cm', 'cn', 'co',
        'cr', 'cu', 'cv', 'cw', 'cy', 'cz', 'de', 'dj', 'dk', 'dm', 'do', 'dz', 'ec', 'ee', 'eg',
        'es', 'et', 'eu', 'fi', 'fj', 'fk', 'fm', 'fo', 'fr', 'ga', 'gd', 'ge', 'gf', 'gg', 'gh',
        'gi', 'gl', 'gm', 'gn', 'gp', 'gq', 'gr', 'gs', 'gt', 'gu', 'gw', 'gy', 'hk', 'hn', 'hr',
        'ht', 'hu', 'id', 'ie', 'il', 'im', 'in', 'io', 'iq', 'ir', 'is', 'it', 'je', 'jm', 'jo',
        'jp', 'ke', 'kg', 'kh', 'ki', 'km', 'kn', 'kr', 'kw', 'ky', 'kz', 'la', 'lb', 'lc', 'li',
        'lk', 'lr', 'ls', 'lt', 'lu', 'lv', 'ly', 'ma', 'mc', 'me', 'mg', 'mh', 'mk', 'ml', 'mm',
        'mn', 'mo', 'mp', 'mq', 'mr', 'ms', 'mt', 'mu', 'mv', 'mw', 'mx', 'my', 'mz', 'na', 'nc',
        'ne', 'nf', 'ng', 'ni', 'nl', 'no', 'np', 'nr', 'nu', 'nz', 'om', 'pa', 'pe', 'pf', 'pg',
        'ph', 'pk', 'pl', 'pm', 'pn', 'pr', 'ps', 'pt', 'pw', 'qa', 're', 'ro', 'ru', 'rw', 'sa',
        'sb', 'sc', 'sd', 'se', 'sg', 'si', 'sk', 'sl', 'sm', 'sn', 'sr', 'st', 'su', 'sv', 'sx',
        'sy', 'sz', 'tc', 'td', 'tf', 'tg', 'th', 'tj', 'tk', 'tl', 'tm', 'tn', 'to', 'tr', 'tt',
        'tv', 'tw', 'tz', 'ua', 'ug', 'uk', 'us', 'uy', 'uz', 'va', 'vc', 've', 'vg', 'vi', 'vn',
        'vu', 'wf', 'ws', 'ye', 'yt', 'za', 'zm', 'zw',
    ];

    private static ?string $pattern = null;

    /**
     * Build the URL regex, once per process.
     */
    private static function pattern(): string
    {
        return self::$pattern ??= str_replace('__TLDS__', implode('|', self::TLDS), self::URL_PATTERN);
    }

    /**
     * Extract every URL-shaped token from content that has already had its formatting stripped.
     *
     * @return array<string> The matched tokens, in the order they appear.
     */
    public static function extractUrls(string $content): array
    {
        if (! preg_match_all(self::pattern(), $content, $matches)) {
            return [];
        }

        return $matches[0];
    }

    /**
     * Give a matched token a scheme so it can be parsed as a URI.
     */
    private static function normalizeUrl(string $url): string
    {
        if (str_starts_with($url, '//')) {
            return 'http:'.$url;
        }

        if (preg_match('~^[a-z][a-z0-9+.\-]*://~i', $url)) {
            return $url;
        }

        return 'http://'.$url;
    }

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
        $urls = self::extractUrls($cleanContent);
        $count = count($urls);

        if ($count === 0) {
            return new SpamScore();
        }

        // Get allowed domains
        $allowedDomains = $this->config->getAllowedDomains();
        $domainCallbacks = $this->config->getDomainCallbacks();

        // Filter URLs by allowlist
        $flaggedUrls = [];
        $allowedUrls = [];

        foreach ($urls as $url) {
            try {
                $uri = new Uri(self::normalizeUrl($url));

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
        $score = min(80, $flaggedCount * 50); // 50 points per URL, cap at 80

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
     * Check if a URL is allowed based on domain allowlist and callbacks.
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

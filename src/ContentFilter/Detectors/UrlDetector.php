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
    protected const string URL_PATTERN = '~
        (?<! [\w@.\-] )
        (?:
            [a-z][a-z0-9+.\-]* :// [-\w]++ (?: \. [-\w]++ )*+      # scheme://host
          | // [-\w]++ (?: \. [-\w]++ )*+                          # protocol-relative //host
          | www \. [-\w]++ (?: \. [-\w]++ )*+                      # www.host
          | LABELS (?: __TLDS__ ) (?! [-\w] | \(\) )                # bare host.tld, not foo.top()
          | LABELS (?: __PATH_TLDS__ ) (?= / [-\w%] )                # t.me/… : a word-like TLD, with a path
        )
    ~ixu';

    /**
     * Hostname labels. Possessive, so a long dotted run is read once rather than exhausting the
     * regex engine's stack; a label only repeats when another follows its dot, so a sentence's
     * full stop is not taken for one.
     */
    protected const string LABELS = '(?: [a-z0-9] [-a-z0-9]*+ \. (?= [a-z0-9] ) )++';

    /**
     * Country codes left out of TLDS because they are English words or identifiers, still counted
     * once a path follows: `t.me/…`, `wa.me/…`, `youtu.be/…` and `amzn.to/…` are the short hosts
     * spam leans on most, and `user.id` or `that.It` never carry a path.
     *
     * @var array<string>
     */
    protected const array PATH_TLDS = [
        'ai', 'am', 'as', 'at', 'be', 'by', 'do', 'id', 'im', 'in', 'is', 'it', 'me', 'my', 'no',
        'pl', 'pm', 'to', 'us',
    ];

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
     *  - ccTLDs that are English words or common identifiers (`it`, `in`, `is`, `to`, `me`, `id`,
     *    `at`, …). A sentence missing its space after a full stop — `that.It`, `done.it` — and a
     *    property such as `user.id` or `arr.at` would otherwise read as links, as would Perl's
     *    `.pl` and `.pm` files and Illustrator's `.ai`.
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
    protected const array TLDS = [
        // Classic gTLDs
        'com', 'org', 'net', 'edu', 'gov', 'mil', 'int', 'biz', 'mobi', 'asia', 'travel', 'jobs',
        'coop', 'aero', 'museum', 'xxx',

        // New gTLDs disproportionately used by spam
        'top', 'xyz', 'icu', 'vip', 'buzz', 'cyou', 'sbs', 'cfd', 'bond', 'gdn', 'wtf', 'lol',
        'mom', 'monster', 'quest', 'online', 'site', 'website', 'space', 'store', 'shop', 'club',
        'cloud',

        // ccTLDs
        'ac', 'ad', 'ae', 'af', 'ag', 'al', 'ao', 'ar', 'au', 'aw', 'ax', 'az', 'ba', 'bb', 'bd',
        'bf', 'bg', 'bh', 'bi', 'bj', 'bm', 'bn', 'bo', 'br', 'bs', 'bt', 'bw', 'bz', 'ca', 'cd',
        'cf', 'cg', 'ch', 'ci', 'ck', 'cl', 'cm', 'cn', 'co', 'cr', 'cu', 'cv', 'cw', 'cy', 'cz',
        'de', 'dj', 'dk', 'dm', 'dz', 'ec', 'ee', 'eg', 'es', 'et', 'eu', 'fi', 'fj', 'fk', 'fm',
        'fo', 'fr', 'ga', 'gd', 'ge', 'gf', 'gg', 'gh', 'gi', 'gl', 'gm', 'gn', 'gp', 'gq', 'gr',
        'gs', 'gt', 'gu', 'gw', 'gy', 'hk', 'hn', 'hr', 'ht', 'hu', 'ie', 'il', 'io', 'iq', 'ir',
        'je', 'jm', 'jo', 'jp', 'ke', 'kg', 'kh', 'ki', 'km', 'kn', 'kr', 'kw', 'ky', 'kz', 'la',
        'lb', 'lc', 'li', 'lk', 'lr', 'ls', 'lt', 'lu', 'lv', 'ly', 'ma', 'mc', 'mg', 'mh', 'mk',
        'ml', 'mm', 'mn', 'mo', 'mp', 'mq', 'mr', 'ms', 'mt', 'mu', 'mv', 'mw', 'mx', 'mz', 'na',
        'nc', 'ne', 'nf', 'ng', 'ni', 'nl', 'np', 'nr', 'nu', 'nz', 'om', 'pa', 'pe', 'pf', 'pg',
        'ph', 'pk', 'pn', 'pr', 'ps', 'pt', 'pw', 'qa', 're', 'ro', 'ru', 'rw', 'sa', 'sb', 'sc',
        'sd', 'se', 'sg', 'si', 'sk', 'sl', 'sm', 'sn', 'sr', 'st', 'su', 'sv', 'sx', 'sy', 'sz',
        'tc', 'td', 'tf', 'tg', 'th', 'tj', 'tk', 'tl', 'tm', 'tn', 'tr', 'tt', 'tv', 'tw', 'tz',
        'ua', 'ug', 'uk', 'uy', 'uz', 'va', 'vc', 've', 'vg', 'vi', 'vn', 'vu', 'wf', 'ws', 'ye',
        'yt', 'za', 'zm', 'zw',
    ];

    protected const int CLICKABLE_SCORE = 50;

    protected const int BARE_SCORE = 30;

    private static ?string $pattern = null;

    /**
     * Whether a matched token renders as a link: it carries a scheme, `//` or `www.`.
     */
    protected static function isClickable(string $url): bool
    {
        return (bool) preg_match('~^(?:[a-z][a-z0-9+.\-]*://|//|www\.)~i', $url);
    }

    /**
     * Build the URL regex, once per process.
     */
    protected static function pattern(): string
    {
        return self::$pattern ??= str_replace(
            ['LABELS', '__PATH_TLDS__', '__TLDS__'],
            [self::LABELS, implode('|', self::PATH_TLDS), implode('|', self::TLDS)],
            self::URL_PATTERN
        );
    }

    /**
     * Extract every URL-shaped token from content that has already had its formatting stripped.
     *
     * @return array<string> The matched tokens, in the order they appear.
     */
    public static function extractUrls(string $content): array
    {
        $found = preg_match_all(self::pattern(), $content, $matches);

        if ($found === false) {
            throw new \RuntimeException('URL pattern failed: '.preg_last_error_msg());
        }

        return $matches[0];
    }

    /**
     * Give a matched token a scheme so it can be parsed as a URI.
     */
    protected static function normalizeUrl(string $url): string
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
        if (! $this->config->get('detect_urls')) {
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

        $flaggedCount = count($flaggedUrls);
        $explicit = count(array_filter($flaggedUrls, [self::class, 'isClickable']));

        // A bare hostname cannot be clicked, so in a post one on its own lands between the flag
        // and spam thresholds: in front of a moderator, without the post being taken down.
        // Anywhere else it counts in full. A profile field can only be refused, and a title or a
        // display name is shown on every list, where a domain is the payload either way.
        $bareScore = ($context['type'] ?? null) === 'post' ? self::BARE_SCORE : self::CLICKABLE_SCORE;
        $score = min(80, $explicit * self::CLICKABLE_SCORE + ($flaggedCount - $explicit) * $bareScore);

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
    protected function isUrlAllowed(
        UriInterface $uri,
        User $user,
        array $allowedDomains,
        array $domainCallbacks
    ): bool {
        $host = strtolower($uri->getHost());

        if (self::isLocalOrReserved($host)) {
            return true;
        }

        if (self::hostIsAllowed($host, $allowedDomains)) {
            return true;
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

    /**
     * Whether a host is an allowed domain or one of its subdomains.
     *
     * @param array<string> $allowedDomains
     */
    public static function hostIsAllowed(string $host, array $allowedDomains): bool
    {
        foreach ($allowedDomains as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hosts that cannot send a reader anywhere: their own machine, a private network, or a name
     * reserved for examples (RFC 2606 and RFC 6761). Developers paste these constantly.
     */
    public static function isLocalOrReserved(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
        }

        foreach (['example.com', 'example.net', 'example.org'] as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        $tld = substr((string) strrchr('.'.$host, '.'), 1);

        return in_array($tld, ['localhost', 'test', 'example', 'invalid'], true);
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

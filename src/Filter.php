<?php

namespace FoF\AntiSpam;

use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Flarum\Foundation\Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;

class Filter implements ExtenderInterface
{
    public static array $acceptableDomains = [];
    public static int $userPostCount = 1;
    public static int $userAge = 1;
    public static ?int $moderatorUserId = null;
    /** @var array|callable[] */
    public static array $allowLinksCallables = [];
    /** @var array|string[] */
    public static array $disabled = [];

    public function allowLinksFromDomain(string $domain): self
    {
        static::$acceptableDomains[] = static::parseDomain($domain);

        return $this;
    }

    protected static function parseDomain(string $domain): string
    {
        $scheme = parse_url($domain, PHP_URL_SCHEME) ?? 'http://';

        $domain = $scheme . Str::after($domain, $scheme);

        return parse_url($domain, PHP_URL_HOST);
    }

    public function allowLinksFromDomains(array $domains): self
    {
        foreach ($domains as $domain) {
            $this->allowLinksFromDomain($domain);
        }

        return $this;
    }

    public function allowLink(callable $callable): self
    {
        static::$allowLinksCallables[] = $callable;

        return $this;
    }

    public function checkForUserUpToPostContribution(int $posts = 1): self
    {
        static::$userPostCount = $posts;

        return $this;
    }

    public function checkForUserUpToHoursSinceSignUp(int $hours = 1): self
    {
        static::$userAge = $hours;

        return $this;
    }

    public function moderateAsUser(int $userId): self
    {
        static::$moderatorUserId = $userId;

        return $this;
    }

    public function extend(Container $container, Extension $extension = null)
    {
        // ..
    }

    public static function getAcceptableDomains(): array
    {
        /** @var Config $config */
        $config = resolve(Config::class);

        $domains = array_merge(static::$acceptableDomains, [
            $config->url()->getHost()
        ]);

        return array_filter($domains);
    }

    public function disable(string $class): self
    {
        static::$disabled[] = $class;

        return $this;
    }
}

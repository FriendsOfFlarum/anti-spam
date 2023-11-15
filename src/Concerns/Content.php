<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Concerns;

use Flarum\Locale\LocaleManager;
use Flarum\User\User;
use FoF\AntiSpam\Filter;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laminas\Diactoros\Uri;
use LanguageDetection\Language;

trait Content
{
    public function containsProblematicContent(string $content, User $actor = null): bool
    {
        return $this->containsProblematicLinks($content, $actor)
            || $this->containsAlternateLanguage($content);
    }

    public function containsProblematicLinks(string $content, User $actor = null): bool
    {
        $domains = Filter::getAcceptableDomains();

        // First check for links.
        preg_match_all('~(?<uri>(\w+)://(?<domain>[-\w.]+))~', $content, $links);

        foreach (array_filter($links['domain']) as $link) {
            $uri = (new Uri("http://$link"));
            $host = $uri->getHost();

            // If custom callable allows it.
            foreach (Filter::$allowLinksCallables as $callable) {
                if ($callable($uri, $actor) === true) {
                    continue 2;
                }
            }

            // Match exact domain or subdomains.
            if (Arr::first($domains, fn ($domain) => $domain === $host || Str::endsWith($host, ".$domain"))) {
                continue;
            }

            return true;
        }

        return
            // phone
            preg_match('~(\+|00)[0-9 ]{9,}~', $content) ||
            // email
            preg_match('~[\S]+@[\S]+\.[\S]+~', $content);
    }

    public function containsAlternateLanguage(string $content): bool
    {
        // strip links
        $content = preg_replace('~[\S]+@[\S]+\.[\S]+~', '', $content);
        $content = preg_replace('~https?:\/\/([-\w.]+)~', '', $content);
        $content = preg_replace('~(\+|00)[0-9 ]{9,}~', '', $content);

        // Let's not do language analysis on short strings.
        if (mb_strlen($content) < 10) {
            return false;
        }

        /** @var LocaleManager $locales */
        $locales = resolve(LocaleManager::class);

        $locales = array_keys($locales->getLocales());

        $languageDetection = (new Language)->detect($content);

        return ! in_array((string) $languageDetection, $locales);
    }
}

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

use Flarum\Api\Resource\ForumResource;
use Flarum\Api\Resource\UserResource;
use Flarum\Audit\Extend\Audit;
use Flarum\Extend;
use Flarum\User\User;
use FoF\AntiSpam\Event\MarkedUserAsSpammer;
use FoF\AntiSpam\Event\RegistrationWasBlocked;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),

    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Routes('api'))
        ->post('/users/{id}/spamblock', 'users.spamblock', Api\Controllers\MarkAsSpammerController::class),

    (new Extend\ApiResource(ForumResource::class))
        ->fields(Api\AddForumFields::class),

    (new Extend\ApiResource(UserResource::class))
        ->fields(Api\AddUserPermissions::class),

    (new Extend\Policy())
        ->modelPolicy(User::class, Access\UserPolicy::class),

    // Registration is checked on the API stack: the forum's /register route only proxies to the
    // users.create endpoint, so hooking the API covers both it and direct API registration.
    (new Extend\Middleware('api'))
        ->add(Middleware\CheckRegistrationMiddleware::class),

    (new Extend\Settings())
        ->default('fof-anti-spam.regionalEndpoint', 'closest')
        ->default('fof-anti-spam.sfs-lookup', true)
        ->default('fof-anti-spam.username', false)
        ->default('fof-anti-spam.ip', true)
        ->default('fof-anti-spam.email', true)
        ->default('fof-anti-spam.emailhash', false)
        ->default('fof-anti-spam.frequency', 5)
        ->default('fof-anti-spam.confidence', 70.0)
        ->default('fof-anti-spam.blockTorExitNodes', false)
        ->default('fof-anti-spam.actions.deleteUser', false)
        ->default('fof-anti-spam.actions.deletePosts', false)
        ->default('fof-anti-spam.actions.deleteDiscussions', false)
        ->default('fof-anti-spam.reportToStopForumSpam', true)
        ->default('fof-anti-spam.report_blocked_registrations', true)
        // Content filter defaults
        ->default('fof-anti-spam.moderation.system_user_id', 1)
        ->default('fof-anti-spam.content-filter.enabled', true)
        ->default('fof-anti-spam.content-filter.monitor_post_count', 5)
        ->default('fof-anti-spam.content-filter.monitor_hours_old', 24)
        ->default('fof-anti-spam.content-filter.detect_phones', true)
        ->default('fof-anti-spam.content-filter.detect_emails', true)
        ->default('fof-anti-spam.content-filter.detect_urls', true)
        ->default('fof-anti-spam.content-filter.spam_threshold', 50)
        // Deliberately below spam_threshold: scores in between are flagged for a moderator to
        // look at without being hidden, which is the only way a weak signal can be useful.
        ->default('fof-anti-spam.content-filter.flag_threshold', 30)
        ->default('fof-anti-spam.content-filter.auto_unapprove', true)
        ->default('fof-anti-spam.content-filter.auto_flag', true)
        ->default('fof-anti-spam.content-filter.allowed_domains', '')
        ->default('fof-anti-spam.content-filter.blocked_words', '')
        ->default('fof-anti-spam.content-filter.advanced_block_patterns', '[]'),

    (new Extend\ServiceProvider())
        ->register(Providers\SfsProvider::class)
        ->register(Providers\ContentFilterProvider::class),

    (new Extend\Event())
        ->subscribe(Listener\CheckPostContent::class)
        ->subscribe(Listener\CheckDiscussionContent::class),

    new Extend\ApiResource(Api\Resource\BlockedRegistrationResource::class),

    (new Extend\Conditional())
        ->whenExtensionEnabled('flarum-audit', fn () => [
            (new Audit())
                ->listen(MarkedUserAsSpammer::class, 'user.marked_as_spammer', fn (MarkedUserAsSpammer $e) => [
                    'user_id' => $e->user->id,
                ])
                ->listen(RegistrationWasBlocked::class, 'registration.blocked', fn (RegistrationWasBlocked $e) => [
                    'ip' => $e->blocked->ip,
                    'email' => $e->blocked->email,
                    'username' => $e->blocked->username,
                ]),
        ]),
];

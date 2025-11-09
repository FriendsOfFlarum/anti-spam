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
use Flarum\Extend;
use Flarum\User\User;

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

    (new Extend\Middleware('forum'))
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
        ->default('fof-anti-spam.actions.deleteUser', false)
        ->default('fof-anti-spam.actions.deletePosts', false)
        ->default('fof-anti-spam.actions.deleteDiscussions', false)
        ->default('fof-anti-spam.reportToStopForumSpam', true)
        ->default('fof-anti-spam.report_blocked_registrations', true),

    (new Extend\ServiceProvider())
        ->register(Providers\SfsProvider::class),

    new Extend\ApiResource(Api\Resource\BlockedRegistrationResource::class),
];

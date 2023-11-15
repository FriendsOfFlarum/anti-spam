<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) 2023 FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam;

use Flarum\Api\Serializer\UserSerializer;
use Flarum\Extend;
use Flarum\User\Event\RegisteringFromProvider;
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

    (new Extend\ApiSerializer(UserSerializer::class))
        ->attributes(Api\AddUserPermissions::class),

    (new Extend\Policy())
        ->modelPolicy(User::class, Access\UserPolicy::class),

    (new Extend\Middleware('forum'))
        ->add(Middleware\CheckLoginMiddleware::class),

    (new Extend\Event())
        ->listen(Event\MarkedUserAsSpammer::class, Listener\ReportSpammer::class)
        ->listen(RegisteringFromProvider::class, Listener\ProviderRegistration::class)
        ->subscribe(Filters\CommentPost::class)
        ->subscribe(Filters\Discussion::class)
        ->subscribe(Filters\UserBio::class),

    (new Extend\Settings())
        ->default('fof-anti-spam.regionalEndpoint', 'closest')
        ->default('fof-anti-spam.emailhash', false)
        ->default('fof-anti-spam.frequency', 5)
        ->default('fof-anti-spam.confidence', 50.0),
];

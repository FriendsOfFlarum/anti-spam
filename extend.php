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

use Flarum\Api\Serializer\ForumSerializer;
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
        ->post('/users/{id}/spamblock', 'users.spamblock', Api\Controllers\MarkAsSpammerController::class)
        ->get('/blocked-registrations', 'fof-anti-spam.blocked-registrations.index', Api\Controllers\ListBlockedRegistrationsController::class)
        ->delete('/blocked-registrations/{id}', 'fof-anti-spam.blocked-registrations.delete', Api\Controllers\DeleteBlockedRegistrationController::class),

    (new Extend\ApiSerializer(ForumSerializer::class))
        ->attributes(Api\AddForumAttributes::class),

    (new Extend\ApiSerializer(UserSerializer::class))
        ->attributes(Api\AddUserPermissions::class),

    (new Extend\Policy())
        ->modelPolicy(User::class, Access\UserPolicy::class),

    (new Extend\Middleware('forum'))
        ->add(Middleware\CheckLoginMiddleware::class),

    (new Extend\Event())
        ->listen(RegisteringFromProvider::class, Listener\ProviderRegistration::class),

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
        ->default('fof-anti-spam.reportToStopForumSpam', true),
];

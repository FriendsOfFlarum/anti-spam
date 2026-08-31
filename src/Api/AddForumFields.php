<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\AntiSpam\Search\BlockedRegistrationGambits;
use FoF\AntiSpam\StopForumSpam;

class AddForumFields
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected StopForumSpam $stopForumSpam
    ) {
    }

    public function __invoke(): array
    {
        return [
            // Filter metadata for the blocked registrations list, so its search box can show
            // clickable examples and a help panel rather than expecting an admin to know the
            // syntax. Admin-only: the list lives in the admin panel, and the values name the
            // rules this forum blocks on. Mirrors flarum/audit's auditFilters attribute.
            Schema\Arr::make('fof-anti-spam.filters')
                ->visible(fn (mixed $resource, Context $context) => $context->getActor()->isAdmin())
                ->get(function (mixed $resource, Context $context) {
                    BlockedRegistrationGambits::registerDefaults();

                    return BlockedRegistrationGambits::$filters;
                }),

            Schema\Arr::make('fof-anti-spam')
                ->visible(function (mixed $resource, Context $context) {
                    return $context->getActor()->hasPermission('user.spamblock');
                })
                ->get(function (mixed $resource, Context $context) {
                    $quarantine = $this->settings->get('fof-anti-spam.actions.moveDiscussionsToTags');

                    return [
                        'default-options' => [
                            'deleteUser' => (bool) $this->settings->get('fof-anti-spam.actions.deleteUser'),
                            'deletePosts' => (bool) $this->settings->get('fof-anti-spam.actions.deletePosts'),
                            'deleteDiscussions' => (bool) $this->settings->get('fof-anti-spam.actions.deleteDiscussions'),
                            'spamQuarantine' => ($quarantine === null || $quarantine === '[]') ? false : $quarantine,
                            'reportToSfs' => (bool) $this->settings->get('fof-anti-spam.reportToStopForumSpam'),
                        ],
                        'stopforumspam' => [
                            // Reporting needs an API key; checking never did. They are reported
                            // separately so an admin can tell a quiet forum from a broken one.
                            'canReport' => $this->stopForumSpam->canReport(),
                            'lookupEnabled' => (bool) $this->settings->get('fof-anti-spam.sfs-lookup'),
                            'lookupFailedAt' => $this->settings->get(SfsClient::FAILURE_KEY),

                            // Kept for frontends written against the old payload.
                            'enabled' => $this->stopForumSpam->canReport(),
                        ]
                    ];
                }),
        ];
    }
}

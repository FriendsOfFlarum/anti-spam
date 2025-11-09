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
                            'enabled' => $this->stopForumSpam->isEnabled(),
                        ]
                    ];
                }),

        ];
    }
}

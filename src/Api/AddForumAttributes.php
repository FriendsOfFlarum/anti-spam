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

use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\AntiSpam\StopForumSpam;

class AddForumAttributes
{
    public function __construct(protected SettingsRepositoryInterface $settings, protected StopForumSpam $stopForumSpam)
    {
    }

    public function __invoke(ForumSerializer $serializer, $model, array $attributes): array
    {
        if ($serializer->getActor()->hasPermission('user.spamblock')) {
            $quarantine = $this->settings->get('fof-anti-spam.actions.moveDiscussionsToTags');

            $attributes['fof-anti-spam'] = [
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
        }

        return $attributes;
    }
}

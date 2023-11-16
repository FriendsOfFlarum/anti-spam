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

class AddForumAttributes
{
    protected $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function __invoke(ForumSerializer $serializer, $model, array $attributes): array
    {
        if ($serializer->getActor()->hasPermission('user.spamblock')) {
            $attributes['fof-anti-spam'] = [
                'default-options' => [
                    'deleteUser' => $this->settings->get('fof-anti-spam.actions.deleteUser'),
                    'deletePosts' => $this->settings->get('fof-anti-spam.actions.deletePosts'),
                    'deleteDiscussions' => $this->settings->get('fof-anti-spam.actions.deleteDiscussions'),
                ]
            ];
        }

        return $attributes;
    }
}
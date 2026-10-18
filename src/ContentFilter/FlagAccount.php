<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\ContentFilter;

use Flarum\Group\Group;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;

/**
 * The account automatic spam flags are raised by.
 *
 * An account chosen with assignFlagsToModerator() wins over one chosen in the admin panel. With
 * neither, or when the chosen account no longer exists, it is the first administrator, so a
 * flag always has an author and never needs anyone to know a user ID.
 */
class FlagAccount
{
    public const string SETTING = 'fof-anti-spam.moderation.system_user_id';

    public function __construct(
        protected ConfigurationManager $config,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    /**
     * The chosen account's ID, or null when none was chosen.
     */
    public function chosenId(): ?int
    {
        $id = $this->config->getCodeValue('flag_moderator_id') ?? $this->settings->get(self::SETTING);

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    public function chosenInCode(): bool
    {
        return $this->config->isCodeConfigured('flag_moderator_id');
    }

    public function chosen(): ?User
    {
        $id = $this->chosenId();

        return $id === null ? null : User::find($id);
    }

    public function firstAdmin(): ?User
    {
        return User::query()
            ->whereHas('groups', fn ($groups) => $groups->where('groups.id', Group::ADMINISTRATOR_ID))
            ->orderBy('id')
            ->first();
    }

    public function resolve(): ?User
    {
        return $this->chosen() ?? $this->firstAdmin();
    }
}

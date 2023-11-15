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

use Carbon\Carbon;
use Flarum\Extension\ExtensionManager;
use Flarum\Flags\Flag;
use Flarum\Post\Post;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait Approval
{
    use Users;

    protected function requireApproval(Post $post, string $reason = null)
    {
        /** @var ExtensionManager $extensions */
        $extensions = resolve(ExtensionManager::class);

        if ($extensions->isEnabled('flarum-approval')) {
            /** @phpstan-ignore-next-line */
            $post->is_approved = false;

            $post->afterSave(function (Post $post) use ($reason) {
                $this->unapproveAndFlag($post, $reason);
            });

            return true;
        }

        return false;
    }

    protected function unapproveAndFlag(Post $post, string $reason = null)
    {
        /** @var ExtensionManager $extensions */
        $extensions = resolve(ExtensionManager::class);

        if ($extensions->isEnabled('flarum-approval') && $post->number === 1) {
            /** @phpstan-ignore-next-line */
            $post->discussion->is_approved = false;
            $post->discussion->save();
        }

        if ($extensions->isEnabled('flarum-flags')) {
            /**
             * @var HasMany $flags
             * @phpstan-ignore-next-line
             */
            $flags = $post->flags();

            // Only add the flag once.
            if ($flags->where('reason', 'Blocked by spam prevention')->doesntExist()) {
                $flag = new Flag;

                $flag->post_id = $post->id;
                $flag->type = $extensions->isEnabled('flarum-approval') ? 'approval' : 'user';
                $flag->reason = 'Blocked by spam prevention';
                $flag->reason_detail = $reason;
                $flag->user()->associate($this->getModerator());
                $flag->created_at = Carbon::now();

                $flag->save();
            }
        }

        return true;
    }
}

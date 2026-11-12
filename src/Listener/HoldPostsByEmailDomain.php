<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Listener;

use Carbon\Carbon;
use Flarum\Flags\Flag;
use Flarum\Post\Event\Saving;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\AntiSpam\ContentFilter\Analyzer;
use FoF\AntiSpam\ContentFilter\ConfigurationManager;
use FoF\AntiSpam\ContentFilter\FlagAccount;
use FoF\AntiSpam\ContentFilter\HeldEmailDomains;
use FoF\AntiSpam\ContentFilter\MonitoredUsers;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;

/**
 * Holds every new post from a new account for approval when its email address is on a listed
 * domain, whatever the post says.
 *
 * "New" is the content filter's post-count and age window only: monitoring everyone does not
 * extend it, or an account on a listed domain could never get out. Held posts are private until
 * approved and so do not count towards the post count. It does not depend on the content filter
 * being switched on, because it is a policy about who posts rather than a score of what they post.
 */
class HoldPostsByEmailDomain extends AbstractContentCheck
{
    public const string FLAG_TYPE = 'held_email_domain';

    public function __construct(
        Analyzer $analyzer,
        LoggerInterface $log,
        SettingsRepositoryInterface $settings,
        ConfigurationManager $config,
        Dispatcher $events,
        FlagAccount $flagAccount,
        protected MonitoredUsers $monitoredUsers,
        protected HeldEmailDomains $heldEmailDomains
    ) {
        parent::__construct($analyzer, $log, $settings, $config, $events, $flagAccount);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Saving::class, [$this, 'hold']);
    }

    public function hold(Saving $event): void
    {
        $post = $event->post;
        $actor = $event->actor;

        // New comment posts only. Edits are left to the content filter.
        if ($post->exists || ($post->type !== null && $post->type !== 'comment')) {
            return;
        }

        if ($this->monitoredUsers->isExempt($actor) || ! $this->monitoredUsers->isNew($actor)) {
            return;
        }

        $domain = $this->heldEmailDomains->matching($actor);

        if ($domain === null) {
            return;
        }

        $this->log->info("[FoF Anti Spam] Holding a post by user {$actor->username} (ID: {$actor->id}) for approval: their email domain matches {$domain}");

        $this->unapprove($post);

        // Flagged so it reaches the moderation queue with the reason. No author, like core
        // approval's own flag: nobody raised it.
        $post->afterSave(function (Post $post) use ($domain) {
            $flag = new Flag();
            $flag->type = self::FLAG_TYPE;
            $flag->post_id = $post->id;
            $flag->reason = $domain;
            $flag->created_at = Carbon::now();
            $flag->save();
        });
    }
}

<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Command;

use Carbon\Carbon;
use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\DispatchEventsTrait;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Guest;
use Flarum\User\User;
use FoF\AntiSpam\Event\MarkedUserAsSpammer;
use FoF\AntiSpam\Job\ReportSpammerJob;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;

class MarkUserAsSpammerHandler
{
    use DispatchEventsTrait;

    /**
     * @var bool
     */
    private $deleteUser;

    /**
     * @var bool
     */
    private $deletePosts;

    /**
     * @var bool
     */
    private $deleteDiscussions;

    /**
     * @var bool
     */
    private $moveDiscussionsToQuarantine;

    /**
     * @var bool
     */
    private $reportToSfs;

    const settings_prefix = 'fof-anti-spam.actions.';

    public function __construct(
        public ExtensionManager $extensions,
        public Events $events,
        public SettingsRepositoryInterface $settings,
        public Queue $queue,
        public LoggerInterface $log
    ) {
    }

    public function handle(MarkUserAsSpammer $command): User
    {
        $user = $command->user;
        $actor = $command->actor ?? new Guest();

        $this->parseOptions($command->options);

        $this->reportToStopForumSpam($user);

        $this->handleDiscussions($user, $actor);

        $this->handlePosts($user, $actor);
        $this->handleUser($user);

        $this->events->dispatch(
            new MarkedUserAsSpammer($user, $actor)
        );

        return $this->deleteUser ? new Guest() : $command->user->refresh();
    }

    protected function parseOptions(array $options): void
    {
        $this->deleteUser = (bool) Arr::get($options, 'deleteUser', $this->settings->get(self::settings_prefix.'deleteUser'));
        $this->deletePosts = (bool) Arr::get($options, 'deletePosts', $this->settings->get(self::settings_prefix.'deletePosts'));
        $this->deleteDiscussions = (bool) Arr::get($options, 'deleteDiscussions', $this->settings->get(self::settings_prefix.'deleteDiscussions'));
        $this->moveDiscussionsToQuarantine = (bool) Arr::get($options, 'moveDiscussionsToQuarantine', $this->shouldMoveToQuarantineSetting());
        $this->reportToSfs = (bool) Arr::get($options, 'reportToSfs', $this->settings->get('fof-anti-spam.reportToStopForumSpam'));
    }

    protected function shouldMoveToQuarantineSetting(): bool
    {
        $value = $this->settings->get(self::settings_prefix.'moveDiscussionsToTags');

        return ($value === null || $value === '[]') ? false : true;
    }

    protected function flagsEnabled(): bool
    {
        return $this->extensions->isEnabled('flarum-flags');
    }

    /**
     * Takes the defined actions on the User.
     *
     * @param User $user
     * @return void
     */
    protected function handleUser(User $user): void
    {
        if ($this->deleteUser) {
            // Direct deletion - events fire via Eloquent model
            $user->delete();
        } elseif ($this->extensions->isEnabled('flarum-suspend') && $user->suspended_until === null) {
            // Direct property update - events fire when model is saved
            $user->suspended_until = Carbon::now()->addYears(20);
            $user->save();
        }

        if (! $this->deleteUser) {
            $user->refreshDiscussionCount();
            $user->refreshCommentCount();
        }
    }

    /**
     * Takes the defined actions on the User's Posts.
     *
     * @param User $user
     * @param User $actor
     * @return void
     */
    protected function handlePosts(User $user, User $actor): void
    {
        if ($this->deletePosts) {
            // Delete models individually so Eloquent's `deleted` event fires for each.
            // Core's DiscussionMetadataUpdater listens for Post\Event\Deleted to refresh
            // comment_count, participant_count and last_post_* on the affected discussions.
            // A bulk query-builder delete bypasses these events and leaves stale metadata.
            $this->deleteModels($user->posts(), $actor);
        } else {
            // Bulk hide: use model methods which fire Hidden events
            $flagsEnabled = $this->flagsEnabled();

            $user->posts()->where('hidden_at', null)->get()->each(function (Post $post) use ($actor, $flagsEnabled) {
                // Only hide posts that support the hide() method (e.g., CommentPost)
                // Event posts like DiscussionTaggedPost don't have hide() method
                if (method_exists($post, 'hide')) {
                    /** @var \Flarum\Post\CommentPost $post */
                    $post->hide($actor);
                    $post->save();
                }

                if ($flagsEnabled) {
                    // Flags are deleted via the post relationship
                    $post->flags()->delete();
                }
            });
        }
    }

    /**
     * Takes the defined actions on the User's Discussions.
     *
     * @param User $user
     * @param User $actor
     * @return void
     */
    protected function handleDiscussions(User $user, User $actor): void
    {
        if ($this->deleteDiscussions) {
            // Delete models individually so Eloquent's `deleted` event fires for each,
            // allowing core and extensions (e.g. flarum/tags) to clean up cached metadata.
            $this->deleteModels($user->discussions(), $actor);
        } else {
            // Bulk hide: use model methods which fire Hidden events
            $user->discussions()->where('hidden_at', null)->get()->each(function ($discussion) use ($actor) {
                $discussion->hide($actor);
                $discussion->save();
            });

            if ($this->moveDiscussionsToQuarantine) {
                $this->moveUserDiscussionsToQuarantine($user);
            }
        }
    }

    /**
     * Delete every model on a relation one at a time so Eloquent model events fire,
     * chunking to keep memory bounded for users with large amounts of content.
     *
     * Deleting a model only queues its domain events (e.g. Post\Event\Deleted) via
     * raise(); they must be released through dispatchEventsFor() to reach listeners
     * such as core's DiscussionMetadataUpdater and flarum/audit.
     *
     * @template TRelated of \Illuminate\Database\Eloquent\Model
     * @template TDeclaring of \Illuminate\Database\Eloquent\Model
     *
     * @param HasMany<TRelated, TDeclaring> $relation
     */
    protected function deleteModels(HasMany $relation, User $actor): void
    {
        $relation->chunkById(100, function ($models) use ($actor) {
            foreach ($models as $model) {
                $model->delete();

                $this->dispatchEventsFor($model, $actor);
            }
        });
    }

    protected function moveUserDiscussionsToQuarantine(User $user): void
    {
        if (! $this->moveDiscussionsToQuarantine) {
            return;
        }

        $discussions = $user->discussions;
        $quarantineTagsString = (string) $this->settings->get(self::settings_prefix.'moveDiscussionsToTags');

        $tags = json_decode($quarantineTagsString);

        if (! $tags || ! is_array($tags)) {
            return;
        }

        // Use Eloquent relationship sync which fires tag events
        foreach ($discussions as $discussion) {
            /** @phpstan-ignore-next-line - tags() relationship is added by flarum/tags extension */
            $discussion->tags()->sync($tags);
            $discussion->unsetRelation('tags');
        }
    }

    protected function reportToStopForumSpam(User $user): void
    {
        if (! $this->reportToSfs) {
            return;
        }

        $post = $user->posts()->first();

        // Only report if we have a valid public IP address
        // Don't fall back to a fake IP as it would report an innocent address
        if (! $post || ! filter_var($post->ip_address, FILTER_VALIDATE_IP, [FILTER_FLAG_NO_PRIV_RANGE])) {
            return;
        }

        $this->queue->push(new ReportSpammerJob($user->username, $user->email, $post->ip_address));
    }
}

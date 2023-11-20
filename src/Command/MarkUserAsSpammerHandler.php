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
use Flarum\Discussion\Command\EditDiscussion;
use Flarum\Extension\ExtensionManager;
use Flarum\Flags\Command\DeleteFlags;
use Flarum\Post\Command\EditPost;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Command\DeleteUser;
use Flarum\User\Command\EditUser;
use Flarum\User\Guest;
use Flarum\User\User;
use FoF\AntiSpam\Event\MarkedUserAsSpammer;
use FoF\AntiSpam\Job\ReportSpammerJob;
use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;

class MarkUserAsSpammerHandler
{
    public $extensions;

    public $bus;

    public $events;

    public $settings;

    public $queue;

    public $log;

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

    public function __construct(ExtensionManager $extensions, Bus $bus, Events $events, SettingsRepositoryInterface $settings, Queue $queue, LoggerInterface $log)
    {
        $this->extensions = $extensions;
        $this->bus = $bus;
        $this->events = $events;
        $this->settings = $settings;
        $this->queue = $queue;
        $this->log = $log;
    }

    public function handle(MarkUserAsSpammer $command): User
    {
        $user = $command->user;
        $actor = $command->actor ?? new Guest();

        $this->parseOptions($command->options);

        $this->reportToStopForumSpam($user);

        $this->handleDiscussions($user, $actor);

        $this->handlePosts($user, $actor);
        $this->handleUser($user, $actor);

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
     * @param User $actor
     * @return void
     */
    protected function handleUser(User $user, User $actor): void
    {
        if ($this->deleteUser) {
            $this->bus->dispatch(
                new DeleteUser($user->id, $actor)
            );
        }
        /** @phpstan-ignore-next-line */
        elseif ($this->extensions->isEnabled('flarum-suspend') && $user->suspended_until === null) {
            $this->bus->dispatch(
                new EditUser($user->id, $actor, [
                    'attributes' => ['suspendedUntil' => Carbon::now()->addYears(20)],
                ])
            );
        } else {
            $this->log->info('User was marked as spam, but no action was taken.', [
                'user' => $user->id,
                'actor' => $actor->id,
            ]);
        }

        $user->refreshDiscussionCount();
        $user->refreshCommentCount();
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
            $user->posts()->delete();
        } else {
            $flagsEnabled = $this->flagsEnabled();

            $user->posts()->where('hidden_at', null)->chunk(50, function ($posts) use ($actor, $flagsEnabled) {
                foreach ($posts as $post) {
                    $this->bus->dispatch(
                        new EditPost($post->id, $actor, [
                            'attributes' => ['isHidden' => true],
                        ])
                    );

                    if ($flagsEnabled) {
                        $this->bus->dispatch(
                            new DeleteFlags($post->id, $actor)
                        );
                    }
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
            $user->discussions()->delete();
        } elseif ($this->moveDiscussionsToQuarantine) {
            $this->moveUserDiscussionsToQuarantine($user, $actor);
        } else {
            $user->discussions()->where('hidden_at', null)->chunk(50, function ($discussions) use ($actor) {
                foreach ($discussions as $discussion) {
                    $this->bus->dispatch(
                        new EditDiscussion($discussion->id, $actor, [
                            'attributes' => ['isHidden' => true],
                        ])
                    );
                }
            });
        }
    }

    protected function moveUserDiscussionsToQuarantine(User $user, User $actor): void
    {
        if (! $this->moveDiscussionsToQuarantine) {
            return;
        }

        $discussions = $user->discussions;
        $quarantineTagsString = (string) $this->settings->get(self::settings_prefix.'moveDiscussionsToTags');

        $tags = json_decode($quarantineTagsString);

        if (! $tags) {
            return;
        }

        $data = [];

        foreach ($tags as $tag) {
            $data[] = [
                'type' => 'tags',
                'id' => $tag,
            ];
        }

        foreach ($discussions as $discussion) {
            $this->bus->dispatch(
                new EditDiscussion($discussion->id, $actor, [
                    'attributes' => [],
                    'relationships' => [
                        'tags' => [
                            'data' => $data,
                        ],
                    ],
                ])
            );
        }
    }

    protected function reportToStopForumSpam(User $user): void
    {
        if (! $this->reportToSfs) {
            return;
        }

        $post = $user->posts()->first();

        $ipAddress = '8.8.8.8';

        if ($post && filter_var($post->ip_address, FILTER_VALIDATE_IP, [FILTER_FLAG_NO_PRIV_RANGE])) {
            $ipAddress = $post->ip_address;
        }

        $this->queue->push(new ReportSpammerJob($user->username, $user->email, $ipAddress));
    }
}

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
use Flarum\User\Command\EditUser;
use FoF\AntiSpam\Event\MarkedUserAsSpammer;
use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Events\Dispatcher as Events;

class MarkUserAsSpammerHandler
{
    public $extensions;

    public $bus;

    public $events;

    public function __construct(ExtensionManager $extensions, Bus $bus, Events $events)
    {
        $this->extensions = $extensions;
        $this->bus = $bus;
        $this->events = $events;
    }

    public function handle(MarkUserAsSpammer $command): void
    {
        $user = $command->user;
        $actor = $command->actor;
        
        $this->parseOptions($command->options);

        $flagsEnabled = $this->extensions->isEnabled('flarum-flags');

        /** @phpstan-ignore-next-line */
        if ($this->extensions->isEnabled('flarum-suspend') && $user->suspended_until === null) {
            $this->bus->dispatch(
                new EditUser($user->id, $actor, [
                    'attributes' => ['suspendedUntil' => Carbon::now()->addYears(20)],
                ])
            );
        }

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

        $user->discussions()->where('hidden_at', null)->chunk(50, function ($discussions) use ($actor) {
            foreach ($discussions as $discussion) {
                $this->bus->dispatch(
                    new EditDiscussion($discussion->id, $actor, [
                        'attributes' => ['isHidden' => true],
                    ])
                );
            }
        });

        $this->events->dispatch(
            new MarkedUserAsSpammer($user, $actor)
        );
    }

    protected function parseOptions(array $options): void
    {

    }
}

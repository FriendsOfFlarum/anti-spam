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

use Flarum\Discussion\Event\Started;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Checks discussion titles for spam when created.
 */
class CheckDiscussionContent extends AbstractContentCheck
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Started::class, [$this, 'checkDiscussion']);
    }

    public function checkDiscussion(Started $event): void
    {
        $discussion = $event->discussion;
        $actor = $event->actor;

        // Don't check if actor is staff
        if ($this->isStaff($actor)) {
            return;
        }

        $title = $discussion->title ?? '';

        if ($this->shouldSkipAnalysis($title)) {
            return;
        }

        // Analyze title
        $result = $this->analyzer->analyze($title, $actor, [
            'type' => 'discussion_title',
            'discussion_id' => $discussion->id,
        ]);

        // No spam indicators detected at all
        if (! $this->shouldProcessResult($result)) {
            return;
        }

        // Log spam detection
        $this->logSpamDetection($actor, $result, 'discussion title', ['title' => $title]);

        // Take actions if above threshold
        if ($result->shouldUnapprove()) {
            $this->unapprove($discussion);
        }

        if ($result->shouldFlag() && $discussion->first_post_id) {
            $this->flagFirstPost($discussion, $result);
        }
    }

    /**
     * Create a moderation flag on first post.
     */
    private function flagFirstPost(\Flarum\Discussion\Discussion $discussion, \FoF\AntiSpam\ContentFilter\AnalysisResult $result): void
    {
        if ($this->isAlreadyFlaggedForSpam($discussion->first_post_id)) {
            return;
        }

        $this->createSpamFlag($discussion->firstPost, $result, 'in discussion title');
    }
}

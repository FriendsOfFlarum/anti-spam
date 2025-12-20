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
use Flarum\Discussion\Event\Started;
use Flarum\Flags\Flag;
use FoF\AntiSpam\ContentFilter\Analyzer;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;

/**
 * Checks discussion titles for spam when created.
 */
class CheckDiscussionContent
{
    public function __construct(
        private Analyzer $analyzer,
        private LoggerInterface $log
    ) {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Started::class, [$this, 'checkDiscussion']);
    }

    public function checkDiscussion(Started $event): void
    {
        $discussion = $event->discussion;
        $actor = $event->actor;

        // Don't check if actor is staff
        if ($actor->isAdmin() || $actor->can('discussion.hide')) {
            return;
        }

        $title = $discussion->title ?? '';

        if (empty($title)) {
            return;
        }

        // Analyze title
        $result = $this->analyzer->analyze($title, $actor, [
            'type' => 'discussion_title',
            'discussion_id' => $discussion->id,
        ]);

        // No spam indicators detected at all
        if ($result->getTotalScore() === 0) {
            return;
        }

        // Log spam detection
        $this->log->info(
            "[FoF Anti Spam] Spam indicators detected in discussion title by user {$actor->username} (ID: {$actor->id})",
            [
                'title' => $title,
                'spam_score' => $result->getTotalScore(),
                'reasons' => $result->getAllReasons(),
                'will_flag' => $result->shouldFlag(),
                'will_unapprove' => $result->shouldUnapprove(),
            ]
        );

        // Take actions if above threshold
        if ($result->shouldUnapprove()) {
            $this->unapproveDiscussion($discussion);
        }

        if ($result->shouldFlag() && $discussion->first_post_id) {
            $this->flagFirstPost($discussion, $result);
        }
    }

    /**
     * Unapprove the discussion.
     */
    private function unapproveDiscussion(\Flarum\Discussion\Discussion $discussion): void
    {
        /** @phpstan-ignore-next-line - is_approved added by flarum/approval */
        $discussion->is_approved = false;

        $this->log->info(
            "[FoF Anti Spam] Unapproved discussion ID {$discussion->id} by user {$discussion->user_id}"
        );
    }

    /**
     * Create a moderation flag on first post.
     */
    private function flagFirstPost(\Flarum\Discussion\Discussion $discussion, \FoF\AntiSpam\ContentFilter\AnalysisResult $result): void
    {
        // Check if already flagged
        $existingFlag = Flag::where('post_id', $discussion->first_post_id)
            ->where('type', 'spam')
            ->whereNull('hidden_at')
            ->first();

        if ($existingFlag) {
            return; // Already flagged
        }

        // Create flag
        $flag = new Flag();
        $flag->post_id = $discussion->first_post_id;
        $flag->type = 'spam';
        $flag->reason = "Automatic spam detection in discussion title (score: {$result->getTotalScore()})";
        $flag->reason_detail = implode("\n", $result->getAllReasons());
        $flag->created_at = Carbon::now();
        $flag->save();

        $this->log->info(
            "[FoF Anti Spam] Created spam flag for discussion ID {$discussion->id}"
        );
    }
}

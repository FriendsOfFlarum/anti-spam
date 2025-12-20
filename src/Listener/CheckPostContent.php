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
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;
use Flarum\Post\Event\Saving;
use Flarum\Post\Post;
use FoF\AntiSpam\ContentFilter\Analyzer;
use FoF\AntiSpam\ContentFilter\AnalysisResult;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;

/**
 * Checks post content for spam when created or edited
 */
class CheckPostContent
{
    /**
     * @var array<int, AnalysisResult>
     */
    private array $pendingAnalysis = [];

    public function __construct(
        private Analyzer $analyzer,
        private LoggerInterface $log
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Saving::class, [$this, 'analyzePost']);
        $events->listen(Posted::class, [$this, 'recordResults']);
        $events->listen(Revised::class, [$this, 'recordResults']);
    }

    public function analyzePost(Saving $event): void
    {
        $post = $event->post;
        $actor = $event->actor;

        // Only check comment posts (or posts without type set yet - new posts)
        if ($post->type !== null && $post->type !== 'comment') {
            return;
        }

        // Only check on creation or when content is modified
        if ($post->exists && ! $post->isDirty('content')) {
            return;
        }

        // Don't check if actor is staff
        if ($actor->isAdmin() || $actor->can('discussion.hide')) {
            return;
        }

        // Get the post content
        $content = $post->content ?? '';

        if (empty($content)) {
            return;
        }

        // Analyze content
        $result = $this->analyzer->analyze($content, $actor, [
            'type' => 'post',
            'post_id' => $post->id,
            'discussion_id' => $post->discussion_id,
        ]);

        // No spam indicators detected at all
        if ($result->getTotalScore() === 0) {
            return;
        }

        // Log spam detection
        $this->log->info(
            "[FoF Anti Spam] Spam indicators detected in post by user {$actor->username} (ID: {$actor->id})",
            [
                'spam_score' => $result->getTotalScore(),
                'reasons' => $result->getAllReasons(),
                'will_flag' => $result->shouldFlag(),
                'will_unapprove' => $result->shouldUnapprove(),
            ]
        );

        // Store analysis for after save (needed for flagging)
        $this->pendingAnalysis[spl_object_id($post)] = $result;

        // Take approval action immediately (before save)
        if ($result->shouldUnapprove()) {
            $this->unapprovePost($post);
        }
    }

    public function recordResults(Posted|Revised $event): void
    {
        $post = $event->post;
        $objectId = spl_object_id($post);

        // Check if we have pending analysis for this post
        if (! isset($this->pendingAnalysis[$objectId])) {
            return;
        }

        $result = $this->pendingAnalysis[$objectId];
        unset($this->pendingAnalysis[$objectId]);

        // Create flag if needed (requires post ID)
        if ($result->shouldFlag()) {
            $this->flagPost($post, $result);
        }
    }

    /**
     * Unapprove the post
     */
    private function unapprovePost(Post $post): void
    {
        $post->is_approved = false;

        $this->log->info(
            "[FoF Anti Spam] Unapproved post ID {$post->id} by user {$post->user_id}"
        );
    }

    /**
     * Create a moderation flag (requires flarum/flags)
     */
    private function flagPost(Post $post, \FoF\AntiSpam\ContentFilter\AnalysisResult $result): void
    {
        // Check if already flagged
        $existingFlag = Flag::where('post_id', $post->id)
            ->where('type', 'spam')
            ->whereNull('hidden_at')
            ->first();

        if ($existingFlag) {
            return; // Already flagged
        }

        // Create flag
        $flag = new Flag();
        $flag->post_id = $post->id;
        $flag->type = 'spam';
        $flag->reason = "Automatic spam detection (score: {$result->getTotalScore()})\n\n"
            . implode("\n", $result->getAllReasons());
        $flag->created_at = Carbon::now();
        $flag->save();

        $this->log->info(
            "[FoF Anti Spam] Created spam flag for post ID {$post->id}"
        );
    }
}

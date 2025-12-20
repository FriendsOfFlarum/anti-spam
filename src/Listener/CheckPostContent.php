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

use Flarum\Flags\Flag;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;
use Flarum\Post\Event\Saving;
use Flarum\Post\Post;
use FoF\AntiSpam\ContentFilter\AnalysisResult;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Checks post content for spam when created or edited.
 */
class CheckPostContent extends AbstractContentCheck
{
    /**
     * @var array<int, AnalysisResult>
     */
    private array $pendingAnalysis = [];

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
        if ($this->isStaff($actor)) {
            return;
        }

        // Get the post content
        $content = $post->content ?? '';

        if ($this->shouldSkipAnalysis($content)) {
            return;
        }

        // Analyze content
        $result = $this->analyzer->analyze($content, $actor, [
            'type' => 'post',
            'post_id' => $post->id,
            'discussion_id' => $post->discussion_id,
        ]);

        // No spam indicators detected at all
        if (! $this->shouldProcessResult($result)) {
            return;
        }

        // Log spam detection
        $this->logSpamDetection($actor, $result, 'post');

        // Store analysis for after save (needed for flagging)
        $this->pendingAnalysis[spl_object_id($post)] = $result;

        // Take approval action immediately (before save)
        if ($result->shouldUnapprove()) {
            $this->unapprove($post);
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
     * Create a moderation flag.
     */
    private function flagPost(Post $post, AnalysisResult $result): void
    {
        if ($this->isAlreadyFlaggedForSpam($post->id)) {
            return;
        }

        $this->createSpamFlag($post, $result);
    }
}

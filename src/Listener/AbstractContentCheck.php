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
use Flarum\Discussion\Discussion;
use Flarum\Flags\Flag;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use FoF\AntiSpam\ContentFilter\AnalysisResult;
use FoF\AntiSpam\ContentFilter\Analyzer;
use Illuminate\Contracts\Events\Dispatcher;
use Psr\Log\LoggerInterface;

abstract class AbstractContentCheck
{
    public function __construct(
        protected Analyzer $analyzer,
        protected LoggerInterface $log,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    abstract public function subscribe(Dispatcher $events): void;

    /**
     * Check if actor is staff and should be exempt from spam checks.
     */
    protected function isStaff(User $actor): bool
    {
        return $actor->isAdmin() || $actor->can('discussion.hide');
    }

    /**
     * Check if we should skip analysis (empty content).
     */
    protected function shouldSkipAnalysis(string $content): bool
    {
        return empty(trim($content));
    }

    /**
     * Check if analysis result should be acted upon (non-zero score).
     */
    protected function shouldProcessResult(AnalysisResult $result): bool
    {
        return $result->getTotalScore() > 0;
    }

    /**
     * Log spam detection with context.
     *
     * @param User $actor
     * @param AnalysisResult $result
     * @param string $contentType Type of content (e.g., 'post', 'discussion title')
     * @param array<string, mixed> $extraContext Additional context to log
     */
    protected function logSpamDetection(
        User $actor,
        AnalysisResult $result,
        string $contentType,
        array $extraContext = []
    ): void {
        $this->log->info(
            "[FoF Anti Spam] Spam indicators detected in {$contentType} by user {$actor->username} (ID: {$actor->id})",
            array_merge([
                'spam_score' => $result->getTotalScore(),
                'reasons' => $result->getAllReasons(),
                'will_flag' => $result->shouldFlag(),
                'will_unapprove' => $result->shouldUnapprove(),
            ], $extraContext)
        );
    }

    /**
     * Check if a post already has a spam flag.
     */
    protected function isAlreadyFlaggedForSpam(int $postId): bool
    {
        $existingFlag = Flag::where('post_id', $postId)
            ->where('type', 'spam')
            ->first();

        return $existingFlag !== null;
    }

    /**
     * Create a spam flag on a post.
     *
     * @param Post $post
     * @param AnalysisResult $result
     * @param string|null $reasonPrefix Optional prefix for the reason (e.g., "in discussion title")
     */
    protected function createSpamFlag(
        Post $post,
        AnalysisResult $result,
        ?string $reasonPrefix = null
    ): void {
        $systemActor = $this->getSystemActor();
        if (! $systemActor) {
            // If no system actor is available, skip flagging
            return;
        }

        $reasonDetail = implode("\n", $result->getAllReasons());
        if ($reasonPrefix) {
            $reasonDetail = "Detected {$reasonPrefix}:\n\n".$reasonDetail;
        }

        $this->flag(
            $systemActor,
            $post,
            $result->getTotalScore(),
            $reasonDetail
        );
    }

    /**
     * Create a flag on a post directly in the database.
     *
     * Note: We use type='spam' for automatic spam detection flags.
     * This is displayed via our custom frontend extension (addSpamFlagType.tsx).
     */
    protected function flag(User $actor, Post $post, int $score, string $reasonDetail): void
    {
        $flag = new Flag();
        $flag->type = 'spam';
        $flag->user_id = $actor->id;
        $flag->post_id = $post->id;
        $flag->reason = (string) $score;
        $flag->reason_detail = $reasonDetail;
        $flag->created_at = Carbon::now();

        $flag->save();
    }

    /**
     * Unapprove content (post or discussion).
     *
     * When the entity is a discussion's first post, the discussion is unapproved too — otherwise
     * the post is held for approval but the thread stays publicly visible. This mirrors core
     * approval's UnapproveNewContent. The post number isn't reliably set until the row is saved,
     * so the discussion is unapproved in an afterSave hook.
     */
    protected function unapprove(Discussion|Post $entity): void
    {
        $entity->is_approved = false;

        if ($entity instanceof Post) {
            $entity->afterSave(function (Post $post) {
                if ($post->number == 1 && $post->discussion && $post->discussion->is_approved) {
                    $post->discussion->is_approved = false;
                    $post->discussion->save();
                }
            });
        }
    }

    /**
     * Get the system actor to use for automatic flags.
     */
    protected function getSystemActor(): ?User
    {
        $actorId = $this->settings->get('fof-anti-spam.moderation.system_user_id');

        return User::where('id', $actorId)->first() ?? null;
    }
}

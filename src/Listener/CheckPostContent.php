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
use FoF\AntiSpam\ContentFilter\AnalysisResult;
use FoF\AntiSpam\ContentFilter\MonitoredUsers;
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

    /**
     * Edits re-checked, for a note on a flag the post already has. Null when the account is no
     * longer monitored, so a clean result would say nothing about the edit.
     *
     * @var array<int, AnalysisResult|null>
     */
    private array $pendingEdits = [];

    /**
     * Edit notes kept on a flag besides what first raised it, so a run of edits cannot grow it
     * without limit.
     */
    private const int KEPT_EDIT_NOTES = 5;

    private const int EDIT_NOTE_LENGTH = 2000;

    private const string EDIT_NOTE_SEPARATOR = "\n\nEdited ";

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
            // Not always the actor: someone allowed to edit others' posts may be editing it.
            'author_id' => $post->user_id,
            // As stored, for comparing with stored posts: the source above has been through
            // every formatter extension's unparse, which rewrites some of it.
            'parsed_content' => $post->getAttributes()['content'] ?? null,
            'original_parsed_content' => $post->exists ? $post->getRawOriginal('content') : null,
        ]);

        // A filter switched off checked nothing, so it has nothing to add to a flag.
        if ($post->exists && $this->config->isEnabled()) {
            $monitored = (new MonitoredUsers($this->config))->shouldMonitor($actor);
            $this->pendingEdits[spl_object_id($post)] = $monitored ? $result : null;
        }

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

        // An edit of a post already flagged goes on that flag: what first tripped it stays, for
        // the moderator to judge, and what the edit left is added beneath it.
        if (array_key_exists($objectId, $this->pendingEdits)) {
            $edit = $this->pendingEdits[$objectId];
            unset($this->pendingEdits[$objectId]);

            $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->first();

            if ($flag) {
                unset($this->pendingAnalysis[$objectId]);
                $flag->reason_detail = self::withEditNote((string) $flag->reason_detail, $edit);
                $flag->save();

                return;
            }

            // Just looked for one, so there is no need to ask again before flagging.
            $result = $this->pendingAnalysis[$objectId] ?? null;
            unset($this->pendingAnalysis[$objectId]);

            if ($result?->shouldFlag()) {
                $this->createSpamFlag($post, $result);
            }

            return;
        }

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
     * A flag's detail with a note on an edit's re-check added, keeping what first raised the
     * flag and the latest few notes.
     */
    protected static function withEditNote(string $detail, ?AnalysisResult $edit): string
    {
        $time = Carbon::now('UTC')->format('Y-m-d H:i').' UTC';

        $note = match (true) {
            $edit === null => "{$time}: not re-checked, the editing account is no longer monitored",
            // Only the post's text is checked again: not a discussion title the flag may be about,
            // nor the posting rate, which is judged when a post is made.
            $edit->getTotalScore() === 0 => "{$time}: the edited text trips no content check",
            default => "{$time}, score {$edit->getTotalScore()}:\n".implode("\n", $edit->getFlagReasons()),
        };

        // A note per matching pattern could otherwise add up, over a few edits, to more than the
        // column holds.
        if (mb_strlen($note) > self::EDIT_NOTE_LENGTH) {
            $note = mb_substr($note, 0, self::EDIT_NOTE_LENGTH).'…';
        }

        $parts = explode(self::EDIT_NOTE_SEPARATOR, $detail);
        $original = array_shift($parts);
        $notes = array_slice([...$parts, $note], -self::KEPT_EDIT_NOTES);

        return $original.self::EDIT_NOTE_SEPARATOR.implode(self::EDIT_NOTE_SEPARATOR, $notes);
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

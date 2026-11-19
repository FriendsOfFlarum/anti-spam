<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\ContentFilter\Detectors;

use Flarum\Post\Post;
use Flarum\User\User;
use FoF\AntiSpam\ContentFilter\AbstractDetector;
use FoF\AntiSpam\ContentFilter\SpamScore;

/**
 * Detects a monitored account posting the same thing again: the same words as one of its
 * recent posts, whatever the case, spacing, punctuation or formatting.
 *
 * A person new to a community rarely says the same paragraph twice; an account pasting its
 * pitch into thread after thread does. Short replies are left alone, because people do say
 * "thanks, that worked" more than once.
 */
class RepeatedPostDetector extends AbstractDetector
{
    /**
     * Below this many characters, once normalised, a post is too short to call a repeat.
     */
    protected const int MIN_LENGTH = 40;

    /**
     * How many of the author's latest posts are compared: enough to catch a spammer's run, few
     * enough to stay one cheap query.
     */
    protected const int RECENT_POSTS = 20;

    public function analyze(string $content, User $user, array $context = []): SpamScore
    {
        if (($context['type'] ?? null) !== 'post' || ! $user->exists) {
            return new SpamScore();
        }

        if (! $this->config->get('detect_repeats') || ! $this->shouldMonitorUser($user)) {
            return new SpamScore();
        }

        $normalised = $this->normalise($content);

        if (mb_strlen($normalised) < self::MIN_LENGTH) {
            return new SpamScore();
        }

        $authorId = $context['author_id'] ?? $user->id;
        $postId = $context['post_id'] ?? null;

        // Held posts and posts moderators hid count: a held post is the one most likely to be
        // posted again. Posts the author deleted, or that are in a discussion they deleted, do
        // not: posting in the wrong place, deleting it and posting it again is not spam.
        //
        // An edit is compared only with the posts before it: neither with itself, which it always
        // repeats, nor with a later copy, which is the repeat and has already been judged.
        //
        // Compared as stored, without rebuilding each post's source: that runs every formatter
        // extension's unparse callbacks, and mentions' query for the models they mention.
        $recent = Post::query()
            ->where('user_id', $authorId)
            ->where('type', 'comment')
            ->where(fn ($query) => $query->whereNull('hidden_user_id')->orWhereColumn('hidden_user_id', '!=', 'user_id'))
            ->whereNotIn('discussion_id', fn ($query) => $query->select('id')->from('discussions')->where('hidden_user_id', $authorId))
            ->when($postId, fn ($query) => $query->where('id', '<', $postId))
            ->latest()
            ->limit(self::RECENT_POSTS)
            ->toBase()
            ->pluck('content', 'id');

        foreach ($recent as $id => $stored) {
            if ($this->normalise((string) $stored) === $normalised) {
                return new SpamScore(
                    score: 50,
                    reasons: ['Repeats one of their earlier posts'],
                    metadata: [
                        'detector' => 'repeat',
                        'post_id' => $id,
                    ]
                );
            }
        }

        return new SpamScore();
    }

    /**
     * The words alone: formatting, punctuation and case removed, whitespace collapsed. Works on
     * both a post's source and its stored XML, whose tags are stripped the same way.
     *
     * Combining marks are kept: in scripts such as Devanagari they are the vowels, and words
     * that differ only in them are different words.
     */
    protected function normalise(string $content): string
    {
        $text = $this->stripFormatting($content);

        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text) ?: $text;
        }

        return trim((string) preg_replace('~[^\p{L}\p{M}\p{N}]+~u', ' ', mb_strtolower($text)));
    }

    public function getName(): string
    {
        return 'Repeated Post Detector';
    }

    public function getDescription(): string
    {
        return 'Detects new accounts posting the same content again';
    }
}

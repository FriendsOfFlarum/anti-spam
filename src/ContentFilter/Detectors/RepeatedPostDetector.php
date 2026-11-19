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
 * Detects a new account posting the same thing again: the same words as one of its recent
 * posts, whatever the case, spacing, punctuation or formatting. New accounts only, even when
 * everyone is monitored: a regular pasting the same answer to the same question is helping.
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

        // Someone allowed to edit others' posts is editing one. Their own history says nothing
        // about it, and checking the author's would let them test guesses at posts they cannot see.
        if (isset($context['author_id']) && (int) $context['author_id'] !== (int) $user->id) {
            return new SpamScore();
        }

        if (! $this->config->get('detect_repeats') || ! $this->isNewAccount($user)) {
            return new SpamScore();
        }

        // Compared as stored, on both sides, when the caller has it.
        $normalised = $this->normalise($context['parsed_content'] ?? $content);

        if (mb_strlen($normalised) < self::MIN_LENGTH) {
            return new SpamScore();
        }

        // An edit that leaves the words as they were is not saying anything again: fixing the
        // punctuation of a post whose later copy was held must not hold the original as well.
        if (isset($context['original_parsed_content']) && $this->normalise((string) $context['original_parsed_content']) === $normalised) {
            return new SpamScore();
        }

        $postId = $context['post_id'] ?? null;

        // Held posts and posts moderators hid count: a held post is the one most likely to be
        // posted again. Posts the author deleted, or that are in a discussion they deleted, do
        // not: posting in the wrong place, deleting it and posting it again is not spam.
        //
        // An edit is compared with every other post, later ones too: otherwise placeholders
        // edited into the same text from the newest back would never meet a copy.
        //
        // Compared as stored, without rebuilding each post's source: that runs every formatter
        // extension's unparse callbacks, and mentions' query for the models they mention.
        $recent = Post::query()
            ->where('posts.user_id', $user->id)
            ->where('posts.type', 'comment')
            ->where(fn ($query) => $query->whereNull('posts.hidden_user_id')->orWhereColumn('posts.hidden_user_id', '!=', 'posts.user_id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('discussions')
                ->whereColumn('discussions.id', 'posts.discussion_id')
                ->where('discussions.hidden_user_id', $user->id))
            ->when($postId, fn ($query) => $query->where('posts.id', '!=', $postId))
            ->latest('posts.created_at')
            ->limit(self::RECENT_POSTS)
            ->toBase()
            ->pluck('posts.content', 'posts.id');

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
     * both a post's source and its stored XML.
     *
     * In the XML, the characters that marked formatting up (`**`, `[b]`) are kept in `<s>` and
     * `<e>` elements; they are dropped, or BBCode's tag names would read as words.
     *
     * Combining marks are kept: in scripts such as Devanagari they are the vowels, and words
     * that differ only in them are different words.
     */
    protected function normalise(string $content): string
    {
        $content = (string) preg_replace('~<([se])>.*?</\1>~s', ' ', $content);
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

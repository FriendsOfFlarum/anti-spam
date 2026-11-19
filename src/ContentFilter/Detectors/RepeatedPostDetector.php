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
     * How many of the account's latest posts are compared. Monitoring ends after a handful of
     * posts by default, so this is only reached on a forum that raises that or monitors everyone.
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

        // Every comment, held or hidden ones too: a held post is the one most likely to be
        // posted again. Not the post being edited, which would always repeat itself.
        $recent = Post::query()
            ->where('user_id', $user->id)
            ->where('type', 'comment')
            ->when(! empty($context['post_id']), fn ($query) => $query->where('id', '!=', $context['post_id']))
            ->latest()
            ->limit(self::RECENT_POSTS)
            ->get();

        foreach ($recent as $post) {
            if ($this->normalise((string) $post->content) === $normalised) {
                return new SpamScore(
                    score: 50,
                    reasons: ['Repeats one of their earlier posts'],
                    metadata: [
                        'detector' => 'repeat',
                        'post_id' => $post->id,
                    ]
                );
            }
        }

        return new SpamScore();
    }

    /**
     * The words alone: formatting, punctuation and case removed, whitespace collapsed.
     */
    protected function normalise(string $content): string
    {
        $text = mb_strtolower($this->stripFormatting($content));

        return trim((string) preg_replace('~[^\p{L}\p{N}]+~u', ' ', $text));
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

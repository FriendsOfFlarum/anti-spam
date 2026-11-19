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

use Carbon\Carbon;
use Flarum\Post\Post;
use Flarum\User\User;
use FoF\AntiSpam\ContentFilter\AbstractDetector;
use FoF\AntiSpam\ContentFilter\SpamScore;

/**
 * Detects a monitored account posting faster than a person finding their feet in a community:
 * `burst_post_count` posts elsewhere within `burst_minutes`, and then another.
 *
 * Says nothing about what the post says, so it catches spam with no link, address or number in
 * it, and it scores at the default hiding threshold on its own. Only new posts are counted
 * against the limit; editing one is not posting again.
 *
 * Posts in the discussion being posted in are not counted. A new member going back and forth
 * with whoever is helping them posts quickly too, but in one place; spam goes everywhere.
 */
class PostBurstDetector extends AbstractDetector
{
    public const int DEFAULT_POSTS = 5;

    public const int DEFAULT_MINUTES = 10;

    public function analyze(string $content, User $user, array $context = []): SpamScore
    {
        if (($context['type'] ?? null) !== 'post' || ! empty($context['post_id']) || ! $user->exists) {
            return new SpamScore();
        }

        $limit = $this->number('burst_post_count', self::DEFAULT_POSTS);
        $minutes = $this->number('burst_minutes', self::DEFAULT_MINUTES);

        if ($limit <= 0 || $minutes <= 0 || ! $this->shouldMonitorUser($user)) {
            return new SpamScore();
        }

        // Every comment, held or hidden ones too: otherwise holding one post would make room
        // for the next.
        $recent = Post::query()
            ->where('user_id', $user->id)
            ->where('type', 'comment')
            ->where('created_at', '>=', Carbon::now()->subMinutes($minutes))
            ->when(! empty($context['discussion_id']), fn ($query) => $query->where('discussion_id', '!=', $context['discussion_id']))
            ->count();

        if ($recent < $limit) {
            return new SpamScore();
        }

        // The limit, not the count: the count includes posts in tags the moderator reading the
        // flag may not be able to see.
        return new SpamScore(
            score: 50,
            reasons: ["Made at least {$limit} posts in the last {$minutes} minutes"],
            metadata: [
                'detector' => 'burst',
                'recent_posts' => $recent,
                'minutes' => $minutes,
            ]
        );
    }

    /**
     * A blank number field in the admin panel saves an empty string, which would read as 0 and
     * turn the limit off while the field shows the default as its placeholder.
     */
    protected function number(string $key, int $default): int
    {
        $value = $this->config->get($key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    public function getName(): string
    {
        return 'Posting Rate Detector';
    }

    public function getDescription(): string
    {
        return 'Detects new accounts posting more often than the configured limit';
    }
}

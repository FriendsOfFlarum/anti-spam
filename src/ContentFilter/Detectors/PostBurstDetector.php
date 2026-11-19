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
 * more than `burst_post_count` posts within `burst_minutes`.
 *
 * Says nothing about what the post says, so it catches spam with no link, address or number in
 * it, and it scores at the default hiding threshold on its own. Only new posts are counted
 * against the limit; editing one is not posting again.
 */
class PostBurstDetector extends AbstractDetector
{
    public function analyze(string $content, User $user, array $context = []): SpamScore
    {
        if (($context['type'] ?? null) !== 'post' || ! empty($context['post_id']) || ! $user->exists) {
            return new SpamScore();
        }

        $limit = (int) $this->config->get('burst_post_count');
        $minutes = (int) $this->config->get('burst_minutes');

        if ($limit <= 0 || $minutes <= 0 || ! $this->shouldMonitorUser($user)) {
            return new SpamScore();
        }

        // Every comment, held or hidden ones too: otherwise holding one post would make room
        // for the next.
        $recent = Post::query()
            ->where('user_id', $user->id)
            ->where('type', 'comment')
            ->where('created_at', '>=', Carbon::now()->subMinutes($minutes))
            ->count();

        if ($recent < $limit) {
            return new SpamScore();
        }

        return new SpamScore(
            score: 50,
            reasons: ["Already made {$recent} posts in the last {$minutes} minutes"],
            metadata: [
                'detector' => 'burst',
                'recent_posts' => $recent,
                'minutes' => $minutes,
            ]
        );
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

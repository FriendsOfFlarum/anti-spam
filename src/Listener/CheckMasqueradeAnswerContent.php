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

use Flarum\Foundation\ValidationException;
use FoF\AntiSpam\ContentFilter\Analyzer;
use FoF\AntiSpam\ContentFilter\Detectors\EmailDetector;
use FoF\AntiSpam\ContentFilter\Detectors\UrlDetector;
use FoF\Masquerade\Answer;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Runs the content filter over a fof/masquerade answer as it is saved.
 *
 * Masquerade has no event before an answer is written, so this listens for the model's own saving
 * event. That runs inside masquerade's transaction: refusing one answer rolls back the whole
 * profile save, which is what the user is then told happened.
 *
 * The answer's owner is judged, as no actor reaches a model event. Only a changed answer is
 * examined, so a moderator saving someone else's profile is not refused over a value they left
 * untouched.
 */
class CheckMasqueradeAnswerContent
{
    /**
     * Detectors that would only object to a typed field holding what it is meant to hold.
     *
     * @var array<string, list<class-string>>
     */
    protected const array IGNORED_FOR_TYPE = [
        'url' => [UrlDetector::class],
        'email' => [EmailDetector::class],
    ];

    public function __construct(
        protected Analyzer $analyzer,
        protected LoggerInterface $log,
        protected TranslatorInterface $translator
    ) {
    }

    public function handle(Answer $answer): void
    {
        if (! $answer->isDirty('content') || ! is_string($answer->content) || trim($answer->content) === '') {
            return;
        }

        $owner = $answer->user;

        if ($owner === null) {
            return;
        }

        $result = $this->analyzer->analyze($answer->content, $owner, [
            'type' => 'profile',
            'field' => 'masquerade.'.$answer->field_id,
            'user_id' => $owner->id,
            'ignore_detectors' => self::IGNORED_FOR_TYPE[(string) $answer->field?->type] ?? [],
        ]);

        // Held to the unapprove threshold, as every other profile field is.
        if (! $result->shouldUnapprove()) {
            return;
        }

        $this->log->info(
            "[FoF Anti Spam] Spam indicators detected in a masquerade answer for user {$owner->username}",
            [
                'spam_score' => $result->getTotalScore(),
                'reasons' => $result->getAllReasons(),
                'field_id' => $answer->field_id,
                'user_id' => $owner->id,
            ]
        );

        // Keyed as masquerade keys its own validation errors, so its form reports it the same way.
        throw new ValidationException([
            'answer' => $this->translator->trans('fof-anti-spam.forum.message.profile.blocked'),
        ]);
    }
}

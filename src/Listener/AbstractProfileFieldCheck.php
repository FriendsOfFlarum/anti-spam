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
use Flarum\Locale\TranslatorInterface;
use Flarum\User\Event\Saving;
use Flarum\User\User;
use FoF\AntiSpam\ContentFilter\AnalysisResult;
use FoF\AntiSpam\ContentFilter\Analyzer;
use Psr\Log\LoggerInterface;

/**
 * Runs the content filter over a single profile field.
 *
 * A profile field has no approval queue to hold it in, so there is nothing to unapprove: the save
 * is refused instead, the same way flarum/nicknames refuses a nickname that fails its regex and
 * fof/user-bio refuses one that is too long. Refusing also means the value is never stored, so a
 * spam bio never has a window in which it is publicly visible.
 *
 * Both User\Event\Saving paths are covered by one hook — registration and a later profile edit —
 * because both go through UserResource.
 */
abstract class AbstractProfileFieldCheck
{
    public function __construct(
        protected Analyzer $analyzer,
        protected LoggerInterface $log,
        protected TranslatorInterface $translator
    ) {
    }

    /**
     * The model attribute this check reads.
     */
    abstract protected function attribute(): string;

    public function handle(Saving $event): void
    {
        $user = $event->user;
        $attribute = $this->attribute();

        // A moderator tidying up someone's profile must not be turned away by the filter.
        if ($this->isStaff($event->actor)) {
            return;
        }

        // Only judge what this request actually changes. Analysing an untouched value would trap a
        // user who already carries a bad one: every later save of any field would be refused.
        if (! $user->isDirty($attribute)) {
            return;
        }

        $value = (string) ($user->getAttribute($attribute) ?? '');

        if (trim($value) === '') {
            return;
        }

        $result = $this->analyzer->analyze($value, $user, [
            'type' => 'profile',
            'field' => $attribute,
            'user_id' => $user->id,
        ]);

        if (! $this->shouldReject($result)) {
            return;
        }

        $this->log->info(
            "[FoF Anti Spam] Spam indicators detected in {$attribute} for user {$user->username}",
            [
                'spam_score' => $result->getTotalScore(),
                'reasons' => $result->getAllReasons(),
                'field' => $attribute,
                'user_id' => $user->id,
            ]
        );

        throw new ValidationException([
            $attribute => $this->translator->trans('fof-anti-spam.forum.message.profile.blocked'),
        ]);
    }

    /**
     * Refusing a save is the profile-field equivalent of unapproving a post, so it is held to the
     * same threshold and the same admin switch rather than the softer flagging one.
     */
    protected function shouldReject(AnalysisResult $result): bool
    {
        return $result->shouldUnapprove();
    }

    protected function isStaff(User $actor): bool
    {
        return $actor->isAdmin() || $actor->can('discussion.hide');
    }
}

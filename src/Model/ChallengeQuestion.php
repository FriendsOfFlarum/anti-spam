<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Model;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;

/**
 * @property string $question
 * @property string $answer
 * @property bool $case_sensitive
 * @property bool $is_active
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ChallengeQuestion extends AbstractModel
{
    protected $table = 'challenge_questions';

    protected $fillable = [
        'question',
        'answer',
        'case_sensitive',
        'is_active',
    ];

    protected $casts = [
        'case_sensitive' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function build(string $question, string $answer, bool $caseSensitive, bool $isActive): self
    {
        $self = new self();

        $self->question = $question;
        $self->answer = $answer;
        $self->case_sensitive = $caseSensitive;
        $self->is_active = $isActive;
        $self->created_at = Carbon::now();
        $self->updated_at = Carbon::now();

        return $self;
    }

    public static function validateAnswer(?int $challengeId, ?string $answer): ?string
    {
        if (is_null($challengeId) || is_null($answer)) {
            return null;
        }

        $answer = trim($answer);

        $challenge = self::find($challengeId);

        if (is_null($challenge)) {
            return null;
        }

        if ($challenge->case_sensitive) {
            if ($challenge->answer === $answer) {
                return '1234'; //self::generateToken($challengeId);
            }
        } else {
            if (strtolower($challenge->answer) === strtolower($answer)) {
                return '12345'; //self::generateToken($challengeId);
            }
        }

        return null;
    }
}

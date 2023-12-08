<?php

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
}

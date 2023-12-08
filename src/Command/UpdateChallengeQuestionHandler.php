<?php

namespace FoF\AntiSpam\Command;

use FoF\AntiSpam\Model\ChallengeQuestion;
use FoF\AntiSpam\Validator\ChallengeValidator;
use Illuminate\Support\Arr;

class UpdateChallengeQuestionHandler
{
    /**
     * @var ChallengeValidator
     */
    protected $validator;

    public function __construct(ChallengeValidator $validator)
    {
        $this->validator = $validator;
    }

    public function handle(UpdateChallengeQuestion $command)
    {
        $command->actor->assertAdmin();

        /** @var ChallengeQuestion $challenge */
        $challenge = ChallengeQuestion::findOrFail($command->id);

        if ($question = Arr::get($command->data, 'attributes.question')) {
            $challenge->question = $question;
        }

        if ($answer = Arr::get($command->data, 'attributes.answer')) {
            $challenge->answer = $answer;
        }

        if (($caseSensitive = Arr::get($command->data, 'attributes.case_sensitive')) !==  null) {
            $challenge->case_sensitive = $caseSensitive;
        }

        if (($isActive = Arr::get($command->data, 'attributes.is_active')) !== null) {
            $challenge->is_active = $isActive;
        }

        $this->validator->assertValid($challenge->getAttributes());

        if ($challenge->isDirty()) {
            $challenge->save();
        }

        return $challenge;
    }
}

<?php

namespace FoF\AntiSpam\Command;

use FoF\AntiSpam\Model\ChallengeQuestion;
use FoF\AntiSpam\Validator\ChallengeValidator;
use Illuminate\Support\Arr;

class CreateChallengeQuestionHandler
{
    /**
     * @var ChallengeValidator
     */
    protected $validator;

    public function __construct(ChallengeValidator $validator)
    {
        $this->validator = $validator;
    }

    public function handle(CreateChallengeQuestion $command)
    {
        $actor = $command->actor;
        $data = $command->data;

        $actor->assertAdmin();

        $attributes = Arr::only(Arr::get($data, 'attributes'), ['question', 'answer', 'case_sensitive', 'is_active']);
        

        $question = ChallengeQuestion::build(
            Arr::get($attributes, 'question', ''),
            Arr::get($attributes, 'answer', ''),
            Arr::get($attributes, 'case_sensitive', false),
            Arr::get($attributes, 'is_active', false)
            );

        $this->validator->assertValid($question->getAttributes());

        $question->save();

        return $question;
    }
}

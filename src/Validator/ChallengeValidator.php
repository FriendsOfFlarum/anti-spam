<?php

namespace FoF\AntiSpam\Validator;

use Flarum\Foundation\AbstractValidator;

class ChallengeValidator extends AbstractValidator
{
    protected $rules = [
        'question' => [
            'required', // The question field must not be empty
            'string',   // The question should be a string
            'min:10',   // Minimum length of 10 characters for the question
            'max:255'   // Maximum length of 255 characters for the question
        ],
        'answer' => [
            'required', // The answer field must not be empty
            'string',   // The answer should be a string
            'max:255'   // Maximum length of 255 characters for the answer
        ],
        'case_sensitive' => [
            'required', // The case_sensitive field must not be empty
            'boolean'   // This field should be a boolean value
        ],
        'is_active' => [
            'required', // The is_active field must not be empty
            'boolean'   // This field should be a boolean value
        ]
    ];
}

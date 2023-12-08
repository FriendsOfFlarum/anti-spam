<?php

namespace FoF\AntiSpam\Tests\integration;

trait ProvidesChallengeQuestions
{
    public function challengeQuestion()
    {
        return ['id' => 1, 'question' => 'What is the answer to life, the universe, and everything?', 'answer' => '42', 'case_sensitive' => 0, 'is_active' => 1];
    }
}

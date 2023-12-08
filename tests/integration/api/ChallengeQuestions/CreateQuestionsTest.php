<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\integration\api\ChallengeQuestions;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Psr\Http\Message\ResponseInterface;

class CreateQuestionsTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-anti-spam');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ]
        ]);
    }

    protected function makeRequest(array $attributes, int $userId = 1): ResponseInterface
    {
        return $this->send(
            $this->request(
                'POST',
                '/api/fof/antispam/question',
                [
                    'authenticatedAs' => $userId,
                    'json' => [
                        'data' => [
                            'attributes' => $attributes
                        ]
                    ]
                ]
            )
        );
    }

    /**
     * @test
     */
    public function non_admin_cannot_create_a_new_question()
    {
        $response = $this->makeRequest([
            'question' => 'What is the answer to life, the universe, and everything?',
            'answer' => '42',
            'case_sensitive' => false,
            'is_active' => false,
        ], 2);

        $this->assertEquals(403, $response->getStatusCode());
    }

    protected function adminCreateOptions(): array
    {
        return [
            [false, false],
            [false, true],
            [true, false],
            [true, true],
        ];
    }

    /**
     * @test
     * @dataProvider adminCreateOptions
     */
    public function admin_can_create_a_new_question(bool $caseSensitive, bool $isActive)
    {
        $response = $this->makeRequest([
            'question' => 'What is the answer to life, the universe, and everything?',
            'answer' => '42',
            'case_sensitive' => $caseSensitive,
            'is_active' => $isActive,
        ]);

        $this->assertEquals(201, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('What is the answer to life, the universe, and everything?', $json['data']['attributes']['question']);
        $this->assertEquals('42', $json['data']['attributes']['answer']);
        $this->assertEquals($caseSensitive, $json['data']['attributes']['caseSensitive']);
        $this->assertEquals($isActive, $json['data']['attributes']['isActive']);
    }

    /**
     * @test
     */
    public function admin_cannot_create_a_new_question_without_a_question()
    {
        $response = $this->makeRequest([
            'answer' => '42',
            'case_sensitive' => false,
            'is_active' => false,
        ]);

        $this->assertEquals(422, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('validation_error', $json['errors'][0]['code']);
        $this->assertEquals('The question field is required.', $json['errors'][0]['detail']);
    }

    /**
     * @test
     */
    public function admin_cannot_create_a_question_that_is_too_short()
    {
        $response = $this->makeRequest([
            'question' => 'Short?',
            'answer' => '42',
            'case_sensitive' => false,
            'is_active' => false,
        ]);

        $this->assertEquals(422, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('validation_error', $json['errors'][0]['code']);
        $this->assertEquals('The question must be at least 10 characters.', $json['errors'][0]['detail']);
    }

    /**
     * @test
     */
    public function admin_cannot_create_a_question_that_is_too_long()
    {
        $longQuestion = str_repeat('This is a really, really long question that is way too long to be a question. ', 5);

        $response = $this->makeRequest([
            'question' => $longQuestion,
            'answer' => '42',
            'case_sensitive' => false,
            'is_active' => false,
        ]);

        $this->assertEquals(422, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('validation_error', $json['errors'][0]['code']);
        $this->assertEquals('The question must not be greater than 255 characters.', $json['errors'][0]['detail']);
    }

    /**
     * @test
     */
    public function admin_cannot_create_a_question_without_an_answer()
    {
        $response = $this->makeRequest([
            'question' => 'What is the answer to life, the universe, and everything?',
            'case_sensitive' => false,
            'is_active' => false,
        ]);

        $this->assertEquals(422, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('validation_error', $json['errors'][0]['code']);
        $this->assertEquals('The answer field is required.', $json['errors'][0]['detail']);
    }

    /**
     * @test
     */
    public function admin_cannot_create_a_question_with_an_answer_that_is_too_long()
    {
        $longAnswer = str_repeat('This is a really, really long answer that is way too long to be an answer. ', 5);

        $response = $this->makeRequest([
            'question' => 'What is the answer to life, the universe, and everything?',
            'answer' => $longAnswer,
            'case_sensitive' => false,
            'is_active' => false,
        ]);

        $this->assertEquals(422, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals('validation_error', $json['errors'][0]['code']);
        $this->assertEquals('The answer must not be greater than 255 characters.', $json['errors'][0]['detail']);
    }
}

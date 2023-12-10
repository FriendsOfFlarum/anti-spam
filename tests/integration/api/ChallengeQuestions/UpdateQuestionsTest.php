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
use FoF\AntiSpam\Model\ChallengeQuestion;
use FoF\AntiSpam\Tests\integration\ProvidesChallengeQuestions;

class UpdateQuestionsTest extends TestCase
{
    use RetrievesAuthorizedUsers;
    use ProvidesChallengeQuestions;

    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-anti-spam');

        $this->prepareDatabase([
            'users' => [
                $this->normalUser(),
            ],
            'challenge_questions' => [
                $this->challengeQuestion(),
            ]
        ]);
    }

    /**
     * @test
     */
    public function normal_user_cannot_update_questions()
    {
        $response = $this->send(
            $this->request(
                'POST',
                '/api/challenge-questions/1',
                [
                    'authenticatedAs' => 2,
                ]
            )
        );

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function update_to_non_existing_question_fails()
    {
        $response = $this->send(
            $this->request(
                'POST',
                '/api/challenge-questions/2',
                [
                    'authenticatedAs' => 1,
                    'json' => [
                        'data' => [
                            'attributes' => [
                                'question' => 'What is the answer to life, the universe, and everything?',
                                'answer' => '42',
                                'caseSensitive' => false,
                                'isActive' => true,
                            ],
                        ],
                    ],
                ]
            )
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function admin_can_update_question()
    {
        $response = $this->send(
            $this->request(
                'POST',
                '/api/challenge-questions/1',
                [
                    'authenticatedAs' => 1,
                    'json' => [
                        'data' => [
                            'attributes' => [
                                'question' => 'What is my bestest colour?',
                            ],
                        ],
                    ],
                ]
            )
        );

        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals(1, $json['data']['id']);
        $this->assertEquals('What is my bestest colour?', $json['data']['attributes']['question']);
        $this->assertEquals($this->challengeQuestion()['answer'], $json['data']['attributes']['answer']);
        $this->assertFalse($json['data']['attributes']['caseSensitive']);
        $this->assertTrue($json['data']['attributes']['isActive']);

        $model = ChallengeQuestion::find(1);

        $this->assertNotNull($model);
        $this->assertEquals('What is my bestest colour?', $model->question);
    }

    /**
     * @test
     */
    public function admin_can_update_question_answer()
    {
        $response = $this->send(
            $this->request(
                'POST',
                '/api/challenge-questions/1',
                [
                    'authenticatedAs' => 1,
                    'json' => [
                        'data' => [
                            'attributes' => [
                                'answer' => 'blue',
                            ],
                        ],
                    ],
                ]
            )
        );

        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals(1, $json['data']['id']);
        $this->assertEquals($this->challengeQuestion()['question'], $json['data']['attributes']['question']);
        $this->assertEquals('blue', $json['data']['attributes']['answer']);
        $this->assertFalse($json['data']['attributes']['caseSensitive']);
        $this->assertTrue($json['data']['attributes']['isActive']);

        $model = ChallengeQuestion::find(1);

        $this->assertNotNull($model);
        $this->assertEquals('blue', $model->answer);
    }

    /**
     * @test
     */
    public function admin_can_update_question_case_sensitive()
    {
        $response = $this->send(
            $this->request(
                'POST',
                '/api/challenge-questions/1',
                [
                    'authenticatedAs' => 1,
                    'json' => [
                        'data' => [
                            'attributes' => [
                                'case_sensitive' => true,
                            ],
                        ],
                    ],
                ]
            )
        );

        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals(1, $json['data']['id']);
        $this->assertEquals($this->challengeQuestion()['question'], $json['data']['attributes']['question']);
        $this->assertEquals($this->challengeQuestion()['answer'], $json['data']['attributes']['answer']);
        $this->assertTrue($json['data']['attributes']['caseSensitive']);
        $this->assertTrue($json['data']['attributes']['isActive']);

        $model = ChallengeQuestion::find(1);

        $this->assertNotNull($model);
        $this->assertEquals(1, $model->case_sensitive);
    }

    /**
     * @test
     */
    public function admin_can_update_question_is_active()
    {
        $response = $this->send(
            $this->request(
                'POST',
                '/api/challenge-questions/1',
                [
                    'authenticatedAs' => 1,
                    'json' => [
                        'data' => [
                            'attributes' => [
                                'is_active' => false,
                            ],
                        ],
                    ],
                ]
            )
        );

        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals(1, $json['data']['id']);
        $this->assertEquals($this->challengeQuestion()['question'], $json['data']['attributes']['question']);
        $this->assertEquals($this->challengeQuestion()['answer'], $json['data']['attributes']['answer']);
        $this->assertFalse($json['data']['attributes']['caseSensitive']);
        $this->assertFalse($json['data']['attributes']['isActive']);

        $model = ChallengeQuestion::find(1);

        $this->assertNotNull($model);
        $this->assertEquals(0, $model->is_active);
    }
}

<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\integration\Api;

use Flarum\Testing\integration\TestCase;

class AnswerChallengeTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-anti-spam');

        $this->prepareDatabase([
            'challenge_questions' => [
                ['id' => 1, 'question' => 'What is the answer?', 'answer' => 'abcDEF', 'case_sensitive' => true, 'is_active' => true],
            ],
        ]);
    }

    /**
     * @test
     */
    public function can_answer_challenge_question_incorrectly()
    {
        $response = $this->send(
            $this->request('POST', '/api/challenge', [
                'json' => [
                    'data' => [
                        'attributes' => [
                            'challengeId' => 1,
                            'answer' => 'abcdef',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(422, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('errors', $json);

        $errors = $json['errors'];
        $this->assertNotNull($errors, 'Incorrect answer should have errors');
        $this->assertNotEmpty($errors, 'Incorrect answer should have errors');
        $this->assertEquals('/data/attributes/answer', $errors[0]['source']['pointer']);
    }

    /**
     * @test
     */
    public function can_answer_challenge_question_correctly()
    {
        $response = $this->send(
            $this->request('POST', '/api/challenge', [
                'json' => [
                    'data' => [
                        'attributes' => [
                            'challengeId' => 1,
                            'answer' => 'abcDEF',
                        ],
                    ],
                ],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertArrayHasKey('token', $json);

        $token = $json['token'];
        $this->assertNotNull($token, 'Correct answer should have a token');
    }
}

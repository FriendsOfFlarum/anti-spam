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
use FoF\AntiSpam\Tests\integration\ProvidesChallengeQuestions;

class ListQuestionsTest extends TestCase
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
    public function normal_user_cannot_list_questions()
    {
        $response = $this->send(
            $this->request(
                'GET',
                '/api/fof/antispam/question',
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
    public function admin_can_list_questions()
    {
        $response = $this->send(
            $this->request(
                'GET',
                '/api/fof/antispam/question',
                [
                    'authenticatedAs' => 1,
                ]
            )
        );
        $this->assertEquals(200, $response->getStatusCode());

        $json = json_decode($response->getBody()->getContents(), true);

        $this->assertEquals(1, count($json['data']));

        $this->assertEquals(1, $json['data'][0]['id']);
        $this->assertEquals($this->challengeQuestion()['question'], $json['data'][0]['attributes']['question']);
        $this->assertEquals($this->challengeQuestion()['answer'], $json['data'][0]['attributes']['answer']);
        $this->assertFalse($json['data'][0]['attributes']['caseSensitive']);
        $this->assertTrue($json['data'][0]['attributes']['isActive']);
    }
}

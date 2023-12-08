<?php

namespace FoF\AntiSpam\Tests\integration\api\ChallengeQuestions;

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\AntiSpam\Model\ChallengeQuestion;
use FoF\AntiSpam\Tests\integration\ProvidesChallengeQuestions;

class DeleteQuestionsTest extends TestCase
{
    use RetrievesAuthorizedUsers, ProvidesChallengeQuestions;

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
    public function normal_user_cannot_delete_questions()
    {
        $response = $this->send(
            $this->request(
                'DELETE',
                '/api/fof/antispam/question/1',
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
    public function delete_non_existing_question_fails()
    {
        $response = $this->send(
            $this->request(
                'DELETE',
                '/api/fof/antispam/question/2',
                [
                    'authenticatedAs' => 1,
                ]
            )
        );

        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function admin_can_delete_questions()
    {
        $response = $this->send(
            $this->request(
                'DELETE',
                '/api/fof/antispam/question/1',
                [
                    'authenticatedAs' => 1,
                ]
            )
        );

        $this->assertEquals(204, $response->getStatusCode());

        $challenge = ChallengeQuestion::find(1);

        $this->assertNull($challenge);
    }
}

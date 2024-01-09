<?php

namespace FoF\AntiSpam\Api\Controllers;

use Flarum\Foundation\ValidationException;
use FoF\AntiSpam\Model\ChallengeQuestion;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AnswerChallengeQuestionController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $params = Arr::get($request->getParsedBody(), 'data.attributes', []);

        $token = ChallengeQuestion::validateAnswer(Arr::get($params, 'challengeId'), Arr::get($params, 'answer'));

        if (is_null($token)) {
            throw new ValidationException([
                'answer' => 'Invalid answer',
            ]);
        }
        return new JsonResponse([
            'token' => $token,
        ]);
    }
}

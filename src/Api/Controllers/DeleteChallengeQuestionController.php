<?php

namespace FoF\AntiSpam\Api\Controllers;

use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Http\RequestUtil;
use FoF\AntiSpam\Model\ChallengeQuestion;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class DeleteChallengeQuestionController extends AbstractDeleteController
{
    public function delete(ServerRequestInterface $request): void
    {
        $actor = RequestUtil::getActor($request);

        $actor->assertAdmin();

        $id = (int) Arr::get($request->getQueryParams(), 'id');

        $challenge = ChallengeQuestion::findOrFail($id);

        $challenge->delete();
    }
}

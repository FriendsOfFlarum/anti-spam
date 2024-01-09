<?php

namespace FoF\AntiSpam\Api\Controllers;

use Flarum\Api\Controller\AbstractShowController;
use FoF\AntiSpam\Api\Serializers\BasicChallengeQuestionSerializer;
use FoF\AntiSpam\Model\ChallengeQuestion;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ShowChallengeQuestionController extends AbstractShowController
{
    public $serializer = BasicChallengeQuestionSerializer::class;

    public function data(ServerRequestInterface $request, Document $document)
    {
        // TODO: get a random active challenge question
        return ChallengeQuestion::first();
    }
}

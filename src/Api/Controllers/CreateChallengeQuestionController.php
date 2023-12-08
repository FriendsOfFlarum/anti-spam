<?php

namespace FoF\AntiSpam\Api\Controllers;

use Flarum\Api\Controller\AbstractCreateController;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;
use FoF\AntiSpam\Command\CreateChallengeQuestion;
use FoF\AntiSpam\Api\Serializers\ChallengeQuestionSerializer;

class CreateChallengeQuestionController extends AbstractCreateController
{
    public $serializer = ChallengeQuestionSerializer::class;

    protected $bus;

    public function __construct(Dispatcher $bus)
    {
        $this->bus = $bus;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);
        $data = Arr::get($request->getParsedBody(), 'data', []);
        
        $model = $this->bus->dispatch(
            new CreateChallengeQuestion($actor, $data)
        );
        
        return $model;
    }
}

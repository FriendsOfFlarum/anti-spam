<?php

namespace FoF\AntiSpam\Api\Controllers;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;
use FoF\AntiSpam\Api\Serializers\ChallengeQuestionSerializer;
use FoF\AntiSpam\Command\UpdateChallengeQuestion;

class UpdateChallengeQuestionController extends AbstractShowController
{
    public $serializer = ChallengeQuestionSerializer::class;

    /**
     * @var Dispatcher
     */
    protected $bus;

    public function __construct(Dispatcher $bus)
    {
        $this->bus = $bus;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        $actor = RequestUtil::getActor($request);

        $modelId = (int) Arr::get($request->getQueryParams(), 'id');
        $data = Arr::get($request->getParsedBody(), 'data', []);
        
        return $this->bus->dispatch(
            new UpdateChallengeQuestion($modelId, $actor, $data)
        );
    }
}

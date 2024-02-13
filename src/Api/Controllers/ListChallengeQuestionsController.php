<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Api\Controllers;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use FoF\AntiSpam\Api\Serializers\ChallengeQuestionSerializer;
use FoF\AntiSpam\Model\ChallengeQuestion;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListChallengeQuestionsController extends AbstractListController
{
    public $serializer = ChallengeQuestionSerializer::class;

    /**
     * @var UrlGenerator
     */
    protected $url;

    /**
     * @param UrlGenerator $url
     */
    public function __construct(UrlGenerator $url)
    {
        $this->url = $url;
    }

    protected function data(ServerRequestInterface $request, Document $document)
    {
        RequestUtil::getActor($request)->assertAdmin();

        $limit = $this->extractLimit($request);
        $offset = $this->extractOffset($request);

        $query = ChallengeQuestion::query();

        $totalItems = $query->count();
        $results = $query->orderBy('id', 'desc')->skip($offset)->take($limit)->get();

        $document->addPaginationLinks(
            $this->url->to('api')->route('fof-anti-spam.question.index'),
            $request->getQueryParams(),
            $offset,
            $limit,
            $totalItems - ($offset + $limit) > 0 ? null : 0
        );

        return $results;
    }
}

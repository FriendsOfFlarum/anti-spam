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

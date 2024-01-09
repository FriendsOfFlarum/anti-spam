<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Api\Serializers;

use Flarum\Api\Serializer\AbstractSerializer;
use Flarum\Http\RequestUtil;

class BasicChallengeQuestionSerializer extends AbstractSerializer
{
    protected $type = 'challenge-questions';

    /**
     * Get the default set of serialized attributes for a model.
     *
     * @param \FoF\AntiSpam\Model\ChallengeQuestion $model
     *
     * @return array
     */
    protected function getDefaultAttributes($model)
    {
        return [
            'question' => $model->question,
        ];
    }
}

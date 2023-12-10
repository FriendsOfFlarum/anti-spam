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

class ChallengeQuestionSerializer extends AbstractSerializer
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
        $data = [
            'question' => $model->question,
        ];

        if (RequestUtil::getActor($this->request)->isAdmin()) {
            $data['answer'] = $model->answer;
            $data['caseSensitive'] = $model->case_sensitive;
            $data['isActive'] = $model->is_active;
            $data['createdAt'] = $this->formatDate($model->created_at);
            $data['updatedAt'] = $this->formatDate($model->updated_at);
        }

        return $data;
    }
}

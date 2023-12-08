<?php

namespace FoF\AntiSpam\Api\Serializers;

use Flarum\Api\Serializer\AbstractSerializer;

class ChallengeQuestionSerializer extends AbstractSerializer
{
    protected $type = 'challenge_questions';

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
            'question'       => $model->question,
            'answer'         => $model->answer,
            'caseSensitive' => $model->case_sensitive,
            'isActive'      => $model->is_active,
        ];
    }
}

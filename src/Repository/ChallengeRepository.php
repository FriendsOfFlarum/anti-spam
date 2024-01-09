<?php

namespace FoF\AntiSpam\Repository;

use Flarum\Settings\SettingsRepositoryInterface;
use FoF\AntiSpam\Model\ChallengeQuestion;

class ChallengeRepository
{
    protected $settings;
    
    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function challengeEnabled(): bool
    {
        return ((bool) $this->settings->get('fof-anti-spam.ask-challenge-questions')) && ChallengeQuestion::query()->where('is_active', true)->count() > 0;
    }
}

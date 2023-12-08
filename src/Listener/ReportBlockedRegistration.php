<?php

namespace FoF\AntiSpam\Listener;

use Flarum\Settings\SettingsRepositoryInterface;
use FoF\AntiSpam\Event\RegistrationWasBlocked;
use FoF\AntiSpam\Job\ReportSpammerJob;
use FoF\AntiSpam\StopForumSpam;
use Illuminate\Contracts\Queue\Queue;

class ReportBlockedRegistration
{
    protected $settings;
    protected $sfs;
    protected $queue;
    
    public function __construct(SettingsRepositoryInterface $settings, StopForumSpam $sfs, Queue $queue)
    {
        $this->settings = $settings;
        $this->sfs = $sfs;
        $this->queue = $queue;
    }

    public function handle(RegistrationWasBlocked $event): void
    {
        if ($this->settings->get('fof-anti-spam.report_blocked_registrations') && $this->sfs->isEnabled()) {
            $blocked = $event->blocked;
            $this->queue->push(
                new ReportSpammerJob(
                    $blocked->username,
                    $blocked->email,
                    $blocked->ip
                )
            );
        }
    }
}

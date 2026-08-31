<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\integration;

use Carbon\Carbon;
use Flarum\Flags\Flag;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * A post flagged by the content filter leaves a record that outlives the flag.
 *
 * flarum/flags hard-deletes a flag when it is dismissed, and again when its post is deleted —
 * which is exactly what marking the author as a spammer does. Counting that table would
 * therefore report the open queue while claiming to report the filter's work, and would fall
 * as moderators cleared it. The durable record is an audit action this extension registers.
 */
class SpamFlagAuditTest extends TestCase
{
    protected function setup(): void
    {
        parent::setup();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam', 'flarum-audit');

        $this->prepareDatabase([
            User::class => [
                ['id' => 3, 'username' => 'new_user', 'email' => 'new@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()],
            ],
        ]);
    }

    private function postSpam(): void
    {
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 20);

        $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => [
                            'title' => 'Cheap deals',
                            'content' => 'Call me on 07700 900123 or visit https://suspicious-site.example for cheap deals',
                        ],
                    ],
                ],
            ])
        );
    }

    private function auditedFlaggings(): int
    {
        return (int) $this->database()
            ->table('audit_log')
            ->where('action', 'post.flagged_as_spam')
            ->count();
    }

    #[Test]
    public function flagging_a_post_is_recorded_in_the_audit_log()
    {
        $this->app();

        $this->assertSame(0, $this->auditedFlaggings());

        $this->postSpam();

        $this->assertSame(1, $this->auditedFlaggings(), 'The flagging should have been audited');
    }

    #[Test]
    public function the_audited_record_survives_the_flag_being_dismissed()
    {
        $this->app();

        $this->postSpam();

        $this->assertSame(1, $this->auditedFlaggings());
        $this->assertGreaterThan(0, Flag::where('type', 'spam')->count(), 'The post should have been flagged');

        // What dismissing does: flarum/flags removes the rows outright.
        Flag::where('type', 'spam')->delete();

        $this->assertSame(0, Flag::where('type', 'spam')->count(), 'Dismissal deletes the flag');
        $this->assertSame(1, $this->auditedFlaggings(), 'But the record of the flagging must remain');
    }

    #[Test]
    public function the_audited_record_carries_the_score_that_triggered_it()
    {
        $this->app();

        $this->postSpam();

        $payload = json_decode(
            (string) $this->database()->table('audit_log')->where('action', 'post.flagged_as_spam')->value('payload'),
            true
        );

        $this->assertArrayHasKey('post_id', $payload);
        $this->assertArrayHasKey('discussion_id', $payload);
        // The score is what an admin needs to judge whether their threshold is set sensibly.
        $this->assertGreaterThan(0, $payload['score']);
    }
}

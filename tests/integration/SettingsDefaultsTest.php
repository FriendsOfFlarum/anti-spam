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

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * extend.php is the only place a default may be declared.
 *
 * Defaults used to be restated inline at every read site — `get('spam_threshold', 70)` while
 * extend.php said 50 — and again in the admin JS. Those copies drift, and the inline one is dead
 * anyway because the settings extender already answers first. This locks the arrangement in: every
 * key the extension reads has to resolve to a non-null value with nothing stored in the database,
 * which is only true if extend.php declares it.
 */
class SettingsDefaultsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function settingKeys(): iterable
    {
        $keys = [
            // StopForumSpam
            'fof-anti-spam.regionalEndpoint',
            'fof-anti-spam.sfs-lookup',
            'fof-anti-spam.username',
            'fof-anti-spam.ip',
            'fof-anti-spam.email',
            'fof-anti-spam.emailhash',
            'fof-anti-spam.frequency',
            'fof-anti-spam.confidence',
            'fof-anti-spam.blockTorExitNodes',
            'fof-anti-spam.reportToStopForumSpam',
            'fof-anti-spam.report_blocked_registrations',

            // Spamblock actions
            'fof-anti-spam.actions.deleteUser',
            'fof-anti-spam.actions.deletePosts',
            'fof-anti-spam.actions.deleteDiscussions',

            // Content filter
            'fof-anti-spam.moderation.system_user_id',
            'fof-anti-spam.content-filter.enabled',
            'fof-anti-spam.content-filter.monitor_post_count',
            'fof-anti-spam.content-filter.monitor_hours_old',
            'fof-anti-spam.content-filter.detect_phones',
            'fof-anti-spam.content-filter.detect_emails',
            'fof-anti-spam.content-filter.detect_urls',
            'fof-anti-spam.content-filter.spam_threshold',
            'fof-anti-spam.content-filter.flag_threshold',
            'fof-anti-spam.content-filter.auto_unapprove',
            'fof-anti-spam.content-filter.auto_flag',
            'fof-anti-spam.content-filter.allowed_domains',
            'fof-anti-spam.content-filter.blocked_words',
            'fof-anti-spam.content-filter.advanced_block_patterns',
        ];

        foreach ($keys as $key) {
            yield $key => [$key];
        }
    }

    #[Test]
    #[DataProvider('settingKeys')]
    public function every_setting_has_a_default_declared_in_extend_php(string $key): void
    {
        $settings = $this->app()->getContainer()->make(SettingsRepositoryInterface::class);

        $this->assertNotNull(
            $settings->get($key),
            "'$key' has no default. Declare it on the Settings extender in extend.php — never as an inline fallback at the read site."
        );
    }

    #[Test]
    public function flagging_starts_below_hiding_so_weak_signals_can_be_reviewed_rather_than_hidden(): void
    {
        $settings = $this->app()->getContainer()->make(SettingsRepositoryInterface::class);

        $flag = (int) $settings->get('fof-anti-spam.content-filter.flag_threshold');
        $spam = (int) $settings->get('fof-anti-spam.content-filter.spam_threshold');

        $this->assertLessThan(
            $spam,
            $flag,
            'With both thresholds equal there is no band in which content is flagged for review without also being hidden'
        );
    }
}

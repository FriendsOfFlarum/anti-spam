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
use Flarum\Discussion\Discussion;
use Flarum\Flags\Flag;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;

/**
 * A spam flag names what tripped it, so a moderator can tell even after the author has edited the
 * post.
 */
class FlagDetailTest extends TestCase
{
    protected function setup(): void
    {
        parent::setup();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $this->prepareDatabase([
            User::class => [
                ['id' => 3, 'username' => 'new_user', 'email' => 'new@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Welcome', 'created_at' => Carbon::now()->subYear(), 'user_id' => 1, 'first_post_id' => 1, 'comment_count' => 1, 'is_approved' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Welcome</p></t>', 'number' => 1, 'created_at' => Carbon::now()->subYear(), 'is_approved' => 1],
            ],
        ]);

        $this->setting('fof-anti-spam.content-filter.monitor_post_count', 100);
        $this->setting('fof-anti-spam.content-filter.flag_threshold', 20);
    }

    private function detailFor(string $content): string
    {
        $response = $this->send(
            $this->request('POST', '/api/posts', [
                'authenticatedAs' => 3,
                'json' => ['data' => [
                    'type' => 'posts',
                    'attributes' => ['content' => $content],
                    'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => '1']]],
                ]],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        $post = Post::where('user_id', 3)->orderBy('id', 'desc')->firstOrFail();

        return Flag::where('post_id', $post->id)->where('type', 'spam')->firstOrFail()->reason_detail;
    }

    private function edit(int $postId, string $content): Post
    {
        // Out of core's flood window, which an edit straight after posting would hit.
        $this->app();
        Post::where('id', $postId)->update(['created_at' => Carbon::now()->subMinute(), 'edited_at' => null]);

        $response = $this->send(
            $this->request('PATCH', "/api/posts/$postId", [
                'authenticatedAs' => 3,
                'json' => ['data' => ['type' => 'posts', 'id' => (string) $postId, 'attributes' => ['content' => $content]]],
            ])
        );

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());

        return Post::findOrFail($postId);
    }

    private function latestPost(): Post
    {
        return Post::where('user_id', 3)->orderBy('id', 'desc')->firstOrFail();
    }

    #[Test]
    public function an_edit_that_removes_the_link_keeps_the_flag_and_notes_it()
    {
        $this->detailFor('Look at my site https://www.spam-site.com');
        $post = $this->edit($this->latestPost()->id, 'Look at my site');

        $flags = Flag::where('post_id', $post->id)->where('type', 'spam')->get();
        $this->assertCount(1, $flags);
        $this->assertStringContainsString('https://www.spam-site.com', $flags[0]->reason_detail);
        $this->assertStringContainsString('trips no content check', $flags[0]->reason_detail);

        // Still held: a moderator decides, not the edit.
        $this->assertFalse((bool) $post->is_approved);
    }

    #[Test]
    public function an_edit_that_adds_another_link_names_it_on_the_same_flag()
    {
        $this->detailFor('Look at my site https://www.spam-site.com');
        $post = $this->edit($this->latestPost()->id, 'Look at my other site https://other-spam.com');

        $flags = Flag::where('post_id', $post->id)->where('type', 'spam')->get();
        $this->assertCount(1, $flags);
        $this->assertStringContainsString('https://www.spam-site.com', $flags[0]->reason_detail);
        $this->assertStringContainsString('https://other-spam.com', $flags[0]->reason_detail);
        $this->assertStringNotContainsString('trips no content check', $flags[0]->reason_detail);
    }

    #[Test]
    public function an_edit_by_an_account_no_longer_monitored_is_not_called_clean()
    {
        $this->detailFor('Look at my site https://www.spam-site.com');
        $postId = $this->latestPost()->id;

        // The app has booted by now, so setting() would no longer reach it.
        $settings = $this->app()->getContainer()->make(SettingsRepositoryInterface::class);
        $settings->set('fof-anti-spam.content-filter.monitor_post_count', 5);
        $settings->set('fof-anti-spam.content-filter.monitor_hours_old', 0);
        User::where('id', 3)->update(['comment_count' => 100, 'joined_at' => Carbon::now()->subYear()]);

        $this->edit($postId, 'Look at my site');

        $detail = Flag::where('post_id', $postId)->where('type', 'spam')->firstOrFail()->reason_detail;
        $this->assertStringNotContainsString('trips no content check', $detail);
        $this->assertStringContainsString('not re-checked', $detail);
    }

    #[Test]
    public function a_run_of_edits_keeps_the_first_detection_and_the_latest_notes()
    {
        $this->detailFor('Look at my site https://www.spam-site.com');
        $postId = $this->latestPost()->id;

        foreach (range(1, 7) as $i) {
            $this->edit($postId, "Look at my site, take {$i}, https://take{$i}-spam.com");
        }

        $detail = Flag::where('post_id', $postId)->where('type', 'spam')->firstOrFail()->reason_detail;

        $this->assertStringStartsWith('[URL Detector] Contains a URL to a non-allowlisted domain: https://www.spam-site.com', $detail);
        $this->assertSame(5, substr_count($detail, "\n\nEdited "));
        $this->assertStringNotContainsString('take2-spam.com', $detail);
        $this->assertStringContainsString('take3-spam.com', $detail);
        $this->assertStringContainsString('take7-spam.com', $detail);
    }

    #[Test]
    public function an_edit_while_the_filter_is_off_adds_no_note()
    {
        $this->detailFor('Look at my site https://www.spam-site.com');
        $postId = $this->latestPost()->id;

        $this->app()->getContainer()->make(SettingsRepositoryInterface::class)
            ->set('fof-anti-spam.content-filter.enabled', false);

        $this->edit($postId, 'Look at my site');

        $detail = Flag::where('post_id', $postId)->where('type', 'spam')->firstOrFail()->reason_detail;
        $this->assertStringNotContainsString('Edited', $detail);
    }

    #[Test]
    public function a_match_cut_inside_a_character_is_stored_as_valid_text()
    {
        // No `u` modifier: the match counts bytes, and the space and 5 more end inside the third "é".
        $this->setting('fof-anti-spam.content-filter.advanced_block_patterns', json_encode([
            ['pattern' => '/casino.{0,6}/i', 'description' => 'Casino pitch'],
        ]));

        $detail = $this->detailFor('casino ééééé');

        $this->assertTrue(mb_check_encoding($detail, 'UTF-8'));
        $this->assertStringContainsString('Casino pitch: "casino é', $detail);
    }

    #[Test]
    public function a_very_long_match_is_shortened()
    {
        $host = str_repeat('a', 300).'.com';

        $detail = $this->detailFor("https://{$host}");

        $this->assertStringNotContainsString($host, $detail);
        $this->assertStringContainsString('https://'.str_repeat('a', 92).'…', $detail);
    }

    #[Test]
    public function url_flag_names_the_link_and_not_the_allowed_ones()
    {
        $detail = $this->detailFor('My upload https://cdn.localhost/a.png and my site https://www.spam-site.com/page');

        $this->assertStringContainsString('https://www.spam-site.com', $detail);
        $this->assertStringNotContainsString('cdn.localhost', $detail);
    }

    #[Test]
    public function url_flag_lists_each_link_once()
    {
        $detail = $this->detailFor('https://one-spam.com and https://two-spam.com and https://one-spam.com again');

        $this->assertStringContainsString('https://one-spam.com, https://two-spam.com', $detail);
        $this->assertSame(1, substr_count($detail, 'one-spam.com'));
    }

    #[Test]
    public function url_flag_shortens_a_long_list()
    {
        $links = implode(' ', array_map(fn (int $i) => "https://spam{$i}.com", range(1, 8)));

        $detail = $this->detailFor($links);

        $this->assertStringContainsString('https://spam5.com', $detail);
        $this->assertStringNotContainsString('https://spam6.com', $detail);
        $this->assertStringContainsString('and 3 more', $detail);
    }

    #[Test]
    public function email_flag_names_the_address()
    {
        $this->assertStringContainsString('sales@spam-site.com', $this->detailFor('Write to sales@spam-site.com today'));
    }

    #[Test]
    public function phone_flag_names_the_number()
    {
        $this->assertStringContainsString('+1234567890', $this->detailFor('Call +1234567890 now'));
    }

    #[Test]
    public function described_pattern_flag_names_the_matched_text()
    {
        $this->setting('fof-anti-spam.content-filter.advanced_block_patterns', json_encode([
            ['pattern' => '/cheap\s+\w+/i', 'description' => 'Sales pitch'],
        ]));

        $detail = $this->detailFor('Buy cheap watches here');

        $this->assertStringContainsString('Sales pitch', $detail);
        $this->assertStringContainsString('cheap watches', $detail);
    }

    #[Test]
    public function an_undescribed_pattern_names_the_alternative_that_matched()
    {
        $this->setting('fof-anti-spam.content-filter.advanced_block_patterns', json_encode([
            ['pattern' => '/casino|lottery/i'],
        ]));

        $this->assertStringContainsString('Matched pattern: /casino|lottery/i: "lottery"', $this->detailFor('Win the lottery'));
    }

    #[Test]
    public function an_edit_that_adds_a_link_to_an_unflagged_post_flags_it()
    {
        $this->prepareDatabase([
            Post::class => [
                ['id' => 100, 'discussion_id' => 1, 'user_id' => 3, 'type' => 'comment', 'content' => '<t><p>Hello there</p></t>', 'number' => 2, 'created_at' => Carbon::now()->subHour(), 'is_approved' => 1],
            ],
        ]);

        $this->edit(100, 'Hello there, see https://www.spam-site.com');

        $detail = Flag::where('post_id', 100)->where('type', 'spam')->firstOrFail()->reason_detail;
        $this->assertStringContainsString('https://www.spam-site.com', $detail);
        $this->assertStringNotContainsString('Edited', $detail);
    }

    #[Test]
    public function a_matched_line_separator_cannot_start_a_line_of_its_own()
    {
        $this->setting('fof-anti-spam.content-filter.advanced_block_patterns', json_encode([
            ['pattern' => '/win.{0,30}/u', 'description' => 'Prize'],
        ]));

        $detail = $this->detailFor("win\u{2028}\u{2028}Edited 2026-01-01 00:00 UTC: fine");

        $this->assertStringNotContainsString("\u{2028}", $detail);
        $this->assertStringContainsString('Prize: "win Edited', $detail);
    }
}

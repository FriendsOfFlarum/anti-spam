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
use Flarum\Group\Group;
use Flarum\Post\Post;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\AntiSpam\Extend\ContentFilter;
use PHPUnit\Framework\Attributes\Test;

/**
 * The README documents assignFlagsToModerator() as the way to choose who automatic flags are
 * raised by. It wrote a key nothing read: the flag author came from the admin setting regardless.
 */
class FlagModeratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $this->prepareDatabase([
            User::class => [
                ['id' => 3, 'username' => 'new_user', 'email' => 'new@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()],
                ['id' => 4, 'username' => 'the_bot', 'email' => 'bot@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()->subYear()],
            ],
        ]);
    }

    private function postSpam(): Post
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => [
                    'data' => [
                        'type' => 'discussions',
                        'attributes' => ['title' => 'Hello there', 'content' => 'Buy at https://suspicious-site.com now'],
                    ],
                ],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        return Post::where('user_id', 3)->orderBy('id', 'desc')->first();
    }

    #[Test]
    public function the_moderator_chosen_in_code_raises_the_flag()
    {
        $this->extend((new ContentFilter())->assignFlagsToModerator(4));

        $flag = Flag::where('post_id', $this->postSpam()->id)->where('type', 'spam')->first();

        $this->assertNotNull($flag);
        $this->assertEquals(4, $flag->user_id, 'assignFlagsToModerator() should decide who raises the flag');
    }

    #[Test]
    public function the_admin_setting_is_used_when_code_says_nothing()
    {
        $this->setting('fof-anti-spam.moderation.system_user_id', 4);

        $flag = Flag::where('post_id', $this->postSpam()->id)->where('type', 'spam')->first();

        $this->assertNotNull($flag);
        $this->assertEquals(4, $flag->user_id);
    }

    #[Test]
    public function a_flagging_account_that_no_longer_exists_falls_back_to_the_first_admin()
    {
        // The setting defaults to user 1, which plenty of forums have since deleted. Skipping the
        // flag then left nothing in the moderation queue while the post was quietly held.
        $this->prepareDatabase([
            User::class => [
                ['id' => 7, 'username' => 'second_admin', 'email' => 'admin7@machine.local', 'is_email_confirmed' => 1],
                ['id' => 6, 'username' => 'first_admin', 'email' => 'admin6@machine.local', 'is_email_confirmed' => 1],
            ],
            'group_user' => [
                ['user_id' => 7, 'group_id' => Group::ADMINISTRATOR_ID],
                ['user_id' => 6, 'group_id' => Group::ADMINISTRATOR_ID],
            ],
        ]);
        $this->setting('fof-anti-spam.moderation.system_user_id', 999);
        $this->app();

        // User 1 is still around but no longer an administrator, so the fallback has to look the
        // administrators up rather than assume the root account.
        User::find(1)->groups()->detach(Group::ADMINISTRATOR_ID);

        $flag = Flag::where('post_id', $this->postSpam()->id)->where('type', 'spam')->first();

        $this->assertNotNull($flag, 'The flag must still be raised');
        $this->assertEquals(6, $flag->user_id, 'The lowest-id administrator raises it');
    }

    #[Test]
    public function a_flagging_account_set_in_code_that_does_not_exist_falls_back_too()
    {
        $this->extend((new ContentFilter())->assignFlagsToModerator(999));

        $flag = Flag::where('post_id', $this->postSpam()->id)->where('type', 'spam')->first();

        $this->assertNotNull($flag);
        $this->assertEquals(1, $flag->user_id);
    }

    #[Test]
    public function code_wins_over_the_admin_setting()
    {
        $this->setting('fof-anti-spam.moderation.system_user_id', 1);
        $this->extend((new ContentFilter())->assignFlagsToModerator(4));

        $flag = Flag::where('post_id', $this->postSpam()->id)->where('type', 'spam')->first();

        $this->assertNotNull($flag);
        $this->assertEquals(4, $flag->user_id);
    }
}

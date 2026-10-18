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
        $this->setting('fof-anti-spam.moderation.system_user_id', 999);
        $this->twoAdminsAndUserOneDemoted();

        $flag = Flag::where('post_id', $this->postSpam()->id)->where('type', 'spam')->first();

        $this->assertNotNull($flag, 'The flag must still be raised');
        $this->assertEquals(6, $flag->user_id, 'The lowest-id administrator raises it');
    }

    /**
     * Two administrators, neither of them user 1, so the default is visibly looked up.
     */
    private function twoAdminsAndUserOneDemoted(): void
    {
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
        $this->app();
        User::find(1)->groups()->detach(Group::ADMINISTRATOR_ID);
    }

    #[Test]
    public function with_no_account_chosen_the_first_admin_raises_the_flag()
    {
        // Nobody should have to know a user ID: left alone, the setting means the first admin.
        $this->twoAdminsAndUserOneDemoted();

        $flag = Flag::where('post_id', $this->postSpam()->id)->where('type', 'spam')->first();

        $this->assertNotNull($flag);
        $this->assertEquals(6, $flag->user_id);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function flagAccountSeenBy(int $actor): ?array
    {
        $response = $this->send($this->request('GET', '/api', ['authenticatedAs' => $actor]));

        return json_decode((string) $response->getBody(), true)['data']['attributes']['fof-anti-spam.flagAccount'] ?? null;
    }

    #[Test]
    public function an_admin_is_shown_the_default_account_by_name()
    {
        $this->twoAdminsAndUserOneDemoted();

        $account = $this->flagAccountSeenBy(6);

        $this->assertSame('6', $account['id'] ?? null);
        $this->assertSame('first_admin', $account['displayName'] ?? null);
        $this->assertTrue($account['isDefault'] ?? null);
        $this->assertFalse($account['configuredMissing'] ?? null);
    }

    #[Test]
    public function an_admin_is_shown_the_chosen_account_by_name()
    {
        $this->setting('fof-anti-spam.moderation.system_user_id', 4);

        $account = $this->flagAccountSeenBy(1);

        $this->assertSame('4', $account['id'] ?? null);
        $this->assertSame('the_bot', $account['displayName'] ?? null);
        $this->assertFalse($account['isDefault'] ?? null);
    }

    #[Test]
    public function an_admin_is_told_when_the_chosen_account_no_longer_exists()
    {
        $this->setting('fof-anti-spam.moderation.system_user_id', 999);

        $account = $this->flagAccountSeenBy(1);

        $this->assertTrue($account['configuredMissing'] ?? null);
        $this->assertSame('1', $account['id'] ?? null, 'and who is raising the flags instead');
    }

    #[Test]
    public function the_flagging_account_is_not_shown_to_members()
    {
        $this->assertNull($this->flagAccountSeenBy(3));
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

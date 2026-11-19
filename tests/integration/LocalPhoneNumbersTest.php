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
use Flarum\Post\Post;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use FoF\AntiSpam\ContentFilter\ConfigurationManager;
use FoF\AntiSpam\Extend\ContentFilter;
use PHPUnit\Framework\Attributes\Test;

/**
 * Phone numbers written for callers inside one country, which have no `+` or `00` to find.
 */
class LocalPhoneNumbersTest extends TestCase
{
    /**
     * One of the posts that got through on a live forum, word for word.
     */
    private const string SPAM = 'Cara Mengatasi Salah Transfer BCA Segera laporkan ke layanan BCA WhatsApp Halo BCA 0811-2506-6288 resmi BCA atau 1500888 Siapkan bukti transfer seperti mutasi rekening atau struk.';

    protected function setup(): void
    {
        parent::setup();

        $this->extension('flarum-flags', 'flarum-approval', 'fof-anti-spam');

        $this->prepareDatabase([
            User::class => [
                ['id' => 3, 'username' => 'new_user', 'email' => 'new@machine.local', 'is_email_confirmed' => 1, 'joined_at' => Carbon::now()],
            ],
        ]);
    }

    private function freshUserPosts(string $content): Post
    {
        $response = $this->send(
            $this->request('POST', '/api/discussions', [
                'authenticatedAs' => 3,
                'json' => ['data' => ['type' => 'discussions', 'attributes' => [
                    'title' => 'Hello there',
                    'content' => $content,
                ]]],
            ])
        );

        $this->assertEquals(201, $response->getStatusCode(), (string) $response->getBody());

        return Post::where('user_id', 3)->orderBy('id', 'desc')->firstOrFail();
    }

    #[Test]
    public function local_numbers_are_not_looked_for_unless_asked()
    {
        $post = $this->freshUserPosts(self::SPAM);

        $this->assertTrue((bool) $post->is_approved);
        $this->assertNull(Flag::where('post_id', $post->id)->first());
    }

    #[Test]
    public function a_local_number_is_held_at_the_default_thresholds_once_turned_on()
    {
        $this->setting('fof-anti-spam.content-filter.detect_local_phones', true);

        $post = $this->freshUserPosts(self::SPAM);

        $this->assertFalse((bool) $post->is_approved);

        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->firstOrFail();
        $this->assertStringContainsString('phone number', $flag->reason_detail);
    }

    #[Test]
    public function local_numbers_are_not_looked_for_when_phone_numbers_are_not()
    {
        $this->setting('fof-anti-spam.content-filter.detect_local_phones', true);
        $this->setting('fof-anti-spam.content-filter.detect_phones', false);

        $post = $this->freshUserPosts(self::SPAM);

        $this->assertTrue((bool) $post->is_approved);
    }

    #[Test]
    public function an_international_number_with_its_trunk_zero_counts_once()
    {
        // Flag only, then read the count: counted twice it would also cross the hiding threshold.
        $this->setting('fof-anti-spam.content-filter.detect_local_phones', true);
        $this->setting('fof-anti-spam.content-filter.spam_threshold', 90);

        $post = $this->freshUserPosts('WhatsApp +62 0811-2506-6288 sekarang');

        $flag = Flag::where('post_id', $post->id)->where('type', 'spam')->firstOrFail();
        $this->assertSame('50', $flag->reason);
    }

    #[Test]
    public function local_numbers_can_be_turned_on_in_code()
    {
        $this->extend((new ContentFilter())->blockLocalPhoneNumbers());

        $config = $this->app()->getContainer()->make(ConfigurationManager::class);

        $this->assertTrue($config->get('detect_local_phones'));
        $this->assertTrue($config->toArray()['detectLocalPhones']['isCodeConfigured']);
    }
}

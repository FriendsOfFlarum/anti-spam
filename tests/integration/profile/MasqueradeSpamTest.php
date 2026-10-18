<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Tests\integration\profile;

use Flarum\Group\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

/**
 * Masquerade answers marked "on bio" are shown beside every post their author makes.
 */
class MasqueradeSpamTest extends ProfileFieldTestCase
{
    protected function owningExtension(): ?string
    {
        return 'fof-masquerade';
    }

    protected function permissions(): array
    {
        return [
            ['group_id' => Group::MEMBER_ID, 'permission' => 'fof.masquerade.have-profile'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase([
            'fof_masquerade_fields' => [
                ['id' => 1, 'name' => 'About me', 'type' => null, 'on_bio' => true],
                ['id' => 2, 'name' => 'Website', 'type' => 'url', 'on_bio' => true],
                ['id' => 3, 'name' => 'Location', 'type' => null, 'on_bio' => false],
                ['id' => 4, 'name' => 'Contact', 'type' => 'email', 'on_bio' => false],
                ['id' => 5, 'name' => 'Available', 'type' => 'boolean', 'on_bio' => false],
            ],
        ]);
    }

    /**
     * @param array<int, string> $answers
     */
    private function configure(int $userId, array $answers, int $actor): ResponseInterface
    {
        return $this->send(
            $this->request('POST', '/api/masquerade-answers/configure/'.$userId, [
                'authenticatedAs' => $actor,
                'json' => $answers,
            ])
        );
    }

    private function answer(int $userId, int $fieldId): ?string
    {
        return $this->database()->table('fof_masquerade_answers')
            ->where('user_id', $userId)->where('field_id', $fieldId)->value('content');
    }

    #[Test]
    public function a_spam_answer_is_refused_and_not_stored()
    {
        $response = $this->configure(3, [1 => 'Cheap pills, mail me at spammer@suspicious-site.com'], 3);

        $this->assertEquals(422, $response->getStatusCode(), (string) $response->getBody());
        $this->assertNull($this->answer(3, 1));
    }

    #[Test]
    public function one_spam_answer_refuses_the_whole_save()
    {
        // Masquerade saves every answer in one transaction. Keeping the clean ones would leave a
        // half-saved profile the user was told had failed. The clean answer is on the field saved
        // first, so it has been written by the time the spam one is refused.
        $response = $this->configure(3, [1 => 'Long-time lurker.', 3 => 'Reach me on spammer@suspicious-site.com'], 3);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertNull($this->answer(3, 1));
    }

    #[Test]
    public function an_address_in_an_email_field_is_what_the_field_is_for()
    {
        $response = $this->configure(3, [4 => 'me@my-own-domain.net'], 3);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function a_yes_or_no_answer_is_not_analysed()
    {
        $response = $this->configure(3, [5 => '1'], 3);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function an_unchanged_answer_is_not_checked_again()
    {
        // A value stored before this check existed must not lock the user out of saving the rest
        // of their profile.
        $this->prepareDatabase([
            'fof_masquerade_answers' => [
                ['id' => 1, 'field_id' => 1, 'user_id' => 3, 'content' => 'Mail me at old@suspicious-site.com'],
            ],
        ]);

        $response = $this->configure(3, [1 => 'Mail me at old@suspicious-site.com', 3 => 'Leeds'], 3);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame('Leeds', $this->answer(3, 3));
    }

    #[Test]
    public function a_moderator_writing_spam_into_a_new_users_profile_is_refused_too()
    {
        // The answer's owner is judged, since no actor reaches a model event. A moderator has no
        // reason to write spam into a profile, and blanking a field is never refused.
        $this->prepareDatabase([
            'group_permission' => [
                ['group_id' => Group::MODERATOR_ID, 'permission' => 'fof.masquerade.edit-others-profile'],
                ['group_id' => Group::MODERATOR_ID, 'permission' => 'fof.masquerade.view-profile'],
            ],
            'group_user' => [['user_id' => 4, 'group_id' => Group::MODERATOR_ID]],
        ]);

        $this->assertEquals(422, $this->configure(3, [1 => 'Mail me at spammer@suspicious-site.com'], 4)->getStatusCode());
        $this->assertEquals(200, $this->configure(3, [1 => ''], 4)->getStatusCode());
    }

    #[Test]
    public function an_ordinary_answer_is_accepted()
    {
        $response = $this->configure(3, [1 => 'Long-time lurker, here for the tags discussion.'], 3);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertSame('Long-time lurker, here for the tags discussion.', $this->answer(3, 1));
    }

    #[Test]
    public function a_link_in_a_url_field_is_what_the_field_is_for()
    {
        // A website field exists to hold an off-site link, so the URL detector has nothing to say
        // about it. Blocked words still apply.
        $response = $this->configure(3, [2 => 'https://my-own-blog.net'], 3);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
    }

    #[Test]
    public function a_blocked_word_in_a_url_field_is_still_refused()
    {
        $this->setting('fof-anti-spam.content-filter.blocked_words', 'viagra');

        $response = $this->configure(3, [2 => 'https://viagra.example.net'], 3);

        $this->assertEquals(422, $response->getStatusCode());
    }

    #[Test]
    public function an_established_member_is_not_checked()
    {
        $response = $this->configure(4, [1 => 'Mail me at someone@suspicious-site.com'], 4);

        $this->assertEquals(200, $response->getStatusCode(), (string) $response->getBody());
    }
}

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
        // half-saved profile the user was told had failed.
        $response = $this->configure(3, [2 => 'https://example.org', 1 => 'Reach me on spammer@suspicious-site.com'], 3);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertNull($this->answer(3, 2));
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
        $response = $this->configure(3, [2 => 'https://my-own-blog.example.net'], 3);

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

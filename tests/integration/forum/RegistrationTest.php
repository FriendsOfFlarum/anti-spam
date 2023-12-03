<?php

namespace FoF\AntiSpam\Tests\integration\forum;

use Flarum\Extend;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;

class RegistrationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-anti-spam');

        $this->extend(
            (new Extend\Csrf)->exemptRoute('register')
        );
    }

    /**
     * @test
     */
    public function it_can_register_a_new_user()
    {
        $response = $this->send(
            $this->request('POST', '/register', [
                'json' => [
                    'username' => 'test',
                    'password' => 'too-obscure',
                    'email' => 'test@flarum.org',
                ]
            ])
        );

        $this->assertEquals(201, $response->getStatusCode());

        /** @var User $user */
        $user = User::where('username', 'test')->firstOrFail();

        $this->assertEquals(0, $user->is_email_confirmed);
        $this->assertEquals('test', $user->username);
        $this->assertEquals('test@flarum.org', $user->email);
    }

    /**
     * @test
     */
    public function it_can_register_a_new_user_when_hash_is_on()
    {
        $this->setting('fof-anti-spam.emailhash', true);

        $this->it_can_register_a_new_user();
    }

    /**
     * @test
     */
    public function it_rejects_registration_from_a_known_spammer()
    {
        $response = $this->send(
            $this->request('POST', '/register', [
                'json' => [
                    'username' => 'xrumer',
                    'password' => 'too-obscure',
                    'email' => 'testing@xrumer.ru',
                ]
            ])
        );
        $this->assertEquals(422, $response->getStatusCode());

        $body = json_decode($response->getBody()->getContents(), true);
        $this->assertEquals('validation_error', $body['errors'][0]['code']);
        $this->assertEquals('/data/attributes/username', $body['errors'][0]['source']['pointer']);
    }

    /**
     * @test
     */
    public function it_rejects_registration_from_a_known_spammer_when_hash_is_on()
    {
        $this->setting('fof-anti-spam.emailhash', true);

        $this->it_rejects_registration_from_a_known_spammer();
    }
}

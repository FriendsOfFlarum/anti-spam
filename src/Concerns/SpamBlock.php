<?php

namespace FoF\AntiSpam\Concerns;

use Flarum\Api\Client;
use Flarum\User\User;

trait SpamBlock
{
    use Users;

    protected function markAsSpammer(User $user)
    {
        /** @var Client $api */
        $api = resolve(Client::class);

        $response = $api
            ->withActor($this->getModerator())
            ->send(
                'post',
                "/users/$user->id/spamblock"
            );

        return $response->getStatusCode() === 204;
    }
}

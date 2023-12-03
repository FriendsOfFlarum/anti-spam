<?php

/*
 * This file is part of fof/anti-spam.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\AntiSpam\Api\Controllers;

use Flarum\Api\Controller\AbstractDeleteController;
use Flarum\Http\RequestUtil;
use FoF\AntiSpam\Model\BlockedRegistration;
use Psr\Http\Message\ServerRequestInterface;

class DeleteBlockedRegistrationController extends AbstractDeleteController
{
    public function delete(ServerRequestInterface $request)
    {
        RequestUtil::getActor($request)->assertAdmin();

        $id = $request->getQueryParams()['id'];

        $registration = BlockedRegistration::findOrFail($id);

        $registration->delete();
    }
}

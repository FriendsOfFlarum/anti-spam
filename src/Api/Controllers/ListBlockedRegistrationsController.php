<?php

namespace FoF\AntiSpam\Api\Controllers;

use Flarum\Api\Controller\AbstractListController;
use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use FoF\AntiSpam\Api\Serializers\BlockedRegistrationSerializer;
use FoF\AntiSpam\Model\BlockedRegistration;
use Psr\Http\Message\ServerRequestInterface;
use Tobscure\JsonApi\Document;

class ListBlockedRegistrationsController extends AbstractListController
{
    public $serializer = BlockedRegistrationSerializer::class;

    /**
     * @var UrlGenerator
     */
    public $url;

    public function __construct(UrlGenerator $url)
    {
        $this->url = $url;
    }

    public function data(ServerRequestInterface $request, Document $document)
    {
        RequestUtil::getActor($request)->assertCan('fof-anti-spam.viewBlockedRegistrations');

        $limit = $this->extractLimit($request);
		$offset = $this->extractOffset($request);

        $query = BlockedRegistration::query();

        $totalItems = $query->count();
		$items = $query->orderBy('id')->skip($offset)->take($limit)->get();

        $document->addPaginationLinks(
			$this->url->to('api')->route('fof-anti-spam.blocked-registrations.index'),
			$request->getQueryParams(),
			$offset,
			$limit,
			$totalItems - ($offset + $limit) > 0 ? null : 0
		);

        return $items;
    }
}

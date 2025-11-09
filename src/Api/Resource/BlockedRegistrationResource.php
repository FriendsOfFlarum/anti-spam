<?php

namespace FoF\AntiSpam\Api\Resource;

use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use FoF\AntiSpam\Model\BlockedRegistration;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * @extends Resource\AbstractDatabaseResource<BlockedRegistration>
 */
class BlockedRegistrationResource extends Resource\AbstractDatabaseResource
{
    public function type(): string
    {
        return 'blocked-registrations';
    }

    public function model(): string
    {
        return BlockedRegistration::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $query->whereVisibleTo($context->getActor());
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Delete::make()
                ->can('delete'),
            Endpoint\Index::make()
                ->can('fof-anti-spam.viewBlockedRegistrations')
                ->paginate(),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Date::make('createdAt')
                ->property('attempted_at'),
            Schema\Str::make('ip'),
            Schema\Str::make('email'),
            Schema\Str::make('username'),
            Schema\Str::make('sfsData')
                ->property('data'),
            Schema\Str::make('provider'),
            Schema\Str::make('providerData')
                ->property('provider_data'),
        ];
    }

    public function sorts(): array
    {
        return [
            SortColumn::make('createdAt'),
        ];
    }
}

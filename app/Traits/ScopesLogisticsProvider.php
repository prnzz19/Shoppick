<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait ScopesLogisticsProvider
{
    protected static function bootScopesLogisticsProvider(): void
    {
        static::addGlobalScope('logistics_provider_request', function (Builder $query) {
            if (! app()->bound('request')) return;
            $provider=app('request')->attributes->get('logistics_provider');
            if ($provider) $query->where($query->getModel()->qualifyColumn('logistics_provider_id'),$provider->id);
        });
    }
}

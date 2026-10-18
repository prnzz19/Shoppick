<?php

namespace App\Support;

use App\Models\LogisticsProvider;
use App\Models\User;

class LogisticsProviderContext
{
    public function forUser(?User $user, bool $requireOperational = true): ?LogisticsProvider
    {
        if (! $user || ! $user->hasRole('logistics')) {
            return null;
        }

        $provider = LogisticsProvider::query()
            ->where(function ($query) use ($user) {
                $query->where('owner_user_id', $user->id)
                    ->orWhereHas('users', fn ($members) => $members
                        ->where('users.id', $user->id)
                        ->where('logistics_provider_user.is_active', true));
            })
            ->first();

        if ($requireOperational && (! $provider || $provider->status !== 'approved' || ! $provider->is_active)) {
            return null;
        }

        return $provider;
    }

    public function requireFor(User $user): LogisticsProvider
    {
        $provider = $this->forUser($user);
        abort_unless($provider, 403, 'A verified, active Logistics Provider membership is required.');

        return $provider;
    }

}

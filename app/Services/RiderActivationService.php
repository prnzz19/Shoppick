<?php

namespace App\Services;

use App\Mail\RiderActivationInvitation;
use App\Models\{RiderActivationToken, RiderProfile};
use Illuminate\Support\Facades\{DB, Mail};
use Illuminate\Support\Str;

class RiderActivationService
{
    public function send(RiderProfile $profile): void
    {
        $plain = Str::random(64);
        DB::transaction(function () use ($profile, $plain) {
            RiderActivationToken::updateOrCreate(
                ['rider_profile_id' => $profile->id],
                ['token_hash' => hash('sha256', $plain), 'expires_at' => now()->addHours(24)]
            );
        });

        $url = route('rider.activation.show', ['token' => $plain]);
        Mail::to($profile->user->email)->send(new RiderActivationInvitation(
            $profile->user->name,
            $profile->logisticsOwner?->name ?? 'SHOPPICK Logistics',
            $url
        ));
    }
}

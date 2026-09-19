<?php

namespace App\Support;

final class GoogleOAuth
{
    public static function isAvailable(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}

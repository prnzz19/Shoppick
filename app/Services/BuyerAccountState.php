<?php

namespace App\Services;

class BuyerAccountState
{
    /** Ordinary registration never needs an administrator decision. */
    public static function attributes(bool $profileComplete = true): array
    {
        return [
            'registration_type' => 'buyer',
            'registration_status' => $profileComplete ? 'approved' : 'incomplete',
            'is_active' => true,
        ];
    }
}

<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class BirthdayAge
{
    public static function calculate(?CarbonInterface $birthday, ?CarbonInterface $today = null): ?int
    {
        if (! $birthday) {
            return null;
        }

        $today ??= now();

        return $birthday->isAfter($today) ? null : (int) $birthday->diffInYears($today);
    }
}

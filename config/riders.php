<?php

return [
    'license_expiry_warning_days' => (int) env('RIDER_LICENSE_EXPIRY_WARNING_DAYS', 30),
    'license_verification_required' => env('RIDER_LICENSE_VERIFICATION_REQUIRED', true),
    'document_max_kilobytes' => (int) env('RIDER_DOCUMENT_MAX_KILOBYTES', 5120),
];

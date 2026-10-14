<?php

return [
    'full_trial_premium' => (bool) env('MEMBERSHIP_FULL_TRIAL_PREMIUM', true),
    'announcement_version' => 'standard-premium-v1',
    'store_environment' => env('REVENUECAT_STORE_ENVIRONMENT', 'production'),
];

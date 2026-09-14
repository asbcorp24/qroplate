<?php

return [
    'super_admin_login' => env('SUPER_ADMIN_LOGIN', 'superadmin'),
    'super_admin_password' => env('SUPER_ADMIN_PASSWORD', ''),
    'device_token_secret' => env('DEVICE_TOKEN_SECRET', ''),
    'payment_provider' => env('PAYMENT_PROVIDER', 'disabled'),
    'reservation_minutes' => (int) env('RESERVATION_MINUTES', 2),
    'session_command_ttl_seconds' => (int) env('SESSION_COMMAND_TTL_SECONDS', 120),
];

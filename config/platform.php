<?php

return [
    'owner_email' => env('PLATFORM_OWNER_EMAIL', 'admin@floopware.com'),
    'module_unlock_seconds' => (int) env('PLATFORM_MODULE_UNLOCK_SECONDS', 900),
];

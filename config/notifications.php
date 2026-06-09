<?php

return [
    'gateways' => [
        'sms'   => env('NOTIFICATION_SMS_GATEWAY', 'mock'),
        'email' => env('NOTIFICATION_EMAIL_GATEWAY', 'mock'),
    ],
];

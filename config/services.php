<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Firebase Cloud Messaging — push notifications to the Android app.
    // 'credentials' is the path to the Firebase service-account JSON; leave
    // unset and the fcm channel silently no-ops (see docs/push-notifications.md).
    'fcm' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    // Android app distribution — where /download sends people when no APK
    // is placed at public/downloads. Later: the Play Store listing URL.
    'app' => [
        'apk_url' => env('APP_APK_URL'),
    ],

];

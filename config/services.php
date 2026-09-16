<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'google_drive' => [
        'enabled' => env('GOOGLE_DRIVE_ENABLED', false),
        'oauth_credentials' => env('GOOGLE_DRIVE_OAUTH_CREDENTIALS'),
        'oauth_redirect_uri' => env('GOOGLE_DRIVE_OAUTH_REDIRECT_URI'),
        'root_folder_id' => env('GOOGLE_DRIVE_ROOT_FOLDER_ID'),
        'projects_folder_id' => env('GOOGLE_DRIVE_DP_PROYEK_FOLDER_ID'),
        'lkpm_folder_id' => env('GOOGLE_DRIVE_LKPM_FOLDER_ID'),
        'sectors_folder_id' => env('GOOGLE_DRIVE_SECTOR_FOLDER_ID'),
        'failed_imports_folder_id' => env('GOOGLE_DRIVE_FAILED_IMPORTS_FOLDER_ID'),
        'archive_folder_id' => env('GOOGLE_DRIVE_ARCHIVE_FOLDER_ID'),
    ],

];

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

    'fast2sms' => [
        'key' => env('FAST2SMS_API_KEY'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
    ],

    'deepseek' => [
        'key' => env('DEEPSEEK_API_KEY'),
    ],

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
    ],

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'service_account_path' => env('FCM_SERVICE_ACCOUNT_PATH'),
    ],

    'firebase' => [
        'dynamic_link' => [
            'uri_prefix' => env('FIREBASE_DYNAMIC_LINK_URI', 'https://utterlazy.page.link'),
            'android_package_name' => 'com.khaalo.customer_app',
            'ios_bundle_id' => 'com.khaalo.customer_app',
        ],
    ],

    'internal_token' => env('SERVICE_TOKEN'),

    'whatsapp_service_url' => env('WHATSAPP_SERVICE_URL', 'http://whatsapp-service:8002'),

    'ondc' => [
        'enabled' => (bool) env('ONDC_ENABLED', false),
        'require_signature' => (bool) env('ONDC_REQUIRE_SIGNATURE', true),
        'callback_enabled' => (bool) env('ONDC_CALLBACK_ENABLED', false),
        'subscriber_id' => env('ONDC_SUBSCRIBER_ID', ''),
        'key_id' => env('ONDC_KEY_ID', ''),
        'private_key' => env('ONDC_PRIVATE_KEY', ''),
        'bpp_id' => env('ONDC_BPP_ID', ''),
        'bpp_uri' => env('ONDC_BPP_URI', ''),
        'registry_keys' => env('ONDC_REGISTRY_KEYS', ''),
    ],

    'sos' => [
        'partner_webhook' => env('SOS_PARTNER_WEBHOOK', ''),
    ],

    'projection' => [
        'transport' => env('PROJECTION_TRANSPORT', 'redis'),
        'redis_stream' => env('PROJECTION_REDIS_STREAM', 'outbox:order.events.v1'),
        'redis_cursor' => env('PROJECTION_REDIS_CURSOR', 'projection:cursor'),
        'kafka_brokers' => env('KAFKA_BROKERS', 'kafka:9092'),
        'kafka_topic' => env('PROJECTION_KAFKA_TOPIC', 'utterlazy.order.events.v1'),
        'group_id' => env('PROJECTION_KAFKA_GROUP', 'utterlazy-order-projection'),
    ],

    'opensearch' => [
        'enabled' => (bool) env('OPENSEARCH_ENABLED', false),
        'url' => env('OPENSEARCH_URL', 'http://opensearch:9200'),
        'index' => env('OPENSEARCH_INDEX', 'utterlazy_restaurants'),
    ],

    'ranker' => [
        'enabled' => (bool) env('RANKER_ENABLED', false),
        'url' => env('RANKER_URL', 'http://ranker:8010'),
    ],

    'analytics' => [
        'enabled' => (bool) env('ANALYTICS_ENABLED', false),
        'clickhouse_url' => env('CLICKHOUSE_URL', 'http://clickhouse:8123'),
        'clickhouse_user' => env('CLICKHOUSE_USER'),
        'clickhouse_password' => env('CLICKHOUSE_PASSWORD'),
        'database' => env('CLICKHOUSE_DATABASE', 'utterlazy'),
        'command_center_source' => env('ANALYTICS_COMMAND_CENTER_SOURCE', 'mysql'),
    ],

    'order_command' => [
        'url' => env('ORDER_COMMAND_URL', 'http://order-command:8003'),
        'shadow_enabled' => (bool) env('ORDER_COMMAND_SHADOW_ENABLED', false),
        // Wave 2 cutover is fail-closed: both flags + an allowlist are required.
        'cutover_enabled' => (bool) env('ORDER_COMMAND_CUTOVER_ENABLED', false),
        'cutover_ready' => (bool) env('ORDER_COMMAND_CUTOVER_READY', false),
        'cutover_cities' => env('ORDER_COMMAND_CITIES', ''),
        'cutover_restaurants' => env('ORDER_COMMAND_RESTAURANTS', ''),
        'require_certification_artifact' => (bool) env('CUTOVER_REQUIRE_CERTIFICATION', false),
        'certification_dir' => env('CERTIFICATION_DIR', base_path('../infra/certification')),
    ],

    'outbox' => [
        'transport' => env('OUTBOX_TRANSPORT', 'redis'),
        'kafka' => [
            'brokers' => env('KAFKA_BROKERS', 'localhost:9092'),
            'topic_prefix' => env('KAFKA_TOPIC_PREFIX', 'utterlazy.'),
            'dlq_suffix' => env('KAFKA_DLQ_SUFFIX', '.dlq'),
        ],
        'batch_size' => (int) env('OUTBOX_BATCH_SIZE', 100),
        'poll_seconds' => (int) env('OUTBOX_POLL_SECONDS', 1),
        'max_retries' => (int) env('OUTBOX_MAX_RETRIES', 5),
    ],

    'enablex' => [
        'app_id' => env('ENABLEX_APP_ID'),
        'app_key' => env('ENABLEX_APP_KEY'),
        'max_concurrent' => env('ENABLEX_MAX_CONCURRENT', 5),
        'virtual_numbers' => array_filter(array_map('trim', explode(',', env('ENABLEX_VIRTUAL_NUMBERS', '')))),
        'webhook_url' => env('ENABLEX_WEBHOOK_URL', 'https://catalog.utterlazy.com/api/webhooks/enablex'),
    ],

];

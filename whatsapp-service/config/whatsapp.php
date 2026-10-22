<?php

return [
    'provider' => env('WHATSAPP_PROVIDER', 'meta_cloud'),

    'catalog_service_url' => env('CATALOG_SERVICE_URL', 'http://catalog-service:8001'),
    'service_token' => env('SERVICE_TOKEN'),

    // Where the customer completes Razorpay payment (app/web payment screen).
    'payment_link_base' => env('WA_PAYMENT_LINK_BASE', 'https://utterlazy.com/pay'),

    'event_bus_driver' => env('EVENT_BUS_DRIVER', 'redis'),
    'kafka_brokers' => env('KAFKA_BROKERS', 'kafka:9092'),
    'kafka_topic_prefix' => env('KAFKA_TOPIC_PREFIX', 'utterlazy.'),

    'session_ttl_minutes' => (int) env('WA_SESSION_TTL_MINUTES', 30),
    'intent_confidence_threshold' => (float) env('WA_INTENT_CONFIDENCE_THRESHOLD', 0.75),
    'tip_max' => (float) env('WA_TIP_MAX', 500),

    'meta_api_base' => env('META_API_BASE', 'https://graph.facebook.com'),
    'meta_api_version' => env('META_API_VERSION', 'v22.0'),
    'meta_phone_number_id' => env('META_PHONE_NUMBER_ID'),
    'meta_access_token' => env('META_ACCESS_TOKEN'),
    'meta_app_secret' => env('META_APP_SECRET'),
    'meta_verify_token' => env('META_VERIFY_TOKEN', 'utterlazy_wa_webhook'),
];

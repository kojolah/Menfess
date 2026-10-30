<?php

return [
    'base_url' => env('QRIS_BASE_URL', 'https://qris.interactive.co.id/restapi/qris/'),
    'api_key' => env('QRIS_API_KEY'),
    'merchant_id' => env('QRIS_MERCHANT_ID'),
    'use_tip' => env('QRIS_USE_TIP', 'no'),
    'timeout' => (int) env('QRIS_TIMEOUT', 15),
    'check_status_cooldown_seconds' => (int) env('QRIS_CHECK_COOLDOWN_SECONDS', 10),
    'min_unique_code' => (int) env('QRIS_MIN_UNIQUE_CODE', 1),
    'max_unique_code' => (int) env('QRIS_MAX_UNIQUE_CODE', 999),
    'min_transaction_amount_idr' => (int) env('QRIS_MIN_TRANSACTION_AMOUNT_IDR', 100),
    'max_transaction_amount_idr' => (int) env('QRIS_MAX_TRANSACTION_AMOUNT_IDR', 2000000),
    'enable_unique_amount' => (bool) env('QRIS_ENABLE_UNIQUE_AMOUNT', true),
];

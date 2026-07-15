<?php

return [
    'access_token' => env('MERCADO_PAGO_ACCESS_TOKEN'),
    'public_key' => env('MERCADO_PAGO_PUBLIC_KEY'),
    'sandbox' => (bool) env('MERCADO_PAGO_SANDBOX', true),
    'statement_descriptor' => env('MERCADO_PAGO_STATEMENT_DESCRIPTOR', 'STORE TI'),
];

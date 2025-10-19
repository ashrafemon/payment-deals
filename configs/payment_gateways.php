<?php

return [
    // Bangladeshi payment gateways
    'bkash'    => [
        'urls'       => [
            'production' => env('BKASH_BASE_URL', 'https://tokenized.pay.bka.sh'),
            'sandbox'    => env('BKASH_SANDBOX_URL', 'https://tokenized.sandbox.bka.sh'),
            'token'      => '/v1.2.0-beta/tokenized/checkout/token/grant',
            'request'    => '/v1.2.0-beta/tokenized/checkout/create',
            'query'      => '/v1.2.0-beta/tokenized/checkout/payment/status',
            'execute'    => '/v1.2.0-beta/tokenized/checkout/execute',
        ],
        'currencies' => ["BDT"],
    ],

    // International payment gateways
    'paypal'   => [
        'urls'       => [
            'production' => env('PAYPAL_BASE_URL', 'https://api-m.paypal.com'),
            'sandbox'    => env('PAYPAL_SANDBOX_URL', 'https://api-m.sandbox.paypal.com'),
            'token'      => '/v1/oauth2/token',
            'request'    => '/v2/checkout/orders',
            'query'      => '/v2/checkout/orders/:orderId',
            'execute'    => '/v2/checkout/orders/:orderId/capture',
        ],
        'currencies' => ['AUD', 'BRL', 'CAD', 'CNY', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY', 'MYR', 'MXN', 'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'SGD', 'SEK', 'CHF', 'THB', 'USD'],
    ],
    'stripe'   => [
        'urls'       => [
            'production' => env('STRIPE_BASE_URL', 'https://api.stripe.com'),
            'request'    => '/v1/checkout/sessions',
            'query'      => '/v1/checkout/sessions/:orderId',
        ],
        'currencies' => ["USD", "AED", "AFN", "ALL", "AMD", "ANG", "AOA", "ARS", "AUD", "AWG", "AZN", "BAM", "BBD", "BDT", "BGN", "BIF", "BMD", "BND", "BOB", "BRL", "BSD", "BWP", "BYN", "BZD", "CAD", "CDF", "CHF", "CLP", "CNY", "COP", "CRC", "CVE", "CZK", "DJF", "DKK", "DOP", "DZD", "EGP", "ETB", "EUR", "FJD", "FKP", "GBP", "GEL", "GIP", "GMD", "GNF", "GTQ", "GYD", "HKD", "HNL", "HTG", "HUF", "IDR", "ILS", "INR", "ISK", "JMD", "JPY", "KES", "KGS", "KHR", "KMF", "KRW", "KYD", "KZT", "LAK", "LBP", "LKR", "LRD", "LSL", "MAD", "MDL", "MGA", "MKD", "MMK", "MNT", "MOP", "MUR", "MVR", "MWK", "MXN", "MYR", "MZN", "NAD", "NGN", "NIO", "NOK", "NPR", "NZD", "PAB", "PEN", "PGK", "PHP", "PKR", "PLN", "PYG", "QAR", "RON", "RSD", "RUB", "RWF", "SAR", "SBD", "SCR", "SEK", "SGD", "SHP", "SLE", "SOS", "SRD", "STD", "SZL", "THB", "TJS", "TOP", "TRY", "TTD", "TWD", "TZS", "UAH", "UGX", "UYU", "UZS", "VND", "VUV", "WST", "XAF", "XCD", "XOF", "XPF", "YER", "ZAR", "ZMW"],
    ],

    // Indian payment gateways
    'razorpay' => [
        'urls'       => [
            'production' => env('RAZORPAY_BASE_URL', 'https://api.razorpay.com'),
            'request'    => '/v1/payment_links',
            'query'      => '/v1/payment_links/:orderId',
        ],
        'currencies' => ["USD", "EUR", "GBP", "SGD", "AED", "AUD", "CAD", "CNY", "SEK", "NZD", "MXN", "HKD", "NOK", "RUB", "ALL", "AMD", "ARS", "AWG", "BBD", "BDT", "BMD", "BND", "BOB", "BSD", "BWP", "BZD", "CHF", "COP", "CRC", "CUP", "CZK", "DKK", "DOP", "DZD", "EGP", "ETB", "FJD", "GIP", "GMD", "GTQ", "GYD", "HKD", "HNL", "HRK", "HTG", "HUF", "IDR", "ILS", "INR", "JMD", "KES", "KGS", "KHR", "KYD", "KZT", "LAK", "LBP", "LKR", "LRD", "LSL", "MAD", "MDL", "MKD", "MMK", "MNT", "MOP", "MUR", "MVR", "MWK", "MYR", "NAD", "NGN", "NIO", "NOK", "NPR", "PEN", "PGK", "PHP", "PKR", "QAR", "SAR", "SCR", "SLL", "SOS", "SSP", "SVC", "SZL", "THB", "TTD", "TZS", "UYU", "UZS", "YER", "ZAR", "GHS"],
    ],

    // African payment gateways
    'paystack' => [
        'urls'       => [
            'production' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
            'request'    => '/transaction/initialize',
            'query'      => '/transaction/verify/:orderId',
        ],
        'currencies' => ["GHS", 'NGN', 'USD', 'ZAR', 'KES'],
    ],
];

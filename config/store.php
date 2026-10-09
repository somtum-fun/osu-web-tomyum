<?php

return [
    'allow_restricted_users' => get_bool(env('STORE_ALLOW_RESTRICTED_USERS')) ?? false,
    'mail' => [
        // TomYum: from this server's store address, not osu!'s.
        'donation_thanks' => [
            'sender_address' => presence(env('STORE_THANKS_SENDER_ADDRESS')) ?? presence(env('MAIL_STORE_ADDRESS')) ?? presence(env('MAIL_ADDRESS')),
            'sender_name' => presence(env('STORE_THANKS_SENDER_NAME')) ?? 'TomYum',
        ],
    ],
    'order' => [
        'prefix' => presence(env('STORE_ORDER_PREFIX'), 'store'),
        'stale_days' => get_int(env('STORE_STALE_DAYS')) ?? 14,
    ],
    'shopify' => [
        'domain' => presence(env('SHOPIFY_DOMAIN')),
        'storefront_token' => presence(env('SHOPIFY_STOREFRONT_TOKEN')),
    ],
];

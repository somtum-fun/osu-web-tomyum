<?php

// TomYum game server integration (staff panels). Requests to its admin API are signed with the
// same SHARED_INTEROP_SECRET TomYum uses for InterOp (osu.legacy.shared_interop_secret).

return [
    // Where the website reaches TomYum's admin API: its compose service on the shared network.
    'admin_url' => env('TOMYUM_ADMIN_URL', 'http://server:8080'),

    // Ranked status is TomYum's (the osu! API plus the BN panel): don't run modding:rank here.
    'manages_ranking' => get_bool(env('TOMYUM_MANAGES_RANKING')) ?? true,

    // Store payments (TrueMoney, Stripe) through payments-service (Store\TomYumCheckoutController):
    // where it's reached from here; empty, paying fails with "not available".
    'payments' => [
        'url' => presence(env('TOMYUM_PAYMENTS_URL')),
    ],

    // Accounts players link in their settings (App\Libraries\TomYumConnections): an osu! OAuth app
    // (osu.ppy.sh/home/account/edit#oauth, callback .../home/account/connections/osu_bancho/callback)
    // and a Discord application (callback .../home/account/connections/discord/callback). Either one
    // without its id and secret isn't offered.
    'connections' => [
        'osu_bancho' => [
            'client_id' => presence(env('OSU_LINK_CLIENT_ID')),
            'client_secret' => presence(env('OSU_LINK_CLIENT_SECRET')),
        ],
        'discord' => [
            'client_id' => presence(env('DISCORD_OAUTH_CLIENT_ID')),
            'client_secret' => presence(env('DISCORD_OAUTH_CLIENT_SECRET')),
        ],
    ],

    // Mail through NoCodeAPI's Zoho Mail endpoint when MAIL_DRIVER=nocodeapi (TomYumNocodeapiTransport).
    'nocodeapi_mail_url' => presence(env('NOCODEAPI_MAIL_URL')),

    // Who store mail (receipts, supporter thanks and gifts) comes from; account mail (sign-in codes,
    // password and email changes, alerts) comes from MAIL_ADDRESS. Both must be addresses the Zoho
    // account can send as; if it refuses this one, the mail goes from MAIL_ADDRESS instead.
    'store_mail' => [
        'address' => presence(env('MAIL_STORE_ADDRESS')) ?? presence(env('MAIL_ADDRESS')),
        'name' => presence(env('MAIL_STORE_SENDER')) ?? 'osu!store',
    ],
];

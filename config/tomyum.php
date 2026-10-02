<?php

// TomYum game server integration (staff panels). Requests to its admin API are signed with the
// same SHARED_INTEROP_SECRET TomYum uses for InterOp (osu.legacy.shared_interop_secret).

return [
    // Where the website reaches TomYum's admin API: its compose service on the shared network.
    'admin_url' => env('TOMYUM_ADMIN_URL', 'http://server:8080'),

    // The server's name where the website speaks for it (support ticket mail, the maintenance notice).
    'server_name' => presence(env('TOMYUM_SERVER_NAME')) ?? 'TomYum',

    // The assets host (badges, tournament banners, medal icons; the game server keeps them), from the
    // badge images' address: https://assets.<domain>/profile-badges/ → https://assets.<domain>.
    'assets_url' => rtrim(presence(env('TOMYUM_ASSETS_URL')) ?? preg_replace(
        '#/profile-badges/?$#',
        '',
        env('USER_BADGE_IMAGE_PREFIX', 'https://assets.ppy.sh/profile-badges/'),
    ), '/'),

    // osu! sets' audio previews (the play buttons), {set} the set's id: TomYum's b.<domain> (the
    // website at osu.<domain>), which keeps them from catboy.best or osu! and serves them with their
    // length; without an osu. host, osu!'s own.
    'audio_preview_url' => presence(env('TOMYUM_AUDIO_PREVIEW_URL')) ?? (function () {
        $url = parse_url((string) env('APP_URL'));
        $host = $url['host'] ?? '';

        return str_starts_with($host, 'osu.')
            ? ($url['scheme'] ?? 'https').'://b.'.substr($host, 4).'/preview/{set}.mp3'
            : null;
    })(),

    // The osu!store (supporter, paid through payments-service below). Off, every store page says
    // it isn't available (TomYumStoreUnavailable) and the menus don't link to it.
    'store' => [
        'enabled' => get_bool(env('TOMYUM_STORE_ENABLED')) ?? false,
    ],

    // The Project Loved forum (osu!'s id), where the Project Loved site posts its polls; its team posts
    // there however much they've played.
    'loved_forum_id' => get_int(env('TOMYUM_LOVED_FORUM_ID')) ?? 120,

    // Ranked status is TomYum's (the osu! API plus the BN panel): don't run modding:rank here.
    'manages_ranking' => get_bool(env('TOMYUM_MANAGES_RANKING')) ?? true,

    // Project Loved (App\Libraries\TomYumLoved): the forum the rounds' polls are posted in (osu!'s is 120
    // too; made when the first round opens its polls, or by tomyum:setup).
    'loved' => [
        'forum_id' => get_int(env('TOMYUM_LOVED_FORUM_ID')) ?? 120,
    ],

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

    // Support tickets from email (App\Libraries\TomYumTickets): the domain of the ticket addresses
    // (support@, accounts@, appeal-review@, abuse@, privacy@, dmca@, tournaments@), all received by the
    // Zoho mailbox NoCodeAPI reads (aliases of it); empty TICKETS_ENABLED turns the fetching off.
    'tickets' => [
        'enabled' => get_bool(env('TICKETS_ENABLED')) ?? true,
        // (by default the site's domain without its "osu." part: osu.sundei.eu → sundei.eu)
        'domain' => presence(env('TICKETS_MAIL_DOMAIN'))
            ?? preg_replace('/^osu\./', '', parse_url((string) env('APP_URL'), PHP_URL_HOST) ?? ''),
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

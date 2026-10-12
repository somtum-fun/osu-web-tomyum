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

    // The vote page (/home/vote): server lists players vote on. A site without a url shows as
    // "coming soon". days_per_vote matches the tomyum.votes plugin's daysPerVote (supporter per vote
    // on osu-server-list.com, which calls TomYum's /vote-callback).
    'votes' => [
        'days_per_vote' => get_int(env('TOMYUM_VOTE_DAYS_PER_VOTE')) ?? 1,
        'sites' => [
            [
                'name' => 'osu-server-list.com',
                'url' => presence(env('TOMYUM_VOTE_OSL_URL')),
                'rewarded' => true,
            ],
            [
                'name' => 'find.circles-to.click',
                'url' => presence(env('TOMYUM_VOTE_CIRCLES_URL')),
                'rewarded' => false,
            ],
        ],
    ],

    // Ranked status is TomYum's (the osu! API plus the BN panel): don't run modding:rank here.
    'manages_ranking' => get_bool(env('TOMYUM_MANAGES_RANKING')) ?? true,
    // Players with no score in a ruleset for this many days are off its rankings (0: off; the game
    // server's TOMYUM_RANKING__INACTIVEDAYS must say the same).
    'ranking_inactive_days' => get_int(env('TOMYUM_RANKING_INACTIVE_DAYS')) ?? 0,

    // Store payments (Stripe) through payments-service (Store\TomYumCheckoutController):
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
    // (support@, accounts@, appeal-review@, abuse@, privacy@, dmca@, tournaments@), all aliases of one
    // mailbox: read over IMAP (TICKETS_IMAP_HOST, any provider) with replies by SMTP (the MAIL_* server),
    // or else the Zoho mailbox NoCodeAPI reads; empty TICKETS_ENABLED turns the fetching off.
    'tickets' => [
        'enabled' => get_bool(env('TICKETS_ENABLED')) ?? true,
        // (by default the site's domain without its "osu." part: osu.example.com → example.com)
        'domain' => presence(env('TICKETS_MAIL_DOMAIN'))
            ?? preg_replace('/^osu\./', '', parse_url((string) env('APP_URL'), PHP_URL_HOST) ?? ''),
        // IMAP over TLS (port 993); the login is the SMTP one unless set apart.
        'imap' => [
            'host' => presence(env('TICKETS_IMAP_HOST')),
            'port' => get_int(env('TICKETS_IMAP_PORT')) ?? 993,
            'username' => presence(env('TICKETS_IMAP_USERNAME')) ?? presence(env('MAIL_USERNAME')),
            'password' => presence(env('TICKETS_IMAP_PASSWORD')) ?? presence(env('MAIL_PASSWORD')),
            'folder' => presence(env('TICKETS_IMAP_FOLDER')) ?? 'INBOX',
        ],
    ],

    // Mail through NoCodeAPI's Zoho Mail endpoint when MAIL_DRIVER=nocodeapi (TomYumNocodeapiTransport).
    'nocodeapi_mail_url' => presence(env('NOCODEAPI_MAIL_URL')),

    // Who store mail (receipts, supporter thanks and gifts) comes from; account mail (sign-in codes,
    // password and email changes, alerts) comes from MAIL_ADDRESS. Both must be addresses the Zoho
    // account can send as; if it refuses this one, the mail goes from MAIL_ADDRESS instead.
    'store_mail' => [
        'address' => presence(env('MAIL_STORE_ADDRESS')) ?? presence(env('MAIL_ADDRESS')),
        'name' => presence(env('MAIL_STORE_SENDER')) ?? 'TomYum store',
    ],

    // This server's name, logo, favicons and contact addresses (App\Libraries\TomYumBranding). These are
    // the defaults; staff change them in Admin panel > Branding.
    'branding' => [
        'name' => presence(env('BRAND_NAME')) ?? 'TomYum',
        // support@, dmca@, store@… on this domain; the tickets' (TICKETS_DOMAIN) if unset.
        'email_domain' => presence(env('BRAND_EMAIL_DOMAIN')),
        // text (the name, drawn), osu (osu!'s logo) or an image's address.
        'logo' => presence(env('BRAND_LOGO')) ?? 'text',
        // letter (the name's first letter, drawn) or osu.
        'favicon' => presence(env('BRAND_FAVICON')) ?? 'letter',
    ],

    // Sign-up rules for accounts made in lazer and stable (App\Libraries\TomYumRegistrationGuard):
    // IP bans, 1 account per network a day, Tor and hosting networks, then proxycheck.io (works without
    // a key for about 100 checks a day; a free key gives 1,000). Staff switch the rules and keep the
    // allowlist in the GMT panel (Sign-ups).
    'registration' => [
        'proxycheck_enabled' => get_bool(env('PROXYCHECK_ENABLED')) ?? true,
        'proxycheck_key' => presence(env('PROXYCHECK_API_KEY')),
        // The lists tomyum:registration-lists-update downloads every day.
        'hosting_asn_list_url' => presence(env('TOMYUM_HOSTING_ASN_LIST_URL')) ?? 'https://raw.githubusercontent.com/X4BNet/lists_vpn/main/input/datacenter/ASN.txt',
        'tor_exit_list_url' => presence(env('TOMYUM_TOR_EXIT_LIST_URL')) ?? 'https://check.torproject.org/torbulkexitlist',
    ],
];

<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\User;
use Cache;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Http;

/**
 * TomYum: accounts a player links in their settings (tomyum_user_connections), as
 * example-org/private-repo's connections: Discord and their osu! account on osu.ppy.sh, both by OAuth.
 * BN applications (QAT) need both, and no osu! restriction in the last 12 months.
 *
 * osu!'s API never shows past restrictions (not even to the player: only silences and tournament
 * bans of the last 28 days). What can be known: restricted when linking (`is_restricted` on /me), and
 * restricted at a later check (their public profile is gone); each time, the date is kept and counts
 * for 12 months.
 */
class TomYumConnections
{
    public const KINDS = ['discord', 'osu_bancho'];

    public const LABELS = ['discord' => 'Discord', 'osu_bancho' => 'osu! (osu.ppy.sh)'];

    /** How long a restriction seen on osu! keeps a player from applying. */
    public const RESTRICTION_DAYS = 365;

    /** How often a linked osu! account is checked again. */
    private const RECHECK_HOURS = 24;

    private const PROVIDERS = [
        'discord' => [
            'authorize' => 'https://discord.com/oauth2/authorize',
            'token' => 'https://discord.com/api/oauth2/token',
            'me' => 'https://discord.com/api/users/@me',
        ],
        'osu_bancho' => [
            'authorize' => 'https://osu.ppy.sh/oauth/authorize',
            'token' => 'https://osu.ppy.sh/oauth/token',
            'me' => 'https://osu.ppy.sh/api/v2/me',
        ],
    ];

    public static function configured(string $kind): bool
    {
        $config = $GLOBALS['cfg']['tomyum']['connections'][$kind] ?? null;

        return $config !== null && $config['client_id'] !== null && $config['client_secret'] !== null;
    }

    public static function redirectUri(string $kind): string
    {
        return route('account.connections.callback', ['kind' => $kind]);
    }

    public static function authorizeUrl(string $kind, string $state): string
    {
        return static::PROVIDERS[$kind]['authorize'].'?'.http_build_query([
            'client_id' => $GLOBALS['cfg']['tomyum']['connections'][$kind]['client_id'],
            'redirect_uri' => static::redirectUri($kind),
            'response_type' => 'code',
            'scope' => 'identify',
            'state' => $state,
        ]);
    }

    /** Links the account the provider sends back; returns why it didn't, or null when it did. */
    public static function link(User $user, string $kind, string $code): ?string
    {
        $label = static::LABELS[$kind];
        try {
            $token = Http::asForm()->acceptJson()->timeout(15)->post(static::PROVIDERS[$kind]['token'], [
                'client_id' => $GLOBALS['cfg']['tomyum']['connections'][$kind]['client_id'],
                'client_secret' => $GLOBALS['cfg']['tomyum']['connections'][$kind]['client_secret'],
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => static::redirectUri($kind),
            ])->json('access_token');
            if ($token === null) {
                return "{$label} didn't accept the sign-in. Please try again.";
            }
            $profile = Http::withToken($token)->acceptJson()->timeout(15)->get(static::PROVIDERS[$kind]['me'])->json();
        } catch (\Throwable $e) {
            log_error($e);

            return "{$label} couldn't be reached. Please try again.";
        }

        $externalId = (string) ($profile['id'] ?? '');
        if ($externalId === '') {
            return "{$label} didn't send your account. Please try again.";
        }
        $owner = DB::table('tomyum_user_connections')->where('kind', $kind)->where('external_id', $externalId)->value('user_id');
        if ($kind === 'discord') {
            $owner ??= DB::table('tomyum.discord_links')->where('discord_id', $externalId)->value('user_id');
        }
        if ($owner !== null && (int) $owner !== $user->getKey()) {
            return "That {$label} account is already linked to another account here.";
        }

        $now = Carbon::now();
        $data = $kind === 'osu_bancho' ? static::osuData($profile, null, $now) : null;
        DB::table('tomyum_user_connections')->updateOrInsert(
            ['user_id' => $user->getKey(), 'kind' => $kind],
            [
                'external_id' => $externalId,
                'display_name' => mb_substr((string) ($profile['global_name'] ?? $profile['username'] ?? ''), 0, 100),
                'data' => $data === null ? null : json_encode($data),
                'connected_at' => $now,
                'checked_at' => $kind === 'osu_bancho' ? $now : null,
            ],
        );
        if ($kind === 'discord') {
            // The Discord bot's own table, so its roles and commands know the link too.
            DB::table('tomyum.discord_links')->updateOrInsert(
                ['user_id' => $user->getKey()],
                ['discord_id' => $externalId, 'linked_at' => $now],
            );
        }

        return null;
    }

    public static function unlink(User $user, string $kind): void
    {
        DB::table('tomyum_user_connections')->where('user_id', $user->getKey())->where('kind', $kind)->delete();
        if ($kind === 'discord') {
            DB::table('tomyum.discord_links')->where('user_id', $user->getKey())->delete();
        }
    }

    /**
     * What the player has linked, checking a linked osu! account again when it's due:
     * ['discord' => ['linked', 'id', 'name'], 'osu_bancho' => ['linked', 'id', 'name', 'restricted',
     * 'restricted_at', 'restricted_recently', 'checked_at']].
     */
    public static function status(User $user, bool $recheck = true): array
    {
        $rows = DB::table('tomyum_user_connections')->where('user_id', $user->getKey())->get()->keyBy('kind');

        $discordId = DB::table('tomyum.discord_links')->where('user_id', $user->getKey())->value('discord_id');
        $discord = [
            'linked' => $discordId !== null,
            'id' => $discordId === null ? null : (string) $discordId,
            'name' => $discordId !== null && ($rows['discord']->external_id ?? null) === (string) $discordId
                ? $rows['discord']->display_name
                : null,
        ];

        $osu = $rows['osu_bancho'] ?? null;
        if ($osu !== null && $recheck && ($osu->checked_at === null || Carbon::parse($osu->checked_at)->lt(Carbon::now()->subHours(static::RECHECK_HOURS)))) {
            $osu = static::recheckOsu($osu) ?? $osu;
        }
        $data = $osu === null ? [] : (json_decode($osu->data ?? 'null', true) ?? []);
        $restrictedAt = isset($data['restriction_seen_at']) ? Carbon::parse($data['restriction_seen_at']) : null;

        return [
            'discord' => $discord,
            'osu_bancho' => [
                'linked' => $osu !== null,
                'id' => $osu?->external_id === null ? null : (int) $osu->external_id,
                'name' => $osu?->display_name,
                'restricted' => (bool) ($data['restricted'] ?? false),
                'restricted_at' => $restrictedAt?->toIso8601String(),
                'restricted_recently' => $restrictedAt !== null && $restrictedAt->gt(Carbon::now()->subDays(static::RESTRICTION_DAYS)),
                'checked_at' => $osu?->checked_at === null ? null : Carbon::parse($osu->checked_at)->toIso8601String(),
            ],
        ];
    }

    /** Asks osu! about a linked account again (its public profile is gone while it's restricted). */
    private static function recheckOsu(object $row): ?object
    {
        $token = static::osuAppToken();
        if ($token === null) {
            return null;
        }
        try {
            $response = Http::withToken($token)->acceptJson()->timeout(15)
                ->get('https://osu.ppy.sh/api/v2/users/'.rawurlencode($row->external_id), ['key' => 'id']);
        } catch (\Throwable $e) {
            return null;
        }
        if (!$response->successful() && $response->status() !== 404) {
            return null;
        }

        $previous = json_decode($row->data ?? 'null', true) ?? [];
        $profile = $response->status() === 404 ? ['is_restricted' => true] : $response->json();
        $now = Carbon::now();
        $data = static::osuData($profile, $previous, $now);
        $update = ['data' => json_encode($data), 'checked_at' => $now];
        if (isset($profile['username'])) {
            $update['display_name'] = mb_substr($profile['username'], 0, 100);
        }
        DB::table('tomyum_user_connections')->where('user_id', $row->user_id)->where('kind', 'osu_bancho')->update($update);

        return DB::table('tomyum_user_connections')->where('user_id', $row->user_id)->where('kind', 'osu_bancho')->first();
    }

    /** What's kept from an osu! profile: restricted now, when a restriction was last seen, recent silences. */
    private static function osuData(array $profile, ?array $previous, Carbon $now): array
    {
        $restricted = (bool) ($profile['is_restricted'] ?? false);
        $infringements = [];
        foreach ($profile['account_history'] ?? [] as $entry) {
            $infringements[] = [
                'type' => $entry['type'] ?? null,
                'at' => $entry['timestamp'] ?? null,
                'length' => $entry['length'] ?? null,
            ];
            if (($entry['type'] ?? null) === 'restriction') {
                $restricted = true;
            }
        }

        return [
            'restricted' => $restricted,
            'restriction_seen_at' => $restricted ? $now->toIso8601String() : ($previous['restriction_seen_at'] ?? null),
            'recent_infringements' => $infringements,
        ];
    }

    /** A client-credentials token for the osu! app, kept until shortly before it expires. */
    private static function osuAppToken(): ?string
    {
        if (!static::configured('osu_bancho')) {
            return null;
        }

        return Cache::get('tomyum:osu-app-token') ?? (function () {
            try {
                $response = Http::asForm()->acceptJson()->timeout(15)->post(static::PROVIDERS['osu_bancho']['token'], [
                    'client_id' => $GLOBALS['cfg']['tomyum']['connections']['osu_bancho']['client_id'],
                    'client_secret' => $GLOBALS['cfg']['tomyum']['connections']['osu_bancho']['client_secret'],
                    'grant_type' => 'client_credentials',
                    'scope' => 'public',
                ]);
            } catch (\Throwable $e) {
                return null;
            }
            $token = $response->json('access_token');
            if ($token !== null) {
                Cache::put('tomyum:osu-app-token', $token, max(60, (int) $response->json('expires_in', 3600) - 300));
            }

            return $token;
        })();
    }
}

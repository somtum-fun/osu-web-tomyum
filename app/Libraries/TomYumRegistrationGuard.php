<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\IpBan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TomYum: who may make an account. Accounts are made only in the game: lazer's sign-up screen
 * (UsersController::store) and stable's (TomYum asks through /_lio/tomyum/registration-check). Every
 * sign-up is checked here, in this order:
 *
 *  1. the address isn't banned (osu_ip_bans);
 *  2. the staff allowlist (addresses, ranges or AS numbers; GMT panel → Sign-ups) skips the rest;
 *  3. no account was made from the same network in the last 24 hours (the IPv4 address, or the IPv6 /64);
 *  4. it isn't a Tor exit or a hosting network (a datacenter AS list, plus staff's own);
 *  5. proxycheck.io doesn't call it a VPN or proxy. When proxycheck.io can't answer, the sign-up goes
 *     through and the log says so.
 *
 * The quick check (stable asks on every keystroke) runs 1 to 3 only, so a player sees the 24-hour
 * message early. Accounts made and sign-ups refused are kept in tomyum_registrations for 90 days.
 */
class TomYumRegistrationGuard
{
    public const SOURCES = ['lazer', 'stable', 'web'];
    public const KEEP_DAYS = 90;

    public const SETTING_IP_LIMIT = 'registration_ip_limit_enabled';
    public const SETTING_VPN_BLOCK = 'registration_vpn_block_enabled';
    public const SETTING_ALLOWLIST = 'registration_allowlist';
    public const SETTING_BLOCKED_ASNS = 'registration_blocked_asns';

    /** result => what the panel calls it */
    public const RESULTS = [
        'created' => 'Account made',
        'blocked_ban' => 'Banned address',
        'blocked_ip_limit' => 'Already one today',
        'blocked_tor' => 'Tor',
        'blocked_hosting' => 'Hosting network',
        'blocked_proxy' => 'VPN or proxy',
        'blocked_invalid_ip' => 'Unknown address',
        'proxy_unchecked' => 'Not checked (proxycheck.io down)',
    ];

    private const HOSTING_CACHE = 'tomyum:registration:hosting-asns';
    private const TOR_CACHE = 'tomyum:registration:tor-exits';

    /**
     * Whether this sign-up may go ahead: null when it may, otherwise ['code' => result, 'message' =>
     * what to tell the player]. A refused full check is logged.
     */
    public static function check(string $ip, string $source, bool $quick = false, ?string $username = null, ?string $country = null): ?array
    {
        $ip = trim($ip);
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return $quick ? null : static::refuse($ip, $source, 'blocked_invalid_ip', static::message('invalid_ip'), $username, $country);
        }

        if (IpBan::where('ip', '=', $ip)->exists()) {
            return static::refuse($ip, $source, 'blocked_ban', static::message('ban'), $username, $country, $quick);
        }

        $asn = static::asn($ip);
        if (static::isAllowlisted($ip, $asn)) {
            return null;
        }

        if (static::ipLimitEnabled()) {
            $last = DB::table('tomyum_registrations')
                ->where('ip_prefix', static::ipPrefix($ip))
                ->where('result', 'created')
                ->where('created_at', '>=', Carbon::now()->subDay())
                ->max('created_at');
            if ($last !== null) {
                $after = Carbon::parse($last)->addDay();

                return static::refuse($ip, $source, 'blocked_ip_limit', static::message('ip_limit', $after), $username, $country, $quick);
            }
        }

        if ($quick || !static::vpnBlockEnabled()) {
            return null;
        }

        if (isset(static::torExits()[$ip])) {
            return static::refuse($ip, $source, 'blocked_tor', static::message('vpn'), $username, $country);
        }
        if ($asn !== null && (isset(static::hostingAsns()[$asn]) || in_array($asn, static::extraBlockedAsns(), true))) {
            return static::refuse($ip, $source, 'blocked_hosting', static::message('vpn'), $username, $country, false, "AS{$asn}");
        }

        $proxy = static::proxycheck($ip);
        if ($proxy === null) {
            static::log($ip, $source, 'proxy_unchecked', 'proxycheck.io could not be reached; let through', null, $username, $country);
        } elseif ($proxy['proxy']) {
            return static::refuse($ip, $source, 'blocked_proxy', static::message('vpn'), $username, $country, false, $proxy['type']);
        }

        return null;
    }

    /** Records an account made in the game (it counts for the 24-hour rule). */
    public static function created(string $ip, string $source, int $userId, ?string $username = null, ?string $country = null): void
    {
        if (filter_var(trim($ip), FILTER_VALIDATE_IP) === false) {
            return;
        }
        static::log(trim($ip), $source, 'created', null, $userId, $username, $country);
    }

    /** Removes log rows older than 90 days; returns how many. */
    public static function prune(): int
    {
        return DB::table('tomyum_registrations')->where('created_at', '<', Carbon::now()->subDays(static::KEEP_DAYS))->delete();
    }

    public static function ipLimitEnabled(): bool
    {
        return TomYumSiteSettings::get(static::SETTING_IP_LIMIT) !== '0';
    }

    public static function vpnBlockEnabled(): bool
    {
        return TomYumSiteSettings::get(static::SETTING_VPN_BLOCK) !== '0';
    }

    /** The allowlist: one address, range (CIDR) or AS number ("AS1234") per entry. */
    public static function allowlist(): array
    {
        return static::lines(TomYumSiteSettings::get(static::SETTING_ALLOWLIST));
    }

    /** AS numbers staff block on top of the downloaded list. */
    public static function extraBlockedAsns(): array
    {
        return array_values(array_filter(array_map(
            fn ($line) => ctype_digit($asn = preg_replace('/^AS/i', '', $line)) ? (int) $asn : null,
            static::lines(TomYumSiteSettings::get(static::SETTING_BLOCKED_ASNS)),
        )));
    }

    /** Whether an allowlist entry is well formed (an address, a CIDR range or "AS<number>"). */
    public static function isValidEntry(string $entry): bool
    {
        if (preg_match('/^AS\d+$/i', $entry) === 1 || filter_var($entry, FILTER_VALIDATE_IP) !== false) {
            return true;
        }
        $parts = explode('/', $entry, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[1]) || filter_var($parts[0], FILTER_VALIDATE_IP) === false) {
            return false;
        }

        return (int) $parts[1] <= (str_contains($parts[0], ':') ? 128 : 32);
    }

    /** The network an address counts for: an IPv4 address itself, an IPv6 address's /64. */
    public static function ipPrefix(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $ip;
        }

        return inet_ntop(substr(inet_pton($ip), 0, 8).str_repeat("\0", 8)).'/64';
    }

    /** The AS number of an address (osu-web's ip2asn database), or null. */
    public static function asn(string $ip): ?int
    {
        try {
            $asn = (int) app('ip2asn')->lookup($ip);
        } catch (\Throwable $e) {
            log_error($e);

            return null;
        }

        return $asn > 0 ? $asn : null;
    }

    /**
     * Downloads the hosting AS list and the Tor exit list (tomyum:registration-lists-update, daily).
     * Returns [hosting AS count, Tor exit count]; a list that fails to download keeps the old one.
     */
    public static function updateLists(int $timeout = 20): array
    {
        $config = $GLOBALS['cfg']['tomyum']['registration'];

        $hosting = Cache::get(static::HOSTING_CACHE);
        try {
            $body = Http::timeout($timeout)->get($config['hosting_asn_list_url'])->throw()->body();
            preg_match_all('/^\s*AS(\d+)/mi', $body, $matches);
            if (count($matches[1]) > 0) {
                $hosting = array_fill_keys(array_map('intval', $matches[1]), true);
                Cache::forever(static::HOSTING_CACHE, $hosting);
            }
        } catch (\Throwable $e) {
            Log::warning("Registration: the hosting AS list couldn't be downloaded: {$e->getMessage()}");
        }

        $tor = Cache::get(static::TOR_CACHE);
        try {
            $body = Http::timeout($timeout)->get($config['tor_exit_list_url'])->throw()->body();
            $ips = array_filter(array_map('trim', explode("\n", $body)), fn ($line) => filter_var($line, FILTER_VALIDATE_IP) !== false);
            if (count($ips) > 0) {
                $tor = array_fill_keys($ips, true);
                Cache::forever(static::TOR_CACHE, $tor);
            }
        } catch (\Throwable $e) {
            Log::warning("Registration: the Tor exit list couldn't be downloaded: {$e->getMessage()}");
        }

        return [count($hosting ?? []), count($tor ?? [])];
    }

    /** [hosting AS count, Tor exit count] as they are now (for the panel). */
    public static function listSizes(): array
    {
        return [count(Cache::get(static::HOSTING_CACHE) ?? []), count(Cache::get(static::TOR_CACHE) ?? [])];
    }

    private static function message(string $key, ?Carbon $after = null): string
    {
        $accounts = TomYumTickets::address('accounts');

        return match ($key) {
            'ban' => "Sign-ups from your network are blocked. If you think this is a mistake, email {$accounts}.",
            'ip_limit' => 'An account was already made from your network in the last 24 hours. You can make another one after '
                .$after->format('j M Y, H:i')." UTC. If someone else in your home or school needs an account, email {$accounts}.",
            'vpn' => "You can't make an account through a VPN, proxy or hosting network. Please turn it off and try again.",
            'invalid_ip' => "We couldn't tell where you're signing up from. Please try again later.",
        };
    }

    private static function refuse(string $ip, string $source, string $code, string $message, ?string $username, ?string $country, bool $quick = false, ?string $detail = null): array
    {
        // Keystroke checks aren't logged: one sign-up would fill the log.
        if (!$quick) {
            static::log($ip, $source, $code, $detail === null ? $message : "{$detail}: {$message}", null, $username, $country);
        }

        return ['code' => $code, 'message' => $message];
    }

    private static function log(string $ip, string $source, string $result, ?string $reason, ?int $userId, ?string $username, ?string $country): void
    {
        $valid = filter_var($ip, FILTER_VALIDATE_IP) !== false;
        $country = $country === null ? null : strtoupper(substr($country, 0, 2));

        try {
            DB::table('tomyum_registrations')->insert([
                'ip' => mb_substr($ip, 0, 45),
                'ip_prefix' => $valid ? static::ipPrefix($ip) : mb_substr($ip, 0, 45),
                'asn' => $valid ? static::asn($ip) : null,
                'country' => $country === null || preg_match('/^[A-Z0-9]{2}$/', $country) !== 1 ? null : $country,
                'source' => in_array($source, static::SOURCES, true) ? $source : 'web',
                'result' => $result,
                'reason' => $reason === null ? null : mb_substr($reason, 0, 500),
                'user_id' => $userId,
                'username' => $username === null ? null : mb_substr($username, 0, 32),
                'created_at' => Carbon::now(),
            ]);
        } catch (\Throwable $e) {
            log_error($e);
        }
    }

    private static function isAllowlisted(string $ip, ?int $asn): bool
    {
        foreach (static::allowlist() as $entry) {
            if (preg_match('/^AS(\d+)$/i', $entry, $match) === 1) {
                if ($asn !== null && (int) $match[1] === $asn) {
                    return true;
                }
            } elseif (static::inRange($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    /** Whether $ip is $range (an address, or a CIDR range of the same IP version). */
    private static function inRange(string $ip, string $range): bool
    {
        [$network, $bits] = array_pad(explode('/', $range, 2), 2, null);
        $ipBin = inet_pton($ip);
        $networkBin = filter_var($network, FILTER_VALIDATE_IP) === false ? false : inet_pton($network);
        if ($ipBin === false || $networkBin === false || strlen($ipBin) !== strlen($networkBin)) {
            return false;
        }

        $bits = $bits === null ? strlen($ipBin) * 8 : (int) $bits;
        $bytes = intdiv($bits, 8);
        if (substr($ipBin, 0, $bytes) !== substr($networkBin, 0, $bytes)) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xff << (8 - $rest)) & 0xff;

        return (ord($ipBin[$bytes]) & $mask) === (ord($networkBin[$bytes]) & $mask);
    }

    /**
     * proxycheck.io's verdict, cached a day: ['proxy' => bool, 'type' => ?string], or null when it
     * couldn't answer (down, out of checks, or switched off).
     */
    private static function proxycheck(string $ip): ?array
    {
        $config = $GLOBALS['cfg']['tomyum']['registration'];
        if (!$config['proxycheck_enabled']) {
            return null;
        }

        $cacheKey = 'tomyum:registration:proxycheck:'.$ip;
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(4)->get('https://proxycheck.io/v2/'.rawurlencode($ip), array_filter([
                'key' => $config['proxycheck_key'],
                'vpn' => 1,
                'asn' => 1,
            ]));
            $data = $response->json();
            $status = is_array($data) ? ($data['status'] ?? null) : null;
            if (!$response->successful() || !in_array($status, ['ok', 'warning'], true)) {
                Log::warning('Registration: proxycheck.io gave no answer', ['status' => $response->status(), 'message' => is_array($data) ? ($data['message'] ?? null) : null]);

                return null;
            }

            // The answer is keyed by the address (as proxycheck.io writes it).
            $entry = $data[$ip] ?? null;
            foreach ($entry === null ? $data : [] as $value) {
                if (is_array($value) && array_key_exists('proxy', $value)) {
                    $entry = $value;
                    break;
                }
            }
            if (!is_array($entry)) {
                return null;
            }

            $verdict = ['proxy' => ($entry['proxy'] ?? 'no') === 'yes', 'type' => $entry['type'] ?? null];
            Cache::put($cacheKey, $verdict, 86400);

            return $verdict;
        } catch (\Throwable $e) {
            Log::warning("Registration: proxycheck.io couldn't be reached: {$e->getMessage()}");

            return null;
        }
    }

    private static function hostingAsns(): array
    {
        static::ensureLists();

        return Cache::get(static::HOSTING_CACHE) ?? [];
    }

    private static function torExits(): array
    {
        static::ensureLists();

        return Cache::get(static::TOR_CACHE) ?? [];
    }

    /** Before the first daily update: downloads the lists now (trying at most once an hour). */
    private static function ensureLists(): void
    {
        if ((Cache::has(static::HOSTING_CACHE) && Cache::has(static::TOR_CACHE)) || !Cache::add('tomyum:registration:lists-tried', true, 3600)) {
            return;
        }
        static::updateLists(5);
    }

    private static function lines(?string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $text ?? ''))));
    }
}

<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumRegistrationGuard;
use App\Libraries\TomYumSiteSettings;
use Auth;
use Carbon\Carbon;
use DB;
use Request;

/**
 * TomYum: GMT panel → Sign-ups. Accounts made in the game and the sign-ups the rules refused (the
 * last 90 days), the rules' switches, and the allowlist for a network staff have checked (a school,
 * a dorm, an internet cafe). Sign-up addresses are personal data: GMT and admins only.
 */
class RegistrationsController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseGmt(Auth::user()));

            return $next($request);
        });
    }

    public function index()
    {
        $filter = Request::input('show');
        $filter = in_array($filter, ['blocked', 'created'], true) ? $filter : 'all';
        $query = trim(Request::input('q') ?? '');

        $rows = DB::table('tomyum_registrations')->orderByDesc('id')->limit(200);
        if ($filter === 'created') {
            $rows->where('result', 'created');
        } elseif ($filter === 'blocked') {
            $rows->where('result', 'like', 'blocked_%');
        }
        if ($query !== '') {
            $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
            $rows->where(fn ($q) => $q->where('ip', 'like', $like)->orWhere('username', 'like', $like));
        }

        $since = Carbon::now()->subDay();
        [$hostingCount, $torCount] = TomYumRegistrationGuard::listSizes();

        return ext_view('panel.gmt.registrations', [
            'filter' => $filter,
            'query' => $query,
            'rows' => $rows->get(),
            'today' => DB::table('tomyum_registrations')->where('created_at', '>=', $since)
                ->selectRaw("SUM(result = 'created') AS created, SUM(result LIKE 'blocked\\_%') AS blocked")->first(),
            'ipLimit' => TomYumRegistrationGuard::ipLimitEnabled(),
            'vpnBlock' => TomYumRegistrationGuard::vpnBlockEnabled(),
            'allowlist' => implode("\n", TomYumRegistrationGuard::allowlist()),
            'blockedAsns' => implode("\n", array_map(fn ($asn) => "AS{$asn}", TomYumRegistrationGuard::extraBlockedAsns())),
            'hostingCount' => $hostingCount,
            'torCount' => $torCount,
            'proxycheck' => $GLOBALS['cfg']['tomyum']['registration']['proxycheck_enabled']
                ? ($GLOBALS['cfg']['tomyum']['registration']['proxycheck_key'] === null ? 'on, without a key (about 100 checks a day)' : 'on')
                : 'off',
        ]);
    }

    public function settings()
    {
        $allowlist = static::entries(Request::input('allowlist'));
        $blockedAsns = static::entries(Request::input('blocked_asns'));

        $invalid = array_values(array_filter($allowlist, fn ($entry) => !TomYumRegistrationGuard::isValidEntry($entry)));
        $invalidAsns = array_values(array_filter($blockedAsns, fn ($entry) => preg_match('/^(AS)?\d+$/i', $entry) !== 1));
        if (count($invalid) + count($invalidAsns) > 0) {
            return $this->back('error', 'Not an address, range or AS number: '.implode(', ', array_merge($invalid, $invalidAsns)).'.');
        }

        $ipLimit = Request::boolean('ip_limit');
        $vpnBlock = Request::boolean('vpn_block');
        $blockedAsns = array_map(fn ($entry) => 'AS'.preg_replace('/^AS/i', '', $entry), $blockedAsns);

        TomYumSiteSettings::set(TomYumRegistrationGuard::SETTING_IP_LIMIT, $ipLimit ? '1' : '0');
        TomYumSiteSettings::set(TomYumRegistrationGuard::SETTING_VPN_BLOCK, $vpnBlock ? '1' : '0');
        TomYumSiteSettings::set(TomYumRegistrationGuard::SETTING_ALLOWLIST, implode("\n", $allowlist));
        TomYumSiteSettings::set(TomYumRegistrationGuard::SETTING_BLOCKED_ASNS, implode("\n", $blockedAsns));

        $this->staffLog('registration.settings', null, sprintf(
            'Sign-up rules: 1 a day %s, VPN block %s, %d allowlisted, %d extra blocked networks',
            $ipLimit ? 'on' : 'off',
            $vpnBlock ? 'on' : 'off',
            count($allowlist),
            count($blockedAsns),
        ));

        return $this->back('success', 'Sign-up rules saved.');
    }

    public function updateLists()
    {
        [$hosting, $tor] = TomYumRegistrationGuard::updateLists();

        return $this->back('success', "Lists updated: {$hosting} hosting networks, {$tor} Tor exits.");
    }

    private static function entries(?string $text): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $text ?? '')))));
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.gmt.registrations.index'))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}

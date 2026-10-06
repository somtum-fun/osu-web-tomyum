{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: GMT panel → Sign-ups (Panel\RegistrationsController). --}}
@php
    use App\Libraries\TomYumRegistrationGuard;

    $sourceNames = ['lazer' => 'lazer-fork', 'stable' => 'stable', 'web' => 'website'];
@endphp
@extends('master', ['titlePrepend' => 'Sign-ups'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.gmt.index'), 'title' => 'GMT panel'],
        ['title' => 'Sign-ups'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-user-plus"></i>Sign-up rules</h2>
            <p class="tomyum-panel__hint">
                Accounts are made in the game only (lazer-fork and stable). Every sign-up is checked in this order:
                banned addresses; then the allowlist below skips the rest; one account per network (an IPv4
                address, or an IPv6 /64) in 24 hours; Tor and hosting networks ({{ i18n_number_format($hostingCount) }}
                hosting networks and {{ i18n_number_format($torCount) }} Tor exits on the lists, updated daily);
                then proxycheck.io ({{ $proxycheck }}). If proxycheck.io doesn't answer, the sign-up goes through
                and shows here as "not checked".
            </p>
            <p>
                In the last 24 hours: <strong>{{ (int) ($today->created ?? 0) }}</strong> account(s) made,
                <strong>{{ (int) ($today->blocked ?? 0) }}</strong> sign-up(s) refused.
            </p>
            <form method="POST" action="{{ route('panel.gmt.registrations.settings') }}">
                @csrf
                <div class="tomyum-panel__row" style="gap: 16px; margin-bottom: 10px">
                    <label class="tomyum-panel__check"><input type="checkbox" name="ip_limit" value="1" @if ($ipLimit) checked @endif> One account per network a day</label>
                    <label class="tomyum-panel__check"><input type="checkbox" name="vpn_block" value="1" @if ($vpnBlock) checked @endif> Block VPNs, proxies, Tor and hosting networks</label>
                </div>
                <div class="tomyum-panel__row" style="align-items: flex-start">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Allowlist: addresses, ranges (CIDR) or AS numbers, one per line</span>
                        <textarea class="input-text" name="allowlist" rows="6" placeholder="203.0.113.7&#10;198.51.100.0/24&#10;AS131445">{{ $allowlist }}</textarea>
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Also block these networks (AS numbers, one per line)</span>
                        <textarea class="input-text" name="blocked_asns" rows="6" placeholder="AS14061">{{ $blockedAsns }}</textarea>
                    </label>
                </div>
                <p class="tomyum-panel__hint">
                    Allowlist a network only after checking it (a school, a dorm or an internet cafe that wrote to
                    {{ App\Libraries\TomYumTickets::address('accounts') }}): everyone on it skips the daily limit and the VPN check. Bans still apply.
                </p>
                <div class="tomyum-panel__row" style="gap: 8px">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save</button>
                </div>
            </form>
            <form method="POST" action="{{ route('panel.gmt.registrations.update-lists') }}" style="margin-top: 10px">
                @csrf
                <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" type="submit"><i class="fas fa-sync"></i>Update the lists now</button>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-list"></i>Recent sign-ups</h2>
            <form method="GET" action="{{ route('panel.gmt.registrations.index') }}" class="tomyum-panel__row" style="align-items: flex-end">
                <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 180px">
                    <span class="input-container__label">Show</span>
                    <select class="input-text" name="show">
                        @foreach (['all' => 'Everything', 'blocked' => 'Refused', 'created' => 'Accounts made'] as $value => $label)
                            <option value="{{ $value }}" @if ($filter === $value) selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Address or username starts with</span>
                    <input class="input-text" type="text" name="q" value="{{ $query }}">
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Filter</button>
            </form>
            <p class="tomyum-panel__hint">The last 90 days are kept; addresses are only for staff.</p>
            @if (count($rows) === 0)
                <p class="tomyum-panel__empty">Nothing yet.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>When</th><th>Result</th><th>From</th><th>Address</th><th>Network</th><th>Username</th><th>Why</th></tr>
                    @foreach ($rows as $row)
                        <tr>
                            <td style="white-space: nowrap">{!! timeago(Carbon\Carbon::parse($row->created_at)) !!}</td>
                            <td>
                                <span class="tomyum-panel__badge {{ $row->result === 'created' ? 'tomyum-panel__badge--success' : (str_starts_with($row->result, 'blocked_') ? 'tomyum-panel__badge--error' : '') }}">
                                    {{ TomYumRegistrationGuard::RESULTS[$row->result] ?? $row->result }}
                                </span>
                            </td>
                            <td>{{ $sourceNames[$row->source] ?? $row->source }}{{ $row->country !== null ? " ({$row->country})" : '' }}</td>
                            <td><code>{{ $row->ip }}</code></td>
                            <td>{{ $row->asn !== null ? "AS{$row->asn}" : '—' }}</td>
                            <td>
                                @if ($row->user_id !== null)
                                    <a href="{{ route('panel.gmt.users.show', ['user' => $row->user_id]) }}">{{ $row->username ?? "#{$row->user_id}" }}</a>
                                @else
                                    {{ $row->username ?? '—' }}
                                @endif
                            </td>
                            <td class="tomyum-panel__hint">{{ $row->reason }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    </div>
@endsection

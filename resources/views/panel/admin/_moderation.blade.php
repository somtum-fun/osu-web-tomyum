{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: moderation on the admin panel's account page. --}}
@php
    $historyTypes = [0 => 'Note', 1 => 'Restriction', 2 => 'Silence', 3 => 'Tournament ban'];
    $resultLabels = ['ok' => 'Logged in', 'held' => 'Device to verify', 'locked' => 'Refused (locked)', 'bad_password' => 'Wrong password'];
    $locked = $flags?->locked_at !== null;
    $whitelisted = $flags?->whitelisted_at !== null;
    $actorNames = App\Http\Controllers\Panel\GmtController::userNames([$flags?->locked_by, $flags?->whitelisted_by]);
    $silenceOptions = function () use ($silenceLengths) {
        $html = '';
        foreach ($silenceLengths as $seconds => $label) {
            $html .= '<option value="'.e($seconds).'">'.e($label).'</option>';
        }

        return $html;
    };
@endphp

<div class="tomyum-panel__card" id="standing">
    <h2><i class="fas fa-balance-scale"></i>Account standing</h2>
    <p class="tomyum-panel__row" style="gap: 6px; margin: 0 0 10px">
        @if ($target->isRestricted())
            <span class="tomyum-panel__badge tomyum-panel__badge--error">{{ $target->isBanned() ? 'Banned' : 'Restricted' }}</span>
        @endif
        @if ($silencedUntil !== null)
            <span class="tomyum-panel__badge tomyum-panel__badge--error">Silenced until {{ $silencedUntil->toDayDateTimeString() }} UTC</span>
        @endif
        @if ($locked)
            <span class="tomyum-panel__badge tomyum-panel__badge--error">Locked</span>
        @endif
        @if ($whitelisted)
            <span class="tomyum-panel__badge tomyum-panel__badge--success">Anti-cheat whitelisted</span>
        @endif
        @if (!$target->isRestricted() && $silencedUntil === null && !$locked)
            <span class="tomyum-panel__badge tomyum-panel__badge--success">In good standing</span>
        @endif
    </p>
    <p class="tomyum-panel__hint" style="margin: 0 0 12px">
        Anti-cheat last flagged them:
        @if ($lastDetection !== null)
            {!! timeago(Carbon\Carbon::parse($lastDetection['createdAt'])) !!} &mdash; {{ $lastDetection['summary'] }}
        @else
            never
        @endif
    </p>

    <div class="tomyum-panel__row" style="align-items: flex-end">
        @if ($target->isRestricted())
            <form method="POST" action="{{ route('panel.gmt.users.unrestrict', ['user' => $userId]) }}" class="tomyum-panel__row" style="flex: 1">
                @csrf
                <input type="hidden" name="from" value="admin">
                <label class="input-container tomyum-panel__field">
<span class="input-container__label">Reason</span>
<input class="input-text" type="text" name="reason" required></label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-check"></i>Unrestrict</button>
            </form>
        @else
            <form method="POST" action="{{ route('panel.gmt.users.restrict', ['user' => $userId]) }}" class="tomyum-panel__row" style="flex: 1" onsubmit="return confirm('Restrict {{ $target->username }}?')">
                @csrf
                <input type="hidden" name="from" value="admin">
                <label class="input-container tomyum-panel__field">
<span class="input-container__label">Reason</span>
<input class="input-text" type="text" name="reason" required></label>
                <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-ban"></i>Restrict</button>
            </form>
        @endif
    </div>

    <div class="tomyum-panel__row" style="align-items: flex-end; margin-top: 10px">
        @if ($silencedUntil !== null)
            <form method="POST" action="{{ route('panel.gmt.users.unsilence', ['user' => $userId]) }}" class="tomyum-panel__row" style="flex: 1">
                @csrf
                <input type="hidden" name="from" value="admin">
                <label class="input-container tomyum-panel__field">
<span class="input-container__label">Reason (optional)</span>
<input class="input-text" type="text" name="reason"></label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-comment"></i>Unsilence</button>
            </form>
        @else
            <form method="POST" action="{{ route('panel.gmt.users.silence', ['user' => $userId]) }}" class="tomyum-panel__row" style="flex: 1">
                @csrf
                <input type="hidden" name="from" value="admin">
                <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 160px">
<span class="input-container__label">Length</span>
<select class="input-text" name="length">{!! $silenceOptions() !!}</select></label>
                <label class="input-container tomyum-panel__field">
<span class="input-container__label">Reason</span>
<input class="input-text" type="text" name="reason" required></label>
                <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-comment-slash"></i>Silence</button>
            </form>
        @endif
    </div>

    <form method="POST" action="{{ route('panel.admin.users.note', ['user' => $userId]) }}" class="tomyum-panel__row" style="align-items: flex-end; margin-top: 10px">
        @csrf
        <label class="input-container tomyum-panel__field">
<span class="input-container__label">Add a note to their history</span>
<input class="input-text" type="text" name="reason" required maxlength="500"></label>
        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-sticky-note"></i>Add note</button>
    </form>

    <h3 style="margin-top: 16px">History</h3>
    @if ($history->isEmpty())
        <p class="tomyum-panel__empty">Nothing on record.</p>
    @else
        <table class="tomyum-panel__table">
            <tr><th>When</th><th>Type</th><th>Reason</th><th>Length</th><th>By</th></tr>
            @foreach ($history as $entry)
                <tr>
                    <td style="white-space: nowrap">{!! timeago($entry->timestamp) !!}</td>
                    <td>{{ $historyTypes[$entry->ban_status] ?? $entry->ban_status }}</td>
                    <td>{{ $entry->reason }}</td>
                    <td>{{ $entry->permanent ? 'permanent' : ($entry->period > 0 ? ($silenceLengths[$entry->period] ?? $entry->period.'s') : '—') }}</td>
                    <td>{{ $entry->actor?->username ?? '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</div>

<div class="tomyum-panel__columns">
<div class="tomyum-panel__card" id="access">
    <h2><i class="fas fa-lock"></i>Lock &amp; anti-cheat whitelist</h2>
    <div class="tomyum-panel__row" style="gap: 24px; align-items: flex-start">
        <div style="flex: 1 1 300px">
            <h3 style="margin-top: 0">Lock</h3>
            @if ($locked)
                <p>Locked {!! timeago(Carbon\Carbon::parse($flags->locked_at)) !!} by {{ $actorNames[$flags->locked_by] ?? '—' }}: {{ $flags->lock_reason }}</p>
                <form method="POST" action="{{ route('panel.admin.users.lock', ['user' => $userId]) }}" class="tomyum-panel__row" style="align-items: flex-end">
                    @csrf
                    <input type="hidden" name="action" value="unlock">
                    <label class="input-container tomyum-panel__field">
<span class="input-container__label">Reason (optional)</span>
<input class="input-text" type="text" name="reason"></label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-lock-open"></i>Unlock</button>
                </form>
            @else
                <p class="tomyum-panel__hint">No logins anywhere (website, lazer, stable), and signed out everywhere now, until unlocked.</p>
                <form method="POST" action="{{ route('panel.admin.users.lock', ['user' => $userId]) }}" class="tomyum-panel__row" style="align-items: flex-end" onsubmit="return confirm('Lock {{ $target->username }}?')">
                    @csrf
                    <input type="hidden" name="action" value="lock">
                    <label class="input-container tomyum-panel__field">
<span class="input-container__label">Reason</span>
<input class="input-text" type="text" name="reason" required></label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit" @disabled($isSelf)><i class="fas fa-lock"></i>Lock</button>
                </form>
            @endif
        </div>
        <div style="flex: 1 1 300px">
            <h3 style="margin-top: 0">Anti-cheat whitelist</h3>
            @if ($whitelisted)
                <p>Whitelisted {!! timeago(Carbon\Carbon::parse($flags->whitelisted_at)) !!} by {{ $actorNames[$flags->whitelisted_by] ?? '—' }}: {{ $flags->whitelist_reason }}</p>
                <form method="POST" action="{{ route('panel.admin.users.whitelist', ['user' => $userId]) }}">
                    @csrf
                    <input type="hidden" name="action" value="remove">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-times"></i>Remove from whitelist</button>
                </form>
            @else
                <p class="tomyum-panel__hint">circleguard and the suspicious-play checks leave their plays alone.</p>
                <form method="POST" action="{{ route('panel.admin.users.whitelist', ['user' => $userId]) }}" class="tomyum-panel__row" style="align-items: flex-end">
                    @csrf
                    <input type="hidden" name="action" value="add">
                    <label class="input-container tomyum-panel__field">
<span class="input-container__label">Reason</span>
<input class="input-text" type="text" name="reason" required></label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-shield-alt"></i>Whitelist</button>
                </form>
            @endif
        </div>
    </div>
</div>

@include('panel.admin._connections')
</div>

<div class="tomyum-panel__card" id="devices">
    <h2><i class="fas fa-desktop"></i>Devices (hardware fingerprint)</h2>
    <p class="tomyum-panel__hint">
        Every osu!stable fingerprint they logged in with, and the other accounts seen on it. Wiping makes every
        device unverified: their next stable login asks them to confirm it on the website.
    </p>
    <form method="POST" action="{{ route('panel.admin.users.devices.wipe', ['user' => $userId]) }}" class="tomyum-panel__row" style="align-items: flex-end" onsubmit="return confirm('Reset all of {{ $target->username }}\'s devices?')">
        @csrf
        <label class="input-container tomyum-panel__field">
<span class="input-container__label">Reason (optional)</span>
<input class="input-text" type="text" name="reason"></label>
        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-eraser"></i>Wipe HWID</button>
    </form>
    @if ($devices === null)
        <p>Couldn't load devices from the game server.</p>
    @elseif (count($devices) === 0)
        <p class="tomyum-panel__empty">No stable logins recorded.</p>
    @else
        <div class="tomyum-panel__scroll">
            <table class="tomyum-panel__table">
                <tr><th>Last used</th><th>Logins</th><th>Verified</th><th>Uninstall ID</th><th>Disk</th><th>Adapters</th><th>osu! path</th><th>Users on device</th></tr>
                @foreach ($devices as $device)
                    @php $verified = $verifiedDevices[$device['uninstallId']] ?? null; @endphp
                    <tr>
                        <td style="white-space: nowrap">{!! timeago(Carbon\Carbon::parse($device['lastUsed'])) !!}</td>
                        <td>{{ number_format($device['logins']) }}</td>
                        <td>{{ $verified === null ? '—' : ($verified ? 'yes' : 'no') }}</td>
                        <td><code title="{{ $device['uninstallId'] }}">{{ substr($device['uninstallId'], 0, 10) }}</code></td>
                        <td><code title="{{ $device['diskSerial'] }}">{{ substr($device['diskSerial'], 0, 10) }}</code></td>
                        <td><code title="{{ $device['adapters'] }}">{{ substr($device['adapters'], 0, 10) }}</code></td>
                        <td><code title="{{ $device['osuPath'] }}">{{ substr($device['osuPath'], 0, 10) }}</code></td>
                        <td>
                            @forelse ($device['otherUsers'] as $other)
                                <a href="{{ route('panel.admin.users.show', ['user' => $other['id']]) }}">{{ $other['username'] }}</a>@if (!$loop->last), @endif
                            @empty
                                <span class="tomyum-panel__hint">only them</span>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
</div>

<div class="tomyum-panel__card" id="logins">
    <h2><i class="fas fa-sign-in-alt"></i>Login history</h2>
    <p class="tomyum-panel__hint">Stable, lazer and website logins (the last 50; kept 180 days).</p>
    @if (count($logins) === 0)
        <p class="tomyum-panel__empty">No logins recorded yet.</p>
    @else
        <div class="tomyum-panel__scroll">
            <table class="tomyum-panel__table">
                <tr><th>When</th><th>Client</th><th>Result</th><th>IP</th><th>Country</th><th>Version / browser</th></tr>
                @foreach ($logins as $login)
                    <tr>
                        <td style="white-space: nowrap">{!! timeago(Carbon\Carbon::parse($login->created_at, 'UTC')) !!}</td>
                        <td>{{ ['stable' => 'osu!stable', 'lazer' => 'osu!lazer', 'web' => 'Website'][$login->client] ?? $login->client }}</td>
                        <td>
                            <span class="tomyum-panel__badge tomyum-panel__badge--{{ $login->result === 'ok' ? 'success' : 'error' }}">{{ $resultLabels[$login->result] ?? $login->result }}</span>
                        </td>
                        <td><code>{{ $login->ip ?? '—' }}</code></td>
                        <td>{{ $login->country ?? '—' }}</td>
                        <td style="max-width: 360px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap" title="{{ $login->agent }}">{{ $login->agent ?? '—' }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
</div>

<div class="tomyum-panel__card" id="content">
    <h2><i class="fas fa-eraser"></i>Reset profile content</h2>
    <p class="tomyum-panel__hint">Removes their avatar, profile banner or me! page. Optionally warns them (a note in their history) and/or silences them.</p>
    <form method="POST" action="{{ route('panel.admin.users.reset', ['user' => $userId]) }}" class="tomyum-panel__row" style="align-items: flex-end" onsubmit="return confirm('Reset it?')">
        @csrf
        <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 170px">
            <span class="input-container__label">Reset</span>
            <select class="input-text" name="what">
                <option value="avatar">Avatar</option>
                <option value="cover">Profile banner</option>
                <option value="page">me! page</option>
            </select>
        </label>
        <label class="input-container tomyum-panel__field">
<span class="input-container__label">Reason</span>
<input class="input-text" type="text" name="reason" placeholder="inappropriate content"></label>
        <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 170px">
            <span class="input-container__label">Silence</span>
            <select class="input-text" name="silence"><option value="0">No silence</option>{!! $silenceOptions() !!}</select>
        </label>
        <label class="tomyum-panel__check"><input type="checkbox" name="warn" value="1"> Warn</label>
        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-eraser"></i>Reset</button>
    </form>
</div>

<div class="tomyum-panel__card" id="rollback">
    <h2><i class="fas fa-undo"></i>Rollback account</h2>
    <p class="tomyum-panel__hint">
        Removes every score they set after the moment you pick (UTC), in one mode or all. Their earlier plays
        become their bests again and their pp, rank and stats are recalculated. Can't be undone.
    </p>
    <form method="POST" action="{{ route('panel.admin.users.rollback', ['user' => $userId]) }}" class="tomyum-panel__row" style="align-items: flex-end"
        onsubmit="return confirm('Remove every score after this moment? This can\'t be undone.')">
        @csrf
        <label class="input-container tomyum-panel__field" style="flex: 0 1 220px">
<span class="input-container__label">Remove scores after (UTC)</span>
<input class="input-text" type="datetime-local" name="after" required></label>
        <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 170px">
            <span class="input-container__label">Mode</span>
            <select class="input-text" name="mode">
                <option value="all">All modes</option>
                @foreach ($modes as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="input-container tomyum-panel__field">
<span class="input-container__label">Reason</span>
<input class="input-text" type="text" name="reason" required></label>
        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-undo"></i>Roll back</button>
    </form>
</div>

<div class="tomyum-panel__card" id="names">
    <h2><i class="fas fa-id-badge"></i>Username history</h2>
    @if ($usernames->isEmpty())
        <p class="tomyum-panel__empty">Never renamed.</p>
    @else
        <table class="tomyum-panel__table">
            <tr><th>When</th><th>From</th><th>To</th><th>How</th></tr>
            @foreach ($usernames as $change)
                <tr>
                    <td style="white-space: nowrap">{!! $change->timestamp ? timeago($change->timestamp) : '—' !!}</td>
                    <td>{{ $change->username_last }}</td>
                    <td>{{ $change->username }}</td>
                    <td>{{ $change->type }}</td>
                </tr>
            @endforeach
        </table>
        @php
            $earlier = $usernames->pluck('username_last')->merge($usernames->pluck('username'))
                ->filter(fn ($name) => $name !== null && $name !== $target->username)->unique()->values();
        @endphp
        @if ($earlier->isNotEmpty())
            <form method="POST" action="{{ route('panel.admin.users.username-rollback', ['user' => $userId]) }}" class="tomyum-panel__row" style="align-items: flex-end; margin-top: 10px">
                @csrf
                <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 200px">
                    <span class="input-container__label">Change back to</span>
                    <select class="input-text" name="username">
                        @foreach ($earlier as $name)
                            <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="input-container tomyum-panel__field">
<span class="input-container__label">Reason (optional)</span>
<input class="input-text" type="text" name="reason"></label>
                <label class="tomyum-panel__check"><input type="checkbox" name="warn" value="1"> Warn</label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-undo"></i>Roll back name</button>
            </form>
        @endif
    @endif
</div>

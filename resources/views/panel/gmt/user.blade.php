{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    use App\Http\Controllers\Panel\GmtController;
    use App\Models\UserAccountHistory;

    $historyTypes = array_flip(UserAccountHistory::TYPES);
    $modeNames = GmtController::MODES + [7 => 'osu!mania relax', 9 => 'osu!taiko autopilot', 10 => 'osu!catch autopilot', 11 => 'osu!mania autopilot'];
    $userId = $target->getKey();
@endphp
@extends('master', ['titlePrepend' => $target->username])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.gmt.index'), 'title' => 'GMT panel'],
        ['title' => $target->username],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card tomyum-panel__row" style="align-items: center">
            <img src="{{ App\Libraries\User\AvatarHelper::url($target) }}"
                alt="" style="border-radius: 12px; height: 64px; width: 64px; object-fit: cover">
            <div style="flex: 1">
                <h2 style="margin: 0">
                    <a href="{{ route('users.show', ['user' => $userId]) }}">{{ $target->username }}</a>
                    <span class="tomyum-panel__hint">#{{ $userId }} &middot; {{ $target->country_acronym }}</span>
                </h2>
                <p style="margin: 6px 0 0">
                    @if ($target->isRestricted())
                        <span class="tomyum-panel__badge tomyum-panel__badge--error">Restricted</span>
                    @endif
                    @if ($silencedUntil !== null)
                        <span class="tomyum-panel__badge tomyum-panel__badge--error">Silenced until {{ $silencedUntil->toDayDateTimeString() }} UTC</span>
                    @endif
                    @if (!$target->isRestricted() && $silencedUntil === null)
                        <span class="tomyum-panel__badge tomyum-panel__badge--success">In good standing</span>
                    @endif
                    <span class="tomyum-panel__hint">
                        &middot; {{ $online ? 'online now' : 'offline' }} &middot; joined {{ $target->user_regdate?->toFormattedDateString() }}
                    </span>
                </p>
            </div>
            <a href="{{ route('panel.gmt.chat', ['user' => $userId]) }}">Chat log</a>
        </div>

        @if (!$canModerate)
            <div class="tomyum-panel__status tomyum-panel__status--error">
                You can't moderate this account (it's yours, or staff only an admin can moderate).
            </div>
        @else
            <div class="tomyum-panel__card">
                <h2>Restriction</h2>
                <p class="tomyum-panel__hint">
                    Restricted players can still log in and play, but they're hidden from other players, leaderboards
                    and rankings, and can only message BanchoBot. Takes effect in-game immediately. Their scores are kept.
                </p>
                <form method="POST" action="{{ route($target->isRestricted() ? 'panel.gmt.users.unrestrict' : 'panel.gmt.users.restrict', ['user' => $userId]) }}" class="tomyum-panel__row"
                    onsubmit="return confirm({{ json_encode(($target->isRestricted() ? 'Unrestrict ' : 'Restrict ').$target->username.'?') }})">
                    @csrf
                    <label class="tomyum-panel__field">
                        Reason (shown in their account history)
                        <input type="text" name="reason" required maxlength="2000">
                    </label>
                    <button class="tomyum-panel__button {{ $target->isRestricted() ? '' : 'tomyum-panel__button--danger' }}" type="submit">
                        {{ $target->isRestricted() ? 'Unrestrict' : 'Restrict' }}
                    </button>
                </form>
            </div>

            <div class="tomyum-panel__card">
                <h2>Silence</h2>
                <p class="tomyum-panel__hint">A silenced player can't chat (they can still use BanchoBot commands). A new silence replaces the current one.</p>
                <form method="POST" action="{{ route('panel.gmt.users.silence', ['user' => $userId]) }}" class="tomyum-panel__row">
                    @csrf
                    <label class="tomyum-panel__field" style="flex: 0 1 160px">
                        Length
                        <select name="length" required>
                            @foreach (GmtController::SILENCE_LENGTHS as $seconds => $label)
                                <option value="{{ $seconds }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="tomyum-panel__field">
                        Reason
                        <input type="text" name="reason" required maxlength="2000">
                    </label>
                    <button class="tomyum-panel__button tomyum-panel__button--danger" type="submit">Silence</button>
                </form>
                @if ($silencedUntil !== null)
                    <form method="POST" action="{{ route('panel.gmt.users.unsilence', ['user' => $userId]) }}" class="tomyum-panel__row" style="margin-top: 10px">
                        @csrf
                        <label class="tomyum-panel__field">
                            Reason for lifting it (optional)
                            <input type="text" name="reason" maxlength="2000">
                        </label>
                        <button class="tomyum-panel__button" type="submit">Lift silence</button>
                    </form>
                @endif
            </div>
        @endif

        <div class="tomyum-panel__card">
            <h2>Scores</h2>
            <p class="tomyum-panel__row" style="gap: 16px; margin: 0 0 10px">
                <a href="{{ route('panel.gmt.users.show', ['user' => $userId]) }}" @if (!$best) style="font-weight: 700" @endif>Recent</a>
                <a href="{{ route('panel.gmt.users.show', ['user' => $userId, 'scores' => 'best']) }}" @if ($best) style="font-weight: 700" @endif>Best (by pp)</a>
            </p>
            @if ($scoresError !== null)
                <p>{{ $scoresError }}</p>
            @elseif (count($scores) === 0)
                <p class="tomyum-panel__hint">No scores.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>When</th><th>Beatmap</th><th>Mode</th><th>Score</th><th>Acc</th><th>pp</th><th>Grade</th><th>Status</th>@if ($canModerate)<th></th>@endif</tr>
                    @foreach ($scores as $score)
                        <tr>
                            <td style="white-space: nowrap">{!! timeago(Carbon\Carbon::parse($score['submittedAt'])) !!}</td>
                            <td><a href="{{ route('beatmaps.show', ['beatmap' => $score['beatmapId']]) }}">{{ $score['beatmap'] }}</a></td>
                            <td>{{ $modeNames[$score['mode']] ?? $score['mode'] }}</td>
                            <td>{{ number_format($score['score']) }}</td>
                            <td>{{ number_format($score['accuracy'], 2) }}%</td>
                            <td>{{ number_format($score['pp']) }}</td>
                            <td>{{ $score['grade'] }}</td>
                            <td>{{ $score['status'] }}</td>
                            @if ($canModerate)
                                <td>
                                    <form method="POST" action="{{ route('panel.gmt.users.scores.remove', ['user' => $userId, 'score' => $score['id']]) }}"
                                        onsubmit="var r = prompt('Remove this score? Reason:'); if (r === null) return false; this.reason.value = r; return true;">
                                        @csrf
                                        <input type="hidden" name="reason">
                                        <button class="tomyum-panel__button tomyum-panel__button--danger" type="submit" style="padding: 3px 10px">Remove</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            @endif

            @if ($canModerate)
                <form method="POST" action="{{ route('panel.gmt.users.wipe', ['user' => $userId]) }}" class="tomyum-panel__row" style="margin-top: 16px"
                    onsubmit="return confirm({{ json_encode('Wipe '.$target->username."'s scores? This can't be undone.") }})">
                    @csrf
                    <label class="tomyum-panel__field" style="flex: 0 1 180px">
                        Wipe scores in
                        <select name="mode" required>
                            <option value="all">All modes</option>
                            @foreach (GmtController::MODES as $mode => $label)
                                <option value="{{ $mode }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="tomyum-panel__field">
                        Reason
                        <input type="text" name="reason" required maxlength="2000">
                    </label>
                    <button class="tomyum-panel__button tomyum-panel__button--danger" type="submit">Wipe</button>
                </form>
                <p class="tomyum-panel__hint">Removed scores are gone for good (also their replays); stats and ranks are recalculated.</p>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2>Reports about {{ $target->username }}</h2>
            @if (count($reports) === 0)
                <p class="tomyum-panel__hint">None.</p>
            @else
                @include('panel.gmt._reports', ['reports' => $reports, 'showPlayer' => false])
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2>Account history</h2>
            @if (count($history) === 0)
                <p class="tomyum-panel__hint">Nothing recorded.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>When</th><th>Type</th><th>Length</th><th>By</th><th>Reason</th></tr>
                    @foreach ($history as $entry)
                        <tr>
                            <td style="white-space: nowrap">{!! $entry->timestamp ? timeago($entry->timestamp) : '—' !!}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $historyTypes[$entry->ban_status] ?? 'note')) }}</td>
                            <td>{{ $entry->period > 0 ? Carbon\CarbonInterval::seconds($entry->period)->cascade()->forHumans(['short' => true]) : '—' }}</td>
                            <td>{{ $entry->actor?->username ?? '—' }}</td>
                            <td>{{ $entry->reason }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2>Staff actions</h2>
            @if (count($entries) === 0)
                <p class="tomyum-panel__hint">None yet.</p>
            @else
                @include('panel._staff_log')
            @endif
        </div>
    </div>
@endsection

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
        <div class="tomyum-panel__hero">
            <div class="js-react u-contents" data-react="user-card" data-user="{{ json_encode($userCard) }}"></div>

            <div class="tomyum-panel__card">
                <div class="tomyum-panel__card-head">
                    <h2><i class="fas fa-id-card"></i>{{ $target->username }} <span class="tomyum-panel__muted">#{{ $userId }}</span></h2>
                    <div class="tomyum-panel__row" style="gap: 8px">
                        <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="{{ route('panel.gmt.chat', ['user' => $userId]) }}"><i class="fas fa-comments"></i>Chat log</a>
                        @if (App\Http\Controllers\Panel\Controller::canUseAdmin(Auth::user()))
                            <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="{{ route('panel.admin.users.show', ['user' => $userId]) }}"><i class="fas fa-cog"></i>Admin page</a>
                        @endif
                    </div>
                </div>
                <p class="tomyum-panel__row" style="gap: 6px; margin: 0 0 12px">
                    @if ($target->isRestricted())
                        <span class="tomyum-panel__badge tomyum-panel__badge--error">Restricted</span>
                    @endif
                    @if ($silencedUntil !== null)
                        <span class="tomyum-panel__badge tomyum-panel__badge--error">Silenced until {{ $silencedUntil->toDayDateTimeString() }} UTC</span>
                    @endif
                    @if (!$target->isRestricted() && $silencedUntil === null)
                        <span class="tomyum-panel__badge tomyum-panel__badge--success">In good standing</span>
                    @endif
                </p>
                <div class="tomyum-panel__facts">
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">Now</span>
                        <span class="tomyum-panel__fact-value">
                            <span class="tomyum-panel__live-dot {{ $online ? '' : 'tomyum-panel__live-dot--off' }}"></span>{{ $online ? 'online' : 'offline' }}
                        </span>
                    </div>
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">Joined</span>
                        <span class="tomyum-panel__fact-value">{{ $target->user_regdate?->toFormattedDateString() ?? '—' }}</span>
                    </div>
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">Country</span>
                        <span class="tomyum-panel__fact-value">{{ $target->country?->name ?? $target->country_acronym }}</span>
                    </div>
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">Reports about them</span>
                        <span class="tomyum-panel__fact-value">{{ count($reports) }}</span>
                    </div>
                </div>
            </div>
        </div>

        @if (!$canModerate)
            <div class="tomyum-panel__status tomyum-panel__status--error">
                You can't moderate this account: it belongs to staff, and only an admin can moderate staff.
            </div>
        @else
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-ban"></i>Restriction</h2>
                <p class="tomyum-panel__hint">
                    Restricted players can still log in and play, but they're hidden from other players, leaderboards
                    and rankings, and can only message BanchoBot. Takes effect in-game immediately. Their scores are kept.
                </p>
                <form method="POST" action="{{ route($target->isRestricted() ? 'panel.gmt.users.unrestrict' : 'panel.gmt.users.restrict', ['user' => $userId]) }}" class="tomyum-panel__row"
                    onsubmit="return confirm({{ json_encode(($target->isRestricted() ? 'Unrestrict ' : 'Restrict ').$target->username.'?') }})">
                    @csrf
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Reason (shown in their account history)</span>
                        <input class="input-text" type="text" name="reason" required maxlength="2000">
                    </label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin {{ $target->isRestricted() ? '' : 'btn-osu-big--danger' }}" type="submit">
                        {{ $target->isRestricted() ? 'Unrestrict' : 'Restrict' }}
                    </button>
                </form>
            </div>

            <div class="tomyum-panel__card">
                <h2><i class="fas fa-comment-slash"></i>Silence</h2>
                <p class="tomyum-panel__hint">A silenced player can't chat (they can still use BanchoBot commands). A new silence replaces the current one.</p>
                <form method="POST" action="{{ route('panel.gmt.users.silence', ['user' => $userId]) }}" class="tomyum-panel__row">
                    @csrf
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 160px">
                        <span class="input-container__label">Length</span>
                        <select class="input-text" name="length" required>
                            @foreach (GmtController::SILENCE_LENGTHS as $seconds => $label)
                                <option value="{{ $seconds }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Reason</span>
                        <input class="input-text" type="text" name="reason" required maxlength="2000">
                    </label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Silence</button>
                </form>
                @if ($silencedUntil !== null)
                    <form method="POST" action="{{ route('panel.gmt.users.unsilence', ['user' => $userId]) }}" class="tomyum-panel__row" style="margin-top: 10px">
                        @csrf
                        <label class="input-container tomyum-panel__field">
                            <span class="input-container__label">Reason for lifting it (optional)</span>
                            <input class="input-text" type="text" name="reason" maxlength="2000">
                        </label>
                        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Lift silence</button>
                    </form>
                @endif
            </div>
        @endif

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-list"></i>Scores</h2>
            <p class="tomyum-panel__row" style="gap: 16px; margin: 0 0 10px">
                <a href="{{ route('panel.gmt.users.show', ['user' => $userId]) }}" @if (!$best) style="font-weight: 700" @endif>Recent</a>
                <a href="{{ route('panel.gmt.users.show', ['user' => $userId, 'scores' => 'best']) }}" @if ($best) style="font-weight: 700" @endif>Best (by pp)</a>
            </p>
            @if ($scoresError !== null)
                <p>{{ $scoresError }}</p>
            @elseif (count($scores) === 0)
                <p class="tomyum-panel__empty">No scores.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>When</th><th>Beatmap</th><th>Mode</th><th>Score</th><th>Acc</th><th>pp</th><th>Grade</th><th>Status</th>@if ($canModerate)<th></th>@endif</tr>
                    @foreach ($scores as $score)
                        <tr>
                            <td style="white-space: nowrap">{!! timeago(Carbon\Carbon::parse($score['submittedAt'])) !!}</td>
                            <td><a href="{{ route('beatmaps.show', ['beatmap' => $score['beatmapId']]) }}">{{ $score['beatmap'] }}</a></td>
                            <td>{{ $modeNames[$score['mode']] ?? $score['mode'] }}</td>
                            <td>{{ number_format($score['score']) }}</td>
                            {{-- TomYum gives accuracy as a fraction (0.92 is 92%). --}}
                            <td>{{ number_format($score['accuracy'] <= 1 ? $score['accuracy'] * 100 : $score['accuracy'], 2) }}%</td>
                            <td>{{ number_format($score['pp']) }}</td>
                            <td>{{ $score['grade'] }}</td>
                            <td>{{ $score['status'] }}</td>
                            @if ($canModerate)
                                <td>
                                    <form method="POST" action="{{ route('panel.gmt.users.scores.remove', ['user' => $userId, 'score' => $score['id']]) }}"
                                        onsubmit="var r = prompt('Remove this score? Reason:'); if (r === null) return false; this.reason.value = r; return true;">
                                        @csrf
                                        <input type="hidden" name="reason">
                                        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit" style="padding: 3px 10px">Remove</button>
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
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 180px">
                        <span class="input-container__label">Wipe scores in</span>
                        <select class="input-text" name="mode" required>
                            <option value="all">All modes</option>
                            @foreach (GmtController::MODES as $mode => $label)
                                <option value="{{ $mode }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Reason</span>
                        <input class="input-text" type="text" name="reason" required maxlength="2000">
                    </label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Wipe</button>
                </form>
                <p class="tomyum-panel__hint">Removed scores are gone for good (also their replays); stats and ranks are recalculated.</p>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-flag"></i>Reports about {{ $target->username }}</h2>
            @if (count($reports) === 0)
                <p class="tomyum-panel__empty">None.</p>
            @else
                @include('panel.gmt._reports', ['reports' => $reports, 'showPlayer' => false])
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-book"></i>Account history</h2>
            @if (count($history) === 0)
                <p class="tomyum-panel__empty">Nothing recorded.</p>
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
            <h2><i class="fas fa-history"></i>Staff actions</h2>
            @if (count($entries) === 0)
                <p class="tomyum-panel__empty">None yet.</p>
            @else
                @include('panel._staff_log')
            @endif
        </div>
    </div>
@endsection

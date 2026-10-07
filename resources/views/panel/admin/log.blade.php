{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Staff log'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Staff log'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-history"></i>Staff log</h2>
            <form method="GET" action="{{ route('panel.admin.log') }}" class="tomyum-panel__row">
                <label class="input-container input-container--select tomyum-panel__field">
                    <span class="input-container__label">What</span>
                    <select class="input-text" name="kind">
                        @foreach (App\Http\Controllers\Panel\AdminController::LOG_KINDS as $value => $label)
                            <option value="{{ $value }}" @selected($value === $kind)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">About player</span>
                    <input class="input-text" type="text" name="player" value="{{ $playerName }}" placeholder="name or id">
                </label>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">By staff member</span>
                    <input class="input-text" type="text" name="staff" value="{{ $staffName }}" placeholder="name or id">
                </label>
                <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-filter"></i>Show</button></div>
                @if ($kind !== '' || $playerName !== null || $staffName !== null)
                    <div><a class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" href="{{ route('panel.admin.log') }}">Clear</a></div>
                @endif
            </form>
        </div>

        <div class="tomyum-panel__card">
            @if ($problem !== null)
                <p class="tomyum-panel__empty">{{ $problem }}</p>
            @elseif (count($entries) === 0)
                <p class="tomyum-panel__empty">Nothing{{ request()->has('before') ? ' older' : '' }} in the log for that.</p>
            @else
                @include('panel._staff_log')
                <div class="tomyum-panel__card-foot" style="justify-content: space-between">
                    @if (request()->has('before'))
                        <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="{{ route('panel.admin.log', request()->except('before')) }}"><i class="fas fa-angle-double-up"></i>Newest</a>
                    @else
                        <span></span>
                    @endif
                    @if ($olderThan !== null)
                        <a class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" href="{{ route('panel.admin.log', array_merge(request()->except('before'), ['before' => $olderThan])) }}">Older<i class="fas fa-angle-right"></i></a>
                    @endif
                </div>
            @endif
        </div>
    </div>
@endsection

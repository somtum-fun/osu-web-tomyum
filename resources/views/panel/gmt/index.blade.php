{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'GMT panel'])

@section('content')
    @include('panel._header', ['crumbs' => [['url' => route('panel.gmt.index'), 'title' => 'GMT panel']]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-search"></i>Find a player</h2>
            <form method="GET" action="{{ route('panel.gmt.index') }}" class="tomyum-panel__row">
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Username or user id</span>
                    <input class="input-text" type="text" name="q" value="{{ $query }}" autofocus>
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Open</button>
                <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ route('panel.gmt.chat') }}" style="background: hsl(var(--hsl-b3)); color: inherit">Chat logs</a>
                @if ((Auth::user()?->isAdmin() ?? false))
                    <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ route('panel.gmt.scores.restore.form') }}" style="background: hsl(var(--hsl-b3)); color: inherit">Restore removed score</a>
                @endif
            </form>
            @if ($query !== '')
                @if (count($matches) === 0)
                    <p class="tomyum-panel__empty">Nobody matches "{{ $query }}".</p>
                @else
                    <ul>
                        @foreach ($matches as $match)
                            <li><a href="{{ route('panel.gmt.users.show', ['user' => $match->getKey()]) }}">{{ $match->username }}</a> <span class="tomyum-panel__hint">#{{ $match->getKey() }}</span></li>
                        @endforeach
                    </ul>
                @endif
            @endif
        </div>

        {{-- The report queue: open ones first; take one so others know it's being looked at. --}}
        <div class="tomyum-panel__card" id="reports">
            <div class="tomyum-panel__card-head">
                <h2><i class="fas fa-flag"></i>Reports</h2>
                <nav class="tomyum-panel__segments">
                    @foreach (App\Libraries\TomYumReports::VIEWS as $view => $label)
                        <a
                            class="tomyum-panel__segment {{ $view === $reportView ? 'tomyum-panel__segment--active' : '' }}"
                            href="{{ route('panel.gmt.index', ['reports' => $view]) }}#reports"
                        >{{ $label }} <span class="tomyum-panel__segment-count">{{ number_format($reportCounts[$view]) }}</span></a>
                    @endforeach
                </nav>
            </div>
            @if (count($reports) === 0)
                <p class="tomyum-panel__empty">
                    {{ match ($reportView) { 'taken' => 'Nobody is handling a report right now.', 'resolved' => 'No resolved reports yet.', default => 'No open reports. Players report others from their profile (the "…" menu), scores and chat.' } }}
                </p>
            @else
                <div class="tomyum-panel__scroll">
                    @include('panel.gmt._reports', ['reports' => $reports, 'showPlayer' => true, 'handlers' => $handlers])
                </div>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-history"></i>Recent moderation</h2>
            @if (count($entries) === 0)
                <p class="tomyum-panel__empty">No restrictions, silences or score removals yet.</p>
            @else
                @include('panel._staff_log')
            @endif
        </div>
    </div>
@endsection

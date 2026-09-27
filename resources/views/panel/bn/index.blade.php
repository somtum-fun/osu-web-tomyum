{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'BN panel'])

@section('content')
    @include('panel._header', ['crumbs' => [['url' => route('panel.bn.index'), 'title' => 'BN panel']]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <a class="tomyum-panel__link" href="{{ route('panel.bn.sets.index') }}">
            <h2 style="margin: 0">Manage beatmap sets &rarr;</h2>
            <p class="tomyum-panel__hint" style="margin: 4px 0 0">Search sets on the server, change their ranked status, refresh them from bancho.</p>
        </a>

        <div class="tomyum-panel__card">
            <h2>Import a beatmap set</h2>
            <p class="tomyum-panel__hint">
                Paste an osu.ppy.sh set link or a set id. Every difficulty is added with its stats and cover,
                keeps its ranked status from bancho, and shows up in the beatmap listing.
            </p>
            <form method="POST" action="{{ route('panel.bn.import') }}" class="tomyum-panel__row">
                @csrf
                <label class="tomyum-panel__field">
                    Set link or id
                    <input type="text" name="set" placeholder="https://osu.ppy.sh/beatmapsets/2424357" required autofocus>
                </label>
                <button class="tomyum-panel__button" type="submit">Import</button>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2>Bulk import</h2>
            <p class="tomyum-panel__hint">Runs in the background; you can leave the page and check back under "Recent imports".</p>

            <form method="POST" action="{{ route('panel.bn.import-bulk') }}">
                @csrf
                <input type="hidden" name="mode" value="ids">
                <label class="tomyum-panel__field">
                    Set links or ids, one per line (or separated by commas)
                    <textarea name="ids" placeholder="https://osu.ppy.sh/beatmapsets/950289&#10;2285243&#10;2291277"></textarea>
                </label>
                <p><button class="tomyum-panel__button" type="submit">Import these sets</button></p>
            </form>

            <form method="POST" action="{{ route('panel.bn.import-bulk') }}" class="tomyum-panel__row">
                @csrf
                <input type="hidden" name="mode" value="dates">
                <label class="tomyum-panel__field">
                    Ranked or loved from
                    <input type="date" name="from" required>
                </label>
                <label class="tomyum-panel__field">
                    Until (leave empty for today)
                    <input type="date" name="to">
                </label>
                <button class="tomyum-panel__button" type="submit">Import everything in this range</button>
            </form>
            <p class="tomyum-panel__hint">
                Date ranges need the osu! API key on the server and are capped at 10,000 sets per import.
                A few days is usually a few hundred sets.
            </p>
        </div>

        <div class="tomyum-panel__card">
            <h2>Import log</h2>
            <p class="tomyum-panel__hint">Every import, newest first: single sets and each set of a bulk import.</p>
            @if ($logError !== null)
                <p>{{ $logError }}</p>
            @elseif (count($entries) === 0)
                <p class="tomyum-panel__hint">Nothing imported yet.</p>
            @else
                @include('panel.bn._log_table', ['showJob' => true])
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2>Bulk imports</h2>
            <p class="tomyum-panel__hint">Progress is kept until the game server restarts; the sets stay in the import log.</p>
            @if ($jobsError !== null)
                <p>{{ $jobsError }}</p>
            @elseif (count($jobs) === 0)
                <p class="tomyum-panel__hint">No bulk imports since the game server started.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>Started</th><th>By</th><th>What</th><th>Status</th><th>Imported</th><th>Not found</th><th>Failed</th><th></th></tr>
                    @foreach ($jobs as $job)
                        <tr>
                            <td>{!! timeago(Carbon\Carbon::parse($job['createdAt'])) !!}</td>
                            <td>{{ $actors[$job['startedBy']] ?? '—' }}</td>
                            <td>
                                @if ($job['request']['rankedFrom'] ?? null)
                                    Ranked {{ substr($job['request']['rankedFrom'], 0, 10) }}
                                    &ndash; {{ substr($job['request']['rankedTo'] ?? 'now', 0, 10) }}
                                @else
                                    {{ count($job['request']['setIds'] ?? []) }} set(s)
                                @endif
                            </td>
                            <td>{{ $job['status'] }}</td>
                            <td>{{ $job['imported'] }} / {{ $job['total'] }}</td>
                            <td>{{ $job['notFound'] }}</td>
                            <td>{{ $job['failed'] }}</td>
                            <td><a href="{{ route('panel.bn.job', ['job' => $job['id']]) }}">Details</a></td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    </div>
@endsection

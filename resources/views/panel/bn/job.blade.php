{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    // $job is null when the game server restarted since (progress is in memory); $entries is the log.
    $running = $job !== null && in_array($job['status'], ['queued', 'resolving', 'running'], true);
    if ($job !== null) {
        $processed = $job['imported'] + $job['notFound'] + $job['failed'];
        $percent = $job['total'] > 0 ? round($processed * 100 / $job['total']) : ($running ? 0 : 100);
        $statusText = [
            'queued' => 'Waiting for the previous import to finish…',
            'resolving' => 'Finding every set ranked in the range…',
            'running' => 'Importing…',
            'done' => 'Finished',
            'failed' => 'Stopped with an error',
        ][$job['status']] ?? $job['status'];
        $what = ($job['request']['rankedFrom'] ?? null)
            ? 'Everything ranked '.substr($job['request']['rankedFrom'], 0, 10).' – '.substr($job['request']['rankedTo'] ?? 'now', 0, 10)
            : count($job['request']['setIds'] ?? []).' set(s) from a list';
    }
@endphp
@extends('master', ['titlePrepend' => 'Bulk import'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.bn.index'), 'title' => 'BN panel'],
        ['title' => 'Bulk import'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            @if ($job !== null)
                <h2>{{ $statusText }}</h2>
                <p class="tomyum-panel__hint">
                    {{ $what }}, started by {{ $actors[$job['startedBy']] ?? 'unknown' }}
                    {!! timeago(Carbon\Carbon::parse($job['createdAt'])) !!}
                    @if ($job['finishedAt'] ?? null)
                        &middot; finished {!! timeago(Carbon\Carbon::parse($job['finishedAt'])) !!}
                    @endif
                    @if ($running)
                        &middot; this page refreshes by itself
                    @endif
                </p>
                <div class="tomyum-panel__progress"><div style="width: {{ $percent }}%"></div></div>
                <p>
                    {{ $processed }} of {{ $job['total'] }} sets processed:
                    {{ $job['imported'] }} imported, {{ $job['notFound'] }} not found on osu!, {{ $job['failed'] }} failed.
                </p>
            @else
                <h2>Finished</h2>
                <p class="tomyum-panel__hint">
                    The game server has restarted since, so the progress summary is gone; every set it handled is listed below.
                </p>
            @endif
        </div>

        @if ($job !== null && count($job['errors']) > 0)
            <div class="tomyum-panel__card">
                <h2>Problems</h2>
                <ul>
                    @foreach ($job['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="tomyum-panel__card">
            <h2>Sets ({{ count($entries) }})</h2>
            @if (count($entries) === 0)
                <p class="tomyum-panel__hint">Nothing processed yet.</p>
            @else
                @include('panel.bn._log_table')
            @endif
        </div>
    </div>

    @if ($running)
        <script>
            setTimeout(function () {
                if (location.pathname === {!! json_encode(parse_url(route('panel.bn.job', ['job' => $job['id']]), PHP_URL_PATH)) !!}) {
                    location.reload();
                }
            }, 3000);
        </script>
    @endif
@endsection

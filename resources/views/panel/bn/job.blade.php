{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    $running = in_array($job['status'], ['queued', 'resolving', 'running'], true);
    $processed = $job['imported'] + $job['notFound'] + $job['failed'];
    $percent = $job['total'] > 0 ? round($processed * 100 / $job['total']) : ($running ? 0 : 100);
    $statusText = [
        'queued' => 'Waiting for the previous import to finish…',
        'resolving' => 'Finding every set ranked in the range…',
        'running' => 'Importing…',
        'done' => 'Finished',
        'failed' => 'Stopped with an error',
    ][$job['status']] ?? $job['status'];
@endphp
@extends('master', ['titlePrepend' => 'Bulk import'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.bn.index'), 'title' => 'BN panel'],
        ['title' => 'Bulk import'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2>{{ $statusText }}</h2>
            <div class="tomyum-panel__progress"><div style="width: {{ $percent }}%"></div></div>
            <p>
                {{ $processed }} of {{ $job['total'] }} sets processed:
                {{ $job['imported'] }} imported, {{ $job['notFound'] }} not found on osu!, {{ $job['failed'] }} failed.
            </p>
            <p class="tomyum-panel__hint">
                Started {!! timeago(Carbon\Carbon::parse($job['createdAt'])) !!}
                @if ($job['finishedAt'] ?? null)
                    &middot; finished {!! timeago(Carbon\Carbon::parse($job['finishedAt'])) !!}
                @endif
                @if ($running)
                    &middot; this page refreshes by itself
                @endif
            </p>
        </div>

        @if (count($job['errors']) > 0)
            <div class="tomyum-panel__card">
                <h2>Problems</h2>
                <ul>
                    @foreach ($job['errors'] as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
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

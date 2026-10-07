{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- Beatmap import log rows. Params: $entries (TomYum staff log), $actors (id => username), $showJob. --}}
@php
    $resultLabels = [
        'imported' => ['Imported', 'good'],
        'not_found' => ['Not on osu!', 'bad'],
        'failed' => ['Failed', 'bad'],
        'uploaded' => ['Uploaded', 'good'],
        'updated' => ['Updated', 'good'],
        'rejected' => ['Refused', 'bad'],
        'nominators' => ['Nominators', 'info'], // TomYum: set by hand (BnSetsController@nominators)
    ];
@endphp
<div class="tomyum-panel__scroll">
<table class="tomyum-panel__table">
    <tr>
        <th>When</th>
        <th>By</th>
        <th>Beatmap set</th>
        <th>Result</th>
        @if ($showJob ?? false)
            <th></th>
        @endif
    </tr>
    @foreach ($entries as $entry)
        @php
            // Status changes carry the new status as their result.
            [$label, $tone] = $resultLabels[$entry['result']] ?? ['Set to '.ucfirst($entry['result'] ?? '?'), 'info'];
        @endphp
        <tr>
            <td class="tomyum-panel__nowrap tomyum-panel__muted">{!! timeago(Carbon\Carbon::parse($entry['createdAt'], 'UTC')) !!}</td>
            <td class="tomyum-panel__nowrap">@include('panel._who')</td>
            <td>
                @if (($entry['targetId'] ?? null) !== null && !in_array($entry['result'], ['not_found', 'failed', 'rejected'], true))
                    <a href="{{ route('panel.bn.sets.show', ['set' => $entry['targetId']]) }}">{{ preg_replace('/(?<![0-9])1 difficulties/', '1 difficulty', $entry['summary']) }}</a>
                @else
                    {{ preg_replace('/(?<![0-9])1 difficulties/', '1 difficulty', $entry['summary']) }}
                @endif
            </td>
            <td><span class="tomyum-panel__pill tomyum-panel__pill--{{ $tone }}">{{ $label }}</span></td>
            @if ($showJob ?? false)
                <td>
                    @if ($entry['jobId'] ?? null)
                        <a href="{{ route('panel.bn.job', ['job' => $entry['jobId']]) }}" class="tomyum-panel__nowrap"><i class="fas fa-layer-group"></i> Bulk import</a>
                    @endif
                </td>
            @endif
        </tr>
    @endforeach
</table>
</div>

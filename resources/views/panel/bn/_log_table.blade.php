{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- Beatmap import log rows. Params: $entries (TomYum staff log), $actors (id => username), $showJob. --}}
@php
    $resultLabels = [
        'imported' => ['Imported', 'success'],
        'not_found' => ['Not on osu!', 'error'],
        'failed' => ['Failed', 'error'],
    ];
@endphp
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
            [$label, $tone] = $resultLabels[$entry['result']] ?? ['Set to '.ucfirst($entry['result'] ?? '?'), 'success'];
            $actorName = $actors[$entry['actorId']] ?? null;
        @endphp
        <tr>
            <td>{!! timeago(Carbon\Carbon::parse($entry['createdAt'], 'UTC')) !!}</td>
            <td>
                @if ($actorName !== null)
                    <a href="{{ route('users.show', ['user' => $entry['actorId']]) }}">{{ $actorName }}</a>
                @else
                    &mdash;
                @endif
            </td>
            <td>
                @if ($entry['result'] !== 'not_found' && $entry['result'] !== 'failed')
                    <a href="{{ route('panel.bn.sets.show', ['set' => $entry['targetId']]) }}">{{ $entry['summary'] }}</a>
                @else
                    {{ $entry['summary'] }}
                @endif
            </td>
            <td><span class="tomyum-panel__badge tomyum-panel__badge--{{ $tone }}">{{ $label }}</span></td>
            @if ($showJob ?? false)
                <td>
                    @if ($entry['jobId'] ?? null)
                        <a href="{{ route('panel.bn.job', ['job' => $entry['jobId']]) }}">Bulk import</a>
                    @endif
                </td>
            @endif
        </tr>
    @endforeach
</table>

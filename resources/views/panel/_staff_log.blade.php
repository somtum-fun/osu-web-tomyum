{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- Staff log rows about users. Params: $entries (TomYum staff log), $actors (id => name), $targets (optional id => name). --}}
@php
    $labels = [
        'user.restrict' => 'Restrict',
        'user.unrestrict' => 'Unrestrict',
        'user.silence' => 'Silence',
        'user.unsilence' => 'Lift silence',
        'user.wipe_scores' => 'Wipe scores',
        'score.remove' => 'Remove score',
    ];
@endphp
<table class="tomyum-panel__table">
    <tr>
        <th>When</th>
        <th>By</th>
        @isset($targets)
            <th>Player</th>
        @endisset
        <th>Action</th>
        <th>Details</th>
    </tr>
    @foreach ($entries as $entry)
        <tr>
            <td style="white-space: nowrap">{!! timeago(Carbon\Carbon::parse($entry['createdAt'], 'UTC')) !!}</td>
            <td>
                @if ($actors[$entry['actorId']] ?? null)
                    <a href="{{ route('users.show', ['user' => $entry['actorId']]) }}">{{ $actors[$entry['actorId']] }}</a>
                @else
                    &mdash;
                @endif
            </td>
            @isset($targets)
                <td>
                    @if ($entry['targetId'] ?? null)
                        <a href="{{ route('panel.gmt.users.show', ['user' => $entry['targetId']]) }}">{{ $targets[$entry['targetId']] ?? '#'.$entry['targetId'] }}</a>
                    @endif
                </td>
            @endisset
            <td style="white-space: nowrap">{{ $labels[$entry['action']] ?? $entry['action'] }}</td>
            <td>{{ $entry['summary'] }}</td>
        </tr>
    @endforeach
</table>

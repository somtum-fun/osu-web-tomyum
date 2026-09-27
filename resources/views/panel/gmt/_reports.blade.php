{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- Player reports. Params: $reports (UserReport with reporter[, user]), $showPlayer. --}}
@php
    $types = [
        'user' => 'Profile',
        'comment' => 'Comment',
        'forum_post' => 'Forum post',
        'beatmapset' => 'Beatmap set',
        'beatmapset_discussion_post' => 'Discussion post',
        'solo_score' => 'Score',
        'message' => 'Chat message',
        'team' => 'Team',
    ];
@endphp
<table class="tomyum-panel__table">
    <tr>
        <th>When</th>
        <th>From</th>
        @if ($showPlayer)
            <th>About</th>
        @endif
        <th>What</th>
        <th>Reason</th>
        <th>Comments</th>
    </tr>
    @foreach ($reports as $report)
        <tr>
            <td style="white-space: nowrap">{!! timeago($report->timestamp) !!}</td>
            <td>
                @if ($report->reporter)
                    <a href="{{ route('users.show', ['user' => $report->reporter_id]) }}">{{ $report->reporter->username }}</a>
                @endif
            </td>
            @if ($showPlayer)
                <td>
                    @if ($report->user)
                        <a href="{{ route('panel.gmt.users.show', ['user' => $report->user_id]) }}">{{ $report->user->username }}</a>
                    @else
                        #{{ $report->user_id }}
                    @endif
                </td>
            @endif
            <td>
                {{ $types[$report->reportable_type] ?? (str_starts_with($report->reportable_type ?? '', 'score_best_') ? 'Score' : ($report->reportable_type ?? '—')) }}
                @if ($report->reportable_type === 'beatmapset' && $report->reportable_id)
                    <a href="{{ route('beatmapsets.show', ['beatmapset' => $report->reportable_id]) }}">#{{ $report->reportable_id }}</a>
                @endif
            </td>
            <td>{{ $report->reason }}</td>
            <td style="max-width: 360px">{{ $report->comments }}</td>
        </tr>
    @endforeach
</table>

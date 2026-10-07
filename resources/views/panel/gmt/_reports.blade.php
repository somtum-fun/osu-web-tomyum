{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    Player reports, with where each stands in the queue (App\Libraries\TomYumReports) and what can be
    done with it. Params: $reports (UserReport with reporter[, user], ->tomyumState), $showPlayer,
    $handlers (user id => name, optional).
--}}
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
    $handlers ??= App\Http\Controllers\Panel\GmtController::userNames(
        collect($reports)->map(fn ($report) => $report->tomyumState?->handler_id)->all()
    );
    $back = request()->getRequestUri();
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
        <th>State</th>
        <th></th>
    </tr>
    @foreach ($reports as $report)
        @php
            $state = $report->tomyumState ?? null;
            $status = $state?->status ?? App\Libraries\TomYumReports::OPEN;
            $link = App\Libraries\TomYumReports::link($report);
        @endphp
        <tr>
            <td>{!! timeago($report->timestamp) !!}</td>
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
            <td class="tomyum-panel__nowrap">
                {{ $types[$report->reportable_type] ?? (str_starts_with($report->reportable_type ?? '', 'score_best_') ? 'Score' : ($report->reportable_type ?? '—')) }}
                @if ($link !== null)
                    <a href="{{ $link }}" title="Open it"><i class="fas fa-external-link-alt"></i></a>
                @endif
            </td>
            <td><span class="tomyum-panel__pill">{{ $report->reason }}</span></td>
            <td style="max-width: 320px">{{ $report->comments }}</td>
            <td class="tomyum-panel__nowrap">
                @if ($status === App\Libraries\TomYumReports::RESOLVED)
                    <span class="tomyum-panel__pill tomyum-panel__pill--good">resolved</span>
                @elseif ($status === App\Libraries\TomYumReports::TAKEN)
                    <span class="tomyum-panel__pill tomyum-panel__pill--info">being handled</span>
                @else
                    <span class="tomyum-panel__pill tomyum-panel__pill--warn">open</span>
                @endif
                @if ($state?->handler_id)
                    <div class="tomyum-panel__hint">by {{ $handlers[$state->handler_id] ?? '#'.$state->handler_id }}</div>
                @endif
                @if (present($state?->note))
                    <div class="tomyum-panel__hint" style="white-space: normal; max-width: 220px">{{ $state->note }}</div>
                @endif
            </td>
            <td class="tomyum-panel__nowrap" style="text-align: right">
                <form method="POST" action="{{ route('panel.gmt.reports.state', ['report' => $report->getKey()]) }}" class="tomyum-panel__inline-actions">
                    @csrf
                    <input type="hidden" name="back" value="{{ $back }}">
                    @if ($status === App\Libraries\TomYumReports::OPEN)
                        <button class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" name="action" value="take" type="submit"><i class="fas fa-hand-paper"></i>Take it</button>
                    @endif
                    @if ($status !== App\Libraries\TomYumReports::RESOLVED)
                        <button class="btn-osu-big btn-osu-big--rounded-small" name="action" value="resolve" type="submit"
                            data-panel-ask="Resolve this report?" data-panel-field="note" data-panel-label="What was done (optional)" data-panel-confirm="Resolve"
                        ><i class="fas fa-check"></i>Resolve</button>
                        <input type="hidden" name="note">
                    @else
                        <button class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" name="action" value="reopen" type="submit"><i class="fas fa-undo"></i>Reopen</button>
                    @endif
                </form>
            </td>
        </tr>
    @endforeach
</table>

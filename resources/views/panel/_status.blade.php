{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- A beatmap(set) status as osu-web shows it (its beatmapset-status badge in the status's colours). Params: $approved. --}}
@php
    $statusName = [-2 => 'graveyard', -1 => 'wip', 0 => 'pending', 1 => 'ranked', 2 => 'approved', 3 => 'qualified', 4 => 'loved'][(int) $approved] ?? 'pending';
@endphp
<span
    class="beatmapset-status beatmapset-status--panel"
    style="--bg-hsl: var(--beatmapset-{{ $statusName }}-bg-hsl); --colour: var(--beatmapset-{{ $statusName }}-colour)"
>{{ osu_trans("beatmapsets.show.status.{$statusName}") }}</span>

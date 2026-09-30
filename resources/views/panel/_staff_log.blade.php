{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- Staff log rows. Params: $entries (TomYum staff log), $actors (id => name), $targets (optional id => name). --}}
@php
    // What each action is called here, and its colour: bad (taking away), good (giving back), warn, info, pink (content).
    $labels = [
        'user.restrict' => ['Restrict', 'bad'],
        'user.ban' => ['Ban', 'bad'],
        'user.unrestrict' => ['Unrestrict', 'good'],
        'user.silence' => ['Silence', 'warn'],
        'user.unsilence' => ['Lift silence', 'good'],
        'user.wipe_scores' => ['Wipe scores', 'bad'],
        'user.delete' => ['Delete account', 'bad'],
        'user.clear_standing' => ['Clear standing', 'good'],
        'user.verify' => ['Verified', 'info'],
        'user.client_flags' => ['Client flags', 'warn'],
        'user.evasion_blocked' => ['Alt blocked', 'bad'],
        'user.shared_machine' => ['Shared machine', 'warn'],
        'user.badge_add' => ['Give badge', 'pink'],
        'user.badge_remove' => ['Take badge', 'pink'],
        'user.title' => ['Title', 'pink'],
        'score.remove' => ['Remove score', 'bad'],
        'scores.recalculate' => ['Recalculate pp', 'info'],
        'admin.profile' => ['Edit profile', 'info'],
        'admin.password' => ['Set password', 'info'],
        'admin.groups' => ['Groups', 'info'],
        'admin.supporter' => ['Supporter', 'pink'],
        'admin.delete' => ['Delete account', 'bad'],
        'beatmapset.import' => ['Import set', 'good'],
        'beatmapset.upload' => ['Upload set', 'good'],
        'beatmapset.status' => ['Set status', 'info'],
        'title.add' => ['New title', 'pink'],
        'title.remove' => ['Remove title', 'pink'],
        'news.save' => ['News post', 'pink'],
        'news.remove' => ['Remove news', 'pink'],
        'seasonal.add' => ['Background', 'pink'],
        'seasonal.remove' => ['Remove background', 'pink'],
        'daily_challenge.add' => ['Daily challenge', 'info'],
        'daily_challenge.remove' => ['Daily challenge', 'info'],
        'matchmaking.pool_add' => ['New pool', 'info'],
        'matchmaking.pool_edit' => ['Edit pool', 'info'],
        'matchmaking.pool_remove' => ['Remove pool', 'info'],
        'matchmaking.map_add' => ['Pool map', 'info'],
        'mods.ranked' => ['Ranked mods', 'info'],
        'banner.add' => ['New banner', 'pink'],
        'banner.toggle' => ['Banner shown/hidden', 'pink'],
        'banner.image' => ['Banner image', 'pink'],
        'banner.remove' => ['Remove banner', 'pink'],
        'user.banner_add' => ['Give banner', 'pink'],
        'user.banner_remove' => ['Take banner', 'pink'],
    ];
    // Actions whose target isn't a player.
    $notPlayers = ['beatmapset.', 'title.', 'news.', 'seasonal.', 'daily_challenge.', 'matchmaking.', 'scores.', 'mods.', 'banner.'];
@endphp
<div class="tomyum-panel__scroll">
<table class="tomyum-panel__table">
    <tr>
        <th>When</th>
        <th>By</th>
        @isset($targets)
            <th>About</th>
        @endisset
        <th>Action</th>
        <th>Details</th>
    </tr>
    @foreach ($entries as $entry)
        @php
            $slug = $entry['action'];
            [$label, $tone] = $labels[$slug] ?? [ucfirst(str_replace('_', ' ', substr($slug, (strpos($slug, '.') ?: -1) + 1))), 'info'];
            $aboutPlayer = !Illuminate\Support\Str::startsWith($slug, $notPlayers);
        @endphp
        <tr>
            <td class="tomyum-panel__nowrap tomyum-panel__muted">{!! timeago(Carbon\Carbon::parse($entry['createdAt'], 'UTC')) !!}</td>
            <td class="tomyum-panel__nowrap">@include('panel._who')</td>
            @isset($targets)
                <td>
                    @if (str_starts_with($slug, 'beatmapset.') && ($entry['targetId'] ?? null))
                        <a href="{{ route('panel.bn.sets.show', ['set' => $entry['targetId']]) }}" class="tomyum-panel__nowrap" title="Beatmap set">
                            <i class="fas fa-music"></i> {{ $entry['targetId'] }}
                        </a>
                    @elseif ($aboutPlayer && ($entry['targetId'] ?? null))
                        <a href="{{ route(str_starts_with($slug, 'admin.') ? 'panel.admin.users.show' : 'panel.gmt.users.show', ['user' => $entry['targetId']]) }}">{{ $targets[$entry['targetId']] ?? '#'.$entry['targetId'] }}</a>
                    @endif
                </td>
            @endisset
            <td><span class="tomyum-panel__pill tomyum-panel__pill--{{ $tone }}">{{ $label }}</span></td>
            <td>{{ $entry['summary'] }}</td>
        </tr>
    @endforeach
</table>
</div>

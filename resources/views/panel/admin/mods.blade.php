{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    $rulesets = App\Http\Controllers\Panel\ModsController::RULESETS;
@endphp
@extends('master', ['titlePrepend' => 'Ranked mods'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Ranked mods'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-info-circle"></i>How it works</h2>
            <p class="tomyum-panel__hint" style="margin-bottom: 0">
                lazer plays only give pp (and count for medals) when every mod on them is ranked. osu! ranks the
                usual mods with their normal settings; switch a mod on here and it counts as ranked on this server
                too, whatever its settings (a custom speed, Difficulty Adjust's sliders and so on). Old scores with
                that mod get their pp worked out again within a minute or so, and players' totals follow.
                The lazer client still shows the mod as unranked while playing; the server decides.
                Relax and Autopilot have their own leaderboards here, and stable plays aren't affected.
            </p>
        </div>

        <div class="tomyum-panel__card">
            <div class="tomyum-panel__card-head">
                <h2><i class="fas fa-sliders-h"></i>{{ $rulesets[$currentRuleset][1] }} mods</h2>
                <div class="tomyum-panel__tabs">
                    @foreach ($rulesets as $name => [, $label])
                        <a href="{{ route('panel.admin.mods.index', ['mode' => $name]) }}"
                            class="tomyum-panel__tab {{ $name === $currentRuleset ? 'tomyum-panel__tab--active' : '' }}">{{ $label }}</a>
                    @endforeach
                </div>
            </div>

            @if (!$ready)
                <p class="tomyum-panel__empty">
                    The score processor hasn't made its mod list yet. It does when it starts (TomYum's build of it, deploy/score-processor).
                </p>
            @else
                <form method="POST" action="{{ route('panel.admin.mods.update', ['mode' => $currentRuleset]) }}">
                    @csrf
                    @foreach ($groups as $type => $mods)
                        <p class="tomyum-panel__section-title" style="margin: 16px 0 6px">{{ $type }}</p>
                        <div class="tomyum-panel__scroll">
                        <table class="tomyum-panel__table">
                            @foreach ($mods as $mod)
                                @php
                                    // A mod osu! ranks that has no settings is ranked whatever happens here.
                                    $alwaysRanked = $mod['osu_ranked'] === true && !$mod['has_settings'];
                                @endphp
                                <tr>
                                    <td style="width: 70px"><span class="tomyum-panel__pill tomyum-panel__pill--pink">{{ $mod['acronym'] }}</span></td>
                                    <td>
                                        <strong>{{ $mod['name'] }}</strong>
                                        <div class="tomyum-panel__muted" style="font-size: 12px">{{ $mod['description'] }}</div>
                                    </td>
                                    <td class="tomyum-panel__nowrap">
                                        @if ($mod['osu_ranked'] === true)
                                            <span class="tomyum-panel__pill tomyum-panel__pill--good" title="osu! ranks it{{ $mod['has_settings'] ? ' with its normal settings' : '' }}">osu!: ranked{{ $mod['has_settings'] ? ' (normal settings)' : '' }}</span>
                                        @elseif ($mod['osu_ranked'] === false)
                                            <span class="tomyum-panel__pill">osu!: unranked</span>
                                        @else
                                            <span class="tomyum-panel__pill">osu!: ?</span>
                                        @endif
                                    </td>
                                    <td class="tomyum-panel__nowrap" style="text-align: right">
                                        @if ($alwaysRanked)
                                            <span class="tomyum-panel__muted" style="font-size: 12px">always ranked</span>
                                        @else
                                            @if ($mod['pending'])
                                                <span class="tomyum-panel__client" title="Old scores are getting their pp worked out again"><i class="fas fa-sync-alt fa-spin"></i>updating</span>
                                            @endif
                                            <label class="tomyum-panel__switch" title="{{ $mod['osu_ranked'] ? 'Also ranked with other settings' : 'Ranked on this server' }}">
                                                <input type="checkbox" name="ranked[]" value="{{ $mod['acronym'] }}" @checked($mod['ranked'])>
                                                <span>{{ $mod['osu_ranked'] ? 'any settings' : 'ranked here' }}</span>
                                            </label>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                        </div>
                    @endforeach
                    <div style="margin-top: 16px">
                        <button class="tomyum-panel__button" type="submit"><i class="fas fa-save"></i>Save {{ $rulesets[$currentRuleset][1] }}</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
@endsection

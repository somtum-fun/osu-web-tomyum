{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    TomYum staff panels: osu-web's page header with the panel's pages as tabs (like the rankings'),
    the other panels next to the title, the way back from a deeper page, and the last action's
    message. Styles: bem/tomyum-panel.less.
--}}
@php
    use App\Http\Controllers\Panel\Controller as PanelController;

    $routeName = Route::currentRouteName() ?? '';
    $user = Auth::user();

    // [title, route, routes it's active for]
    $panels = [
        'bn' => ['BN', 'panel.bn.index', PanelController::canUseBn($user), [
            ['Import & upload', 'panel.bn.index', ['panel.bn.index', 'panel.bn.job', 'panel.bn.import']],
            ['Manage sets', 'panel.bn.sets.index', ['panel.bn.sets.']],
        ]],
        'gmt' => ['GMT', 'panel.gmt.index', PanelController::canUseGmt($user), [
            ['Overview', 'panel.gmt.index', ['panel.gmt.index', 'panel.gmt.users.']],
            ['Chat log', 'panel.gmt.chat', ['panel.gmt.chat']],
        ]],
        // The Tournament Committee: live matches, and the banners and badges pages (also the admin's).
        'tc' => ['TC', 'panel.tc.index', PanelController::canUseTc($user), [
            ['Matches', 'panel.tc.index', ['panel.tc.index', 'panel.tc.matches.']],
            ['Banners', 'panel.tc.tournament-banners.index', ['panel.tc.tournament-banners.']],
            ['Badges', 'panel.tc.badges.index', ['panel.tc.badges.']],
            ['Assets', 'panel.tc.assets.index', ['panel.tc.assets.']],
        ]],
        'admin' => ['Admin', 'panel.admin.index', PanelController::canUseAdmin($user), [
            ['Overview', 'panel.admin.index', ['panel.admin.index', 'panel.admin.pp-rework', 'panel.admin.pp-library']],
            ['Accounts', 'panel.admin.users.index', ['panel.admin.users.']],
            ['Groups', 'panel.admin.groups.index', ['panel.admin.groups.']],
            ['Daily challenge', 'panel.admin.daily-challenge.index', ['panel.admin.daily-challenge.']],
            ['Ranked Play', 'panel.admin.matchmaking.index', ['panel.admin.matchmaking.']],
            ['Ranked mods', 'panel.admin.mods.index', ['panel.admin.mods.']],
            ['Badges', 'panel.admin.badges.index', ['panel.admin.badges.']],
            ['Assets', 'panel.admin.assets.index', ['panel.admin.assets.']],
            ['Titles', 'panel.admin.titles.index', ['panel.admin.titles.']],
            ['Banners', 'panel.admin.tournament-banners.index', ['panel.admin.tournament-banners.']],
            ['News', 'panel.admin.news.index', ['panel.admin.news.']],
            ['Backgrounds', 'panel.admin.seasonal-backgrounds.index', ['panel.admin.seasonal-backgrounds.']],
            ['Menu banners', 'panel.admin.menu-banners.index', ['panel.admin.menu-banners.']],
            ['Artists', 'panel.admin.artists.index', ['panel.admin.artists.']],
        ]],
    ];

    $current = null;
    foreach (array_keys($panels) as $key) {
        if (str_starts_with($routeName, "panel.{$key}.")) {
            $current = $key;
        }
    }

    $matches = fn (array $prefixes) => collect($prefixes)->contains(
        fn ($prefix) => str_ends_with($prefix, '.') ? str_starts_with($routeName, $prefix) : $routeName === $prefix,
    );
    $links = [];
    // The panels' front page has no tabs: its tiles are the panels (and the switch is by the title).
    if ($current !== null) {
        foreach ($panels[$current][3] as [$title, $route, $prefixes]) {
            $links[] = ['title' => $title, 'url' => route($route), 'active' => $matches($prefixes)];
        }
    }

    // A page below a tab (a player, a set, an import) says where it is.
    $deeper = array_slice($crumbs ?? [], 2);
    $status = session('panel_status');
@endphp
@component('layout._page_header_v4', ['params' => [
    'links' => $links,
    'theme' => 'panel',
]])
    {{-- A page about one game mode (ranked mods) picks it like the rankings: osu-web's mode icons. --}}
    @isset($panelRuleset)
        @slot('contentAppend')
            @include('objects._ruleset_selector', ['currentRuleset' => $panelRuleset['current'], 'urlFn' => $panelRuleset['urlFn']])
        @endslot
        @slot('linksAppend')
            <div class="visible-xs">
                @include('objects._ruleset_selector', ['currentRuleset' => $panelRuleset['current'], 'urlFn' => $panelRuleset['urlFn']])
            </div>
        @endslot
    @endisset
    @slot('titleAppend')
        <div class="tomyum-panel-switch">
            @foreach ($panels as $key => [$title, $route, $allowed])
                @if ($allowed)
                    <a
                        class="{{ class_with_modifiers('tomyum-panel-switch__item', ['active' => $key === $current]) }}"
                        href="{{ route($route) }}"
                    >{{ $title }}</a>
                @endif
            @endforeach
        </div>
    @endslot
@endcomponent
@if (count($deeper) > 0 || $status !== null)
    <div class="osu-page osu-page--admin tomyum-panel tomyum-panel--top">
        @if (count($deeper) > 0)
            <nav class="tomyum-panel__crumbs">
                @foreach ($crumbs as $index => $crumb)
                    @continue($index === 0)
                    @if (isset($crumb['url']) && !$loop->last)
                        <a href="{{ $crumb['url'] }}">{{ $crumb['title'] }}</a>
                        <i class="fas fa-chevron-right"></i>
                    @else
                        <span>{{ $crumb['title'] }}</span>
                    @endif
                @endforeach
            </nav>
        @endif
        @if ($status !== null)
            <div class="tomyum-panel__status tomyum-panel__status--{{ $status['type'] }}">
                <span>
                    {{ $status['message'] }}
                    @if ($status['link'] ?? null)
                        &mdash; <a href="{{ $status['link']['url'] }}">{{ $status['link']['text'] }}</a>
                    @endif
                </span>
            </div>
        @endif
    </div>
@endif

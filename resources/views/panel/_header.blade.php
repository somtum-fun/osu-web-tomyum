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
            ['Sign-ups', 'panel.gmt.registrations.index', ['panel.gmt.registrations.']],
        ]],
        // The Tournament Committee: live matches, and the banners and badges pages (also the admin's).
        'tc' => ['TC', 'panel.tc.index', PanelController::canUseTc($user), [
            ['Matches', 'panel.tc.index', ['panel.tc.index', 'panel.tc.matches.']],
            ['Tournaments', 'panel.tc.tournaments.index', ['panel.tc.tournaments.']], // TomYum
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
            ['Tournaments', 'panel.admin.tournaments.index', ['panel.admin.tournaments.']], // TomYum
            ['Banners', 'panel.admin.tournament-banners.index', ['panel.admin.tournament-banners.']],
            ['News', 'panel.admin.news.index', ['panel.admin.news.']],
            ['Backgrounds', 'panel.admin.seasonal-backgrounds.index', ['panel.admin.seasonal-backgrounds.']],
            ['Menu banners', 'panel.admin.menu-banners.index', ['panel.admin.menu-banners.']],
            ['Branding', 'panel.admin.branding.index', ['panel.admin.branding.']],
            ['Staff log', 'panel.admin.log', ['panel.admin.log']],
            ['Artists', 'panel.admin.artists.index', ['panel.admin.artists.']],
            ['Contests', 'panel.admin.contests.index', ['panel.admin.contests.']],
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
        {{-- One box for everything: a player, a beatmap or set, a score (PagesController::search). --}}
        <form class="tomyum-panel-search" action="{{ route('panel.search') }}" method="GET" role="search">
            <i class="fas fa-search tomyum-panel-search__icon"></i>
            <input class="tomyum-panel-search__input" type="search" name="q" placeholder="Player, beatmap or score link…" autocomplete="off" aria-label="Find a player, beatmap or score">
        </form>
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

{{--
    The panels' own "are you sure?" box, in place of the browser's prompt(): a button with
    data-panel-ask="Question" (and data-panel-field="note" for a text answer, data-panel-confirm="Remove",
    data-panel-danger) asks first; confirming fills its form's field and sends the form as that button.
--}}
<dialog class="tomyum-panel-dialog js-panel-dialog">
    <form method="dialog" class="tomyum-panel-dialog__box">
        <p class="tomyum-panel-dialog__question js-panel-dialog-question"></p>
        <label class="input-container js-panel-dialog-field-wrap">
            <span class="input-container__label js-panel-dialog-label">Reason</span>
            <input class="input-text js-panel-dialog-field" type="text" maxlength="500" autocomplete="off">
        </label>
        <div class="tomyum-panel-dialog__buttons">
            <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" value="cancel" type="submit">Cancel</button>
            <button class="btn-osu-big btn-osu-big--rounded-thin js-panel-dialog-confirm" value="confirm" type="submit">OK</button>
        </div>
    </form>
</dialog>
<script>
    // On phones a table row is a card (tomyum-panel.less): each cell gets its column's heading as a label.
    document.querySelectorAll('.tomyum-panel__table').forEach(function (table) {
        var headings = [];
        var headRow = table.querySelector('tr');
        if (headRow === null || headRow.querySelector('th') === null) return;
        headRow.querySelectorAll('th').forEach(function (th) {
            for (var i = 0; i < (th.colSpan || 1); i++) headings.push(th.textContent.trim());
        });
        table.classList.add('tomyum-panel__table--labelled');
        table.querySelectorAll('tr').forEach(function (row) {
            var column = 0;
            row.querySelectorAll(':scope > td').forEach(function (td) {
                if (headings[column]) td.dataset.label = headings[column];
                column += td.colSpan || 1;
            });
        });
    });

    // Registered once per page load (Turbo runs this again on every visit; the listener stays).
    if (!window.tomyumPanelDialog) {
        window.tomyumPanelDialog = true;
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-panel-ask]');
            if (button === null || button.form === null) return;
            event.preventDefault();

            var dialog = document.querySelector('.js-panel-dialog');
            var field = dialog.querySelector('.js-panel-dialog-field');
            var fieldName = button.dataset.panelField || null;
            var confirm = dialog.querySelector('.js-panel-dialog-confirm');

            dialog.querySelector('.js-panel-dialog-question').textContent = button.dataset.panelAsk;
            dialog.querySelector('.js-panel-dialog-field-wrap').hidden = fieldName === null;
            dialog.querySelector('.js-panel-dialog-label').textContent = button.dataset.panelLabel || 'Reason';
            field.value = '';
            field.required = button.dataset.panelRequired !== undefined;
            confirm.textContent = button.dataset.panelConfirm || 'OK';
            confirm.classList.toggle('btn-osu-big--danger', button.dataset.panelDanger !== undefined);

            dialog.onclose = function () {
                if (dialog.returnValue !== 'confirm') return;
                if (fieldName !== null) button.form.elements[fieldName].value = field.value;
                button.form.requestSubmit(button);
            };
            dialog.showModal();
            if (fieldName !== null) field.focus();
        });
    }
</script>

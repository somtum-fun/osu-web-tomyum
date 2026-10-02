{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    Project Loved's header: Overview, the round (the one shown, else the latest) and Requests as tabs,
    and the mode on the right when the page is about one ($urlFn: a mode's url on this page).
    $current: 'overview', 'requests' or the round shown.
--}}
@php
    use App\Models\TomYumLovedRound;

    $shownRound = $current instanceof TomYumLovedRound ? $current : TomYumLovedRound::public()->latest('id')->first();
    $mode = $ruleset ?? null;
    $links = [[
        'active' => $current === 'overview',
        'title' => 'Overview',
        'url' => route('loved.index'),
    ]];
    if ($shownRound !== null) {
        $links[] = [
            'active' => $current instanceof TomYumLovedRound,
            'title' => $shownRound->name,
            'url' => route('loved.rounds.show', ['round' => $shownRound->getKey(), 'mode' => $mode]),
        ];
    }
    $links[] = [
        'active' => $current === 'requests',
        'count' => App\Models\TomYumLovedRequest::where('status', 'open')->count(),
        'title' => 'Requests',
        'url' => route('loved.requests', ['mode' => $mode]),
    ];
@endphp
@component('layout._page_header_v4', ['params' => [
    'links' => $links,
    'theme' => 'contests',
]])
    @isset($urlFn)
        @slot('linksAppend')
            @include('objects._ruleset_selector', ['currentRuleset' => $ruleset, 'urlFn' => $urlFn])
        @endslot
    @endisset
@endcomponent

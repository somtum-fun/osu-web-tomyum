{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    Project Loved's header: the requests by status and the blacklist as tabs, and the mode on the right
    when the page lists one ($urlFn: a mode's url on this page). $current: a status, 'blacklist' or null.
--}}
@php
    use App\Libraries\TomYumLoved;
    use App\Models\TomYumLovedRequest;

    $mode = $ruleset ?? null;
    $tab = fn (string $title, string $status, ?int $count = null) => [
        'active' => $current === $status,
        'count' => $count,
        'title' => $title,
        'url' => route('loved.index', array_filter(['status' => $status === 'open' ? null : $status, 'mode' => $mode])),
    ];
    $links = [
        $tab('Requests', 'open', TomYumLovedRequest::where('status', 'open')->count()),
        $tab('Loved', 'loved'),
        $tab('Rejected', 'rejected'),
    ];
    if (TomYumLoved::isTeam(Auth::user())) {
        $links[] = $tab('Removed', 'removed');
    }
    $links[] = ['active' => $current === 'blacklist', 'title' => 'Blacklist', 'url' => route('loved.blacklist')];
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

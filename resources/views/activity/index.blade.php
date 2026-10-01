{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: Community → Activity. The filters are the header's tabs and the mode its icons, like the rankings. --}}
@php
    use App\Http\Controllers\ActivityController;

    $links = [];
    foreach ($labels as $key => $label) {
        $links[] = ['active' => $key === $type, 'title' => $label, 'url' => ActivityController::url($key, $ruleset)];
    }
    $withRuleset = in_array($type, ActivityController::WITH_RULESET, true);
@endphp
@extends('master', ['titlePrepend' => $labels[$type]])

@section('rulesetSelector')
    {{-- osu-web's mode icons, and "all modes" before them. --}}
    <ul class="game-mode">
        <li>
            <a
                class="{{ class_with_modifiers('game-mode-link', ['active' => $ruleset === null]) }}"
                href="{{ ActivityController::url($type) }}"
                title="All modes"
            >
                <span class="fas fa-globe"></span>
            </a>
        </li>
        @foreach (App\Models\Beatmap::MODES as $mode => $_id)
            <li>
                <a
                    class="{{ class_with_modifiers('game-mode-link', ['active' => $mode === $ruleset]) }}"
                    href="{{ ActivityController::url($type, $mode) }}"
                >
                    <span class="fal fa-extra-mode-{{ $mode }}" title="{{ osu_trans("beatmaps.mode.{$mode}") }}"></span>
                </a>
            </li>
        @endforeach
    </ul>
@endsection

@section('content')
    @component('layout._page_header_v4', ['params' => [
        'links' => $links,
        'theme' => 'activity',
    ]])
        @slot('contentAppend')
            @if ($withRuleset)
                @yield('rulesetSelector')
            @endif
        @endslot

        @slot('linksAppend')
            @if ($withRuleset)
                <div class="visible-xs">
                    @yield('rulesetSelector')
                </div>
            @endif
        @endslot
    @endcomponent

    <div class="osu-page osu-page--generic-compact">
        <div class="user-profile-pages user-profile-pages--no-tabs">
            <div class="page-extra">
                <h2 class="title title--page-extra">{{ $labels[$type] }}</h2>
                <div class="js-react" data-react="activity-index"></div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    @parent

    <script id="json-activity" type="application/json">
        {!! json_encode($json) !!}
    </script>

    @include('layout._react_js', ['src' => 'js/activity-index.js'])
@endsection

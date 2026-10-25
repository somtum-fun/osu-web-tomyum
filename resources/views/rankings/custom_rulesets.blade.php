{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: a custom lazer ruleset's rankings by ranked score (TomYumRulesetsController@rankings). --}}
@extends('rankings.index', [
    'hasFilter' => false,
    'hasMode' => false,
    'pageDescription' => "{$ruleset->name} rankings: TumThai players by ranked score.",
    'params' => ['type' => null],
    'titlePrepend' => "{$ruleset->name} rankings",
])

@section('ranking-header')
    <div class="osu-page osu-page--ranking-info">
        <div class="grid-items grid-items--ranking-filter">
            <div class="ranking-filter">
                <div class="ranking-filter__title">Ruleset</div>
                <div class="sort sort--ranking-header">
                    <div class="sort__items">
                        @foreach ($rulesets as $item)
                            <a
                                class="{{ class_with_modifiers('sort__item', 'button', ['active' => $item->id === $ruleset->id]) }}"
                                href="{{ route('rankings.custom-rulesets', array_filter(['slug' => $item->slug, 'country' => $country])) }}"
                            >{{ $item->name }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="ranking-filter">
                <div class="ranking-filter__title">{{ osu_trans('rankings.countries.title') }}</div>
                <div class="sort sort--ranking-header">
                    <div class="sort__items">
                        <a
                            class="{{ class_with_modifiers('sort__item', 'button', ['active' => $country === null]) }}"
                            href="{{ route('rankings.custom-rulesets', ['slug' => $ruleset->slug]) }}"
                        >{{ osu_trans('rankings.countries.all') }}</a>
                        @php
                            $viewerCountry = Auth::user()?->country_acronym;
                        @endphp
                        @if ($viewerCountry !== null && $viewerCountry !== '' && $viewerCountry !== $country)
                            <a class="sort__item button" href="{{ route('rankings.custom-rulesets', ['slug' => $ruleset->slug, 'country' => $viewerCountry]) }}">
                                @include('objects._flag_country', ['country' => $viewerCountry])
                            </a>
                        @endif
                        @if ($country !== null)
                            <span class="sort__item button active">@include('objects._flag_country', ['country' => $country]) {{ $country }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <p class="ranking-page-info" style="opacity: 0.7; margin-top: 10px">
            {{ $ruleset->name }} is a custom osu!lazer ruleset, played in TumThai (install it in TumThai's Settings). Players are ranked by
            ranked score: their best score on each ranked map, added up. There's no pp.
            @if ($ruleset->source_url !== null)
                <a href="{{ $ruleset->source_url }}" rel="nofollow noopener" target="_blank">About {{ $ruleset->name }}</a>.
            @endif
        </p>
    </div>
@endsection

@section('scores')
    @if ($scores->total() === 0)
        <p style="padding: 20px; text-align: center; opacity: 0.7">Nobody has a ranked {{ $ruleset->name }} play{{ $country !== null ? ' in this country' : '' }} yet.</p>
    @else
        <table class="ranking-page-table">
            <thead>
                <tr>
                    <th></th>
                    <th class="ranking-page-table__heading ranking-page-table__heading--main"></th>
                    <th class="ranking-page-table__heading ranking-page-table__heading--focused">Ranked score</th>
                    <th class="ranking-page-table__heading">Maps</th>
                    <th class="ranking-page-table__heading">Best play</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $firstItem = $scores->firstItem();
                @endphp
                @foreach ($scores as $index => $row)
                    @php
                        $user = $users[$row['user_id']] ?? null;
                    @endphp
                    @if ($user !== null)
                        <tr class="ranking-page-table__row">
                            <td class="ranking-page-table__column">#{{ i18n_number_format($firstItem + $index) }}</td>
                            <td class="ranking-page-table__column ranking-page-table__column--main">
                                @include('rankings._main_column', ['object' => $user])
                            </td>
                            <td class="ranking-page-table__column">{{ i18n_number_format($row['ranked_score']) }}</td>
                            <td class="ranking-page-table__column ranking-page-table__column--dimmed">{{ i18n_number_format($row['maps']) }}</td>
                            <td class="ranking-page-table__column ranking-page-table__column--dimmed">{{ i18n_number_format($row['best']) }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    @endif
@endsection

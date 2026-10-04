{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('rankings.index', [
    'hasPager' => $scores !== null,
    'params' => ['mode' => $rulesetName, 'type' => 'top_plays', 'variant' => $variant ?? null],
    'rulesetSelectorUrlFn' => fn (string $r): string => route('rankings.top-plays', ['mode' => $r]),
    'titlePrepend' => osu_trans('rankings.type.top_plays'),
])

{{-- TomYum: Relax/Autopilot top plays (osu!, taiko, catch), as the global ranking switches. --}}
@php
    $tomyumVariants = isset(App\Http\Controllers\RankingController::TOMYUM_VARIANTS[$rulesetName])
        ? ['all', ...array_keys(App\Http\Controllers\RankingController::TOMYUM_VARIANTS[$rulesetName])]
        : null;
@endphp
@if ($tomyumVariants !== null)
    @section('ranking-header')
        <div class="osu-page osu-page--ranking-info">
            <div class="grid-items grid-items--ranking-filter">
                <div class="js-react u-contents" data-react="ranking-variant-filter">
                    <div class="ranking-filter">
                        <div class="ranking-filter__title">
                            {{ osu_trans('rankings.filter.variant.title') }}
                        </div>
                        <div class="sort sort--ranking-header">
                            <div class="sort__items">
                                @foreach ($tomyumVariants as $v)
                                    <button class="sort__item sort__item--button">
                                        {{ osu_trans("beatmaps.variant.{$rulesetName}.{$v}") }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <script id="json-variant-filter" type="application/json">
                    {!! json_encode([
                        'current' => $variant ?? null,
                        'current_ruleset' => $rulesetName,
                        'items' => $tomyumVariants,
                    ]) !!}
                </script>
            </div>
        </div>
    @endsection
@endif

@if ($scores === null)
    @section('scores')
        {{ osu_trans('rankings.top_plays.empty') }}
    @endsection
@else
    @section('scores-header')
        <p>
            <small>{{ osu_trans('rankings.top_plays.last_updated') }}: {!! timeago($lastUpdate) !!}</small>
        </p>
    @endsection
    @section('scores')
        <div
            class="u-contents js-react"
            data-props="{{ json_encode([
                'first_score_rank' => $scores->firstItem(),
                'scores' => $scoresJson,
            ]) }}"
            data-react="ranking-top-plays"
        >
            <div class="ranking-page-grid">
                <div class="ranking-page-grid-item ranking-page-grid-item--header">
                    <div class="ranking-page-grid-item__content">
                        <div class="ranking-page-grid-item__col">
                            &nbsp;
                        </div>
                    </div>
                </div>
                @foreach ($scores as $score)
                    <div class="ranking-page-grid-item">
                        <div class="ranking-page-grid-item__content">
                            <div class="ranking-page-grid-item__col">
                                <div class="ranking-page-grid-item__icons"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @include('layout._react_js', ['src' => 'js/ranking-top-plays.js'])
    @endsection
@endif

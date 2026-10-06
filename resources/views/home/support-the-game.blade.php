{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    use App\Models\Store\Product;
    use App\Models\SupporterTag;

    static $getDurationText = fn (string $duration): string =>
        SupporterTag::getDurationText($duration, null, osu_trans('common.array_and.two_words_connector'));
@endphp

@extends('master')

@section('content')
    @component('layout._page_header_v4', ['params' => [
        'showTitle' => false,
        'theme' => 'supporter',
    ]])
        @slot('contentAppend')
            <div class="supporter-status">
                {{-- TomYum: no pippi (ppy's mascot). --}}
                @if (!empty($supporterStatus))
                    <!-- supporter status  -->
                    <div class="supporter-status__flex-container">
                        <a class="supporter-eyecatch__link" href="{{ route('store.products.show', Product::SUPPORTER_TAG_NAME) }}" title="{{ osu_trans('community.support.convinced.support') }}">
                            <div class="supporter-heart{{ $supporterStatus['current'] ? ' supporter-heart--active' : '' }}"></div>
                        </a>
                        <div class="supporter-status__flex-container-inner">
                            <div class="supporter-status__progress-bar supporter-status__progress-bar--active">
                                <div class="supporter-status__progress-bar-fill supporter-status__progress-bar-fill--active" style="width: {{ $supporterStatus['remainingPercent'] ?? '0' }}%;"></div>
                            </div>
                            @if ($supporterStatus['expiration'] !== null)
                            <div class="supporter-status__text supporter-status__text--first">
                                {!! osu_trans('community.support.supporter_status.'.($supporterStatus['current'] ? 'valid_until' : 'was_valid_until'), [
                                    'date' => '<strong>'.i18n_date($supporterStatus['expiration']).'</strong>'
                                ]) !!}
                            </div>
                            @else
                            <div class="supporter-status__text">
                                {!! osu_trans('community.support.supporter_status.not_yet') !!}
                            </div>
                            @endif
                            @if ($supporterStatus['duration'] > 0)
                            <div class="supporter-status__text">
                                {!! osu_trans('community.support.supporter_status.contribution_with_duration', [
                                    'dollars' => "<strong>{$supporterStatus['dollars']}</strong>",
                                    'duration' => tag(
                                        'strong',
                                        content: e($getDurationText($supporterStatus['duration'])),
                                    ),
                                ]) !!}
                            </div>
                            @endif
                            @if ($supporterStatus['giftedDuration'] > 0)
                            <div class="supporter-status__text">
                                {!! osu_trans('community.support.supporter_status.gifted._', [
                                    'dollars' => "<strong>{$supporterStatus['giftedDollars']}</strong>",
                                    'duration' => tag(
                                        'strong',
                                        content: e($getDurationText($supporterStatus['giftedDuration'])),
                                    ),
                                    'users' => tag(
                                        'strong',
                                        content: e(osu_trans_choice('community.support.supporter_status.gifted.users', $supporterStatus['giftedUsers'])),
                                    ),
                                ]) !!}
                            </div>
                            @endif
                        </div>
                    </div>
                    <!-- end: supporter status -->
                @endif
            </div>
        @endslot
    @endcomponent

    <div class="osu-page osu-page--supporter">
        <div class="supporter">
            {{-- TomYum: a note from this server's team, in place of peppy's letter (osu!'s, not ours). --}}
            <div class="supporter-quote">
                <div class="supporter-quote__body">
                    <div class="supporter-quote__quote-mark supporter-quote__quote-mark--left"><i class="fas fa-quote-left"></i></div>
                    <blockquote class="supporter-quote__content">
                        Running production isn't easy or cheap.
                        <br/><br/>
                        We're committed to making production a free-to-win experience. We put in a lot of time, effort,
                        and technical skill to keep everything running smoothly and constantly improving.
                        <br/><br/>
                        If you enjoy playing here and want to support our work, consider supporting us. Your support
                        helps us keep production alive and growing. Can't pay? You can also earn supporter for free by
                        <a href="{{ route('vote') }}">voting for us</a>.
                        <br/><br/>
                        A huge thank you to all our donors - you're the reason we can keep doing this.
                    </blockquote>
                    <div class="supporter-quote__quote-mark supporter-quote__quote-mark--right"><i class="fas fa-quote-right"></i></div>
                </div>
                <div class="supporter-quote__signature">— Server Operator, production</div>
                <p class="supporter-quote__signature">
                    Make sure to support osu! (<a href="https://osu.ppy.sh/home/support">osu.ppy.sh/home/support</a>) as well!
                    Huge thanks to peppy for creating this amazing game!
                    production is not affiliated with ppy Pty Ltd or osu!.
                </p>
            </div>
            <h3 class="supporter__title">
                {{ osu_trans('community.support.why-support.title') }}
            </h3>
            @include('home._supporter_perk_group', ['group' => $data['support-reasons']])
            <div class="supporter__block supporter__block--bg-0">
                <h3 class="supporter__title">
                    {{ osu_trans('community.support.perks.title') }}
                </h3>
            </div>
            @foreach($data['perks'] as $index => $group)
                <div class="supporter__block supporter__block--{{'bg-'.$index % 3}}">
                    @include("home._supporter_perk_{$group['type']}", ['group' => $group])
                </div>
            @endforeach
            <h3 class="supporter__title supporter__title--convinced">
                {{ osu_trans('community.support.convinced.title') }}
            </h3>
            <div class="supporter-eyecatch">
                <div class="supporter-eyecatch__box">
                    <a class="supporter-eyecatch__link" href="{{ route('store.products.show', Product::SUPPORTER_TAG_NAME) }}">
                        <div class="supporter-heart supporter-heart--larger supporter-heart--active"></div>
                    </a>
                    <div class="supporter-eyecatch__text supporter-eyecatch__text--main">
                        {{ osu_trans('community.support.convinced.support') }}
                    </div>
                    <div class="supporter-eyecatch__text supporter-eyecatch__text--sub-1">
                        {{ osu_trans('community.support.convinced.gift') }}
                    </div>
                    <div class="supporter-eyecatch__text supporter-eyecatch__text--sub-2">
                        {{ osu_trans('community.support.convinced.instructions') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

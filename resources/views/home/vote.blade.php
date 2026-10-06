{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: vote for the server on server lists (config tomyum.votes). --}}
@extends('master')

@section('content')
@component('layout._page_header_v4')
@endcomponent

<div class="osu-page osu-page--generic">
    <div class="vote-page">
        <h1 class="vote-page__title">Vote for {{ App\Libraries\TomYumBranding::name() }}</h1>
        <p class="vote-page__text">
            Like playing here? A vote on a server list helps new players find us. It only takes a minute,
            and you can vote again every day.
        </p>

        <div class="vote-page__sites">
            @foreach ($sites as $site)
                <div class="vote-page__site">
                    <div class="vote-page__site-name">{{ $site['name'] }}</div>
                    <div class="vote-page__site-text">
                        @if ($site['url'] === null)
                            Coming soon.
                        @elseif ($site['rewarded'] && $daysPerVote > 0)
                            Each vote gives you {{ $daysPerVote }} {{ $daysPerVote === 1 ? 'day' : 'days' }} of
                            {{ App\Libraries\TomYumBranding::name() }} supporter. Vote with your {{ App\Libraries\TomYumBranding::name() }} username
                            @if ($user !== null)
                                (<strong>{{ $user->username }}</strong>)
                            @endif
                            so we know it's you.
                        @else
                            Thank you for helping us grow!
                        @endif
                    </div>
                    @if ($site['url'] !== null)
                        <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ $site['url'] }}" target="_blank" rel="noopener">
                            Vote on {{ $site['name'] }}
                        </a>
                    @else
                        <span class="vote-page__soon">Link coming soon</span>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($user !== null)
            <p class="vote-page__text">
                @if ($myVotes > 0)
                    You've voted {{ i18n_number_format($myVotes) }} {{ $myVotes === 1 ? 'time' : 'times' }}. Thank you!
                @else
                    You haven't voted yet.
                @endif
                @if ($user->osu_subscriber && $user->osu_subscriptionexpiry !== null)
                    Your supporter tag lasts until {{ i18n_date($user->osu_subscriptionexpiry) }}.
                @endif
            </p>
        @endif
        <p class="vote-page__text vote-page__text--small">
            In osu!stable, type <code>!vote</code> to BanchoBot to see your votes. New supporter time shows in
            the game the next time you log in.
        </p>

        @if (count($topVoters) > 0)
            <h2 class="vote-page__subtitle">Top voters</h2>
            <ol class="vote-page__voters">
                @foreach ($topVoters as $entry)
                    <li class="vote-page__voter">
                        @include('objects._flag_country', ['country' => $entry['user']->country_acronym])
                        {!! link_to_user($entry['user'], null, null, ['vote-page__voter-name']) !!}
                        <span class="vote-page__voter-count">
                            {{ i18n_number_format($entry['votes']) }} {{ $entry['votes'] === 1 ? 'vote' : 'votes' }}
                        </span>
                    </li>
                @endforeach
            </ol>
        @endif
    </div>
</div>
@endsection

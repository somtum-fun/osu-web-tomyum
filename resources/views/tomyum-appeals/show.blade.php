{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: the appeal form (TomYumAppealsController). --}}
@php
    use App\Libraries\TomYumAppeals;
    use App\Libraries\TomYumTickets;

    $restrictionsUrl = route('legal', ['locale' => app()->getLocale(), 'path' => 'Restrictions']);
    $error = session('tomyum_appeal_error');
@endphp
@extends('master', ['titlePrepend' => 'Appeal your restriction'])

@section('content')
@component('layout._page_header_v4')
@endcomponent

<div class="osu-page osu-page--generic">
    <div class="tomyum-legal">
        <h1 class="tomyum-legal__title">Appeal your restriction</h1>

        <div class="tomyum-legal__body">
            @if ($sentTicket !== null)
                <div class="tomyum-legal__notice">
                    Thanks, your appeal was sent (ticket #{{ $sentTicket }}). We've emailed you a copy of the
                    ticket number. Appeals usually take 3 to 14 days to look at; we'll reply by email.
                </div>
            @endif

            @if (!$restricted)
                <p>Your account isn't restricted, so there's nothing to appeal. Thanks for playing fair!</p>
            @elseif ($openAppeal !== null)
                <p>
                    Your appeal (ticket #{{ $openAppeal->id }}) is waiting for an answer. We'll reply by email to
                    the address on your account. If you want to add something, reply to that email and keep
                    "[Ticket #{{ $openAppeal->id }}]" in the subject.
                </p>
            @elseif ($appealAfter->isFuture())
                <p>
                    You can appeal from <strong>{{ $appealAfter->format('j F Y') }}</strong>
                    ({!! timeago($appealAfter) !!}). Appeals sent before then can't be reviewed. The
                    <a href="{{ $restrictionsUrl }}">Restrictions &amp; Appeals</a> page explains how the wait works.
                </p>
                <p>
                    If you think your restriction was a mistake, you don't need to wait: email
                    <a href="mailto:{{ TomYumTickets::address('accounts') }}">{{ TomYumTickets::address('accounts') }}</a>.
                </p>
            @elseif (!$hasEmail)
                <p>
                    Your account has no email address, so we couldn't answer you. Please write to
                    <a href="mailto:{{ TomYumTickets::address('appeal') }}">{{ TomYumTickets::address('appeal') }}</a>
                    instead, with your username.
                </p>
            @else
                <p>
                    Your appeal cooldown is over, so you can appeal now. Please be honest and take your time: we
                    read every appeal. Our answer goes to {{ Auth::user()->user_email }}.
                    See <a href="{{ $restrictionsUrl }}">Restrictions &amp; Appeals</a> for what we look at.
                </p>

                @if ($error !== null)
                    <div class="tomyum-legal__notice">{{ $error }}</div>
                @endif

                <form method="POST" action="{{ route('tomyum-appeal.store') }}">
                    @csrf
                    @foreach (TomYumAppeals::QUESTIONS as $key => [$question, $hint])
                        <label class="input-container tomyum-legal__field">
                            <span class="input-container__label">{{ $question }}</span>
                            <span class="tomyum-legal__field-hint">{{ $hint }}</span>
                            <textarea class="input-text tomyum-legal__textarea" name="{{ $key }}" rows="5" maxlength="3000" required>{{ old($key) }}</textarea>
                        </label>
                    @endforeach

                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Send my appeal</button>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection

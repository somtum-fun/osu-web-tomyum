{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: on Restrictions & Appeals (whichever text it shows), when the signed-in player can appeal. --}}
@php
    $currentUser = Auth::user();
    $appealAfter = $currentUser === null ? null : App\Libraries\TomYumAppeals::appealAfter($currentUser);
@endphp
@if ($appealAfter !== null)
    <div class="tomyum-legal__notice">
        @if ($appealAfter->isFuture())
            Your account is restricted. You can appeal from <strong>{{ $appealAfter->format('j F Y') }}</strong>.
        @else
            Your account is restricted, and your appeal cooldown is over: you can <a href="{{ route('tomyum-appeal.show') }}">send your appeal</a> now.
        @endif
    </div>
@endif

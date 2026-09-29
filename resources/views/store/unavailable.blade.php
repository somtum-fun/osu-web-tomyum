{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'osu!store'])

@section('content')
    @include('layout._page_header_v4', ['params' => ['theme' => 'store']])
    <div class="osu-page osu-page--generic">
        <h2>{{ App\Http\Middleware\TomYumStoreUnavailable::MESSAGE }}</h2>
        <p>Nothing is sold here: there's no merch, and supporter tags aren't for sale.</p>
        <p><a href="{{ route('home') }}">Back to the front page</a></p>
    </div>
@endsection

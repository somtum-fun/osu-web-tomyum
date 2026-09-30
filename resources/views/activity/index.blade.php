{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master')

@section('content')
    @include('layout._page_header_v4', ['params' => ['theme' => 'activity']])

    <div class="osu-page osu-page--generic">
        <div class="js-react" data-react="activity-index"></div>
    </div>
@endsection

@section('script')
    @parent

    <script id="json-activity" type="application/json">
        {!! json_encode($json) !!}
    </script>

    @include('layout._react_js', ['src' => 'js/activity-index.js'])
@endsection

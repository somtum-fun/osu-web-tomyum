{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}

@extends('master', [
    'canonicalUrl' => $user->url($mode),
    'currentHue' => $user->user_style,
    'titlePrepend' => App\Libraries\Opengraph\UserOpengraph::escapeForTitle($user->username),
])

@section('content')
    @include('users._restricted_banner', compact('user'))

    @if (count($tomyumModes ?? []) > 0)
        {{-- TomYum: Relax and Autopilot have their own stats (the modes lazer shows too). --}}
        <div class="osu-page" style="display: flex; gap: 8px; justify-content: flex-end; padding-top: 10px; padding-bottom: 0">
            @foreach ($tomyumModes as [$label, $linkMode, $active])
                <a href="{{ route('users.show', ['user' => $user->getKey(), 'mode' => $linkMode]) }}"
                    style="border-radius: 999px; padding: 4px 14px; font-weight: 600; font-size: 13px; text-decoration: none;
                        {{ $active ? 'background: hsl(var(--hsl-h1)); color: hsl(var(--hsl-b6));' : 'background: hsl(var(--hsl-b4)); color: hsl(var(--hsl-c1));' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    @endif

    <div
        class="js-react u-contents"
        data-initial-data="{{ json_encode($initialData) }}"
        data-react="profile-page"
    ></div>
@endsection

@section ("script")
    @parent

    @include('layout._react_js', ['src' => 'js/profile-page.js'])
@endsection

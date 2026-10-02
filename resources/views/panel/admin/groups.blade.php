{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Groups'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Groups'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-users"></i>Groups</h2>
            <p class="tomyum-panel__hint">
                What each group does shows above its members on its page (Markdown: **bold**, *italics*, [links](https://…)).
                Who is in a group is set on the player's <a href="{{ route('panel.admin.users.index') }}">account page</a>.
            </p>
        </div>

        @foreach ($groups as $group)
            <div class="tomyum-panel__card" id="group-{{ $group->getKey() }}">
                <div class="tomyum-panel__card-head">
                    <h2>
                        @if ($group->hasBadge())
                            <span class="user-group-badge" data-label="{{ $group->short_name }}" style="{!! css_group_colour($group) !!}"></span>
                        @endif
                        {{ $group->group_name }}
                    </h2>
                    <span class="tomyum-panel__muted">
                        {{ i18n_number_format($members[$group->getKey()] ?? 0) }} {{ ($members[$group->getKey()] ?? 0) === 1 ? 'member' : 'members' }}
                        ·
                        @if ($group->hasListing())
                            <a href="{{ route('groups.show', ['group' => $group->getKey()]) }}">its page</a>
                        @else
                            not listed (no page)
                        @endif
                    </span>
                </div>
                <form method="POST" action="{{ route('panel.admin.groups.update', ['group' => $group->getKey()]) }}" class="tomyum-panel__row">
                    @csrf
                    <label class="input-container tomyum-panel__field" style="flex-basis: 100%">
                        <span class="input-container__label">Description</span>
                        <textarea class="input-text" name="description" rows="2" maxlength="2000" placeholder="What this group does">{{ $group->group_desc }}</textarea>
                    </label>
                    <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Save</button></div>
                </form>
            </div>
        @endforeach
    </div>
@endsection

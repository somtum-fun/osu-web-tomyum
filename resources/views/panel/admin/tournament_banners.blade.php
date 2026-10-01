{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Tournament banners'])

@section('content')
    @php
        // TomYum: shared by the admin and TC panels; links stay in the one it was opened from.
        $panelPrefix = App\Http\Controllers\Panel\Controller::sharedPanelPrefix();
    @endphp
    @include('panel._header', ['crumbs' => [
        ['url' => route($panelPrefix.'index'), 'title' => $panelPrefix === 'panel.tc.' ? 'TC panel' : 'Admin panel'],
        ['title' => 'Tournament banners'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-plus"></i>Make a banner</h2>
            <p class="tomyum-panel__hint">
                A banner shows under the header of everyone who has it, like osu!'s tournament winner banners, and links to
                its tournament's page (listed with the previous tournaments). It shows 1000 × 60 wide: use an image 2000 × 120
                (like osu!'s) so it's sharp everywhere. Given an address like osu!'s, the double-size one (…@2x.jpg) is taken when there is one.
            </p>
            <form method="POST" action="{{ route($panelPrefix.'tournament-banners.store') }}" enctype="multipart/form-data" style="display: grid; gap: 12px">
                @csrf
                <div class="tomyum-panel__row">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Tournament</span>
                        <input class="input-text" type="text" name="name" required maxlength="255" placeholder="sundei.eu Winter Cup 2026">
                    </label>
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 160px">
                        <span class="input-container__label">Mode</span>
                        <select class="input-text" name="mode">
                            <option value="0">osu!</option>
                            <option value="1">osu!taiko</option>
                            <option value="2">osu!catch</option>
                            <option value="3">osu!mania</option>
                        </select>
                    </label>
                    <label class="input-container tomyum-panel__field" style="flex: 0 1 180px">
                        <span class="input-container__label">Held on (optional)</span>
                        <input class="input-text" type="date" name="date">
                    </label>
                </div>
                <div class="tomyum-panel__row">
                    <label class="tomyum-panel__field">
                        Image (JPEG, PNG, WebP or GIF, up to 10 MB)
                        <input type="file" name="image" accept="image/*">
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">…or the address of one</span>
                        <input class="input-text" type="text" name="image_url" placeholder="https://assets.ppy.sh/tournament-banners/official/owc2025/profile/winner_PL.jpg">
                    </label>
                </div>
                <div class="tomyum-panel__row">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">The tournament's page (optional)</span>
                        <input class="input-text" type="text" name="info_url" maxlength="255" placeholder="https://…">
                    </label>
                    <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-plus"></i>Make it</button></div>
                </div>
            </form>
        </div>

        @forelse ($banners as $banner)
            @php
                $tournament = $banner->tournament;
                $people = $holders[$banner->getKey()] ?? collect();
            @endphp
            <div class="tomyum-panel__card" style="{{ $banner->is_active ? '' : 'opacity: 0.6' }}">
                <div class="tomyum-panel__card-head">
                    <h2>
                        <i class="fas fa-trophy"></i>
                        @if ($tournament !== null)
                            <a href="{{ route('tournaments.show', $tournament) }}">{{ $tournament->name }}</a>
                        @else
                            #{{ $banner->getKey() }}
                        @endif
                        @if (!$banner->is_active)
                            <span class="tomyum-panel__pill">hidden</span>
                        @endif
                    </h2>
                    <div style="display: flex; gap: 8px">
                        <form method="POST" action="{{ route($panelPrefix.'tournament-banners.toggle', ['banner' => $banner->getKey()]) }}">
                            @csrf
                            <button class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" type="submit">
                                <i class="fas {{ $banner->is_active ? 'fa-eye-slash' : 'fa-eye' }}"></i>{{ $banner->is_active ? 'Hide' : 'Show' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route($panelPrefix.'tournament-banners.destroy', ['banner' => $banner->getKey()]) }}"
                            onsubmit="return confirm('Remove this banner (and take it from everyone who has it)?')">
                            @csrf
                            <button class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--danger" type="submit"><i class="fas fa-trash"></i>Remove</button>
                        </form>
                    </div>
                </div>

                <img src="{{ $banner->banner_url_prefix }}.jpg" srcset="{{ $banner->banner_url_prefix }}@2x.jpg 2x" alt=""
                    style="border-radius: 8px; display: block; margin-bottom: 14px; max-height: 160px; object-fit: cover; width: 100%">

                <details style="margin-bottom: 14px">
                    <summary class="tomyum-panel__muted" style="cursor: pointer; font-size: 12px">New image</summary>
                    <form method="POST" action="{{ route($panelPrefix.'tournament-banners.replace', ['banner' => $banner->getKey()]) }}" enctype="multipart/form-data" class="tomyum-panel__row" style="margin-top: 8px">
                        @csrf
                        <label class="tomyum-panel__field">
                            Image
                            <input type="file" name="image" accept="image/*">
                        </label>
                        <label class="input-container tomyum-panel__field">
                            <span class="input-container__label">…or the address of one</span>
                            <input class="input-text" type="text" name="image_url" placeholder="https://…">
                        </label>
                        <div><button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" type="submit"><i class="fas fa-image"></i>Change</button></div>
                    </form>
                </details>

                <form method="POST" action="{{ route($panelPrefix.'tournament-banners.give', ['banner' => $banner->getKey()]) }}" class="tomyum-panel__row">
                    @csrf
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Give it to (name or id)</span>
                        <input class="input-text" type="text" name="user" required>
                    </label>
                    <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-user-plus"></i>Give</button></div>
                </form>

                <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px">
                    @forelse ($people as $holder)
                        @if ($holder->user !== null)
                            <span class="tomyum-panel__pill tomyum-panel__pill--pink" style="align-items: center; display: inline-flex; gap: 6px">
                                <a href="{{ route('users.show', ['user' => $holder->user_id]) }}" style="color: inherit">{{ $holder->user->username }}</a>
                                <form method="POST" action="{{ route($panelPrefix.'tournament-banners.take', ['banner' => $banner->getKey(), 'user' => $holder->user_id]) }}" style="display: inline">
                                    @csrf
                                    <button type="submit" title="Take it away" style="background: none; border: 0; color: inherit; cursor: pointer; padding: 0"><i class="fas fa-times"></i></button>
                                </form>
                            </span>
                        @endif
                    @empty
                        <span class="tomyum-panel__muted" style="font-size: 12px">Nobody has it yet.</span>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="tomyum-panel__card"><p class="tomyum-panel__empty">No banners yet: make one above.</p></div>
        @endforelse
    </div>
@endsection

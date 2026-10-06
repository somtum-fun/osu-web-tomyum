{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Tournament banners'])

@section('content')
    @php
        use App\Http\Controllers\Panel\TournamentBannersController;
        use App\Libraries\TomYumAssets;

        // TomYum: shared by the admin and TC panels; links stay in the one it was opened from.
        $panelPrefix = App\Http\Controllers\Panel\Controller::sharedPanelPrefix();
    @endphp
    @include('panel._header', ['crumbs' => [
        ['url' => route($panelPrefix.'index'), 'title' => $panelPrefix === 'panel.tc.' ? 'TC panel' : 'Admin panel'],
        ['title' => 'Tournament banners'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-plus"></i>Make a tournament's banners</h2>
            <p class="tomyum-panel__hint">
                Like osu!'s: a tournament's banners are the images in its folder on the assets host
                (<code>tournament-banners/official/owc2025/profile/</code>), each at 1000×60 and @2x 2000×120, named like
                <code>winner_CA</code>, <code>supporter_CA</code> or just <code>banner</code>. Everyone you give it gets theirs under their header
                (a player can have several, of one tournament too), linking to the tournament's page.
            </p>
            <form method="POST" action="{{ route($panelPrefix.'tournament-banners.store') }}" class="panel-banners__form">
                @csrf
                {{-- TomYum: or one made on the Tournaments page (its own name, dates and page). --}}
                @if (count($tournaments) > 0)
                    <label class="input-container panel-banners__wide">
                        <span class="input-container__label">For a tournament on the Tournaments page</span>
                        <select class="input-text" name="tournament_id">
                            <option value="">No: a new one, just for its banners (below)</option>
                            @foreach ($tournaments as $existing)
                                <option value="{{ $existing->getKey() }}">{{ $existing->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <label class="input-container panel-banners__wide">
                    <span class="input-container__label">Tournament (its name; not needed for one picked above)</span>
                    <input class="input-text" type="text" name="name" maxlength="255" placeholder="sundei.eu Winter Cup 2026">
                </label>
                <label class="input-container panel-banners__wide">
                    <span class="input-container__label">Its images' folder, under tournament-banners/ (use osu!'s own path to copy theirs)</span>
                    <input class="input-text" type="text" name="folder" required maxlength="150" placeholder="official/owc2025/profile">
                </label>
                <label class="input-container">
                    <span class="input-container__label">Mode</span>
                    <select class="input-text" name="mode">
                        <option value="0">osu!</option>
                        <option value="1">osu!taiko</option>
                        <option value="2">osu!catch</option>
                        <option value="3">osu!mania</option>
                    </select>
                </label>
                <label class="input-container">
                    <span class="input-container__label">Held on (optional)</span>
                    <input class="input-text" type="date" name="date">
                </label>
                <label class="input-container panel-banners__wide">
                    <span class="input-container__label">The tournament's page (optional)</span>
                    <input class="input-text" type="text" name="info_url" maxlength="255" placeholder="https://…">
                </label>
                <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-plus"></i>Make it</button></div>
            </form>
        </div>

        @forelse ($banners as $banner)
            @php
                $tournament = $banner->tournament;
                $people = $holders[$banner->getKey()] ?? collect();
                $folder = TournamentBannersController::folderOf($banner);
                $bannerImages = $images[$banner->getKey()] ?? [];
                $names = array_map(fn ($image) => pathinfo($image['name'], PATHINFO_FILENAME), $bannerImages);
                $countryNamed = collect($names)->contains(fn ($name) => preg_match('/_[A-Z]{2}$/', $name) === 1);
            @endphp
            <div class="tomyum-panel__card {{ $banner->is_active ? '' : 'panel-banners__card--hidden' }}" id="banner-{{ $banner->getKey() }}">
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
                    <div class="panel-banners__buttons">
                        <form method="POST" action="{{ route($panelPrefix.'tournament-banners.toggle', ['banner' => $banner->getKey()]) }}">
                            @csrf
                            <button class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--panel-secondary" type="submit">
                                <i class="fas {{ $banner->is_active ? 'fa-eye-slash' : 'fa-eye' }}"></i>{{ $banner->is_active ? 'Hide' : 'Show' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route($panelPrefix.'tournament-banners.destroy', ['banner' => $banner->getKey()]) }}"
                            onsubmit="return confirm('Remove this tournament\'s banners (and take them from everyone who has one)? Its images stay on the Assets page.')">
                            @csrf
                            <button class="btn-osu-big btn-osu-big--rounded-small btn-osu-big--danger" type="submit"><i class="fas fa-trash"></i>Remove</button>
                        </form>
                    </div>
                </div>

                @if ($folder === null)
                    {{-- Made before: one uploaded image for everyone. --}}
                    <img class="panel-banners__image" src="{{ $banner->banner_url_prefix }}.jpg" srcset="{{ $banner->banner_url_prefix }}@2x.jpg 2x" alt="">
                    <form method="POST" action="{{ route($panelPrefix.'tournament-banners.move', ['banner' => $banner->getKey()]) }}" class="panel-banners__move">
                        @csrf
                        <p class="tomyum-panel__hint">
                            Made before banners went on the assets host: one image for everyone. Move it there to give it more images
                            (its image becomes <code>banner.jpg</code> in the folder; everyone keeps it).
                        </p>
                        <div class="panel-banners__inline">
                            <input class="input-text" type="text" name="folder" required maxlength="150" placeholder="official/owc2025/profile" aria-label="Its folder">
                            <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-truck"></i>Move</button>
                        </div>
                    </form>
                @else
                    <p class="tomyum-panel__muted panel-banners__folder">
                        <i class="fas fa-folder"></i>
                        <a href="{{ route($panelPrefix.'assets.index', ['folder' => "tournament-banners/{$folder}"]) }}">tournament-banners/{{ $folder }}/</a>
                    </p>
                    @if (isset($problems[$banner->getKey()]))
                        <p class="tomyum-panel__empty">{{ $problems[$banner->getKey()] }}</p>
                    @elseif (count($bannerImages) === 0)
                        <p class="tomyum-panel__empty">No images yet: upload one, or copy osu!'s below.</p>
                    @else
                        <div class="panel-banners__images">
                            @foreach ($bannerImages as $image)
                                @php
                                    $path = $image['path'];
                                    $imageName = pathinfo($image['name'], PATHINFO_FILENAME);
                                @endphp
                                <figure class="panel-banners__variant">
                                    <img src="{{ TomYumAssets::url($path) }}" @if ($image['retina'] !== null) srcset="{{ TomYumAssets::url(TomYumAssets::retina($path)) }} 2x" @endif alt="" loading="lazy">
                                    <figcaption>
                                        <strong>{{ $imageName }}</strong>
                                        @if ($image['retina'] === null)
                                            <span class="tomyum-panel__pill tomyum-panel__pill--warn">no @2x</span>
                                        @endif
                                        <span class="tomyum-panel__muted">{{ $people->where('country_acronym', $imageName)->count() }} have it</span>
                                    </figcaption>
                                </figure>
                            @endforeach
                        </div>
                    @endif

                    <details class="panel-banners__add" @if (count($bannerImages) === 0) open @endif>
                        <summary><i class="fas fa-plus"></i> Add images</summary>
                        <div class="panel-banners__add-forms">
                            <form method="POST" action="{{ route($panelPrefix.'tournament-banners.upload', ['banner' => $banner->getKey()]) }}" enctype="multipart/form-data" class="panel-banners__form">
                                @csrf
                                <label class="input-container panel-banners__wide">
                                    <span class="input-container__label">Name (empty: the file's own), like winner_CA or banner</span>
                                    <input class="input-text" type="text" name="name" maxlength="80" placeholder="banner">
                                </label>
                                <label class="tomyum-panel__field">
                                    @2x, 2000×120 (the normal one is made from it)
                                    <input type="file" name="retina" accept="image/*">
                                </label>
                                <label class="tomyum-panel__field">
                                    Normal, 1000×60 (optional)
                                    <input type="file" name="normal" accept="image/*">
                                </label>
                                <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-upload"></i>Upload</button></div>
                            </form>
                            <form method="POST" action="{{ route($panelPrefix.'tournament-banners.copy', ['banner' => $banner->getKey()]) }}" class="panel-banners__form">
                                @csrf
                                <label class="input-container panel-banners__wide">
                                    <span class="input-container__label">…or copy images by link, one per line (both sizes, under their own names)</span>
                                    <textarea class="input-text panel-banners__links" name="links" rows="4" required placeholder="https://assets.ppy.sh/tournament-banners/official/owc2025/profile/supporter_CA.jpg"></textarea>
                                </label>
                                <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-copy"></i>Copy</button></div>
                            </form>
                        </div>
                    </details>
                @endif

                <form method="POST" action="{{ route($panelPrefix.'tournament-banners.give', ['banner' => $banner->getKey()]) }}" class="panel-banners__give">
                    @csrf
                    <label class="input-container">
                        <span class="input-container__label">Give it to (name or id)</span>
                        <input class="input-text" type="text" name="user" required>
                    </label>
                    @if ($folder !== null)
                        <label class="input-container">
                            <span class="input-container__label">Which image</span>
                            <select class="input-text" name="image" required>
                                @if ($countryNamed)
                                    <option value="*country">Their country's (…_XX)</option>
                                @endif
                                @foreach ($names as $name)
                                    <option value="{{ $name }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                    <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit" @disabled($folder !== null && count($names) === 0)><i class="fas fa-user-plus"></i>Give</button></div>
                </form>

                <div class="panel-banners__holders">
                    @forelse ($people as $holder)
                        @if ($holder->user !== null)
                            <span class="tomyum-panel__pill tomyum-panel__pill--pink panel-banners__holder">
                                <a href="{{ route('users.show', ['user' => $holder->user_id]) }}">{{ $holder->user->username }}</a>
                                @if ($holder->country_acronym !== '' && $folder !== null)
                                    <span class="panel-banners__holder-image">{{ $holder->country_acronym }}</span>
                                @endif
                                <form method="POST" action="{{ route($panelPrefix.'tournament-banners.take', ['banner' => $banner->getKey(), 'user' => $holder->user_id]) }}">
                                    @csrf
                                    <input type="hidden" name="image" value="{{ $holder->country_acronym }}">
                                    <button type="submit" title="Take it away"><i class="fas fa-times"></i></button>
                                </form>
                            </span>
                        @endif
                    @empty
                        <span class="tomyum-panel__muted">Nobody has it yet.</span>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="tomyum-panel__card"><p class="tomyum-panel__empty">No banners yet: make one above.</p></div>
        @endforelse
    </div>
@endsection

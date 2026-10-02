{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => $artist->name])

@section('content')
    @php
        $own = $artist->getKey() >= App\Http\Controllers\Panel\ArtistsController::OWN_ID_FLOOR['artists'];
        $trackCount = $tracks->flatten()->count();
        // An album's tracks, then the singles (album_id null), each as a list.
        $groups = $albums->map(fn ($album) => [$album, $tracks->get($album->getKey(), collect())]);
        $singles = $tracks->get('', collect());
        $length = fn ($track) => sprintf('%d:%02d', intdiv($track->length, 60), $track->length % 60);
    @endphp
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['url' => route('panel.admin.artists.index'), 'title' => 'Featured artists'],
        ['title' => $artist->name],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="panel-artists__hero" style="{{ present($artist->header_url) ? "--header: url('".e($artist->header_url)."')" : '' }}">
            <span class="panel-artists__portrait panel-artists__portrait--big" style="{{ present($artist->cover_url) ? "background-image: url('".e($artist->cover_url)."')" : '' }}"></span>
            <div class="panel-artists__hero-text">
                <div class="panel-artists__pills">
                    @if ($artist->visible)
                        <span class="tomyum-panel__pill tomyum-panel__pill--good">Shown</span>
                    @else
                        <span class="tomyum-panel__pill">Hidden</span>
                    @endif
                    <span class="tomyum-panel__pill {{ $own ? 'tomyum-panel__pill--pink' : 'tomyum-panel__pill--info' }}">{{ $own ? 'This server\'s own' : 'From osu!' }}</span>
                    <span class="tomyum-panel__pill">#{{ $artist->getKey() }}</span>
                </div>
                <h1 class="panel-artists__title">{{ $artist->name }}</h1>
                <p class="panel-artists__hero-meta">
                    {{ count($albums) }} albums · {{ $trackCount }} tracks · {{ $beatmapsets }} beatmap sets
                    · <a href="{{ route('artists.show', ['artist' => $artist->getKey()]) }}">Artist page <i class="fas fa-angle-right"></i></a>
                </p>
            </div>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-user-edit"></i>Details</h2>
            <form method="POST" action="{{ route('panel.admin.artists.update', ['artist' => $artist->getKey()]) }}" enctype="multipart/form-data" class="panel-artists__form">
                @csrf
                <label class="input-container panel-artists__wide">
                    <span class="input-container__label">Name</span>
                    <input class="input-text" type="text" name="name" required maxlength="255" value="{{ $artist->name }}">
                </label>
                <label class="tomyum-panel__check panel-artists__wide">
                    <input type="checkbox" name="visible" value="1" @checked($artist->visible)> Shown (on the Featured Artists page and in the track search)
                </label>
                <label class="input-container panel-artists__wide">
                    <span class="input-container__label">About (markdown: **bold**, *italic*, [a link](https://…), a blank line between paragraphs)</span>
                    <textarea class="input-text panel-artists__textarea" name="description" rows="8">{{ $artist->description }}</textarea>
                </label>

                <p class="tomyum-panel__section-title panel-artists__wide">Images: an address, or upload one</p>
                @foreach ([['header', 'header_url', 'Header (wide, behind the name; 1920×360 or so)'], ['cover', 'cover_url', 'Portrait (square)']] as [$name, $column, $label])
                    <div class="panel-artists__image-field panel-artists__wide">
                        <span class="panel-artists__thumb panel-artists__thumb--{{ $name }}" style="{{ present($artist->$column) ? "background-image: url('".e($artist->$column)."')" : '' }}"></span>
                        <label class="input-container">
                            <span class="input-container__label">{{ $label }}</span>
                            <input class="input-text" type="text" name="{{ $column }}" maxlength="512" value="{{ $artist->$column }}" placeholder="https://…">
                        </label>
                        <label class="tomyum-panel__field">
                            …or upload
                            <input type="file" name="{{ $name }}_file" accept="image/*">
                        </label>
                    </div>
                @endforeach

                <p class="tomyum-panel__section-title panel-artists__wide">Profile</p>
                <label class="input-container">
                    <span class="input-container__label"><i class="fas fa-user"></i> Their profile on this server (a player's name or id; they get the FA badge)</span>
                    <input class="input-text" type="text" name="profile" maxlength="255" value="{{ $artist->user_id === null ? '' : (App\Models\User::find($artist->user_id)?->username ?? $artist->user_id) }}" placeholder="none">
                </label>
                <label class="input-container">
                    <span class="input-container__label"><i class="fas fa-user"></i> Their osu! profile (shown when there's none here)</span>
                    <input class="input-text" type="text" name="osu_profile_url" maxlength="512" value="{{ $artist->osu_profile_url }}" placeholder="https://osu.ppy.sh/users/…">
                </label>

                <p class="tomyum-panel__section-title panel-artists__wide">Links (empty: none)</p>
                @foreach ($links as $column => [$label, $icon])
                    <label class="input-container">
                        <span class="input-container__label"><i class="{{ $icon }}"></i> {{ $label }}</span>
                        <input class="input-text" type="text" name="{{ $column }}" maxlength="512" value="{{ $artist->$column }}" placeholder="https://…">
                    </label>
                @endforeach
                <label class="input-container">
                    <span class="input-container__label"><i class="fas fa-video"></i> Video under the description (a video file's address)</span>
                    <input class="input-text" type="text" name="video_url" maxlength="512" value="{{ $artist->video_url }}" placeholder="https://….mp4">
                </label>

                <div class="panel-artists__buttons panel-artists__wide">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-save"></i>Save</button>
                </div>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <div class="tomyum-panel__card-head">
                <h2><i class="fas fa-compact-disc"></i>Albums and tracks</h2>
                <span class="tomyum-panel__hint">Click one to edit it</span>
            </div>

            @foreach ([...$groups, [null, $singles]] as [$album, $albumTracks])
                @continue($album === null && count($albumTracks) === 0)
                <div class="panel-artists__album" id="{{ $album === null ? 'singles' : "album-{$album->getKey()}" }}">
                    @if ($album === null)
                        <div class="panel-artists__album-head">
                            <span class="panel-artists__cover" style="{{ present($artist->cover_url) ? "background-image: url('".e($artist->cover_url)."')" : '' }}"></span>
                            <span class="panel-artists__album-title">Singles</span>
                            <span class="tomyum-panel__muted">{{ count($albumTracks) }} tracks</span>
                        </div>
                    @else
                        <details class="panel-artists__details">
                            <summary class="panel-artists__album-head">
                                <span class="panel-artists__cover" style="{{ present($album->cover_url) ? "background-image: url('".e($album->cover_url)."')" : '' }}"></span>
                                <span class="panel-artists__album-title u-ellipsis-overflow">{{ $album->title }}</span>
                                <span class="tomyum-panel__muted">{{ $album->genre }} · {{ count($albumTracks) }} tracks</span>
                                @unless ($album->visible)
                                    <span class="tomyum-panel__pill">Hidden</span>
                                @endunless
                                <i class="fas fa-pen panel-artists__edit-icon"></i>
                            </summary>
                            <div class="panel-artists__editor">
                                @include('panel.admin.artists._album_form', [
                                    'album' => $album,
                                    'action' => route('panel.admin.artists.albums.update', ['artist' => $artist->getKey(), 'album' => $album->getKey()]),
                                ])
                                <form method="POST" action="{{ route('panel.admin.artists.albums.destroy', ['artist' => $artist->getKey(), 'album' => $album->getKey()]) }}"
                                    onsubmit="return confirm('Remove this album? Its tracks stay, as singles.')">
                                    @csrf
                                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-trash"></i>Remove the album</button>
                                </form>
                            </div>
                        </details>
                    @endif

                    @if (count($albumTracks) === 0)
                        <p class="tomyum-panel__muted panel-artists__no-tracks">No tracks yet.</p>
                    @endif
                    @foreach ($albumTracks as $track)
                        <details class="panel-artists__details panel-artists__track" id="track-{{ $track->getKey() }}">
                            <summary class="panel-artists__track-head">
                                {{-- The site's own player (js-audio--player); playing doesn't unfold the track. --}}
                                <span class="panel-artists__play js-audio--player" data-audio-url="{{ $track->preview }}">
                                    <button class="panel-artists__play-button js-audio--play" type="button" title="Play the preview" onclick="event.preventDefault()">
                                        <i class="fas fa-play"></i>
                                    </button>
                                </span>
                                <span class="panel-artists__track-title u-ellipsis-overflow">
                                    {{ $track->title }}
                                    @if (present($track->version))
                                        <span class="tomyum-panel__muted">({{ $track->version }})</span>
                                    @endif
                                </span>
                                @if ($track->exclusive)
                                    <span class="tomyum-panel__pill tomyum-panel__pill--pink">Exclusive</span>
                                @endif
                                <span class="tomyum-panel__muted panel-artists__track-meta">{{ $track->genre }} · {{ round($track->bpm) }} BPM · {{ $length($track) }}</span>
                                <i class="fas fa-pen panel-artists__edit-icon"></i>
                            </summary>
                            <div class="panel-artists__editor">
                                @include('panel.admin.artists._track_form', [
                                    'track' => $track,
                                    'albums' => $albums,
                                    'action' => route('panel.admin.artists.tracks.update', ['artist' => $artist->getKey(), 'track' => $track->getKey()]),
                                ])
                                <form method="POST" action="{{ route('panel.admin.artists.tracks.destroy', ['artist' => $artist->getKey(), 'track' => $track->getKey()]) }}"
                                    onsubmit="return confirm('Remove this track? Beatmap sets of it stop linking to it.')">
                                    @csrf
                                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-trash"></i>Remove the track</button>
                                </form>
                            </div>
                        </details>
                    @endforeach
                </div>
            @endforeach

            @if (count($albums) === 0 && $trackCount === 0)
                <p class="tomyum-panel__empty">No albums or tracks yet: add them below.</p>
            @endif
        </div>

        <div class="tomyum-panel__card">
                <h2><i class="fas fa-plus"></i>Add a track</h2>
                @include('panel.admin.artists._track_form', [
                    'track' => null,
                    'albums' => $albums,
                    'action' => route('panel.admin.artists.tracks.store', ['artist' => $artist->getKey()]),
                ])
                <p class="tomyum-panel__hint">A track with a cover of its own shows that; otherwise its album's, or the artist's portrait.</p>
        </div>
        <div class="tomyum-panel__card">
                <h2><i class="fas fa-plus"></i>Add an album</h2>
                @include('panel.admin.artists._album_form', [
                    'album' => null,
                    'action' => route('panel.admin.artists.albums.store', ['artist' => $artist->getKey()]),
                ])
        </div>

        <div class="tomyum-panel__card tomyum-panel__card--danger">
            <h2><i class="fas fa-exclamation-triangle"></i>Remove the artist</h2>
            <p class="tomyum-panel__hint">
                With all their albums and tracks; beatmap sets of their tracks stop linking to them.
                @unless ($own)
                    They're osu!'s: the next import adds them back. To keep them off the site, hide them instead.
                @endunless
            </p>
            <form method="POST" action="{{ route('panel.admin.artists.destroy', ['artist' => $artist->getKey()]) }}"
                onsubmit="return confirm('Remove {{ e(addslashes($artist->name)) }} with all their albums and tracks?')">
                @csrf
                <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-trash"></i>Remove {{ $artist->name }}</button>
            </form>
        </div>
    </div>
@endsection

@section('script')
    @parent
    <script>
        // The page opens on what was just saved (its #album-… / #track-…), unfolded.
        (() => {
            const target = location.hash === '' ? null : document.getElementById(location.hash.slice(1));
            const details = target?.matches('details') ? target : target?.querySelector('details');
            if (details != null) details.open = true;
        })();
    </script>
@endsection

{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Featured artists'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Featured artists'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <div class="tomyum-panel__card-head">
                <h2><i class="fas fa-music"></i>Featured artists</h2>
                <a class="tomyum-panel__hint" href="{{ route('artists.index') }}">Featured Artists page <i class="fas fa-angle-right"></i></a>
            </div>
            <div class="tomyum-panel__facts">
                <div class="tomyum-panel__fact">
                    <span class="tomyum-panel__kicker">Artists</span>
                    <span class="tomyum-panel__fact-value">{{ i18n_number_format($totals['artists']) }}</span>
                </div>
                <div class="tomyum-panel__fact">
                    <span class="tomyum-panel__kicker">This server's own</span>
                    <span class="tomyum-panel__fact-value">{{ i18n_number_format($totals['own']) }}</span>
                </div>
                <div class="tomyum-panel__fact">
                    <span class="tomyum-panel__kicker">Hidden</span>
                    <span class="tomyum-panel__fact-value">{{ i18n_number_format($totals['hidden']) }}</span>
                </div>
                <div class="tomyum-panel__fact">
                    <span class="tomyum-panel__kicker">Tracks</span>
                    <span class="tomyum-panel__fact-value">{{ i18n_number_format($totals['tracks']) }}</span>
                </div>
            </div>
            <p class="tomyum-panel__hint" style="margin-top: 12px">
                osu!'s artists come in with <code>php artisan tomyum:artists-import</code> (new ones only, so what's changed
                here stays). Ones added here are this server's own.
            </p>
        </div>

        <div class="tomyum-panel__columns">
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-search"></i>Find</h2>
                <form method="GET" action="{{ route('panel.admin.artists.index') }}" class="panel-artists__inline">
                    <input class="input-text" type="text" name="q" value="{{ $query }}" placeholder="Name or id" aria-label="Name or id">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Search</button>
                    @if ($query !== '')
                        <a class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" href="{{ route('panel.admin.artists.index') }}">Clear</a>
                    @endif
                </form>
            </div>
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-plus"></i>Add an artist</h2>
                <form method="POST" action="{{ route('panel.admin.artists.store') }}" class="panel-artists__inline">
                    @csrf
                    <input class="input-text" type="text" name="name" required maxlength="255" placeholder="Artist's name" aria-label="Artist's name">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Add</button>
                </form>
            </div>
        </div>

        <div class="tomyum-panel__card">
            <div class="tomyum-panel__card-head">
                <h2><i class="fas fa-list"></i>{{ $query === '' ? 'All artists' : "Matching \"{$query}\"" }}</h2>
                <span class="tomyum-panel__hint">{{ i18n_number_format($artists->total()) }}, A to Z</span>
            </div>
            @if ($artists->isEmpty())
                <p class="tomyum-panel__empty">None.</p>
            @else
                <div class="panel-artists__grid">
                    @foreach ($artists as $artist)
                        <a class="panel-artists__artist {{ $artist->visible ? '' : 'panel-artists__artist--hidden' }}" href="{{ route('panel.admin.artists.show', ['artist' => $artist->getKey()]) }}">
                            <span class="panel-artists__portrait" style="{{ present($artist->cover_url) ? "background-image: url('".e($artist->cover_url)."')" : '' }}"></span>
                            <span class="panel-artists__artist-text">
                                <span class="panel-artists__name u-ellipsis-overflow">{{ $artist->name }}</span>
                                <span class="tomyum-panel__muted">{{ $artist->albums_count }} albums · {{ $artist->tracks_count }} tracks</span>
                            </span>
                            @if (!$artist->visible)
                                <span class="tomyum-panel__pill">Hidden</span>
                            @elseif ($artist->getKey() >= App\Http\Controllers\Panel\ArtistsController::OWN_ID_FLOOR['artists'])
                                <span class="tomyum-panel__pill tomyum-panel__pill--pink">Own</span>
                            @endif
                        </a>
                    @endforeach
                </div>
                @if ($artists->lastPage() > 1)
                    <div class="panel-artists__pages">
                        @if ($artists->previousPageUrl() !== null)
                            <a class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" href="{{ $artists->previousPageUrl() }}"><i class="fas fa-angle-left"></i>Previous</a>
                        @endif
                        <span class="tomyum-panel__muted">Page {{ $artists->currentPage() }} of {{ $artists->lastPage() }}</span>
                        @if ($artists->nextPageUrl() !== null)
                            <a class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" href="{{ $artists->nextPageUrl() }}">Next<i class="fas fa-angle-right"></i></a>
                        @endif
                    </div>
                @endif
            @endif
        </div>
    </div>
@endsection

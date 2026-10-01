{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Seasonal backgrounds'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Seasonal backgrounds'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-snowflake"></i>Season</h2>
            <p class="tomyum-panel__hint">
                lazer and osu!stable show these as main menu backgrounds until the season ends, then go back
                to their usual ones. Players see changes the next time they start the game.
            </p>
            @if ($endsAt === null)
                <p>No end date yet (the first upload sets one a month away).</p>
            @elseif ($endsAt->isPast())
                <p><strong>The season ended {{ $endsAt->toDateString() }}</strong>: nothing is shown. Set a new end date to show them again.</p>
            @else
                <p>Shown until <strong>{{ $endsAt->toDateString() }}</strong> ({{ $endsAt->diffForHumans() }}).</p>
            @endif
            <form method="POST" action="{{ route('panel.admin.seasonal-backgrounds.settings') }}" class="tomyum-panel__row">
                @csrf
                <label class="input-container tomyum-panel__field" style="flex: 0 1 220px">
                    <span class="input-container__label">Season ends (UTC)</span>
                    <input class="input-text" type="date" name="ends_at" required value="{{ $endsAt?->toDateString() }}">
                </label>
                <div style="align-self: flex-end"><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Save</button></div>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-images"></i>Backgrounds</h2>
            @if (count($entries) === 0)
                <p class="tomyum-panel__empty">None yet.</p>
            @else
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px">
                    @foreach ($entries as $entry)
                        <div style="{{ $entry->show_in_client ? '' : 'opacity: 0.5' }}">
                            <a href="{{ $entry->seasonalUrl() }}" target="_blank" rel="noopener">
                                <img src="{{ $entry->seasonalUrl() }}" alt="" loading="lazy" style="width: 100%; aspect-ratio: 16 / 9; object-fit: cover; border-radius: 6px">
                            </a>
                            <p style="margin: 4px 0">
                                by <a href="{{ route('users.show', ['user' => $entry->user_id]) }}">{{ $entry->user?->username ?? "#{$entry->user_id}" }}</a>
                                @unless ($entry->show_in_client)
                                    <span class="tomyum-panel__hint">(hidden)</span>
                                @endunless
                            </p>
                            <form method="POST" action="{{ route('panel.admin.seasonal-backgrounds.toggle', ['entry' => $entry->getKey()]) }}" style="display: inline">
                                @csrf
                                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">{{ $entry->show_in_client ? 'Hide' : 'Show' }}</button>
                            </form>
                            <form method="POST" action="{{ route('panel.admin.seasonal-backgrounds.destroy', ['entry' => $entry->getKey()]) }}" style="display: inline"
                                onsubmit="return confirm('Remove this background for good?')">
                                @csrf
                                <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-plus"></i>Add a background</h2>
            <form method="POST" action="{{ route('panel.admin.seasonal-backgrounds.store') }}" enctype="multipart/form-data" class="tomyum-panel">
                @csrf
                <div class="tomyum-panel__row">
                    <label class="tomyum-panel__field">
                        Image (JPEG, PNG or WebP, 16:9 looks best, up to 15 MB)
                        <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Artist (a player's name; empty: you)</span>
                        <input class="input-text" type="text" name="artist" placeholder="{{ Auth::user()->username }}">
                    </label>
                </div>
                <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Upload</button></div>
            </form>
        </div>
    </div>
@endsection

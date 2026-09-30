{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: connecting Last.fm, so the game server scrobbles the player's plays (App\Http\Controllers\Account\LastFmController). --}}
@php
    $lastFm = App\Http\Controllers\Account\LastFmController::status(Auth::user());
    $lastFmStatus = session('lastfm_status');
@endphp
@if (($lastFm['configured'] ?? false) === true)
    <div class="account-edit" id="lastfm">
        <div class="account-edit__section">
            <h2 class="account-edit__section-title">Last.fm</h2>
        </div>

        <div class="account-edit__input-groups">
            <div class="account-edit__input-group">
                <div class="account-edit-entry">
                    <div class="account-edit-entry__label account-edit-entry__label--top-pinned">Scrobbling</div>
                    <div class="account-edit-entry__group">
                        @if ($lastFmStatus !== null)
                            <p style="color: hsl(var({{ $lastFmStatus['type'] === 'success' ? '--hsl-lime-1' : '--hsl-red-1' }}))">{{ $lastFmStatus['message'] }}</p>
                        @endif

                        @if ($lastFm['username'] !== null)
                            <p>
                                Your plays are scrobbled to
                                <a href="https://www.last.fm/user/{{ rawurlencode($lastFm['username']) }}" rel="nofollow noopener" target="_blank">{{ $lastFm['username'] }}</a>:
                                every map you pass, on stable or lazer, as its song. Last.fm shows what you're playing while you play, and your profile shows what you're listening to.
                            </p>
                            @if (count($lastFm['recent'] ?? []) > 0)
                                <div style="margin: 12px 0 16px">
                                    <div style="font-weight: 600; margin-bottom: 6px">Recently scrobbled</div>
                                    @foreach ($lastFm['recent'] as $track)
                                        @php
                                            // Plays from osu! here link to their map; the rest to Last.fm.
                                            $trackUrl = $track['beatmapId'] !== null ? route('beatmaps.show', ['beatmap' => $track['beatmapId']]) : $track['url'];
                                            $pill = static fn (string $colour, string $text) => '<span style="background: hsla(var('.$colour.'), 0.2); border-radius: 999px; color: hsl(var('.$colour.')); font-size: 11px; font-weight: 600; padding: 0 8px">'.e($text).'</span>';
                                        @endphp
                                        <div style="align-items: baseline; display: flex; flex-wrap: wrap; gap: 4px 10px; padding: 3px 0">
                                            @if ($trackUrl !== null)
                                                <a href="{{ $trackUrl }}" @if ($track['beatmapId'] === null) rel="nofollow noopener" target="_blank" @endif>{{ $track['artist'] }} – {{ $track['title'] }}</a>
                                            @else
                                                <span>{{ $track['artist'] }} – {{ $track['title'] }}</span>
                                            @endif
                                            {{-- The clients in their changelog colours (stable blue, lazer red); other players have no tag. --}}
                                            @if ($track['client'] !== null)
                                                {!! $pill($track['client'] === 'lazer' ? '--hsl-red-1' : '--hsl-blue-1', 'osu! '.$track['client']) !!}
                                            @endif
                                            @if ($track['nowPlaying'])
                                                {!! $pill('--hsl-lime-1', 'now playing') !!}
                                            @elseif ($track['at'] !== null)
                                                <span style="color: hsl(var(--hsl-c2)); font-size: 12px">{!! timeago(Carbon\Carbon::parse($track['at'])) !!}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            <form method="POST" action="{{ route('account.lastfm.destroy') }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn-osu-big btn-osu-big--account-edit" type="submit">
                                    <span class="btn-osu-big__content">
                                        <span class="btn-osu-big__left">Disconnect Last.fm</span>
                                        <span class="btn-osu-big__icon"><i class="fas fa-unlink"></i></span>
                                    </span>
                                </button>
                            </form>
                        @else
                            <p>Connect your Last.fm account to scrobble the songs of the maps you pass, on stable and lazer.</p>
                            <a class="btn-osu-big btn-osu-big--account-edit" href="{{ route('account.lastfm.create') }}">
                                <span class="btn-osu-big__content">
                                    <span class="btn-osu-big__left">Connect Last.fm</span>
                                    <span class="btn-osu-big__icon"><i class="fab fa-lastfm"></i></span>
                                </span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

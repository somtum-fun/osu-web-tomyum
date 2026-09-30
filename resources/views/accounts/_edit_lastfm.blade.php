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
                                every map you pass, on stable or lazer, as its song. Last.fm shows what you're playing while you play.
                            </p>
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

{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: linking Discord and the player's osu! account (App\Libraries\TomYumConnections). --}}
@php
    $connections = App\Libraries\TomYumConnections::status(Auth::user());
    $connectionsStatus = session('connections_status');
    $icons = ['discord' => 'fab fa-discord', 'osu_bancho' => 'fal fa-extra-mode-osu'];
    $shownOnProfile = App\Libraries\TomYumConnections::shownOnProfile(Auth::user());
@endphp
<div class="account-edit" id="connections">
    <div class="account-edit__section">
        <h2 class="account-edit__section-title">Connections</h2>
    </div>

    <div class="account-edit__input-groups">
        <div class="account-edit__input-group">
            @if ($connectionsStatus !== null)
                <p style="color: hsl(var({{ $connectionsStatus['type'] === 'success' ? '--hsl-lime-1' : '--hsl-red-1' }}))">{{ $connectionsStatus['message'] }}</p>
            @endif
            <p>Link your Discord and your osu! account (osu.ppy.sh). Applying to be a Beatmap Nominator needs both. Linked accounts stay private unless you show them on your profile.</p>

            @foreach (App\Libraries\TomYumConnections::KINDS as $kind)
                @php
                    $link = $connections[$kind];
                    $label = App\Libraries\TomYumConnections::LABELS[$kind];
                @endphp
                <div class="account-edit-entry">
                    <div class="account-edit-entry__label account-edit-entry__label--top-pinned"><i class="{{ $icons[$kind] }}"></i> {{ $label }}</div>
                    <div class="account-edit-entry__group">
                        @if ($link['linked'])
                            <p>
                                Linked:
                                @if ($kind === 'osu_bancho')
                                    <a href="https://osu.ppy.sh/users/{{ $link['id'] }}" rel="nofollow noopener" target="_blank">{{ $link['name'] ?? $link['id'] }}</a>
                                    @if ($link['restricted'])
                                        &middot; <span style="color: hsl(var(--hsl-red-1))">restricted on osu!</span>
                                    @elseif ($link['restricted_recently'])
                                        &middot; <span style="color: hsl(var(--hsl-red-1))">restricted on osu! in the last 12 months</span>
                                    @endif
                                @else
                                    {{ $link['name'] ?? 'Discord account '.$link['id'] }}
                                @endif
                            </p>
                            @include('accounts._connection_profile_toggle', ['kind' => $kind, 'shown' => in_array($kind, $shownOnProfile, true)])
                            <form method="POST" action="{{ route('account.connections.destroy', ['kind' => $kind]) }}" onsubmit="return confirm('Unlink {{ $label }}?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn-osu-big btn-osu-big--account-edit" type="submit">
                                    <span class="btn-osu-big__content">
                                        <span class="btn-osu-big__left">Unlink {{ $label }}</span>
                                        <span class="btn-osu-big__icon"><i class="fas fa-unlink"></i></span>
                                    </span>
                                </button>
                            </form>
                        @elseif (App\Libraries\TomYumConnections::configured($kind))
                            <a class="btn-osu-big btn-osu-big--account-edit" href="{{ route('account.connections.create', ['kind' => $kind]) }}">
                                <span class="btn-osu-big__content">
                                    <span class="btn-osu-big__left">Link {{ $label }}</span>
                                    <span class="btn-osu-big__icon"><i class="{{ $icons[$kind] }}"></i></span>
                                </span>
                            </a>
                        @else
                            <p class="tomyum-panel__hint">Not available yet.</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

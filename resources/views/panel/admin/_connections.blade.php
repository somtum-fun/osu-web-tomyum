{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: an account's connected profiles (the admin panel's account page, next to lock & whitelist). --}}
<div class="tomyum-panel__card" id="connections">
    <h2><i class="fas fa-link"></i>Connected profiles</h2>
    @if ($links === null)
        <p>Couldn't load their links from the game server.</p>
    @else
        <table class="tomyum-panel__table">
            <tr>
                <td style="width: 120px"><i class="fab fa-discord"></i> Discord</td>
                @if ($links['discord'] !== null)
                    <td>ID <code>{{ $links['discord']['discordId'] }}</code>, linked {!! timeago(Carbon\Carbon::parse($links['discord']['linkedAt'])) !!}</td>
                    <td style="width: 1%">
                        <form method="POST" action="{{ route('panel.admin.users.unlink', ['user' => $userId]) }}" onsubmit="return confirm('Unlink their Discord?')">
                            @csrf
                            <input type="hidden" name="service" value="discord">
                            <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-eraser"></i>Reset</button>
                        </form>
                    </td>
                @else
                    <td colspan="2" class="tomyum-panel__hint">not linked</td>
                @endif
            </tr>
            @php
                $osuLink = App\Libraries\TomYumConnections::status($target, false)['osu_bancho'];
            @endphp
            <tr>
                <td><i class="fal fa-extra-mode-osu"></i> osu!</td>
                @if ($osuLink['linked'])
                    <td>
                        <a href="https://osu.ppy.sh/users/{{ $osuLink['id'] }}" rel="nofollow noreferrer" target="_blank">{{ $osuLink['name'] ?? $osuLink['id'] }}</a>
                        on osu.ppy.sh (id {{ $osuLink['id'] }})
                        @if ($osuLink['restricted'])
                            · <span class="tomyum-panel__badge tomyum-panel__badge--error">restricted there</span>
                        @elseif ($osuLink['restricted_recently'])
                            · <span class="tomyum-panel__badge tomyum-panel__badge--error">restricted there {!! timeago(Carbon\Carbon::parse($osuLink['restricted_at'])) !!}</span>
                        @endif
                        @if ($osuLink['checked_at'])
                            <span class="tomyum-panel__hint">· checked {!! timeago(Carbon\Carbon::parse($osuLink['checked_at'])) !!}</span>
                        @endif
                    </td>
                    <td>
                        <form method="POST" action="{{ route('panel.admin.users.unlink', ['user' => $userId]) }}" onsubmit="return confirm('Unlink their osu! account?')">
                            @csrf
                            <input type="hidden" name="service" value="osu">
                            <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-eraser"></i>Reset</button>
                        </form>
                    </td>
                @else
                    <td colspan="2" class="tomyum-panel__hint">not linked</td>
                @endif
            </tr>
            <tr>
                <td><i class="fab fa-lastfm"></i> Last.fm</td>
                @if ($links['lastFm'] !== null)
                    <td><a href="https://www.last.fm/user/{{ rawurlencode($links['lastFm']['username']) }}" rel="nofollow noreferrer" target="_blank">{{ $links['lastFm']['username'] }}</a>, linked {!! timeago(Carbon\Carbon::parse($links['lastFm']['linkedAt'])) !!}</td>
                    <td>
                        <form method="POST" action="{{ route('panel.admin.users.unlink', ['user' => $userId]) }}" onsubmit="return confirm('Unlink their Last.fm?')">
                            @csrf
                            <input type="hidden" name="service" value="lastfm">
                            <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-eraser"></i>Reset</button>
                        </form>
                    </td>
                @else
                    <td colspan="2" class="tomyum-panel__hint">not linked</td>
                @endif
            </tr>
        </table>
    @endif
</div>

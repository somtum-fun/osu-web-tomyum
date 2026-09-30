{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    Who did a staff log entry: the staff member, or for something a client did by itself (an import of a
    map lazer or the website asked about) the player and their client. Params: $entry, $actors (id => name).
--}}
@php
    $actorName = $actors[$entry['actorId']] ?? null;
    $via = $entry['via'] ?? null;
    $clients = [
        'lazer' => ['fa-gamepad', 'lazer'],
        'website' => ['fa-globe', 'website'],
        'multiplayer' => ['fa-users', 'multiplayer'],
    ];
@endphp
@if ($actorName !== null)
    <a href="{{ route('users.show', ['user' => $entry['actorId']]) }}">{{ $actorName }}</a>
@endif
@if ($via !== null && isset($clients[$via]))
    <span class="tomyum-panel__client" title="Imported by itself when the player's client asked for the map">
        <i class="fas {{ $clients[$via][0] }}"></i>{{ $clients[$via][1] }}
    </span>
@elseif ($actorName === null)
    @if (($entry['action'] ?? null) === 'beatmapset.import')
        <span class="tomyum-panel__client" title="Imported by itself when a player's client asked for the map">
            <i class="fas fa-magic"></i>a client
        </span>
    @else
        <span class="tomyum-panel__client"><i class="fas fa-server"></i>server</span>
    @endif
@endif

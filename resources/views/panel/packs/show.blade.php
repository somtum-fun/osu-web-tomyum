{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: one beatmap pack: its details, sets and download (packs panel). --}}
@extends('master', ['titlePrepend' => "Beatmap pack {$pack->tag}"])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.index'), 'title' => 'Staff panel'],
        ['url' => route('panel.packs.index'), 'title' => 'Beatmap packs'],
        ['title' => "{$pack->tag}: {$pack->name}"],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2>
                <i class="fas fa-box-open"></i>{{ $pack->tag }}: {{ $pack->name }}
                @if ($pack->hidden) <span class="tomyum-panel__pill tomyum-panel__pill--warn">hidden</span> @endif
            </h2>
            @if (!$pack->hidden)
                <p><a href="{{ route('packs.show', ['pack' => $pack->tag]) }}">See it on the packs page</a></p>
            @endif
            @include('panel.packs._form', ['pack' => $pack])
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-download"></i>Download</h2>
            @if ($pack->url === $builtUrl)
                <p class="tomyum-panel__hint">
                    The game server puts the zip together from the sets (from the beatmap mirror, without videos) the first time
                    someone asks for it, and keeps it until the sets change. Building it here first saves the first player the wait.
                    Link: <a href="{{ $builtUrl }}">{{ $builtUrl }}</a>
                </p>
                @if ($downloadError !== null)
                    <p class="tomyum-panel__muted">{{ $downloadError }}</p>
                @elseif ($download !== null)
                    <p>
                        @switch ($download['state'] ?? 'none')
                            @case ('ready')
                                <span class="tomyum-panel__pill tomyum-panel__pill--good">ready</span>
                                {{ number_format(($download['bytes'] ?? 0) / 1048576, 1) }} MB
                                @break
                            @case ('building')
                                <span class="tomyum-panel__pill tomyum-panel__pill--info">building</span>
                                {{ $download['done'] ?? 0 }} of {{ $download['total'] ?? 0 }} sets
                                @break
                            @case ('failed')
                                <span class="tomyum-panel__pill tomyum-panel__pill--bad">failed</span>
                                {{ $download['error'] ?? '' }}
                                @break
                            @default
                                <span class="tomyum-panel__pill">not built yet</span>
                        @endswitch
                    </p>
                @endif
                @if (($download['state'] ?? null) !== 'ready' && count($sets) > 0)
                    <form method="POST" action="{{ route('panel.packs.download', ['pack' => $pack->getKey()]) }}">
                        @csrf
                        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-file-archive"></i>Build it now</button>
                    </form>
                @endif
            @elseif (present($pack->url))
                <p>A link: <a href="{{ $pack->url }}">{{ $pack->url }}</a></p>
            @else
                <p class="tomyum-panel__muted">No download: the pack page shows the sets only.</p>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-plus"></i>Add sets</h2>
            <form method="POST" action="{{ route('panel.packs.sets.store', ['pack' => $pack->getKey()]) }}">
                @csrf
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Set ids or links, one per line (or separated by spaces)</span>
                    <textarea class="input-text" name="sets" required style="min-height: 100px; font-family: monospace" placeholder="https://osu.ppy.sh/beatmapsets/1234567"></textarea>
                </label>
                <div class="tomyum-panel__row">
                    <label class="tomyum-panel__check"><input type="checkbox" name="import" value="1" checked> Fetch missing sets from osu!</label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-plus"></i>Add</button>
                </div>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-music"></i>Sets ({{ count($sets) }})</h2>
            @if (count($sets) === 0)
                <p class="tomyum-panel__empty">None yet.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>Set</th><th>Mapper</th><th>Status</th><th></th></tr>
                    @foreach ($sets as $set)
                        <tr>
                            <td><a href="{{ route('beatmapsets.show', ['beatmapset' => $set->getKey()]) }}">{{ $set->artist }} - {{ $set->title }}</a> <span class="tomyum-panel__muted">#{{ $set->getKey() }}</span></td>
                            <td>{{ $set->creator }}</td>
                            <td>@include('panel._status', ['approved' => $set->approved])</td>
                            <td class="tomyum-panel__nowrap">
                                <form method="POST" action="{{ route('panel.packs.sets.destroy', ['pack' => $pack->getKey(), 'set' => $set->getKey()]) }}" style="display: inline">
                                    @csrf
                                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>

        <div class="tomyum-panel__card tomyum-panel__card--danger">
            <h2><i class="fas fa-trash"></i>Remove the pack</h2>
            <form method="POST" action="{{ route('panel.packs.destroy', ['pack' => $pack->getKey()]) }}" onsubmit="return confirm('Remove this pack for good? (The sets stay.)')">
                @csrf
                <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove pack</button>
            </form>
        </div>
    </div>
@endsection

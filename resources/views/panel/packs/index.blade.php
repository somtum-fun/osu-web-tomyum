{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: the beatmap packs panel (admins and the NAT). --}}
@extends('master', ['titlePrepend' => 'Beatmap packs'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.index'), 'title' => 'Staff panel'],
        ['title' => 'Beatmap packs'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-plus"></i>Make a pack</h2>
            <p class="tomyum-panel__hint">
                Packs are listed on the <a href="{{ route('packs.index') }}">beatmap packs page</a>, by kind (the tag's first letter).
                A new pack starts hidden: add its sets, then untick "Hidden".
            </p>
            @include('panel.packs._form', ['pack' => null])
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-box-open"></i>Packs</h2>
            <form method="GET" action="{{ route('panel.packs.index') }}" class="tomyum-panel__row" style="align-items: flex-end">
                <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 200px">
                    <span class="input-container__label">Kind</span>
                    <select class="input-text" name="type">
                        <option value="">All</option>
                        @foreach ($types as $kind => $letter)
                            <option value="{{ $kind }}" @if ($type === $kind) selected @endif>{{ osu_trans("beatmappacks.mode.{$kind}") }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Name or tag</span>
                    <input class="input-text" type="text" name="q" value="{{ $query }}">
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Find</button>
            </form>
            @if (count($packs) === 0)
                <p class="tomyum-panel__empty">None yet.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>Tag</th><th>Name</th><th>Sets</th><th>Mode</th><th>Date</th><th></th></tr>
                    @foreach ($packs as $pack)
                        <tr>
                            <td><code>{{ $pack->tag }}</code></td>
                            <td>
                                <a href="{{ route('panel.packs.show', ['pack' => $pack->getKey()]) }}">{{ $pack->name }}</a>
                                @if ($pack->hidden) <span class="tomyum-panel__pill tomyum-panel__pill--warn">hidden</span> @endif
                                @if (!present($pack->url)) <span class="tomyum-panel__pill tomyum-panel__pill--bad">no download</span> @endif
                            </td>
                            <td>{{ $pack->items_count }}</td>
                            <td>{{ $modes[$pack->playmode ?? ''] ?? $pack->playmode }}</td>
                            <td class="tomyum-panel__nowrap">{{ $pack->date?->toDateString() }}</td>
                            <td class="tomyum-panel__nowrap">
                                <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ route('panel.packs.show', ['pack' => $pack->getKey()]) }}">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </table>
                @include('objects._pagination_v2', ['object' => $packs])
            @endif
        </div>
    </div>
@endsection

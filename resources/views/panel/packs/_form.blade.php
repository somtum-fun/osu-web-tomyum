{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: a beatmap pack's details (packs panel). Params: $pack (null: a new one), $types, $modes. --}}
@php
    $typeOf = function ($tag) use ($types) {
        foreach ($types as $type => $letter) {
            if ($tag !== null && preg_match('/^'.preg_quote($letter, '/').'\d+$/', $tag) === 1) {
                return $type;
            }
        }

        return null;
    };
    $isBuilt = $pack === null || $pack->url === App\Http\Controllers\Panel\PacksController::builtUrl($pack->tag);
@endphp
<form method="POST" action="{{ route('panel.packs.store') }}">
    @csrf
    @if ($pack !== null)
        <input type="hidden" name="id" value="{{ $pack->getKey() }}">
    @endif
    <div class="tomyum-panel__row" style="flex-wrap: wrap">
        <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 180px">
            <span class="input-container__label">Kind</span>
            <select class="input-text" name="type">
                @foreach ($types as $type => $letter)
                    <option value="{{ $type }}" @if (($typeOf($pack?->tag) ?? 'standard') === $type) selected @endif>{{ osu_trans("beatmappacks.mode.{$type}") }} ({{ $letter }})</option>
                @endforeach
            </select>
        </label>
        <label class="input-container tomyum-panel__field" style="flex: 0 1 140px">
            <span class="input-container__label">Tag{{ $pack === null ? ' (empty: next)' : '' }}</span>
            <input class="input-text" type="text" name="tag" maxlength="5" value="{{ $pack?->tag }}" placeholder="S1">
        </label>
        <label class="input-container tomyum-panel__field">
            <span class="input-container__label">Name</span>
            <input class="input-text" type="text" name="name" maxlength="255" required value="{{ $pack?->name }}" placeholder="production Beatmap Pack #1">
        </label>
    </div>
    <div class="tomyum-panel__row" style="flex-wrap: wrap">
        <label class="input-container tomyum-panel__field" style="flex: 0 1 200px">
            <span class="input-container__label">Author (empty: you)</span>
            <input class="input-text" type="text" name="author" maxlength="255" value="{{ $pack?->author }}">
        </label>
        <label class="input-container tomyum-panel__field" style="flex: 0 1 200px">
            <span class="input-container__label">Date (UTC; empty: now)</span>
            <input class="input-text" type="text" name="date" value="{{ $pack?->date?->format('Y-m-d H:i') }}">
        </label>
        <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 160px">
            <span class="input-container__label">Mode</span>
            <select class="input-text" name="playmode">
                @foreach ($modes as $mode => $label)
                    <option value="{{ $mode }}" @if ((string) ($pack?->playmode ?? '') === (string) $mode) selected @endif>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>
    <div class="tomyum-panel__row" style="flex-wrap: wrap; align-items: flex-end">
        <label class="tomyum-panel__check"><input type="radio" name="download" value="built" @if ($isBuilt) checked @endif> Download built by the server (a zip of the sets)</label>
        <label class="tomyum-panel__check"><input type="radio" name="download" value="link" @if (!$isBuilt) checked @endif> A link:</label>
        <label class="input-container tomyum-panel__field">
            <input class="input-text" type="url" name="url" maxlength="1024" value="{{ $isBuilt ? '' : $pack?->url }}" placeholder="https://…/pack.zip (empty: no download)">
        </label>
    </div>
    <div class="tomyum-panel__row">
        <label class="tomyum-panel__check"><input type="checkbox" name="no_diff_reduction" value="1" @if ($pack?->no_diff_reduction) checked @endif> Challenge pack (no difficulty reduction mods)</label>
        <label class="tomyum-panel__check"><input type="checkbox" name="hidden" value="1" @if ($pack === null || $pack->hidden) checked @endif> Hidden (not listed yet)</label>
    </div>
    <div>
        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">{{ $pack === null ? 'Make pack' : 'Save' }}</button>
    </div>
</form>

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
{{-- TomYum: the fields on panel-packs__form's grid (three to a row), so each row has room below the last. --}}
<form method="POST" action="{{ route('panel.packs.store') }}" class="panel-packs__form">
    @csrf
    @if ($pack !== null)
        <input type="hidden" name="id" value="{{ $pack->getKey() }}">
    @endif
    <label class="input-container input-container--select">
        <span class="input-container__label">Kind</span>
        <select class="input-text" name="type">
            @foreach ($types as $type => $letter)
                <option value="{{ $type }}" @if (($typeOf($pack?->tag) ?? 'standard') === $type) selected @endif>{{ osu_trans("beatmappacks.mode.{$type}") }} ({{ $letter }})</option>
            @endforeach
        </select>
    </label>
    <label class="input-container">
        <span class="input-container__label">Tag{{ $pack === null ? ' (empty: next)' : '' }}</span>
        <input class="input-text" type="text" name="tag" maxlength="5" value="{{ $pack?->tag }}" placeholder="S1">
    </label>
    <label class="input-container">
        <span class="input-container__label">Name</span>
        <input class="input-text" type="text" name="name" maxlength="255" required value="{{ $pack?->name }}" placeholder="{{ App\Libraries\TomYumBranding::name() }} Beatmap Pack #1">
    </label>
    <label class="input-container">
        <span class="input-container__label">Author (empty: you)</span>
        <input class="input-text" type="text" name="author" maxlength="255" value="{{ $pack?->author }}">
    </label>
    <label class="input-container">
        <span class="input-container__label">Date (UTC; empty: now)</span>
        <input class="input-text" type="text" name="date" value="{{ $pack?->date?->format('Y-m-d H:i') }}" placeholder="{{ Carbon\Carbon::now()->format('Y-m-d H:i') }}">
    </label>
    <label class="input-container input-container--select">
        <span class="input-container__label">Mode</span>
        <select class="input-text" name="playmode">
            @foreach ($modes as $mode => $label)
                <option value="{{ $mode }}" @if ((string) ($pack?->playmode ?? '') === (string) $mode) selected @endif>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <div class="panel-packs__actions panel-packs__wide">
        <label class="tomyum-panel__check"><input type="radio" name="download" value="built" @if ($isBuilt) checked @endif> Download built by the server (a zip of the sets)</label>
        <label class="tomyum-panel__check"><input type="radio" name="download" value="link" @if (!$isBuilt) checked @endif> A link instead</label>
    </div>
    <label class="input-container panel-packs__wide">
        <span class="input-container__label">The link (with "A link instead"; empty: no download)</span>
        <input class="input-text" type="url" name="url" maxlength="1024" value="{{ $isBuilt ? '' : $pack?->url }}" placeholder="https://…/pack.zip">
    </label>
    <div class="panel-packs__actions panel-packs__wide">
        <label class="tomyum-panel__check"><input type="checkbox" name="no_diff_reduction" value="1" @if ($pack?->no_diff_reduction) checked @endif> Challenge pack (no difficulty reduction mods)</label>
        <label class="tomyum-panel__check"><input type="checkbox" name="hidden" value="1" @if ($pack === null || $pack->hidden) checked @endif> Hidden (not listed yet)</label>
        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas {{ $pack === null ? 'fa-plus' : 'fa-save' }}"></i>{{ $pack === null ? 'Make pack' : 'Save' }}</button>
    </div>
</form>

{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- A track's form: a new one ($track null) or an existing one. --}}
@php
    $length = $track === null ? '' : sprintf('%d:%02d', intdiv($track->length, 60), $track->length % 60);
@endphp
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="panel-artists__form">
    @csrf
    <label class="input-container panel-artists__wide">
        <span class="input-container__label">Title</span>
        <input class="input-text" type="text" name="title" required maxlength="255" value="{{ $track?->title }}">
    </label>
    <label class="input-container">
        <span class="input-container__label">Romanised title (optional)</span>
        <input class="input-text" type="text" name="title_romanized" maxlength="255" value="{{ $track?->title_romanized }}">
    </label>
    <label class="input-container">
        <span class="input-container__label">Version (optional: "Cut Ver.", "Remix"…)</span>
        <input class="input-text" type="text" name="version" maxlength="255" value="{{ $track?->version }}">
    </label>
    <label class="input-container">
        <span class="input-container__label">Album</span>
        <select class="input-text" name="album_id">
            <option value="">Single (no album)</option>
            @foreach ($albums as $option)
                <option value="{{ $option->getKey() }}" @selected(($track?->album_id ?? $albumId ?? null) === $option->getKey())>{{ $option->title }}</option>
            @endforeach
        </select>
    </label>
    <label class="input-container">
        <span class="input-container__label">Genre</span>
        <input class="input-text" type="text" name="genre" maxlength="255" value="{{ $track?->genre }}" placeholder="Electronic">
    </label>
    <div class="panel-artists__trio">
        <label class="input-container">
            <span class="input-container__label">BPM</span>
            <input class="input-text" type="number" name="bpm" required min="1" max="1000" step="0.01" value="{{ $track?->bpm }}">
        </label>
        <label class="input-container">
            <span class="input-container__label">Length</span>
            <input class="input-text" type="text" name="length" required pattern="\d+:[0-5]?\d|\d+" value="{{ $length }}" placeholder="3:05">
        </label>
        <label class="input-container">
            <span class="input-container__label">Order (optional)</span>
            <input class="input-text" type="number" name="display_order" value="{{ $track?->display_order }}">
        </label>
    </div>
    <label class="tomyum-panel__check">
        <input type="checkbox" name="exclusive" value="1" @checked($track?->exclusive ?? false)> Exclusive (made for osu!)
    </label>

    <p class="tomyum-panel__section-title panel-artists__wide">Files: an address, or upload one</p>
    @foreach ([
        ['preview', 'Preview (MP3)', 'audio/mpeg,audio/ogg,.mp3,.ogg', true],
        ['osz', 'Download (.osz template)', '.osz', true],
        ['cover_url', 'Cover (optional; the album\'s or artist\'s otherwise)', 'image/*', false],
    ] as [$field, $label, $accept, $required])
        @php($fileField = $field === 'cover_url' ? 'cover_file' : "{$field}_file")
        <label class="input-container">
            <span class="input-container__label">{{ $label }}</span>
            <input class="input-text" type="text" name="{{ $field }}" maxlength="512" value="{{ $track?->getRawOriginal($field) }}" placeholder="https://…">
        </label>
        <label class="tomyum-panel__field">
            …or upload
            <input type="file" name="{{ $fileField }}" accept="{{ $accept }}">
        </label>
    @endforeach

    <div class="panel-artists__buttons panel-artists__wide">
        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">{{ $track === null ? 'Add the track' : 'Save the track' }}</button>
    </div>
</form>

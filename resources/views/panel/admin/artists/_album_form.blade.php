{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- An album's form: a new one ($album null) or an existing one. --}}
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="panel-artists__form">
    @csrf
    <label class="input-container panel-artists__wide">
        <span class="input-container__label">Title</span>
        <input class="input-text" type="text" name="title" required maxlength="255" value="{{ $album?->title }}">
    </label>
    <label class="input-container">
        <span class="input-container__label">Romanised title (optional)</span>
        <input class="input-text" type="text" name="title_romanized" maxlength="255" value="{{ $album?->title_romanized }}">
    </label>
    <label class="input-container">
        <span class="input-container__label">Genre</span>
        <input class="input-text" type="text" name="genre" maxlength="255" value="{{ $album?->genre }}" placeholder="Electronic">
    </label>
    <label class="input-container">
        <span class="input-container__label">Cover: an image's address…</span>
        <input class="input-text" type="text" name="cover_url" maxlength="512" value="{{ $album?->cover_url }}" placeholder="https://…">
    </label>
    <label class="tomyum-panel__field">
        …or upload one (square)
        <input type="file" name="cover_file" accept="image/*">
    </label>
    <label class="tomyum-panel__check">
        <input type="checkbox" name="visible" value="1" @checked($album?->visible ?? true)> Shown on the artist's page
    </label>
    <div class="panel-artists__buttons panel-artists__wide">
        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">{{ $album === null ? 'Add the album' : 'Save the album' }}</button>
    </div>
</form>

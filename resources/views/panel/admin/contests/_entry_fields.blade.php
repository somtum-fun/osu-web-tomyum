{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: an entry's fields (contests panel), for a new one ($entry null) or an existing one. --}}
<label class="input-container">
    <span class="input-container__label">Name</span>
    <input class="input-text" type="text" name="name" maxlength="255" required value="{{ $entry?->name }}">
</label>
<label class="input-container">
    <span class="input-container__label">Shown as</span>
    <input class="input-text" type="text" name="masked_name" maxlength="255" value="{{ $entry?->masked_name }}" placeholder="empty: Entry #n">
</label>
<label class="input-container">
    <span class="input-container__label">Made by (optional)</span>
    <input class="input-text" type="text" name="user" value="{{ $entry?->user?->username }}" placeholder="a player's name">
</label>
<label class="input-container">
    <span class="input-container__label">Link</span>
    <input class="input-text" type="text" name="entry_url" maxlength="255" value="{{ $entry?->entry_url }}" placeholder="https://…">
</label>
<label class="input-container">
    <span class="input-container__label">Thumbnail (art; optional)</span>
    <input class="input-text" type="text" name="thumbnail_url" maxlength="255" value="{{ $entry?->thumbnail_url }}" placeholder="https://…">
</label>

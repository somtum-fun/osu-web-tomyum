{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: a contest's settings (contests panel). Params: $contest (null: a new one), $types, $thumbnailShapes, $linkIcons, $otherOptions. --}}
@php
    $options = $contest?->extra_options ?? [];
    $date = fn ($field) => $contest?->$field?->format('Y-m-d H:i');
@endphp
<form method="POST" action="{{ route('panel.admin.contests.store') }}">
    @csrf
    @if ($contest !== null)
        <input type="hidden" name="id" value="{{ $contest->getKey() }}">
    @endif
    <div class="tomyum-panel__row" style="flex-wrap: wrap">
        <label class="input-container tomyum-panel__field">
            <span class="input-container__label">Name</span>
            <input class="input-text" type="text" name="name" maxlength="255" required value="{{ $contest?->name }}">
        </label>
        <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 180px">
            <span class="input-container__label">Kind</span>
            <select class="input-text" name="type">
                @foreach ($types as $type => $label)
                    <option value="{{ $type }}" @if (($contest?->type ?? 'art') === $type) selected @endif>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>
    <label class="input-container tomyum-panel__field">
        <span class="input-container__label">Header image (a link, e.g. from Admin › Assets; optional)</span>
        <input class="input-text" type="url" name="header_url" maxlength="255" value="{{ $contest?->header_url }}" placeholder="https://…/header.jpg">
    </label>
    <label class="input-container tomyum-panel__field">
        <span class="input-container__label">Description while entries are open (markdown)</span>
        <textarea class="input-text" name="description_enter" style="min-height: 120px">{{ $contest?->description_enter }}</textarea>
    </label>
    <label class="input-container tomyum-panel__field">
        <span class="input-container__label">Description while voting (markdown; empty: the one above)</span>
        <textarea class="input-text" name="description_voting" style="min-height: 100px">{{ $contest?->description_voting }}</textarea>
    </label>
    <p class="tomyum-panel__hint">Dates in UTC, like {{ Carbon\Carbon::now()->format('Y-m-d H:i') }}. Empty dates keep that phase closed.</p>
    <div class="tomyum-panel__row" style="flex-wrap: wrap">
        @foreach (['entry_starts_at' => 'Entries open', 'entry_ends_at' => 'Entries close', 'voting_starts_at' => 'Voting opens', 'voting_ends_at' => 'Voting closes'] as $field => $label)
            <label class="input-container tomyum-panel__field" style="flex: 0 1 180px">
                <span class="input-container__label">{{ $label }}</span>
                <input class="input-text" type="text" name="{{ $field }}" value="{{ $date($field) }}">
            </label>
        @endforeach
        <label class="input-container tomyum-panel__field" style="flex: 0 1 140px">
            <span class="input-container__label">Entries per player</span>
            <input class="input-text" type="number" name="max_entries" min="0" max="127" value="{{ $contest?->max_entries ?? 1 }}">
        </label>
        <label class="input-container tomyum-panel__field" style="flex: 0 1 140px">
            <span class="input-container__label">Votes per player</span>
            <input class="input-text" type="number" name="max_votes" min="0" max="127" value="{{ $contest?->max_votes ?? 3 }}">
        </label>
    </div>

    <h3>Options</h3>
    <div class="tomyum-panel__row" style="flex-wrap: wrap; gap: 4px 14px">
        <label class="tomyum-panel__check"><input type="checkbox" name="visible" value="1" @if ($contest?->visible) checked @endif> Visible (listed on the contests page)</label>
        <label class="tomyum-panel__check"><input type="checkbox" name="show_votes" value="1" @if ($contest?->show_votes) checked @endif> Show the results</label>
        <label class="tomyum-panel__check"><input type="checkbox" name="judged" value="1" @if ($options['judged'] ?? false) checked @endif> Judged (judges score entries instead of votes)</label>
        <label class="tomyum-panel__check"><input type="checkbox" name="show_judges" value="1" @if ($options['show_judges'] ?? true) checked @endif> Show the judges</label>
        <label class="tomyum-panel__check"><input type="checkbox" name="is_score_standardised" value="1" @if ($options['is_score_standardised'] ?? false) checked @endif> Standardised judge scores</label>
        <label class="tomyum-panel__check"><input type="checkbox" name="unmasked" value="1" @if ($options['unmasked'] ?? false) checked @endif> Show entries' real names while voting</label>
        <label class="tomyum-panel__check"><input type="checkbox" name="show_names" value="1" @if ($options['show_names'] ?? false) checked @endif> Show names next to "Entry #n"</label>
        <label class="tomyum-panel__check"><input type="checkbox" name="show_entry_user" value="1" @if ($options['show_entry_user'] ?? false) checked @endif> Show who made each entry while voting</label>
        <label class="tomyum-panel__check"><input type="checkbox" name="submitted_beatmaps" value="1" @if ($options['submitted_beatmaps'] ?? false) checked @endif> Entries are beatmap sets on the site</label>
    </div>
    <p class="tomyum-panel__hint">Votes stay anonymous only with the three "show" options for entries left off.</p>
    <div class="tomyum-panel__row" style="flex-wrap: wrap">
        <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 180px">
            <span class="input-container__label">Thumbnails</span>
            <select class="input-text" name="thumbnail_shape">
                @foreach ($thumbnailShapes as $value => $label)
                    <option value="{{ $value }}" @if (($options['thumbnail_shape'] ?? '') === $value) selected @endif>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 180px">
            <span class="input-container__label">Entry link icon</span>
            <select class="input-text" name="link_icon">
                @foreach ($linkIcons as $value => $label)
                    <option value="{{ $value }}" @if (($options['link_icon'] ?? '') === $value) selected @endif>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label class="input-container tomyum-panel__field" style="flex: 0 1 140px">
            <span class="input-container__label">Image width (px)</span>
            <input class="input-text" type="number" name="forced_width" min="1" value="{{ $options['forced_width'] ?? '' }}">
        </label>
        <label class="input-container tomyum-panel__field" style="flex: 0 1 140px">
            <span class="input-container__label">Image height (px)</span>
            <input class="input-text" type="number" name="forced_height" min="1" value="{{ $options['forced_height'] ?? '' }}">
        </label>
        <label class="input-container tomyum-panel__field" style="flex: 0 1 180px">
            <span class="input-container__label">File types (empty: by kind)</span>
            <input class="input-text" type="text" name="allowed_extensions" value="{{ implode(', ', $options['allowed_extensions'] ?? []) }}" placeholder="jpg, png">
        </label>
        <label class="input-container tomyum-panel__field" style="flex: 0 1 140px">
            <span class="input-container__label">Largest file (MB)</span>
            <input class="input-text" type="number" name="max_filesize_mb" min="0" step="0.1" value="{{ isset($options['max_filesize']) ? round($options['max_filesize'] / 1048576, 1) : '' }}">
        </label>
    </div>
    <label class="input-container tomyum-panel__field">
        <span class="input-container__label">Other options (JSON, as osu! has them: best_of, requirement, children, …)</span>
        <textarea class="input-text" name="extra_options_json" style="min-height: 60px; font-family: monospace">{{ count($otherOptions) === 0 ? '' : json_encode($otherOptions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea>
    </label>
    <div>
        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">{{ $contest === null ? 'Make contest' : 'Save' }}</button>
    </div>
</form>

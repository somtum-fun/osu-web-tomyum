{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: the offense (it sets the appeal cooldown) and a date to use instead. Params: $optional (no offense allowed). --}}
<label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 260px">
    <span class="input-container__label">Offense (sets when they may appeal)</span>
    <select class="input-text" name="offense">
        @if ($optional ?? false)
            <option value="">No new offense</option>
        @endif
        <option value="cheating">Cheating: 3 months, 12 the second time</option>
        <option value="multi">Multi-accounting: 2 months</option>
        <option value="sharing">Account sharing: 2 months</option>
        <option value="other" @if (!($optional ?? false)) selected @endif>Something else: 3 months</option>
    </select>
</label>
<label class="input-container tomyum-panel__field" style="flex: 0 1 170px">
    <span class="input-container__label">Or appeal from (optional)</span>
    <input class="input-text" type="date" name="appeal_after">
</label>

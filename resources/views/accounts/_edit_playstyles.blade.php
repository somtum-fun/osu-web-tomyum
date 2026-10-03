{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
<div class="account-edit">
    <div class="account-edit__section">
        <h2 class="account-edit__section-title">
            {{ osu_trans('accounts.playstyles.title') }}
        </h2>
    </div>

    <div class="account-edit__input-groups">
        <div class="account-edit__input-group">
            <div class="account-edit-entry account-edit-entry--no-label">
                <form
                    class="account-edit-entry__checkboxes js-account-edit js-account-edit-auto-submit"
                    data-account-edit-type="array"
                    data-url="{{ route('account.options') }}"
                    data-field="user[osu_playstyle]"
                >
                    @php
                        $userPlaystyles = new Ds\Set(auth()->user()->osu_playstyle ?? []);
                    @endphp
                    @foreach (App\Models\User::PLAYSTYLES as $key => $_value)
                        <label class="account-edit-entry__checkbox">
                            @include('objects._switch', ['locals' => [
                                'checked' => $userPlaystyles->contains($key),
                                'value' => $key,
                            ]])

                            <span class="account-edit-entry__checkbox-label">
                                {{ osu_trans("accounts.playstyles.{$key}") }}
                            </span>
                        </label>
                    @endforeach

                    <div class="account-edit-entry__checkbox-status">
                        @include('accounts._edit_entry_status', ['modifiers' => ['left']])
                    </div>
                </form>
            </div>
        </div>

        <div class="account-edit__input-group">
            <div class="account-edit-entry account-edit-entry--no-label">
                <div class="account-edit-entry__checkboxes-label">
                    {{ osu_trans('accounts.playstyles.default_ruleset') }}
                </div>
                <form
                    class="account-edit-entry__checkboxes js-account-edit js-account-edit-auto-submit"
                    data-account-edit-type="radio"
                    data-url="{{ route('account.options') }}"
                    data-field="user[playmode]"
                >
                    @php
                        // TomYum: Relax/Autopilot can be the default mode too.
                        $currentDefault = App\Libraries\TomYumModeScores::defaultVariant(auth()->user()) ?? default_mode();
                        $defaultChoices = [];
                        foreach (App\Models\Beatmap::MODES as $ruleset => $_rulesetId) {
                            $defaultChoices[$ruleset] = osu_trans("beatmaps.mode.{$ruleset}");
                        }
                        foreach (App\Libraries\TomYumModeScores::MODES as $variant => [$ruleset]) {
                            $defaultChoices[$variant] = osu_trans("beatmaps.mode.{$ruleset}").(str_ends_with($variant, 'ap') ? ' Autopilot' : ' Relax');
                        }
                    @endphp
                    @foreach ($defaultChoices as $choice => $label)
                        <label class="account-edit-entry__checkbox">
                            @include('objects._switch', ['locals' => [
                                'checked' => $choice === $currentDefault,
                                'name' => 'user[playmode]',
                                'type' => 'radio',
                                'value' => $choice,
                            ]])

                            <span class="account-edit-entry__checkbox-label">
                                {{ $label }}
                            </span>
                        </label>
                    @endforeach

                    <div class="account-edit-entry__checkbox-status">
                        @include('accounts._edit_entry_status', ['modifiers' => ['left']])
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}

<ul class="game-mode">
    @foreach (App\Models\Beatmap::MODES as $ruleset => $_rulesetId)
        <li>
            <a
                class="{{ class_with_modifiers('game-mode-link', ['active' => $ruleset === $currentRuleset]) }}"
                href="{{ $urlFn($ruleset) }}"
            >
                <span
                    class="fal fa-extra-mode-{{ $ruleset }}"
                    title="{{ osu_trans("beatmaps.mode.{$ruleset}") }}"
                ></span>
                @if (isset($defaultRuleset) && $ruleset === $defaultRuleset)
                    <span
                      class="game-mode-link__icon"
                      title="{{ osu_trans('users.show.edit.default_playmode.is_default_tooltip') }}"
                    >
                      <span class="fas fa-star"></span>
                    </span>
                @endif
            </a>
        </li>
    @endforeach
    {{-- TomYum: custom lazer rulesets (a page that has them passes $customRulesetLinks: [name, url]). --}}
    @if (!empty($customRulesetLinks))
        <li>
            <details class="game-mode-link" style="position: relative">
                <summary style="cursor: pointer; list-style: none" title="Custom lazer rulesets, played in TumThai">
                    <span class="fas fa-ellipsis-h"></span>
                </summary>
                <div style="position: absolute; right: 0; z-index: 10; min-width: 160px; padding: 6px 0; border-radius: 6px; background: hsl(var(--hsl-b3, 333 18% 15%)); box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4)">
                    @foreach ($customRulesetLinks as [$name, $url])
                        <a href="{{ $url }}" style="display: block; padding: 4px 14px; white-space: nowrap">{{ $name }}</a>
                    @endforeach
                </div>
            </details>
        </li>
    @endif
</ul>

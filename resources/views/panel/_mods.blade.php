{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    TomYum: mods as the website's mod icons (App\Libraries\TomYumModIcons): $mods is a list of
    acronyms, lazer's mod JSON or a stable mod number; $ruleset (optional) picks the mods' colours;
    $empty is what shows without any (default "no mods").
--}}
@php
    $acronyms = App\Libraries\TomYumModIcons::acronyms($mods ?? null);
@endphp
@if (count($acronyms) === 0)
    <span class="tomyum-panel__muted">{{ $empty ?? 'no mods' }}</span>
@else
    <span class="tomyum-panel__mods">
        @foreach ($acronyms as $acronym)
            <span class="mod mod--type-{{ App\Libraries\TomYumModIcons::type($acronym, (int) ($ruleset ?? 0)) }}" title="{{ $acronym }}">
                <span class="mod__icon mod__icon--{{ $acronym }}" data-acronym="{{ $acronym }}"></span>
            </span>
        @endforeach
    </span>
@endif

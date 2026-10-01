{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    use App\Transformers\UserCompactTransformer;
@endphp
{{-- TomYum: the search design's player list (list player cards, with their level). --}}
<div class="js-react"
    data-modifiers="search"
    data-react="user-cards"
    data-view-mode="list"
    data-users="{{ json_encode(json_collection(
        $search->data()->loadMissing(UserCompactTransformer::listIncludesPreload('osu')),
        (new UserCompactTransformer())->setMode('osu'),
        UserCompactTransformer::LIST_INCLUDES,
    )) }}"
></div>

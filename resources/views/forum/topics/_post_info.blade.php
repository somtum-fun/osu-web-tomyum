{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    TomYum: the poster's card beside a forum post, as osu!'s player card design draws it ("Player Card,
    Forum Panel", 180 × 240): their cover behind it in their profile colour; the avatar, name and
    groups at the top; their supporter pill, flags, post count and a menu at the bottom.
--}}
@php
    $visible = $user->hasProfileVisible();
    $groups = $user->userGroupsForBadges();
    $mainGroup = $groups->first()?->group;
    $otherGroups = $groups->slice(1)->map(fn ($userGroup) => $userGroup->group)->filter();
    $cover = $visible ? $user->cover()->url() : null;
    $hue = $user->user_style;
    $menuId = 'forum-post-user-'.($post->getKey() ?? $user->getKey());
@endphp
<div
    class="forum-post-info forum-post-info--card"
    style="{{ $hue !== null ? "--profile-hue: {$hue};" : '' }}{{ $cover !== null ? " --cover: url('{$cover}');" : '' }}"
>
    <div class="forum-post-info__card">
        <div class="forum-post-info__top">
            @if ($visible)
                <a
                    href="{{ route('users.show', $user) }}"
                    class="forum-post-info__row forum-post-info__row--avatar avatar avatar--forum-card"
                    style="background-image: url('{{ $user->user_avatar }}');"
                ></a>

                <a
                    class="forum-post-info__row forum-post-info__row--username js-usercard"
                    data-user-id="{{ $user->user_id }}"
                    href="{{ route('users.show', $user) }}"
                >{{ $user->username }}</a>

                @php
                    $title = $user->title() ?? App\Libraries\User\UserForumTitles::get($user);
                @endphp
                @if ($title !== null)
                    @if ($user->titleUrl() !== null)
                        <a class="forum-post-info__row forum-post-info__row--title" href="{{ $user->titleUrl() }}">{{ $title }}</a>
                    @else
                        <div class="forum-post-info__row forum-post-info__row--title">{{ $title }}</div>
                    @endif
                @endif
            @else
                <span class="forum-post-info__row forum-post-info__row--username">{{ $user->username }}</span>
            @endif

            @if ($mainGroup !== null)
                <div class="forum-post-info__row forum-post-info__row--groups">
                    {{-- Their group's short name in its colour, and their other groups as dots in a second pill. --}}
                    <a
                        class="forum-post-info__group"
                        style="{!! css_group_colour($mainGroup) !!}"
                        title="{{ $mainGroup->group_name }}"
                        @if ($mainGroup->hasListing()) href="{{ route('groups.show', $mainGroup->getKey()) }}" @endif
                    >{{ $mainGroup->short_name }}</a>
                    @if ($otherGroups->isNotEmpty())
                        <span class="forum-post-info__group" title="{{ $otherGroups->pluck('group_name')->implode(', ') }}">
                            @foreach ($otherGroups as $otherGroup)
                                <span class="forum-post-info__group-dot" style="{!! css_group_colour($otherGroup) !!}"></span>
                            @endforeach
                        </span>
                    @endif
                </div>
            @endif
        </div>

        <div class="forum-post-info__bottom">
            @if ($visible && $user->supportLevel() > 0)
                <a class="forum-post-info__row forum-post-info__row--supporter" href="{{ route('support-the-game') }}" title="{{ osu_trans('users.show.is_supporter') }}">
                    @for ($i = 0; $i < $user->supportLevel(); $i++)<span class="fas fa-heart"></span>@endfor
                </a>
            @endif

            @if ($user->country !== null || $user->team !== null)
                <div class="forum-post-info__row forum-post-info__row--flag">
                    @if ($user->country !== null)
                        <a href="{{ route('rankings', [
                            'mode' => default_mode(),
                            'type' => 'performance',
                            'country' => $user->country->getKey(),
                        ]) }}">
                            @include('objects._flag_country', ['country' => $user->country])
                        </a>
                    @endif
                    @if (($team = $user->team) !== null)
                        <a href="{{ route('teams.show', $team) }}">
                            @include('objects._flag_team', compact('team'))
                        </a>
                    @endif
                </div>
            @endif

            @if ($user->getKey() !== null)
                <a class="forum-post-info__row forum-post-info__row--posts" href="{{ route('users.posts', $user) }}">
                    {{ osu_trans_choice('forum.post.info.post_count', $user->user_posts) }}
                    @if ($user->user_regdate !== null)
                        <br>since {{ $user->user_regdate->year }}
                    @endif
                </a>

                <div class="forum-post-info__row forum-post-info__row--menu u-relative">
                    <button
                        type="button"
                        class="forum-post-info__menu-button js-menu"
                        data-menu-target="{{ $menuId }}"
                        title="More"
                    >
                        <span class="fas fa-ellipsis-h"></span>
                    </button>
                    <div
                        class="js-menu simple-menu forum-post-info__menu"
                        data-menu-id="{{ $menuId }}"
                        data-visibility="hidden"
                    >
                        <a class="simple-menu__item" href="{{ route('users.show', $user) }}">Profile</a>
                        <a class="simple-menu__item" href="{{ route('chat.index', ['sendto' => $user->getKey()]) }}">Send message</a>
                        <a class="simple-menu__item" href="{{ route('users.posts', $user) }}">Their posts</a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

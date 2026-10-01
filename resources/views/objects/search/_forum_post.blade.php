{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    TomYum: a forum result as in osu!'s search design proposal: a line down its side, the topic and
    the post's text (the words searched for marked), then the forum as a black pill, who posted it
    (avatar and name), when, and the link.
--}}
@php
    $userLink = $user->user_id !== null ? route('users.show', $user) : null;
@endphp

<div class="search-forum-post">
    <a class="search-forum-post__link" href="{{ $link }}"></a>
    <div class="search-forum-post__content">
        @if (isset($title))
            <div class="search-forum-post__text search-forum-post__text--title">
                <span class="search-highlight">
                    {{ $title }}
                </span>
            </div>
        @endif
        <div class="search-forum-post__text search-forum-post__text--excerpt">
            <span class="search-highlight">
                {{ $excerpt }}
            </span>
        </div>
        <div class="search-forum-post__footer">
            <a class="search-forum-post__forum" href="{{ route('forum.forums.show', ['forum' => $topic->forum_id]) }}">
                <span class="search-forum-post__forum-label">Forum</span>
                {{ $topic->forum?->forum_name }}
            </a>
            <a class="search-forum-post__poster js-usercard"
               @if ($userLink !== null) href="{{ $userLink }}" @endif
               data-user-id="{{ $user->user_id }}"
            >
                <img class="search-forum-post__avatar-image" src="{{ $user->user_avatar }}" alt="">
                <span>by <strong>{{ $user->username }}</strong></span>
            </a>
            <time class="search-forum-post__time js-timeago" datetime="{{ $time }}">
                {{ $time }}
            </time>
            <a class="search-forum-post__url" href="{{ $link }}">{{ $link }}</a>
        </div>
    </div>
</div>

{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: maps nobody can ask to be Loved, and why (LovedController@blacklist); the Loved team changes it. --}}
@php
    $loveStatus = session('loved_status');
@endphp
@extends('master', ['titlePrepend' => 'Blacklist'])

@section('content')
    @include('loved._header', ['current' => 'blacklist'])

    <div class="osu-page osu-page--generic-compact tomyum-loved">
        @if ($loveStatus !== null)
            <div class="{{ class_with_modifiers('tomyum-loved__alert', [$loveStatus['type']]) }}">{{ $loveStatus['message'] }}</div>
        @endif

        <div class="page-extra">
            <h2 class="title title--page-extra">Blacklist</h2>
            <p class="tomyum-loved__muted">
                Maps nobody can ask to be Loved: inappropriate content, the mapper said no, and so on.
            </p>

            @if ($isTeam)
                <form method="POST" action="{{ route('loved.blacklist.store') }}" class="tomyum-loved__form">
                    @csrf
                    <div class="tomyum-loved__form-row">
                        <label class="input-container tomyum-loved__form-set">
                            <span class="input-container__label">Beatmap set (link or id)</span>
                            <input class="input-text" type="text" name="beatmapset" required value="{{ old('beatmapset') }}">
                        </label>
                    </div>
                    <label class="input-container">
                        <span class="input-container__label">Reason (players see it)</span>
                        <input class="input-text" type="text" name="reason" required minlength="3" maxlength="500" value="{{ old('reason') }}">
                    </label>
                    <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-ban"></i> Blacklist it</button></div>
                </form>
            @endif

            @if ($entries->isEmpty())
                <p class="tomyum-loved__muted">No maps are blacklisted.</p>
            @else
                <ul class="tomyum-loved__declined">
                    @foreach ($entries as $entry)
                        @php
                            $set = $sets[$entry->beatmapset_id] ?? null;
                            $by = $users[$entry->user_id] ?? null;
                        @endphp
                        <li>
                            <a href="{{ route('beatmapsets.show', ['beatmapset' => $entry->beatmapset_id]) }}">
                                {{ $set === null ? "Set {$entry->beatmapset_id}" : "{$set->artist} - {$set->title}" }}
                            </a>
                            <span class="tomyum-loved__muted">
                                · {{ $entry->reason }}
                                @if ($by !== null)
                                    ({{ $by->username }}{!! $entry->created_at === null ? '' : ', '.timeago(\Carbon\Carbon::parse($entry->created_at)) !!})
                                @endif
                            </span>
                            @if ($isTeam)
                                <form method="POST" action="{{ route('loved.blacklist.destroy', ['beatmapset' => $entry->beatmapset_id]) }}" style="display: inline">
                                    @csrf
                                    <button type="submit" class="tomyum-loved-review__remove" data-confirm="Take this map off the blacklist?">Remove</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endsection

{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    TomYum: a Loved request (LovedController@show): the map, why it was asked for, the reviews (and
    the player's own), and the Loved team's decision (bem/tomyum-loved.less).
--}}
@php
    use App\Libraries\TomYumLoved;
    use App\Models\Beatmap;

    $loveStatus = session('loved_status');
    $user = Auth::user();
    $set = $request->beatmapset;
    $modeName = TomYumLoved::MODE_NAMES[$request->ruleset_id];
    $title = $set === null ? "Set {$request->beatmapset_id}" : "{$set->artist} - {$set->title}";
    $for = $reviews->where('score', '>', 0)->count() + 1;
    $against = $reviews->where('score', '<', 0)->count();
    $statusLabels = ['open' => 'Open', 'loved' => 'Loved', 'rejected' => 'Rejected', 'removed' => 'Removed'];
    $cannotTakePart = $user === null ? null : TomYumLoved::cannotTakePart($user);
    $userLink = fn ($id, $name) => '<a class="js-usercard" data-user-id="'.e($id).'" href="'.e(route('users.show', ['user' => $id])).'">'.e($name).'</a>';
@endphp
@extends('master', ['titlePrepend' => $title])

@section('content')
    @include('loved._header', ['current' => $request->status])

    <div class="osu-page osu-page--generic-compact tomyum-loved">
        @if ($loveStatus !== null)
            <div class="{{ class_with_modifiers('tomyum-loved__alert', [$loveStatus['type']]) }}">{{ $loveStatus['message'] }}</div>
        @endif

        <div class="page-extra">
            <div class="tomyum-loved__section-head">
                <h2 class="title title--page-extra">{{ $title }}</h2>
                <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ route('loved.index', ['mode' => Beatmap::modeStr($request->ruleset_id)]) }}">
                    <i class="fas fa-angle-left"></i> All requests
                </a>
            </div>
            <div class="tomyum-loved__status">
                <span class="tomyum-loved__status-item">
                    <span class="fal fa-extra-mode-{{ Beatmap::modeStr($request->ruleset_id) }}"></span> {{ $modeName }}
                </span>
                <span class="{{ class_with_modifiers('tomyum-loved__status-item', ['loved' => $request->status === 'loved', 'live' => $request->status === 'open']) }}">
                    {{ $statusLabels[$request->status] }}
                </span>
                <span class="tomyum-loved__status-item">
                    <strong>{{ $request->score > 0 ? '+' : '' }}{{ $request->score }}</strong> · {{ $for }} for · {{ $against }} against
                </span>
                @if ($blacklisted !== null)
                    <span class="tomyum-loved__status-item">Blacklisted: {{ $blacklisted->reason }}</span>
                @endif
            </div>

            <div class="tomyum-loved-entry">
                @if ($beatmapsetJson !== null)
                    <div
                        class="js-react u-contents"
                        data-beatmapset-panel="{{ json_encode(['beatmapset' => $beatmapsetJson]) }}"
                        data-react="beatmapset-panel"
                    >
                        <div class="beatmapset-panel beatmapset-panel--size-normal"></div>
                    </div>
                @endif
                <div class="tomyum-loved-entry__body">
                    <div class="tomyum-loved-entry__writeup">
                        <div class="tomyum-loved-entry__captain">
                            Asked by
                            {!! $request->user === null ? 'someone' : $userLink($request->user_id, $request->user->username) !!}
                            {!! timeago($request->created_at) !!}
                        </div>
                        <div class="tomyum-loved-entry__description">{{ $request->reason }}</div>
                    </div>
                </div>
            </div>

            @if ($request->status !== 'open')
                <div class="tomyum-loved-decision">
                    <div class="tomyum-loved-decision__head">
                        {{ $statusLabels[$request->status] }}
                        @if ($request->handler !== null)
                            by {!! $userLink($request->handled_by, $request->handler->username) !!}
                        @endif
                        @if ($request->decided_at !== null)
                            {!! timeago($request->decided_at) !!}
                        @endif
                    </div>
                    @if (present($request->decision_reason))
                        <div class="tomyum-loved-decision__body">{{ $request->decision_reason }}</div>
                    @endif
                </div>
            @endif
        </div>

        <div class="page-extra">
            <h2 class="title title--page-extra">Reviews</h2>
            @if ($reviews->isEmpty())
                <p class="tomyum-loved__muted">No reviews yet.</p>
            @else
                <div class="tomyum-loved-reviews">
                    @foreach ($reviews as $review)
                        <div class="tomyum-loved-review">
                            <div class="tomyum-loved-review__head">
                                <span class="{{ class_with_modifiers('tomyum-loved-review__score', [$review->score > 0 ? 'for' : 'against']) }}">
                                    <i class="fas fa-thumbs-{{ $review->score > 0 ? 'up' : 'down' }}"></i> {{ $review->score > 0 ? 'For' : 'Against' }}
                                </span>
                                {!! $review->user === null ? 'someone' : $userLink($review->user_id, $review->user->username) !!}
                                <span class="tomyum-loved__muted">{!! timeago($review->updated_at) !!}</span>
                            </div>
                            @if (present($review->comment))
                                <div class="tomyum-loved-review__body">{{ $review->comment }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($request->isOpen())
                <h3 class="tomyum-loved-review__title">Your review</h3>
                @if ($user === null)
                    <p><a class="js-user-link" href="#">Sign in</a> to say whether this map should be Loved.</p>
                @elseif ($request->user_id === $user->getKey())
                    <p class="tomyum-loved__muted">You asked for this map: your reason counts as your review.</p>
                @elseif ($cannotTakePart !== null)
                    <p class="tomyum-loved__muted">{{ $cannotTakePart }}</p>
                @else
                    <form method="POST" action="{{ route('loved.requests.review', ['request' => $request->getKey()]) }}" class="tomyum-loved__form">
                        @csrf
                        <label class="input-container">
                            <span class="input-container__label">Why?</span>
                            <textarea class="input-text" name="comment" rows="3" required minlength="3" maxlength="1000">{{ old('comment', $mine?->comment) }}</textarea>
                        </label>
                        <div class="tomyum-loved-review__buttons">
                            <button type="submit" name="score" value="1"
                                class="{{ class_with_modifiers('tomyum-loved-entry__vote', ['support' => true, 'yes' => true, 'active' => $mine?->score === 1]) }}">
                                <i class="fas fa-thumbs-up"></i> For
                            </button>
                            <button type="submit" name="score" value="-1"
                                class="{{ class_with_modifiers('tomyum-loved-entry__vote', ['support' => true, 'no' => true, 'active' => $mine?->score === -1]) }}">
                                <i class="fas fa-thumbs-down"></i> Against
                            </button>
                        </div>
                    </form>
                    @if ($mine !== null)
                        <form method="POST" action="{{ route('loved.requests.review.destroy', ['request' => $request->getKey()]) }}">
                            @csrf
                            <button type="submit" class="tomyum-loved-review__remove">Remove my review</button>
                        </form>
                    @endif
                @endif
            @endif
        </div>

        @if ($canDecide)
            <div class="page-extra">
                <h2 class="title title--page-extra">Loved team</h2>
                <form method="POST" action="{{ route('loved.requests.decide', ['request' => $request->getKey()]) }}" class="tomyum-loved__form">
                    @csrf
                    <input type="hidden" name="beatmapset" value="{{ $request->beatmapset_id }}">
                    <label class="input-container">
                        <span class="input-container__label">Reason (players see it)</span>
                        <textarea class="input-text" name="reason" rows="2" maxlength="2000">{{ old('reason') }}</textarea>
                    </label>
                    <div class="tomyum-loved-review__buttons">
                        @if ($request->isOpen())
                            <button type="submit" name="action" value="love" class="tomyum-loved-entry__vote tomyum-loved-entry__vote--support tomyum-loved-entry__vote--yes tomyum-loved-entry__vote--active"
                                data-confirm="Make its {{ $modeName }} difficulties Loved now?">
                                <i class="fas fa-heart"></i> Love it
                            </button>
                            <button type="submit" name="action" value="reject" class="tomyum-loved-entry__vote tomyum-loved-entry__vote--support tomyum-loved-entry__vote--no tomyum-loved-entry__vote--active">
                                <i class="fas fa-times"></i> Reject
                            </button>
                            <button type="submit" name="action" value="remove" class="tomyum-loved-entry__vote tomyum-loved-entry__vote--support">
                                <i class="fas fa-trash"></i> Remove from the list
                            </button>
                        @elseif ($request->status !== 'loved')
                            <button type="submit" name="action" value="reopen" class="tomyum-loved-entry__vote tomyum-loved-entry__vote--support">
                                <i class="fas fa-undo"></i> Open again
                            </button>
                        @endif
                        @if ($blacklisted === null)
                            <button type="submit" formaction="{{ route('loved.blacklist.store') }}" class="tomyum-loved-entry__vote tomyum-loved-entry__vote--support"
                                data-confirm="Blacklist this map? Its open requests are removed and nobody can ask for it again.">
                                <i class="fas fa-ban"></i> Blacklist the map
                            </button>
                        @endif
                    </div>
                    <p class="tomyum-loved__muted">
                        Loving makes the map's {{ $modeName }} difficulties Loved straight away. A removed request can be asked for again;
                        a rejected one can't (until it's opened again).
                    </p>
                </form>
            </div>
        @endif
    </div>
@endsection

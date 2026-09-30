{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@php
    use App\Http\Controllers\Panel\BnSetsController;

    $modes = ['osu!', 'osu!taiko', 'osu!catch', 'osu!mania'];
    $title = $beatmapset !== null ? "{$beatmapset->artist} - {$beatmapset->title}" : "Beatmap set #{$setId}";
@endphp
@extends('master', ['titlePrepend' => $title])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.bn.index'), 'title' => 'BN panel'],
        ['url' => route('panel.bn.sets.index'), 'title' => 'Manage sets'],
        ['title' => $title],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card tomyum-panel__row" style="align-items: center">
            @if ($beatmapset !== null)
                <img src="{{ $beatmapset->coverURL('card') }}" alt="" style="border-radius: 6px; height: 70px; object-fit: cover; width: 200px">
            @endif
            <div style="flex: 1">
                <h2 style="margin: 0">{{ $title }}</h2>
                @if ($beatmapset !== null)
                    <p class="tomyum-panel__hint" style="margin: 4px 0 0">
                        mapped by {{ $beatmapset->creator }} &middot; #{{ $setId }} &middot;
                        {{ BnSetsController::APPROVED_LABELS[$beatmapset->approved] ?? $beatmapset->approved }} on the website
                    </p>
                @endif
            </div>
            <a href="{{ route('beatmapsets.show', ['beatmapset' => $setId]) }}">Open on the site</a>
            <a href="https://osu.ppy.sh/beatmapsets/{{ $setId }}" rel="noopener" target="_blank">Open on osu!</a>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-star"></i>Ranked status</h2>
            <p class="tomyum-panel__hint">
                Tick difficulties to change only those, or none for the whole set. A status set here is kept when the set
                is refreshed from bancho, and takes effect in-game immediately. Existing scores are recalculated (pp, bests,
                players' stats and rankings). "Back to bancho" undoes staff changes: the difficulties follow osu!'s status again.
            </p>
            @if ($serverError !== null)
                <p>{{ $serverError }}</p>
            @endif
            <form method="POST" action="{{ route('panel.bn.sets.status', ['set' => $setId]) }}">
                @csrf
                <table class="tomyum-panel__table">
                    <tr><th></th><th>Difficulty</th><th>Mode</th><th>Stars</th><th>Status</th></tr>
                    @forelse ($difficulties as $difficulty)
                        <tr>
                            <td style="width: 30px"><input type="checkbox" name="beatmaps[]" value="{{ $difficulty['id'] }}"></td>
                            <td>{{ $difficulty['version'] }}</td>
                            <td>{{ $modes[$difficulty['mode']] ?? $difficulty['mode'] }}</td>
                            <td>{{ number_format($difficulty['starRating'], 2) }}★</td>
                            <td>
                                {{ BnSetsController::SETTABLE_STATUSES[$difficulty['status']] ?? ucfirst($difficulty['status']) }}
                                @if ($difficulty['staffSet'])
                                    <span class="tomyum-panel__badge tomyum-panel__badge--success">set by staff</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="tomyum-panel__hint">
                                The game server doesn't have this set's difficulties yet; changing the status imports it first.
                            </td>
                        </tr>
                    @endforelse
                </table>
                <div class="tomyum-panel__row" style="margin-top: 12px">
                    <label class="tomyum-panel__field" style="flex: 0 1 220px">
                        New status
                        <select name="status" required>
                            @foreach (BnSetsController::SETTABLE_STATUSES as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <button class="tomyum-panel__button" type="submit">Change status</button>
                </div>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-sync-alt"></i>Refresh from bancho</h2>
            <p class="tomyum-panel__hint">
                Re-fetches the title, difficulties and stats from osu!. Statuses set by staff are kept.
            </p>
            <form method="POST" action="{{ route('panel.bn.sets.refresh', ['set' => $setId]) }}">
                @csrf
                <button class="tomyum-panel__button" type="submit">Refresh</button>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-history"></i>History</h2>
            @if (count($entries) === 0)
                <p class="tomyum-panel__empty">No staff actions on this set yet.</p>
            @else
                @include('panel.bn._log_table', ['showJob' => false])
            @endif
        </div>
    </div>
@endsection

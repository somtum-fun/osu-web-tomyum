{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Kudosu'])

@section('content')
    @include('panel._header', ['crumbs' => [['title' => 'Kudosu']]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-search"></i>Find a player</h2>
            <form method="GET" action="{{ route('panel.kudosu.index') }}" class="tomyum-panel__row" style="align-items: flex-end">
                <label class="input-container tomyum-panel__field" style="flex: 0 1 260px">
                    <span class="input-container__label">Name or id</span>
                    <input class="input-text" type="text" name="user" value="{{ $query }}" required>
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-search"></i>Find</button>
            </form>
            @if ($query !== '' && $target === null)
                <p class="tomyum-panel__empty">Nobody with that name or id.</p>
            @endif
        </div>

        @if ($target !== null)
            <div class="tomyum-panel__card">
                <div class="tomyum-panel__row" style="align-items: center">
                    <img src="{{ App\Libraries\User\AvatarHelper::url($target) }}" alt="" style="border-radius: 12px; height: 48px; width: 48px; object-fit: cover">
                    <div style="flex: 1">
                        <h2 style="margin: 0"><a href="{{ route('users.show', ['user' => $target->getKey()]) }}">{{ $target->username }}</a> <span class="tomyum-panel__hint">#{{ $target->getKey() }}</span></h2>
                        <p class="tomyum-panel__hint" style="margin: 4px 0 0">
                            {{ number_format($target->osu_kudostotal) }} kudosu earned &middot; {{ number_format($target->osu_kudosavailable) }} available
                        </p>
                    </div>
                </div>

                <form method="POST" action="{{ route('panel.kudosu.adjust', ['user' => $target->getKey()]) }}" class="tomyum-panel__row" style="align-items: flex-end; margin-top: 12px">
                    @csrf
                    <label class="input-container input-container--select tomyum-panel__field" style="flex: 0 1 130px">
                        <span class="input-container__label">Change</span>
                        <select class="input-text" name="direction">
                            <option value="give">Give</option>
                            <option value="take">Take</option>
                        </select>
                    </label>
                    <label class="input-container tomyum-panel__field" style="flex: 0 1 130px">
                        <span class="input-container__label">Kudosu</span>
                        <input class="input-text" type="number" name="amount" min="1" max="10000" value="1" required>
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Reason (shown on their profile)</span>
                        <input class="input-text" type="text" name="reason" maxlength="255" required>
                    </label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-check"></i>Save</button>
                </form>
            </div>

            <div class="tomyum-panel__card">
                <h2><i class="fas fa-history"></i>Recent kudosu</h2>
                @if ($history->isEmpty())
                    <p class="tomyum-panel__empty">None yet.</p>
                @else
                    <table class="tomyum-panel__table">
                        <tr><th>When</th><th>Amount</th><th>From</th><th>What for</th></tr>
                        @foreach ($history as $entry)
                            <tr>
                                <td style="white-space: nowrap">{!! timeago($entry->date) !!}</td>
                                <td style="white-space: nowrap; color: {{ $entry->amount < 0 ? 'hsl(var(--hsl-red-2))' : 'hsl(var(--hsl-lime-1))' }}">{{ $entry->amount > 0 ? '+' : '' }}{{ $entry->amount }}</td>
                                <td>{{ $entry->giver?->username ?? (($entry->details['tomyum'] ?? null) === 'staff' ? '—' : 'votes') }}</td>
                                <td>
                                    @if (($entry->details['tomyum'] ?? null) === 'staff')
                                        {{ $entry->details['reason'] ?? '' }}
                                    @elseif ($entry->kudosuable_type !== null)
                                        modding ({{ $entry->details['event'] ?? $entry->action }})
                                    @else
                                        {{ $entry->action }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        @endif
    </div>
@endsection

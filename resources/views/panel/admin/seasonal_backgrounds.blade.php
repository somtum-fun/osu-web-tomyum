{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Seasonal backgrounds'])

@section('content')
    @php
        $now = Carbon\CarbonImmutable::now('UTC');
        $shown = $entries->where('show_in_client', true)->count();
        $presets = [
            '1 week' => $now->addWeek(),
            '1 month' => $now->addMonth(),
            '3 months' => $now->addMonths(3),
            'End of the year' => $now->endOfYear(),
        ];
    @endphp
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Seasonal backgrounds'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <div class="tomyum-panel__card-head">
                <h2><i class="fas fa-snowflake"></i>Season</h2>
                @if ($endsAt === null)
                    <span class="tomyum-panel__pill">No end date</span>
                @elseif ($endsAt->isPast())
                    <span class="tomyum-panel__pill tomyum-panel__pill--bad">Ended</span>
                @elseif ($shown === 0)
                    <span class="tomyum-panel__pill tomyum-panel__pill--warn">Nothing to show</span>
                @else
                    <span class="tomyum-panel__pill tomyum-panel__pill--good">Showing in game</span>
                @endif
            </div>
            <p class="tomyum-panel__hint">
                lazer and osu!stable show these as main menu backgrounds until the season ends, then go back
                to their usual ones. Players see changes the next time they start the game.
            </p>

            <div class="panel-seasonal__season">
                <div class="panel-seasonal__facts">
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">Ends</span>
                        <span class="tomyum-panel__fact-value">{{ $endsAt?->toDateString() ?? '–' }}</span>
                        @if ($endsAt !== null)
                            <span class="tomyum-panel__muted">{{ $endsAt->diffForHumans() }}</span>
                        @endif
                    </div>
                    <div class="tomyum-panel__fact">
                        <span class="tomyum-panel__kicker">Shown in game</span>
                        <span class="tomyum-panel__fact-value">{{ $shown }} of {{ count($entries) }}</span>
                        <span class="tomyum-panel__muted">backgrounds</span>
                    </div>
                </div>

                <form method="POST" action="{{ route('panel.admin.seasonal-backgrounds.settings') }}" class="panel-seasonal__date">
                    @csrf
                    <label class="input-container">
                        <span class="input-container__label">Season ends (UTC)</span>
                        <input class="input-text" type="date" name="ends_at" required value="{{ $endsAt?->toDateString() }}">
                    </label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Save</button>
                </form>
                <form method="POST" action="{{ route('panel.admin.seasonal-backgrounds.settings') }}" class="panel-seasonal__presets">
                    @csrf
                    <span class="tomyum-panel__muted">Or from today:</span>
                    @foreach ($presets as $label => $date)
                        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" type="submit" name="ends_at" value="{{ $date->toDateString() }}" title="{{ $date->toDateString() }}">
                            {{ $label }}
                        </button>
                    @endforeach
                    @if ($endsAt !== null && !$endsAt->isPast())
                        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit" name="ends_at" value="{{ $now->subDay()->toDateString() }}"
                            onclick="return confirm('End the season now? The games go back to their usual backgrounds.')">
                            End now
                        </button>
                    @endif
                </form>
            </div>
        </div>

        <div class="tomyum-panel__card">
            <div class="tomyum-panel__card-head">
                <h2><i class="fas fa-images"></i>Backgrounds</h2>
                <span class="tomyum-panel__hint">{{ count($entries) }} uploaded, newest first</span>
            </div>
            @if (count($entries) === 0)
                <p class="tomyum-panel__empty">None yet. Add the first one below.</p>
            @else
                <div class="panel-seasonal__grid">
                    @foreach ($entries as $entry)
                        <div class="panel-seasonal__item {{ $entry->show_in_client ? '' : 'panel-seasonal__item--hidden' }}">
                            <a href="{{ $entry->seasonalUrl() }}" target="_blank" rel="noopener" class="panel-seasonal__image">
                                <img src="{{ $entry->seasonalUrl() }}" alt="" loading="lazy">
                                <span class="tomyum-panel__pill {{ $entry->show_in_client ? 'tomyum-panel__pill--good' : '' }}">
                                    {{ $entry->show_in_client ? 'Shown' : 'Hidden' }}
                                </span>
                            </a>
                            <div class="panel-seasonal__item-body">
                                <div class="panel-seasonal__item-text">
                                    <div class="u-ellipsis-overflow">
                                        by <a href="{{ route('users.show', ['user' => $entry->user_id]) }}">{{ $entry->user?->username ?? "#{$entry->user_id}" }}</a>
                                    </div>
                                    <div class="tomyum-panel__muted">
                                        <i class="far fa-calendar"></i> added {{ $entry->created_at?->toDateString() ?? '–' }}
                                    </div>
                                </div>
                                <div class="panel-seasonal__actions">
                                    <form method="POST" action="{{ route('panel.admin.seasonal-backgrounds.toggle', ['entry' => $entry->getKey()]) }}">
                                        @csrf
                                        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" type="submit" title="{{ $entry->show_in_client ? 'Hide in game' : 'Show in game' }}">
                                            <i class="fas {{ $entry->show_in_client ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('panel.admin.seasonal-backgrounds.destroy', ['entry' => $entry->getKey()]) }}"
                                        onsubmit="return confirm('Remove this background for good?')">
                                        @csrf
                                        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit" title="Remove">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-plus"></i>Add a background</h2>
            <form method="POST" action="{{ route('panel.admin.seasonal-backgrounds.store') }}" enctype="multipart/form-data" class="panel-seasonal__upload js-panel-seasonal-upload">
                @csrf
                <label class="panel-seasonal__drop js-panel-seasonal-drop">
                    <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required class="js-panel-seasonal-file">
                    <img class="js-panel-seasonal-preview" alt="" hidden>
                    <span class="panel-seasonal__drop-text js-panel-seasonal-drop-text">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <strong>Drop an image here, or click to choose</strong>
                        <span>JPEG, PNG or WebP, up to 15 MB. 16:9 looks best.</span>
                    </span>
                </label>
                <div class="panel-seasonal__upload-side">
                    <label class="input-container">
                        <span class="input-container__label">Artist (a player's name; empty: you)</span>
                        <input class="input-text" type="text" name="artist" placeholder="{{ Auth::user()->username }}">
                    </label>
                    <p class="tomyum-panel__muted js-panel-seasonal-file-name"></p>
                    <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-upload"></i>Upload</button></div>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script')
    @parent
    <script>
        (() => {
            const drop = document.querySelector('.js-panel-seasonal-drop');
            if (drop == null) return;

            const file = drop.querySelector('.js-panel-seasonal-file');
            const preview = drop.querySelector('.js-panel-seasonal-preview');
            const text = drop.querySelector('.js-panel-seasonal-drop-text');
            const name = document.querySelector('.js-panel-seasonal-file-name');

            const show = () => {
                const chosen = file.files?.[0];
                if (preview.src.startsWith('blob:')) URL.revokeObjectURL(preview.src);
                if (chosen == null) {
                    preview.hidden = true;
                    text.hidden = false;
                    name.textContent = '';
                    return;
                }
                preview.src = URL.createObjectURL(chosen);
                preview.hidden = false;
                text.hidden = true;
                name.textContent = `${chosen.name} (${(chosen.size / 1024 / 1024).toFixed(1)} MB)`;
            };
            file.addEventListener('change', show);

            ['dragenter', 'dragover'].forEach((type) => drop.addEventListener(type, (e) => {
                e.preventDefault();
                drop.classList.add('panel-seasonal__drop--over');
            }));
            ['dragleave', 'drop'].forEach((type) => drop.addEventListener(type, () => drop.classList.remove('panel-seasonal__drop--over')));
            drop.addEventListener('drop', (e) => {
                e.preventDefault();
                if (e.dataTransfer?.files?.length > 0) {
                    file.files = e.dataTransfer.files;
                    show();
                }
            });
        })();
    </script>
@endsection

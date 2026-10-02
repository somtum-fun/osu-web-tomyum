{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Badges'])

@section('content')
    @php
        // TomYum: shared by the admin and TC panels; links stay in the one it was opened from.
        $panelPrefix = App\Http\Controllers\Panel\Controller::sharedPanelPrefix();
        $badgePrefix = $GLOBALS['cfg']['osu']['badge']['image_prefix'];
    @endphp
    @include('panel._header', ['crumbs' => [
        ['url' => route($panelPrefix.'index'), 'title' => $panelPrefix === 'panel.tc.' ? 'TC panel' : 'Admin panel'],
        ['title' => 'Badges'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <div class="tomyum-panel__card-head">
                <h2><i class="fas fa-award"></i>Players</h2>
                <form method="GET" action="{{ route($panelPrefix.'badges.index') }}" class="panel-badges__search">
                    <input class="input-text" type="text" name="user" value="{{ $query }}" required placeholder="Name or id" list="panel-badges-holders" aria-label="Player's name or id">
                    <datalist id="panel-badges-holders">
                        @foreach ($holders as $holder)
                            <option value="{{ $holder['name'] }}"></option>
                        @endforeach
                    </datalist>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-search"></i>Show</button>
                </form>
            </div>
            @if (count($holders) === 0)
                <p class="tomyum-panel__empty">Nobody has a badge yet. Look a player up to give them one.</p>
            @else
                <p class="tomyum-panel__hint">With badges</p>
                <div class="panel-badges__holders">
                    @foreach ($holders as $holder)
                        <a
                            href="{{ route($panelPrefix.'badges.index', ['user' => $holder['name']]) }}"
                            class="panel-badges__holder {{ $user?->getKey() === $holder['id'] ? 'panel-badges__holder--active' : '' }}"
                        >
                            <span class="panel-badges__avatar" style="background-image: url('{{ $holder['avatar'] }}')"></span>
                            <span class="u-ellipsis-overflow">{{ $holder['name'] }}</span>
                            <span class="tomyum-panel__pill">{{ $holder['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        @if ($query !== '' && $user === null)
            <div class="tomyum-panel__card"><p class="tomyum-panel__empty">There's no player called "{{ $query }}".</p></div>
        @elseif ($user !== null)
            <div class="tomyum-panel__card">
                <div class="tomyum-panel__card-head">
                    <h2>
                        <span class="panel-badges__avatar panel-badges__avatar--big" style="background-image: url('{{ $user->user_avatar }}')"></span>
                        {{ $user->username }}'s badges
                    </h2>
                    <a class="tomyum-panel__hint" href="{{ route('users.show', ['user' => $user->getKey()]) }}">
                        Profile <i class="fas fa-angle-right"></i>
                    </a>
                </div>

                @if (count($badges) === 0)
                    <p class="tomyum-panel__empty">None yet. Give the first one below.</p>
                @else
                    <p class="tomyum-panel__hint">On their profile, in this order (oldest first):</p>
                    <div class="panel-badges__strip">
                        @foreach ($badges as $badge)
                            <img src="{{ $badge->imageUrl() }}" alt="{{ $badge->description }}" title="{{ $badge->description }}">
                        @endforeach
                    </div>

                    <div class="panel-badges__list">
                        @foreach ($badges as $badge)
                            <div class="panel-badges__item">
                                <div class="panel-badges__item-main">
                                    <img class="panel-badges__image" src="{{ $badge->imageUrl() }}" alt="">
                                    <div class="panel-badges__item-text">
                                        <div class="panel-badges__tooltip">{{ $badge->description }}</div>
                                        <div class="panel-badges__meta">
                                            <span><i class="far fa-calendar"></i>{{ $badge->awarded?->toDateString() ?? 'no date' }}</span>
                                            @if (present($badge->url))
                                                <a href="{{ $badge->url }}" rel="nofollow noopener" target="_blank" class="u-ellipsis-overflow">
                                                    <i class="fas fa-link"></i>{{ parse_url($badge->url, PHP_URL_HOST) ?? $badge->url }}
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="panel-badges__actions">
                                        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary js-panel-badges-edit" type="button">
                                            <i class="fas fa-pen"></i>Edit
                                        </button>
                                        <form method="POST" action="{{ route($panelPrefix.'badges.destroy', ['user' => $user->getKey()]) }}"
                                            onsubmit="return confirm('Take this badge away?')">
                                            @csrf
                                            <input type="hidden" name="image" value="{{ $badge->image }}">
                                            <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit" title="Take away">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <form method="POST" action="{{ route($panelPrefix.'badges.update', ['user' => $user->getKey()]) }}" class="panel-badges__edit" hidden>
                                    @csrf
                                    <input type="hidden" name="image" value="{{ $badge->image }}">
                                    <label class="input-container panel-badges__wide">
                                        <span class="input-container__label">Tooltip</span>
                                        <input class="input-text" type="text" name="description" required maxlength="255" value="{{ $badge->description }}">
                                    </label>
                                    <label class="input-container">
                                        <span class="input-container__label">Link when clicked (optional)</span>
                                        <input class="input-text" type="text" name="url" maxlength="255" value="{{ $badge->url }}" placeholder="https://…">
                                    </label>
                                    <label class="input-container">
                                        <span class="input-container__label">Given on (UTC)</span>
                                        <input class="input-text" type="date" name="awarded" required value="{{ $badge->awarded?->toDateString() ?? $today }}">
                                    </label>
                                    <div class="panel-badges__wide panel-badges__buttons">
                                        <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Save</button>
                                        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary js-panel-badges-edit" type="button">Cancel</button>
                                    </div>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="tomyum-panel__card">
                <h2><i class="fas fa-plus"></i>Give {{ $user->username }} a badge</h2>
                <div class="panel-badges__give">
                    <form method="POST" action="{{ route($panelPrefix.'badges.store', ['user' => $user->getKey()]) }}" class="panel-badges__give-form js-panel-badges-give" data-prefix="{{ $badgePrefix }}">
                        @csrf
                        <label class="input-container">
                            <span class="input-container__label">Image: a link to any image (fitted to 86×40), or an osu! badge's file name</span>
                            <input class="input-text" type="text" name="image" required placeholder="https://… or contributor.png">
                        </label>
                        <label class="input-container">
                            <span class="input-container__label">Tooltip</span>
                            <input class="input-text" type="text" name="description" required maxlength="255" placeholder="Server tester">
                        </label>
                        <div class="panel-badges__pair">
                            <label class="input-container">
                                <span class="input-container__label">Link when clicked (optional)</span>
                                <input class="input-text" type="text" name="url" maxlength="255" placeholder="https://…">
                            </label>
                            <label class="input-container">
                                <span class="input-container__label">Given on (UTC)</span>
                                <input class="input-text" type="date" name="awarded" required value="{{ $today }}">
                            </label>
                        </div>
                        <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-award"></i>Give it</button></div>
                    </form>

                    <div class="panel-badges__preview">
                        <span class="panel-badges__preview-label">Preview</span>
                        <div class="panel-badges__preview-box">
                            <img class="panel-badges__image js-panel-badges-preview-image" alt="" hidden>
                            <span class="panel-badges__preview-empty js-panel-badges-preview-empty">86 × 40</span>
                        </div>
                        <div class="panel-badges__preview-tooltip js-panel-badges-preview-tooltip" hidden></div>
                        <div class="panel-badges__meta js-panel-badges-preview-date"></div>
                        <p class="tomyum-panel__hint">A link is fitted to 86×40 when it's given, so it can look a little different.</p>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection

@section('script')
    @parent
    <script>
        (() => {
            // Edit opens the badge's form in place (and Cancel closes it).
            document.querySelectorAll('.js-panel-badges-edit').forEach((button) => {
                button.addEventListener('click', () => {
                    const form = button.closest('.panel-badges__item').querySelector('.panel-badges__edit');
                    form.hidden = !form.hidden;
                    if (!form.hidden) form.querySelector('input[name=description]').focus();
                });
            });

            // The new badge as it'll look, while it's typed.
            const form = document.querySelector('.js-panel-badges-give');
            if (form == null) return;

            const image = form.querySelector('[name=image]');
            const description = form.querySelector('[name=description]');
            const awarded = form.querySelector('[name=awarded]');
            const preview = document.querySelector('.js-panel-badges-preview-image');
            const empty = document.querySelector('.js-panel-badges-preview-empty');
            const tooltip = document.querySelector('.js-panel-badges-preview-tooltip');
            const date = document.querySelector('.js-panel-badges-preview-date');

            const update = () => {
                const value = image.value.trim();
                const src = value === '' ? '' : (/^https?:\/\//i.test(value) ? value : form.dataset.prefix + value);
                if ((preview.getAttribute('src') ?? '') !== src) {
                    preview.hidden = true;
                    empty.hidden = false;
                    empty.textContent = src === '' ? '86 × 40' : 'Loading…';
                    if (src === '') preview.removeAttribute('src'); else preview.src = src;
                }
                tooltip.textContent = description.value.trim();
                tooltip.hidden = tooltip.textContent === '';
                date.textContent = awarded.value === '' ? '' : `Given ${awarded.value}`;
            };
            preview.addEventListener('load', () => { preview.hidden = false; empty.hidden = true; });
            preview.addEventListener('error', () => { if (preview.getAttribute('src') == null) return; preview.hidden = true; empty.hidden = false; empty.textContent = 'No image there'; });
            [image, description, awarded].forEach((input) => input.addEventListener('input', update));
            update();
        })();
    </script>
@endsection

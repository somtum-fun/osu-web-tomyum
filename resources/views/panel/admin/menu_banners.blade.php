{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Menu banners'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Menu banners'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-plus"></i>Add a banner</h2>
            <p class="tomyum-panel__hint">
                Banners rotate on lazer's main menu, and clicking one opens its link. The game draws the image at half its pixel size (it's made as @2x), so make it twice as big as it should look; a .png or .jpg anywhere on the web
                (put it on the <a href="{{ route('panel.admin.assets.index') }}">assets host</a> to keep it here).
                Discord's <code>/banner_add</code> and <code>/banner_remove</code> change the same list.
            </p>
            <form method="POST" action="{{ route('panel.admin.menu-banners.store') }}" class="tomyum-panel__row">
                @csrf
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Image link</span>
                    <input class="input-text" type="url" name="image" required placeholder="https://…/banner.png">
                </label>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Click link</span>
                    <input class="input-text" type="url" name="url" required placeholder="https://…">
                </label>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Starts (optional, UTC)</span>
                    <input class="input-text" type="date" name="begins">
                </label>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Ends (optional, UTC)</span>
                    <input class="input-text" type="date" name="expires">
                </label>
                <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Add</button></div>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-images"></i>Banners</h2>
            <p class="tomyum-panel__hint">In the order the menu shows them. The game reads <a href="{{ $feedUrl }}" target="_blank" rel="noopener"><code>{{ $feedUrl }}</code></a>.</p>
            @if ($problem !== null)
                <p class="tomyum-panel__empty">{{ $problem }}</p>
            @elseif (count($banners) === 0)
                <p class="tomyum-panel__empty">None yet: the menu shows no banner.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>#</th><th>Banner</th><th>Opens</th><th>When</th><th></th></tr>
                    @foreach ($banners as $banner)
                        <tr>
                            <td>{{ $banner['index'] + 1 }}</td>
                            <td>
                                <a href="{{ $banner['image'] }}" target="_blank" rel="noopener">
                                    <img src="{{ $banner['image'] }}" alt="" loading="lazy" style="display: block; max-width: 300px; max-height: 70px; border-radius: 4px">
                                </a>
                            </td>
                            <td style="word-break: break-all"><a href="{{ $banner['url'] }}" target="_blank" rel="nofollow noopener">{{ $banner['url'] }}</a></td>
                            <td>
                                @if ($banner['state'] === 'showing')
                                    <span class="tomyum-panel__pill tomyum-panel__pill--good">showing</span>
                                @elseif ($banner['state'] === 'upcoming')
                                    <span class="tomyum-panel__pill tomyum-panel__pill--info">upcoming</span>
                                @else
                                    <span class="tomyum-panel__pill tomyum-panel__pill--bad">ended</span>
                                @endif
                                <span class="tomyum-panel__hint">
                                    @if ($banner['begins'] !== null) from {{ $banner['begins']->toDateString() }} @endif
                                    @if ($banner['expires'] !== null) until {{ $banner['expires']->toDateString() }} @endif
                                    @if ($banner['begins'] === null && $banner['expires'] === null) always @endif
                                </span>
                            </td>
                            <td style="text-align: right">
                                <form method="POST" action="{{ route('panel.admin.menu-banners.destroy', ['index' => $banner['index']]) }}"
                                    onsubmit="return confirm('Take this banner off the menu?')">
                                    @csrf
                                    <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    </div>
@endsection

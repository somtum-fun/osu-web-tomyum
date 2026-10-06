{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Branding'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
        ['title' => 'Branding'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <form method="POST" action="{{ route('panel.admin.branding.update') }}">
            @csrf
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-signature"></i>Name and addresses</h2>
                <p class="tomyum-panel__hint">
                    The name is in page titles, mail, the store and the legal pages (<code>:brand</code> in the site's texts).
                    Leave a field empty for its default.
                </p>
                <div class="tomyum-panel__row">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Name</span>
                        <input class="input-text" type="text" name="name" maxlength="50" value="{{ $saved['name'] }}" placeholder="{{ $GLOBALS['cfg']['tomyum']['branding']['name'] }}">
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Email domain (support@, dmca@…)</span>
                        <input class="input-text" type="text" name="email_domain" value="{{ $saved['email_domain'] }}" placeholder="{{ App\Libraries\TomYumTickets::domain() }}">
                    </label>
                </div>
                <p class="tomyum-panel__hint">Now: <strong>{{ $name }}</strong>, {{ App\Libraries\TomYumBranding::email('support') }}</p>
            </div>

            <div class="tomyum-panel__card">
                <h2><i class="fas fa-image"></i>Logo and favicons</h2>
                <div class="tomyum-panel__row">
                    <label class="input-container input-container--select tomyum-panel__field">
                        <span class="input-container__label">Logo</span>
                        <select class="input-text" name="logo">
                            @foreach (App\Libraries\TomYumBranding::LOGOS as $key => $preset)
                                <option value="{{ $key }}" @selected($logoChoice === $key)>{{ $preset['label'] }}</option>
                            @endforeach
                            <option value="custom" @selected($logoChoice === 'custom')>Custom image (below)</option>
                        </select>
                    </label>
                    <label class="input-container input-container--select tomyum-panel__field">
                        <span class="input-container__label">Favicons</span>
                        <select class="input-text" name="favicon">
                            @foreach (App\Libraries\TomYumBranding::FAVICONS as $key => $set)
                                <option value="{{ $key }}" @selected($faviconChoice === $key)>{{ $set['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="tomyum-panel__row">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Custom logo image (a .png or .svg, twice the size it shows)</span>
                        <input class="input-text" type="text" name="logo_url" value="{{ $logoUrl }}" placeholder="https://assets.…/branding/logo.png">
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Its width ÷ height</span>
                        <input class="input-text" type="number" name="logo_ratio" step="0.01" min="0.1" max="10" value="{{ $saved['logo_ratio'] ?? '' }}" placeholder="1 (square)">
                    </label>
                </div>
                <p class="tomyum-panel__hint">Now:</p>
                <div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap">
                    <div style="width: calc(60px * {{ $logo['ratio'] }}); height: 60px; background: url('{{ $logo['image_2x'] }}') left center / contain no-repeat"></div>
                    <div style="padding: 8px; border-radius: 6px; background: hsl(var(--hsl-b5))">
                        <div style="width: calc(40px * {{ $logo['ratio'] }}); height: 40px; background: url('{{ $logo['nav'] }}') center / contain no-repeat"></div>
                    </div>
                    <img src="{{ App\Libraries\TomYumBranding::favicon('favicon-32x32.png') }}" width="32" height="32" alt="favicon">
                </div>
                <p class="tomyum-panel__hint">
                    Upload your own logo on the <a href="{{ route('panel.admin.assets.index') }}">Assets</a> page (or anywhere) and paste its address above.
                </p>
            </div>

            <div class="tomyum-panel__card">
                <h2><i class="fas fa-film"></i>Videos</h2>
                <div class="tomyum-panel__row">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Home page video</span>
                        <input class="input-text" type="text" name="landing_video_url" value="{{ $saved['landing_video_url'] }}" placeholder="{{ $GLOBALS['cfg']['osu']['landing']['video_url'] }}">
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Download page video</span>
                        <input class="input-text" type="text" name="download_video_url" value="{{ $saved['download_video_url'] }}" placeholder="{{ $GLOBALS['cfg']['osu']['urls']['download_video'] }}">
                    </label>
                </div>
                <p class="tomyum-panel__hint">The built-in ones (/media/landing.mp4, /media/download.mp4) are production's.</p>
            </div>

            <div class="tomyum-panel__card">
                <h2><i class="fas fa-paragraph"></i>Texts</h2>
                <p class="tomyum-panel__hint">
                    <code>:brand</code> is the name, <code>:year</code> this year, <code>:vote_url</code> the vote page.
                    Empty one to show nothing there. The defaults are production's.
                </p>
                @foreach ([
                    'support_note' => ['Support page note (markdown)', 8],
                    'support_signature' => ['Its signature', 1],
                    'footer' => ['Footer line', 2],
                    'keywords' => ['Search engine keywords', 1],
                    'invoice_seller' => ['Invoice: seller', 1],
                    'invoice_contact' => ['Invoice: contact (email / phone)', 1],
                ] as $field => [$label, $rows])
                    <label class="input-container" style="display: block; margin-bottom: 10px">
                        <span class="input-container__label">{{ $label }}</span>
                        @if ($rows > 1)
                            <textarea class="input-text" name="{{ $field }}" rows="{{ $rows }}" style="width: 100%">{{ $texts[$field] }}</textarea>
                        @else
                            <input class="input-text" type="text" name="{{ $field }}" value="{{ $texts[$field] }}" style="width: 100%">
                        @endif
                    </label>
                @endforeach
            </div>

            <div class="tomyum-panel__card">
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Save</button>
            </div>
        </form>
    </div>
@endsection

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
                <div class="tomyum-panel__row">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Run by (a name, or empty for "its team")</span>
                        <input class="input-text" type="text" name="operator" maxlength="100" value="{{ $texts['operator'] }}">
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Country (whose laws the terms follow; empty leaves it out)</span>
                        <input class="input-text" type="text" name="country" maxlength="100" value="{{ $texts['country'] }}">
                    </label>
                </div>
                <label class="input-container" style="display: block">
                    <span class="input-container__label">Site description (search engines and link previews)</span>
                    <textarea class="input-text" name="description" rows="2" style="width: 100%">{{ $texts['description'] }}</textarea>
                </label>
                <p class="tomyum-panel__hint">
                    Now: <strong>{{ $name }}</strong>, {{ App\Libraries\TomYumBranding::email('support') }}.
                    In the texts below, <code>:operator</code> and <code>:country</code> are these two.
                </p>
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
                <p class="tomyum-panel__hint">Links to video files (mp4); empty shows none (or LANDING_VIDEO_URL / OSU_URL_DOWNLOAD_VIDEO).</p>
            </div>

            <div class="tomyum-panel__card">
                <h2><i class="fas fa-paragraph"></i>Texts</h2>
                <p class="tomyum-panel__hint">
                    <code>:brand</code> is the name, <code>:year</code> this year, <code>:vote_url</code> the vote page.
                    Empty one to show nothing there.
                </p>
                @foreach ([
                    'support_note' => ['Support page note (markdown)', 8],
                    'support_signature' => ['Its signature', 1],
                    'support_team' => ['Support page: "Run by Volunteers" (who runs the server)', 2],
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

            <div class="tomyum-panel__card" id="downloads">
                <h2><i class="fas fa-download"></i>Download page</h2>
                <p class="tomyum-panel__hint">
                    The buttons come from a file host's <code>manifest.json</code> (the lazer builds, the stable patcher, the hook and the legacy clients),
                    unless you give a link of your own. No host (here or TOMYUM_DL_URL): only your own links show.
                </p>
                <div class="tomyum-panel__row">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">File host</span>
                        <input class="input-text" type="text" name="downloads_host" value="{{ $saved['downloads_host'] }}" placeholder="{{ $downloadsHost }}">
                    </label>
                </div>
                <div class="tomyum-panel__row">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">lazer: button name</span>
                        <input class="input-text" type="text" name="downloads_client_name" maxlength="100" value="{{ $texts['downloads_client_name'] }}">
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">lazer: link (empty: the file host's build)</span>
                        <input class="input-text" type="text" name="downloads_lazer_url" value="{{ $saved['downloads_lazer_url'] }}" placeholder="https://github.com/ppy/osu/releases/latest">
                    </label>
                </div>
                <div class="tomyum-panel__row">
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">stable: patcher name</span>
                        <input class="input-text" type="text" name="downloads_patcher_name" maxlength="100" value="{{ $texts['downloads_patcher_name'] }}">
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">stable: patcher link (empty: the file host's)</span>
                        <input class="input-text" type="text" name="downloads_patcher_url" value="{{ $saved['downloads_patcher_url'] }}" placeholder="https://…/patcher.exe">
                    </label>
                </div>
                <div class="tomyum-panel__row">
                    @foreach ([
                        'downloads_show_lazer' => 'Show the lazer download',
                        'downloads_show_stable' => 'Show the stable patcher',
                        'downloads_show_legacy' => 'Show the legacy clients (2008-2015)',
                    ] as $field => $label)
                        <label class="tomyum-panel__field" style="display: flex; gap: 6px; align-items: center">
                            <input type="checkbox" name="{{ $field }}" value="1" @checked($switches[$field])>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="tomyum-panel__card" id="vote">
                <h2><i class="fas fa-vote-yea"></i>Vote page</h2>
                <label style="display: flex; gap: 6px; align-items: center; margin-bottom: 10px">
                    <input type="checkbox" name="vote_enabled" value="1" @checked($switches['vote_enabled'])>
                    Show the vote page (and its menu, footer and support page links)
                </label>
                <label class="input-container" style="display: block">
                    <span class="input-container__label">Server lists, one a line: name | vote link | rewarded (gives supporter time; leave the link out for "coming soon")</span>
                    <textarea class="input-text" name="vote_sites" rows="4" style="width: 100%" placeholder="osu-server-list.com | https://osu-server-list.com/server/…/vote | rewarded">{{ $voteSites }}</textarea>
                </label>
                <p class="tomyum-panel__hint">
                    "rewarded" only works when that list calls back the game server's votes plugin.
                </p>
            </div>

            <div class="tomyum-panel__card" id="legal">
                <h2><i class="fas fa-balance-scale"></i>Legal pages</h2>
                <p class="tomyum-panel__hint">
                    Built-in: the default wording with this server's name and addresses. From the wiki: the page <code>Legal/&lt;name&gt;</code>
                    in the wiki (<a href="https://github.com/{{ App\Libraries\OsuWiki::user() }}/{{ App\Libraries\OsuWiki::repository() }}/tree/{{ App\Libraries\OsuWiki::branch() }}/wiki/Legal" target="_blank">on GitHub</a>),
                    changed by pull request; the built-in page shows while it's missing. Your own: markdown, with <code>:brand</code> and <code>:year</code>.
                    Hidden: no page and no footer link.
                </p>
                <p class="tomyum-panel__hint">The built-in Privacy Policy's parts that depend on how you run the server (markdown lists; empty hides one):</p>
                @foreach ([
                    'privacy_payments' => 'Payment processors',
                    'privacy_hosting' => 'Where the server is hosted',
                    'privacy_laws' => 'Data protection laws you follow',
                ] as $field => $label)
                    <label class="input-container" style="display: block; margin-bottom: 10px">
                        <span class="input-container__label">{{ $label }}</span>
                        <textarea class="input-text" name="{{ $field }}" rows="3" style="width: 100%">{{ $texts[$field] }}</textarea>
                    </label>
                @endforeach
                @foreach (App\Libraries\TomYumBranding::LEGAL_PAGES as $page => [$path, $title])
                    <div class="js-legal-page" style="margin-bottom: 14px">
                        <div class="tomyum-panel__row">
                            <label class="input-container input-container--select tomyum-panel__field">
                                <span class="input-container__label">{{ $title }}</span>
                                <select class="input-text js-legal-mode" name="legal_{{ $page }}_mode">
                                    <option value="builtin" @selected($legal[$page]['mode'] === 'builtin')>Built-in</option>
                                    <option value="wiki" @selected($legal[$page]['mode'] === 'wiki')>From the wiki (Legal/{{ $path }})</option>
                                    <option value="custom" @selected($legal[$page]['mode'] === 'custom')>Your own text</option>
                                    <option value="hidden" @selected($legal[$page]['mode'] === 'hidden')>Hidden</option>
                                </select>
                            </label>
                        </div>
                        <p class="tomyum-panel__hint">
                            @if ($legal[$page]['mode'] !== 'hidden')
                                <a href="{{ route('legal', ['locale' => app()->getLocale(), 'path' => $path]) }}" target="_blank">View the page</a>
                            @endif
                            <span class="js-legal-wiki" @if ($legal[$page]['mode'] !== 'wiki') hidden @endif>
                                · <a href="https://github.com/{{ App\Libraries\OsuWiki::user() }}/{{ App\Libraries\OsuWiki::repository() }}/blob/{{ App\Libraries\OsuWiki::branch() }}/wiki/Legal/{{ $path }}/en.md" target="_blank">its wiki page on GitHub</a>
                            </span>
                        </p>
                        <textarea class="input-text js-legal-text" name="legal_{{ $page }}" rows="10" style="width: 100%" placeholder="Your own {{ $title }} (markdown)" @if ($legal[$page]['mode'] !== 'custom') hidden @endif>{{ $legal[$page]['text'] }}</textarea>
                    </div>
                @endforeach
            </div>

            <div class="tomyum-panel__card">
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Save</button>
            </div>
        </form>
    </div>
@endsection

@section('script')
    @parent
    <script>
        (() => {
            // Each legal page: its text box only for "Your own text", its GitHub link only for the wiki.
            document.querySelectorAll('.js-legal-page').forEach((page) => {
                const mode = page.querySelector('.js-legal-mode');
                mode.addEventListener('change', () => {
                    page.querySelector('.js-legal-text').hidden = mode.value !== 'custom';
                    page.querySelector('.js-legal-wiki').hidden = mode.value !== 'wiki';
                });
            });
        })();
    </script>
@endsection

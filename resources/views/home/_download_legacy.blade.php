{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: osu!(stable) from 2008 to 2015, patched for this server (dl.example.com/legacy). --}}
<div class="download-page__legacy" id="legacy">
    <div class="download-page__text download-page__text--title">
        Legacy osu! clients (2008-2015)
    </div>
    <div class="download-page__text download-page__text--description">
        Old osu!(stable) builds, rebuilt to play on {{ App\Libraries\TomYumBranding::name() }}: today's beatmaps load, Relax and
        Autopilot scores submit, and the updater stays off. Unzip one anywhere and run osu!.exe.
        They need Windows and .NET Framework 3.5 (a few 2015 builds use .NET Framework 4).
    </div>
    @if (isset($hook['url']))
        <div class="download-page__text download-page__text--description">
            Rather play an unmodified build? Put the
            <a href="{{ $hook['url'] }}">hook</a>
            @if (isset($hook['version']))
                ({{ $hook['version'] }})
            @endif
            next to its osu!.exe and start the game with the hook's .exe. The hook may not work with
            every build; if one doesn't start, try the rebuilt one above.
        </div>
    @endif

    @if (count($legacyYears) === 0)
        <div class="download-page__text">
            The client list isn't available right now. You can find every build on
            <a href="{{ $dlUrl }}/legacy/">the file host</a>.
        </div>
    @endif

    @foreach ($legacyYears as $year => $builds)
        <details class="download-page__legacy-year" @if ($loop->first) open @endif>
            <summary class="download-page__legacy-year-title">
                {{ $year }}
                <span class="download-page__legacy-count">{{ count($builds) }}</span>
            </summary>
            <div class="download-page__legacy-builds">
                @foreach ($builds as $build)
                    <div class="download-page__legacy-build">
                        <div class="download-page__legacy-info">
                            <div class="download-page__legacy-name">{{ $build['build'] }}</div>
                            @if (present($build['note'] ?? null))
                                <div class="download-page__text download-page__text--description">{{ $build['note'] }}</div>
                            @endif
                        </div>
                        <a class="btn-osu-big btn-osu-big--rounded-thin" href="{{ $build['url'] }}">
                            {{ osu_trans('home.download.download') }}
                            <span class="download-page__legacy-size">{{ App\Libraries\TomYumDownloads::size($build['size'] ?? null) }}</span>
                        </a>
                    </div>
                @endforeach
            </div>
        </details>
    @endforeach

    <div class="download-page__text download-page__text--description download-page__legacy-footer">
        The original builds come from <a href="https://osu.titanic.sh/download/">Titanic</a>, as do most of the notes.
        osu! is a registered trademark of ppy Pty Ltd. These clients are unofficial and unsupported by ppy, and are offered as is.
        To have something taken down, email <a href="mailto:{{ App\Libraries\TomYumBranding::email('dmca') }}">{{ App\Libraries\TomYumBranding::email('dmca') }}</a>.
    </div>
</div>

{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Custom rulesets'])

@section('content')
    @include('panel._header', [
        'crumbs' => [
            ['url' => route('panel.admin.index'), 'title' => 'Admin panel'],
            ['title' => 'Custom rulesets'],
        ],
    ])
    <div class="osu-page osu-page--admin tomyum-panel">
        <details class="tomyum-panel__card tomyum-panel__explainer">
            <summary><i class="fas fa-info-circle"></i>How it works</summary>
            <p class="tomyum-panel__hint" style="margin-bottom: 0">
                Custom lazer rulesets from <a href="https://rulesets.info" target="_blank" rel="noopener">rulesets.info</a>, played in TumThai.
                Turning one on pins its release as it is now: the DLL's address and SHA-256. TumThai players install exactly that file from
                Settings (or put it in TumThai's rulesets folder themselves), and a play only submits when the ruleset it ran on has that hash.
                Their leaderboards and rankings go by score (no pp). When the author releases a new version, <strong>Pin again</strong> moves to it;
                players then update the ruleset from Settings. Turning a ruleset off stops new plays submitting; its scores stay.
            </p>
        </details>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-puzzle-piece"></i>rulesets.info</h2>
            @if (!$ready)
                <p class="tomyum-panel__empty">The custom rulesets table isn't there yet: run the website's migrations.</p>
            @elseif (count($listing) === 0)
                <p class="tomyum-panel__empty">rulesets.info couldn't be read right now. <a href="{{ route('panel.admin.rulesets.index', ['refresh' => 1]) }}">Try again</a>.</p>
            @else
                <table class="tomyum-panel__table">
                    <thead>
                        <tr>
                            <th>ruleset</th>
                            <th style="width: 170px">latest on rulesets.info</th>
                            <th style="width: 260px">here</th>
                            <th style="width: 200px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($listing as $entry)
                            @php
                                $slug = (string) ($entry['slug'] ?? '');
                                $ours = $ours[$slug] ?? null;
                                $downloadable = ($entry['can_download'] ?? false) && str_ends_with(strtolower((string) parse_url((string) ($entry['direct_download_link'] ?? ''), PHP_URL_PATH)), '.dll');
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $entry['name'] ?? $slug }}</strong> <code>{{ $slug }}</code>
                                    @if ($entry['verified'] ?? false) <span class="tomyum-panel__badge tomyum-panel__badge--success">VERIFIED</span> @endif
                                    @if ($entry['archive'] ?? false) <span class="tomyum-panel__badge">ARCHIVED</span> @endif
                                    <br><span class="tomyum-panel__hint" style="margin: 0">{{ $entry['description'] ?? '' }}</span>
                                </td>
                                <td>{{ $entry['status']['latest_version'] ?? '-' }}</td>
                                <td>
                                    @if ($ours === null)
                                        <span class="tomyum-panel__hint" style="margin: 0">off</span>
                                    @else
                                        @if ($ours->enabled)
                                            <span class="tomyum-panel__badge tomyum-panel__badge--success">ON</span>
                                        @else
                                            <span class="tomyum-panel__badge">OFF</span>
                                        @endif
                                        id <code>{{ $ours->id }}</code>, {{ $ours->version ?? 'unversioned file' }}
                                        <br><span class="tomyum-panel__hint" style="margin: 0"><code>{{ substr($ours->sha256, 0, 16) }}…</code>, {{ number_format($ours->size / 1024) }} KB, {{ $ours->pinned_at?->diffForHumans() }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($downloadable)
                                        <form method="POST" action="{{ route('panel.admin.rulesets.pin', ['slug' => $slug]) }}" style="display: inline">
                                            @csrf
                                            <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">{{ $ours?->enabled ? 'Pin again' : 'Turn on' }}</button>
                                        </form>
                                    @else
                                        <span class="tomyum-panel__hint" style="margin: 0">no DLL to download</span>
                                    @endif
                                    @if ($ours?->enabled)
                                        <form method="POST" action="{{ route('panel.admin.rulesets.disable', ['slug' => $slug]) }}" style="display: inline">
                                            @csrf
                                            <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" type="submit" onclick="return confirm('Stop new plays of this ruleset submitting?')">Turn off</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="tomyum-panel__hint"><a href="{{ route('panel.admin.rulesets.index', ['refresh' => 1]) }}">Read rulesets.info again</a> (it's kept for an hour).</p>
            @endif
        </div>
    </div>
@endsection

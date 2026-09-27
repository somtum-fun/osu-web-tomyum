{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum staff panels: page header + shared styles + the status message from the last action. --}}
@php
    $links = [['url' => route('panel.index'), 'title' => 'Staff panel']];
    foreach ($crumbs ?? [] as $crumb) {
        $links[] = $crumb;
    }
    $status = session('panel_status');
@endphp
@include('layout._page_header_v4', ['params' => [
    'links' => $links,
    'linksBreadcrumb' => true,
]])
<style>
    .tomyum-panel { display: grid; gap: 20px; }
    .tomyum-panel__card { background: hsl(var(--hsl-b4)); border-radius: 10px; padding: 20px; }
    .tomyum-panel__card h2 { margin: 0 0 10px; font-size: 18px; }
    .tomyum-panel__hint { color: hsl(var(--hsl-c2)); font-size: 12px; margin: 4px 0 12px; }
    .tomyum-panel__row { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; }
    .tomyum-panel__field { display: grid; gap: 4px; flex: 1 1 200px; }
    .tomyum-panel input[type=text], .tomyum-panel input[type=date], .tomyum-panel input[type=number],
    .tomyum-panel select, .tomyum-panel textarea {
        background: hsl(var(--hsl-b5)); border: 1px solid hsl(var(--hsl-b3)); border-radius: 6px;
        color: hsl(var(--hsl-c1)); padding: 8px 10px; font: inherit; width: 100%;
    }
    .tomyum-panel textarea { min-height: 120px; }
    .tomyum-panel__button {
        background: hsl(var(--hsl-h1)); border: 0; border-radius: 6px; color: hsl(var(--hsl-b6));
        cursor: pointer; font-weight: 600; padding: 9px 16px;
    }
    .tomyum-panel__button--danger { background: hsl(var(--hsl-red-2)); color: #fff; }
    .tomyum-panel__status { border-radius: 8px; padding: 12px 16px; }
    .tomyum-panel__status--success { background: hsla(var(--hsl-lime-2), 0.25); }
    .tomyum-panel__status--error { background: hsla(var(--hsl-red-2), 0.3); }
    .tomyum-panel__table { border-collapse: collapse; width: 100%; }
    .tomyum-panel__table th, .tomyum-panel__table td { border-bottom: 1px solid hsl(var(--hsl-b3)); padding: 6px 8px; text-align: left; }
    .tomyum-panel__links { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
    .tomyum-panel__link { background: hsl(var(--hsl-b4)); border-radius: 10px; color: inherit; display: block; padding: 20px; }
    .tomyum-panel__link:hover { background: hsl(var(--hsl-b3)); color: inherit; text-decoration: none; }
    .tomyum-panel__progress { background: hsl(var(--hsl-b5)); border-radius: 4px; height: 8px; overflow: hidden; }
    .tomyum-panel__progress > div { background: hsl(var(--hsl-h1)); height: 100%; }
</style>
@if ($status !== null)
    <div class="osu-page osu-page--admin">
        <div class="tomyum-panel__status tomyum-panel__status--{{ $status['type'] }}">
            {{ $status['message'] }}
            @if ($status['link'] ?? null)
                &mdash; <a href="{{ $status['link']['url'] }}">{{ $status['link']['text'] }}</a>
            @endif
        </div>
    </div>
@endif

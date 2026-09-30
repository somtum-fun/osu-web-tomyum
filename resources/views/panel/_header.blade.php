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
    .tomyum-panel { display: grid; gap: 16px; padding-top: 20px; padding-bottom: 20px; }
    .tomyum-panel a { transition: color 120ms; }

    /* Cards */
    .tomyum-panel__card {
        background: hsl(var(--hsl-b4)); border: 1px solid hsla(var(--hsl-b3), 0.6); border-radius: 12px;
        padding: 20px 24px; min-width: 0;
    }
    .tomyum-panel__card > h2, .tomyum-panel__card-head h2 {
        align-items: center; display: flex; font-size: 17px; font-weight: 600; gap: 10px; margin: 0 0 12px;
    }
    .tomyum-panel__card > h2 .fas, .tomyum-panel__card-head h2 .fas { color: hsl(var(--hsl-h1)); font-size: 14px; }
    .tomyum-panel__card-head { align-items: baseline; display: flex; flex-wrap: wrap; gap: 8px 16px; justify-content: space-between; }
    .tomyum-panel__card--danger { border-color: hsla(var(--hsl-red-2), 0.45); }
    .tomyum-panel__hint { color: hsl(var(--hsl-c2)); font-size: 12px; line-height: 1.5; margin: 4px 0 12px; }
    .tomyum-panel__empty { color: hsl(var(--hsl-c2)); font-size: 13px; padding: 12px 0 4px; text-align: center; }
    .tomyum-panel__section-title {
        color: hsl(var(--hsl-c2)); font-size: 12px; font-weight: 600; letter-spacing: 0.08em;
        margin: 8px 0 -4px; text-transform: uppercase;
    }

    /* Forms */
    .tomyum-panel__row { align-items: end; display: flex; flex-wrap: wrap; gap: 12px; }
    .tomyum-panel__field { color: hsl(var(--hsl-c2)); display: grid; flex: 1 1 200px; font-size: 12px; font-weight: 600; gap: 6px; }
    .tomyum-panel input[type=text], .tomyum-panel input[type=date], .tomyum-panel input[type=number],
    .tomyum-panel input[type=email], .tomyum-panel input[type=password], .tomyum-panel input[type=datetime-local],
    .tomyum-panel select, .tomyum-panel textarea {
        background: hsl(var(--hsl-b5)); border: 1px solid hsl(var(--hsl-b3)); border-radius: 8px;
        color: hsl(var(--hsl-c1)); font: inherit; font-size: 14px; font-weight: 400; min-height: 38px;
        padding: 8px 12px; transition: border-color 120ms, box-shadow 120ms; width: 100%;
    }
    .tomyum-panel input:focus, .tomyum-panel select:focus, .tomyum-panel textarea:focus {
        border-color: hsl(var(--hsl-h1)); box-shadow: 0 0 0 3px hsla(var(--hsl-h1), 0.2); outline: none;
    }
    .tomyum-panel input::placeholder, .tomyum-panel textarea::placeholder { color: hsla(var(--hsl-c2), 0.6); }
    .tomyum-panel textarea { min-height: 120px; resize: vertical; }
    .tomyum-panel input[type=checkbox] { accent-color: hsl(var(--hsl-h1)); height: 16px; width: 16px; }

    /* Buttons */
    .tomyum-panel__button {
        align-items: center; background: hsl(var(--hsl-h1)); border: 0; border-radius: 8px; color: hsl(var(--hsl-b6));
        cursor: pointer; display: inline-flex; font-size: 14px; font-weight: 600; gap: 8px; justify-content: center;
        min-height: 38px; padding: 0 18px; transition: filter 120ms, transform 120ms; white-space: nowrap;
    }
    .tomyum-panel__button:hover { filter: brightness(1.12); }
    .tomyum-panel__button:active { transform: translateY(1px); }
    .tomyum-panel__button--danger { background: hsl(var(--hsl-red-2)); color: #fff; }
    .tomyum-panel__button--secondary { background: hsl(var(--hsl-b3)); color: hsl(var(--hsl-c1)); }
    .tomyum-panel__table .tomyum-panel__button, .tomyum-panel__button--small { border-radius: 6px; font-size: 12px; min-height: 28px; padding: 0 12px; }

    /* Messages */
    .tomyum-panel__status {
        align-items: center; background: hsl(var(--hsl-b4)); border-left: 4px solid; border-radius: 8px;
        display: flex; gap: 10px; margin-top: 20px; padding: 12px 16px;
    }
    .tomyum-panel__status--success { border-color: hsl(var(--hsl-lime-1)); }
    .tomyum-panel__status--success::before { color: hsl(var(--hsl-lime-1)); content: "\f058"; font-family: "Font Awesome 5 Free"; font-weight: 900; }
    .tomyum-panel__status--error { border-color: hsl(var(--hsl-red-2)); }
    .tomyum-panel__status--error::before { color: hsl(var(--hsl-red-2)); content: "\f06a"; font-family: "Font Awesome 5 Free"; font-weight: 900; }

    /* Tables */
    .tomyum-panel__table { border-collapse: collapse; font-size: 13px; width: 100%; }
    .tomyum-panel__table th {
        border-bottom: 1px solid hsl(var(--hsl-b3)); color: hsl(var(--hsl-c2)); font-size: 12px; font-weight: 600;
        padding: 8px 10px; text-align: left; white-space: nowrap;
    }
    .tomyum-panel__table td { border-bottom: 1px solid hsla(var(--hsl-b3), 0.5); padding: 9px 10px; text-align: left; vertical-align: middle; }
    .tomyum-panel__table tr:last-child td { border-bottom: 0; }
    .tomyum-panel__table tr:hover td { background: hsla(var(--hsl-b3), 0.35); }
    .tomyum-panel__table td:first-child, .tomyum-panel__table th:first-child { border-radius: 6px 0 0 6px; }
    .tomyum-panel__table td:last-child, .tomyum-panel__table th:last-child { border-radius: 0 6px 6px 0; }
    .tomyum-panel__table .timeago, .tomyum-panel__nowrap { white-space: nowrap; }
    .tomyum-panel__muted { color: hsl(var(--hsl-c2)); }
    .tomyum-panel__scroll { overflow-x: auto; }

    /* Tiles: links to tools, and numbers */
    .tomyum-panel__links { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); }
    .tomyum-panel__links--stats { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); }
    .tomyum-panel__link {
        background: hsl(var(--hsl-b4)); border: 1px solid hsla(var(--hsl-b3), 0.6); border-radius: 12px; color: inherit;
        display: flex; flex-direction: column; gap: 4px; padding: 18px 20px; position: relative;
        transition: border-color 150ms, transform 150ms, background 150ms;
    }
    .tomyum-panel__link h2 { font-size: 18px; margin: 0; }
    .tomyum-panel__link p { margin: 0; }
    a.tomyum-panel__link:hover { background: hsl(var(--hsl-b3)); border-color: hsla(var(--hsl-h1), 0.6); color: inherit; text-decoration: none; transform: translateY(-2px); }
    a.tomyum-panel__link::after {
        color: hsl(var(--hsl-c2)); content: "\f054"; font-family: "Font Awesome 5 Free"; font-size: 11px; font-weight: 900;
        opacity: 0; position: absolute; right: 18px; top: 20px; transition: opacity 150ms;
    }
    a.tomyum-panel__link:hover::after { opacity: 1; }
    .tomyum-panel__link-icon {
        align-items: center; background: hsla(var(--hsl-h1), 0.15); border-radius: 10px; color: hsl(var(--hsl-h1));
        display: flex; font-size: 16px; height: 38px; justify-content: center; margin-bottom: 8px; width: 38px;
    }
    .tomyum-panel__link--big { padding: 24px; }
    .tomyum-panel__link--big h2 { font-size: 22px; }
    .tomyum-panel__link--big .tomyum-panel__link-icon { font-size: 20px; height: 46px; width: 46px; }
    .tomyum-panel__kicker { color: hsl(var(--hsl-c2)); font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; }
    .tomyum-panel__stat { font-size: 30px; font-weight: 600; line-height: 1.15; }
    .tomyum-panel__live-dot {
        background: hsl(var(--hsl-lime-1)); border-radius: 50%; box-shadow: 0 0 0 3px hsla(var(--hsl-lime-1), 0.25);
        display: inline-block; height: 8px; margin-right: 6px; vertical-align: middle; width: 8px;
    }
    .tomyum-panel__live-dot--off { background: hsl(var(--hsl-red-2)); box-shadow: 0 0 0 3px hsla(var(--hsl-red-2), 0.25); }

    /* Progress */
    .tomyum-panel__progress { background: hsl(var(--hsl-b5)); border-radius: 4px; height: 8px; overflow: hidden; }
    .tomyum-panel__progress > div { background: hsl(var(--hsl-h1)); height: 100%; transition: width 300ms; }

    /* Pills: results, actions, clients */
    .tomyum-panel__badge, .tomyum-panel__pill {
        background: hsla(var(--hsl-b3), 0.8); border-radius: 999px; display: inline-block; font-size: 11px; font-weight: 600;
        line-height: 1.6; padding: 1px 9px; white-space: nowrap;
    }
    .tomyum-panel__badge--success, .tomyum-panel__pill--good { background: hsla(var(--hsl-lime-1), 0.2); color: hsl(var(--hsl-lime-1)); }
    .tomyum-panel__badge--error, .tomyum-panel__pill--bad { background: hsla(var(--hsl-red-2), 0.2); color: hsl(var(--hsl-red-1)); }
    .tomyum-panel__pill--warn { background: hsla(var(--hsl-orange-1), 0.2); color: hsl(var(--hsl-orange-1)); }
    .tomyum-panel__pill--info { background: hsla(var(--hsl-blue-1), 0.2); color: hsl(var(--hsl-blue-1)); }
    .tomyum-panel__pill--pink { background: hsla(var(--hsl-h1), 0.18); color: hsl(var(--hsl-h1)); }
    .tomyum-panel__client { color: hsl(var(--hsl-c2)); font-size: 12px; white-space: nowrap; }
    .tomyum-panel__client .fas { font-size: 11px; margin-right: 4px; }
</style>
@if ($status !== null)
    <div class="osu-page osu-page--admin">
        <div class="tomyum-panel__status tomyum-panel__status--{{ $status['type'] }}">
            <span>
                {{ $status['message'] }}
                @if ($status['link'] ?? null)
                    &mdash; <a href="{{ $status['link']['url'] }}">{{ $status['link']['text'] }}</a>
                @endif
            </span>
        </div>
    </div>
@endif

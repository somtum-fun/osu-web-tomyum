{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum staff panels: page header + the status message from the last action (styles: bem/tomyum-panel.less). --}}
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
    'theme' => 'panel',
]])
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

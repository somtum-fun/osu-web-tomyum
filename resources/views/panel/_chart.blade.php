{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    TomYum: a small one-series chart as inline SVG (App\Libraries\TomYumCharts). Params: $points
    ([label, value, tooltip]), $type ('bars' or 'line'), $empty (what shows without data). One series,
    so no legend: the card's title names it. Hovering a bar or point shows its exact value.
--}}
@php
    $width = 600;
    $height = 150;
    $padTop = 14;
    $padBottom = 20;
    $plot = $height - $padTop - $padBottom;
    $count = count($points);
    $max = max(1, ...array_map(fn ($point) => $point[1], $points ?: [[null, 0]]));
    // A round top value for the one gridline, so its label reads cleanly.
    $magnitude = 10 ** floor(log10($max));
    $top = (int) (ceil($max / $magnitude) * $magnitude);
    $y = fn ($value) => $padTop + $plot - $plot * $value / $top;
    $latest = $count > 0 ? $points[$count - 1] : null;
@endphp
@if ($count === 0)
    <p class="tomyum-panel__empty">{{ $empty ?? 'Nothing yet.' }}</p>
@else
    <div class="tomyum-chart">
        <svg viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" role="img" aria-label="{{ $latest[2] ?? '' }}">
            {{-- The baseline and one gridline at the top value, recessive. --}}
            <line class="tomyum-chart__grid" x1="0" x2="{{ $width }}" y1="{{ $y($top) }}" y2="{{ $y($top) }}"></line>
            <line class="tomyum-chart__axis" x1="0" x2="{{ $width }}" y1="{{ $y(0) }}" y2="{{ $y(0) }}"></line>

            @if (($type ?? 'bars') === 'line')
                @php
                    $step = $count > 1 ? $width / ($count - 1) : 0;
                    $coords = array_map(fn ($i) => round($i * $step, 1).','.round($y($points[$i][1]), 1), array_keys($points));
                @endphp
                <polygon class="tomyum-chart__area" points="0,{{ $y(0) }} {{ implode(' ', $coords) }} {{ $width }},{{ $y(0) }}"></polygon>
                <polyline class="tomyum-chart__line" points="{{ implode(' ', $coords) }}"></polyline>
                {{-- Invisible hover strips, wider than the line, each with its sample's numbers. --}}
                @foreach ($points as $i => [$label, $value, $tip])
                    <rect class="tomyum-chart__hit" x="{{ max(0, $i * $step - $step / 2) }}" y="0" width="{{ max($step, 2) }}" height="{{ $height }}"><title>{{ $tip }}</title></rect>
                @endforeach
            @else
                @php
                    $slot = $width / $count;
                    $barWidth = max(2, $slot - 2); // a 2px gap between bars
                @endphp
                @foreach ($points as $i => [$label, $value, $tip])
                    @php($barHeight = max($value > 0 ? 2 : 0, $plot * $value / $top))
                    <g>
                        <title>{{ $tip }}</title>
                        <rect class="tomyum-chart__hit" x="{{ $i * $slot }}" y="0" width="{{ $slot }}" height="{{ $height }}"></rect>
                        @if ($barHeight > 0)
                            <rect class="tomyum-chart__bar" x="{{ $i * $slot + 1 }}" y="{{ $y(0) - $barHeight }}" width="{{ $barWidth }}" height="{{ $barHeight }}" rx="{{ min(4, $barWidth / 2) }}"></rect>
                        @endif
                    </g>
                @endforeach
            @endif
        </svg>
        <div class="tomyum-chart__labels">
            <span>{{ $points[0][0] }}</span>
            <span>top {{ number_format($top) }}</span>
            <span>{{ $latest[0] }}</span>
        </div>
    </div>
@endif

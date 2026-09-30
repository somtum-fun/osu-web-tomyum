{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{--
    TomYum: the player's Last.fm history (App\Http\Controllers\Account\LastFmController), newest first.
    Params: $recent (artist, title, url, at, nowPlaying, client, beatmapId, image). Refreshed every 10
    seconds by the account page (it asks for this again).
--}}
@php
    // osu! plays show their map's cover; the rest Last.fm's art.
    $mapIds = array_filter(array_column($recent, 'beatmapId'));
    $covers = count($mapIds) === 0
        ? []
        : App\Models\Beatmap::with('beatmapset')->whereIn('beatmap_id', $mapIds)->get()
            ->mapWithKeys(fn ($beatmap) => [$beatmap->getKey() => $beatmap->beatmapset?->coverURL('list@2x')])->all();
    $pill = static fn (string $colour, string $text) => '<span style="background: hsla(var('.$colour.'), 0.2); border-radius: 999px; color: hsl(var('.$colour.')); font-size: 11px; font-weight: 600; padding: 0 8px; white-space: nowrap">'.e($text).'</span>';
@endphp
@if (count($recent) > 0)
    <div style="font-weight: 600; margin-bottom: 8px">Recently scrobbled</div>
    @foreach ($recent as $track)
        @php
            $isMap = $track['beatmapId'] !== null;
            $url = $isMap ? route('beatmaps.show', ['beatmap' => $track['beatmapId']]) : $track['url'];
            $art = ($isMap ? ($covers[$track['beatmapId']] ?? null) : null) ?? $track['image'];
        @endphp
        <div style="align-items: center; display: flex; gap: 12px; padding: 5px 0">
            @if ($art !== null)
                <img src="{{ $art }}" alt="" loading="lazy" style="border-radius: 6px; flex: none; height: 40px; object-fit: cover; width: 40px">
            @else
                <span style="align-items: center; background: hsl(var(--hsl-b4)); border-radius: 6px; color: hsl(var(--hsl-c2)); display: flex; flex: none; height: 40px; justify-content: center; width: 40px">
                    <i class="fas fa-music"></i>
                </span>
            @endif
            <div style="flex: 1; min-width: 0">
                <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap">
                    @if ($url !== null)
                        <a href="{{ $url }}" @if (!$isMap) rel="nofollow noopener" target="_blank" @endif style="font-weight: 600">{{ $track['title'] }}</a>
                    @else
                        <span style="font-weight: 600">{{ $track['title'] }}</span>
                    @endif
                </div>
                <div style="color: hsl(var(--hsl-c2)); font-size: 12px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ $track['artist'] }}</div>
            </div>
            <div style="align-items: center; display: flex; flex: none; gap: 8px">
                {{-- The clients in their changelog colours (stable blue, lazer red); other players have no tag. --}}
                @if ($track['client'] !== null)
                    {!! $pill($track['client'] === 'lazer' ? '--hsl-red-1' : '--hsl-blue-1', 'osu! '.$track['client']) !!}
                @endif
                @if ($track['nowPlaying'])
                    {!! $pill('--hsl-lime-1', 'now playing') !!}
                @elseif ($track['at'] !== null)
                    <span style="color: hsl(var(--hsl-c2)); font-size: 12px; white-space: nowrap">{!! timeago(Carbon\Carbon::parse($track['at'])) !!}</span>
                @endif
            </div>
        </div>
    @endforeach
@endif

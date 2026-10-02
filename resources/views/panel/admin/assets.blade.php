{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Assets'])

@section('content')
    @php
        use App\Libraries\TomYumAssets;

        $panelPrefix = App\Http\Controllers\Panel\Controller::sharedPanelPrefix();
        $route = fn (string $folder) => route($panelPrefix.'assets.index', ['folder' => $folder]);
        // Breadcrumbs: the root, then each folder under it.
        $crumbs = [];
        $walked = $root;
        $crumbs[] = [$roots[$root][0], $root];
        foreach (array_filter(explode('/', substr($folder, strlen($root)))) as $part) {
            $walked .= "/{$part}";
            $crumbs[] = [$part, $walked];
        }
        $size = fn (?array $image) => $image === null ? null : "{$image['width']}×{$image['height']}";
        $kb = fn (?array $image) => $image === null ? '' : ($image['bytes'] >= 1024 * 1024 ? round($image['bytes'] / 1024 / 1024, 1).' MB' : max(1, round($image['bytes'] / 1024)).' KB');
    @endphp
    @include('panel._header', ['crumbs' => [
        ['url' => route($panelPrefix.'index'), 'title' => $panelPrefix === 'panel.tc.' ? 'TC panel' : 'Admin panel'],
        ['title' => 'Assets'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <div class="tomyum-panel__card-head">
                <h2><i class="fas fa-photo-video"></i>Assets</h2>
                <span class="tomyum-panel__hint">{{ TomYumAssets::baseUrl() }}</span>
            </div>
            <p class="tomyum-panel__hint">
                Images at the same places as osu!'s, each as a pair: <code>name.png</code> and <code>name@2x.png</code> (double size,
                for sharp screens). Upload the @2x one and the normal one is made from it.
            </p>
            <div class="panel-assets__roots">
                @foreach ($roots as $key => [$title, $icon, $sizes])
                    <a class="panel-assets__root {{ $root === $key ? 'panel-assets__root--active' : '' }}" href="{{ $route($key) }}">
                        <i class="fas {{ $icon }}"></i>
                        <span>
                            <strong>{{ $title }}</strong>
                            <span class="tomyum-panel__muted">{{ $sizes }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="tomyum-panel__card">
            <div class="panel-assets__crumbs">
                @foreach ($crumbs as $index => [$title, $path])
                    @if ($index > 0)
                        <i class="fas fa-angle-right"></i>
                    @endif
                    @if ($loop->last)
                        <strong>{{ $title }}</strong>
                    @else
                        <a href="{{ $route($path) }}">{{ $title }}</a>
                    @endif
                @endforeach
                <code class="panel-assets__path">{{ $folder }}/</code>
            </div>

            @if ($problem !== null)
                <p class="tomyum-panel__empty">{{ $problem }}</p>
            @else
                @if (count($listing['folders']) > 0)
                    <div class="panel-assets__folders">
                        @foreach ($listing['folders'] as $sub)
                            <a class="panel-assets__folder" href="{{ $route("{$folder}/{$sub}") }}"><i class="fas fa-folder"></i>{{ $sub }}</a>
                        @endforeach
                    </div>
                @endif

                @if (count($listing['images']) === 0)
                    <p class="tomyum-panel__empty">No images here yet{{ count($listing['folders']) > 0 ? ' (open a folder above)' : '' }}.</p>
                @else
                    <div class="panel-assets__grid">
                        @foreach ($listing['images'] as $image)
                            @php
                                $normal = $image['normal'];
                                $retina = $image['retina'];
                                $doubled = $normal !== null && $retina !== null
                                    && $retina['width'] === $normal['width'] * 2 && $retina['height'] === $normal['height'] * 2;
                            @endphp
                            <div class="panel-assets__item">
                                <div class="panel-assets__previews {{ $root === 'tournament-banners' ? 'panel-assets__previews--wide' : '' }}">
                                    @foreach ([['normal', $normal, $image['path']], ['@2x', $retina, TomYumAssets::retina($image['path'])]] as [$label, $info, $path])
                                        <a class="panel-assets__preview" href="{{ TomYumAssets::url($path) }}" target="_blank" rel="noopener" title="{{ $label }}">
                                            @if ($info !== null)
                                                <img src="{{ TomYumAssets::url($path) }}?{{ $info['bytes'] }}" alt="" loading="lazy">
                                            @else
                                                <span class="panel-assets__missing">no {{ $label }}</span>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                                <div class="panel-assets__name u-ellipsis-overflow" title="{{ $image['name'] }}">{{ $image['name'] }}</div>
                                <div class="panel-assets__meta">
                                    <span>{{ $size($normal) ?? '–' }} · {{ $size($retina) ?? '–' }}</span>
                                    <span>{{ $kb($normal) }}{{ $retina === null ? '' : ' + '.$kb($retina) }}</span>
                                </div>
                                @if ($retina === null)
                                    <span class="tomyum-panel__pill tomyum-panel__pill--warn">No @2x: blurry on sharp screens</span>
                                @elseif ($normal === null)
                                    <span class="tomyum-panel__pill tomyum-panel__pill--warn">No normal size</span>
                                @elseif (!$doubled)
                                    <span class="tomyum-panel__pill tomyum-panel__pill--warn">@2x isn't exactly double</span>
                                @endif
                                <details class="panel-assets__actions">
                                    <summary><i class="fas fa-ellipsis-h"></i> Link, rename, remove</summary>
                                    <input class="input-text panel-assets__link" type="text" readonly value="{{ TomYumAssets::url($image['path']) }}" onclick="this.select()" aria-label="Its link">
                                    <form method="POST" action="{{ route($panelPrefix.'assets.rename') }}" class="panel-assets__inline">
                                        @csrf
                                        <input type="hidden" name="path" value="{{ $image['path'] }}">
                                        <input class="input-text" type="text" name="name" required value="{{ pathinfo($image['name'], PATHINFO_FILENAME) }}" aria-label="New name">
                                        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--panel-secondary" type="submit">Rename</button>
                                    </form>
                                    <form method="POST" action="{{ route($panelPrefix.'assets.destroy') }}" onsubmit="return confirm('Remove {{ $image['name'] }} (both sizes)? Badges or banners using it stop showing.')">
                                        @csrf
                                        <input type="hidden" name="path" value="{{ $image['path'] }}">
                                        <button class="btn-osu-big btn-osu-big--rounded-thin btn-osu-big--danger" type="submit"><i class="fas fa-trash"></i>Remove</button>
                                    </form>
                                </details>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>

        <div class="tomyum-panel__columns">
            <div class="tomyum-panel__card">
                <h2><i class="fas fa-upload"></i>Upload</h2>
                <form method="POST" action="{{ route($panelPrefix.'assets.store') }}" enctype="multipart/form-data" class="panel-assets__form">
                    @csrf
                    <input type="hidden" name="folder" value="{{ $folder }}">
                    <label class="input-container">
                        <span class="input-container__label">Name (empty: the file's own), like ndc-2023</span>
                        <input class="input-text" type="text" name="name" maxlength="80" placeholder="ndc-2023">
                    </label>
                    <label class="input-container">
                        <span class="input-container__label">In a folder under {{ $folder }}/ (optional; made if new)</span>
                        <input class="input-text" type="text" name="subfolder" maxlength="120" placeholder="{{ $root === 'tournament-banners' ? 'official/owc2026/profile' : '' }}">
                    </label>
                    <label class="tomyum-panel__field">
                        @2x (double size: {{ $roots[$root][2] }})
                        <input type="file" name="retina" accept="image/*">
                    </label>
                    <label class="tomyum-panel__field">
                        Normal size (optional: made from the @2x)
                        <input type="file" name="normal" accept="image/*">
                    </label>
                    <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-upload"></i>Upload</button></div>
                </form>
            </div>

            <div class="tomyum-panel__card">
                <h2><i class="fas fa-cloud-download-alt"></i>Copy from osu!</h2>
                <p class="tomyum-panel__hint">
                    osu!'s links, one per line (either size's): both sizes are copied to the same place here, like
                    <code>https://assets.ppy.sh/profile-badges/ndc-2023.png</code>.
                </p>
                <form method="POST" action="{{ route($panelPrefix.'assets.import') }}" class="panel-assets__form">
                    @csrf
                    <input type="hidden" name="folder" value="{{ $folder }}">
                    <textarea class="input-text panel-assets__links" name="links" rows="5" required placeholder="https://assets.ppy.sh/tournament-banners/official/owc2025/profile/supporter_CA.jpg"></textarea>
                    <div><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit"><i class="fas fa-copy"></i>Copy</button></div>
                </form>
            </div>
        </div>
    </div>
@endsection

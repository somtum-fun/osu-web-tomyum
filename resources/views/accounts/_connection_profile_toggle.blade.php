{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
{{-- TomYum: "Show on my profile" for a linked account ($kind: osu_bancho, discord or lastfm; $shown). --}}
<form method="POST" action="{{ route('account.connections.profile', ['kind' => $kind]) }}" style="margin: 0 0 10px">
    @csrf
    <input type="hidden" name="shown" value="0">
    <label style="display: inline-flex; gap: 6px; align-items: center; cursor: pointer">
        <input type="checkbox" name="shown" value="1" onchange="this.form.submit()" @if ($shown) checked @endif>
        Show on my profile
    </label>
</form>

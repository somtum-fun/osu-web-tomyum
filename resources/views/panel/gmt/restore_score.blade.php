{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'Restore removed score'])

@section('content')
    @include('panel._header', ['crumbs' => [
        ['url' => route('panel.gmt.index'), 'title' => 'GMT panel'],
        ['title' => 'Restore removed score'],
    ]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <div class="tomyum-panel__card">
            <h2><i class="fas fa-undo-alt"></i>Restore a removed score</h2>

            <p class="tomyum-panel__hint">
                A score removed by hand (<code>/removescore</code> or the per-score button on a user) is gone
                from the game database — pp, totals and the replay all go. If a backup still has it, paste the
                row here as JSON and the server will put it back: it goes in as <strong>Submitted</strong>, the
                best-on-map is re-picked between the restored play and whatever is already there, stats and
                ranks are recomputed, the website copy is written, and the replay (if given) is dropped into
                the replay folder. Logged as <code>score.restore</code> in the staff log.
            </p>

            <p class="tomyum-panel__hint">
                The payload matches TomYum's <code>scores</code> table 1:1 (PascalCase keys). To pull a row
                from a backup, SSH to the server, run the backup's databases dump through
                <code>awk '/^USE `tomyum`;/{flag=1} flag && /^USE `/ &amp;&amp; !/USE `tomyum`;/{flag=0} flag &amp;&amp; /INSERT INTO `scores`/'</code>,
                then grep for <code>(&lt;id&gt;,</code>. Values map to the fields in the order they appear in
                <code>DESCRIBE scores</code>.
            </p>

            @if (session('panel_status'))
                @php($status = session('panel_status'))
                <div class="tomyum-panel__notice tomyum-panel__notice--{{ $status['type'] }}">{{ $status['message'] }}</div>
            @endif

            <form method="POST" action="{{ route('panel.gmt.scores.restore') }}" enctype="multipart/form-data">
                @csrf
                <label class="input-container">
                    <span class="input-container__label">Score payload (JSON)</span>
                    <textarea class="input-text" name="payload" rows="14" required
                        placeholder='{"Id":35827,"UserId":121,"BeatmapId":2680097,"BeatmapMd5":"...","Mode":4,"ScoreValue":61240,"MaxCombo":192,"Count300":178,"Count100":4,"Count50":0,"CountMiss":2,"CountGeki":35,"CountKatu":2,"Grade":"A","Mods":200,"Perfect":false,"Pp":156.064,"Passed":true,"SubmittedAt":"2024-08-02T12:25:11Z","FromLazer":false,"LargeTickHits":null,"SliderEndHits":null,"OffBoard":false}'>{{ $preset ?? '' }}</textarea>
                </label>
                <label class="input-container">
                    <span class="input-container__label">Replay file (.osr, optional)</span>
                    <input class="input-text" type="file" name="replay" accept=".osr">
                </label>
                <label class="input-container">
                    <span class="input-container__label">Reason</span>
                    <input class="input-text" type="text" name="reason" required placeholder="Why this score was wrongly removed, and why we're bringing it back.">
                </label>
                <div class="tomyum-panel__row">
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Restore</button>
                </div>
            </form>
        </div>
    </div>
@endsection

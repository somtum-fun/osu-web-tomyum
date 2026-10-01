{{--
    Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
    See the LICENCE file in the repository root for full licence text.
--}}
@extends('master', ['titlePrepend' => 'BN panel'])

@section('content')
    @include('panel._header', ['crumbs' => [['url' => route('panel.bn.index'), 'title' => 'BN panel']]])
    <div class="osu-page osu-page--admin tomyum-panel">
        <a class="tomyum-panel__link" href="{{ route('panel.bn.sets.index') }}">
            <h2 style="margin: 0">Manage beatmap sets &rarr;</h2>
            <p class="tomyum-panel__hint" style="margin: 4px 0 0">Search sets on the server, change their ranked status, refresh them from bancho.</p>
        </a>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-download"></i>Import a beatmap set</h2>
            <p class="tomyum-panel__hint">
                Paste an osu.ppy.sh set link or a set id. Every difficulty is added with its stats and cover,
                keeps its ranked status from bancho, and shows up in the beatmap listing.
            </p>
            <form method="POST" action="{{ route('panel.bn.import') }}" class="tomyum-panel__row">
                @csrf
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Set link or id</span>
                    <input class="input-text" type="text" name="set" placeholder="https://osu.ppy.sh/beatmapsets/2424357" required autofocus>
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Import</button>
            </form>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-upload"></i>Upload a custom beatmap set</h2>
            <p class="tomyum-panel__hint">
                An .osz from the osu! editor (up to 100 MB). It becomes a pending set with its own ids (from
                {{ number_format(App\Http\Controllers\Panel\BnController::CUSTOM_ID_FLOOR) }}), which players can
                download in-game (osu!direct) and on the website. The mapper named in the difficulties owns it
                when they have an account here. To update a custom set, give its link or id: difficulties with
                the same name keep their ids and scores, and the ones left out are removed.
            </p>
            <form class="js-tomyum-upload" data-ticket-url="{{ route('panel.bn.upload-ticket') }}">
                <div class="tomyum-panel__row">
                    <label class="tomyum-panel__field">
                        .osz file
                        <input type="file" name="osz" accept=".osz" required>
                    </label>
                    <label class="input-container tomyum-panel__field">
                        <span class="input-container__label">Update custom set (optional)</span>
                        <input class="input-text" type="text" name="set" placeholder="{{ App\Http\Controllers\Panel\BnController::CUSTOM_ID_FLOOR }}">
                    </label>
                    <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Upload</button>
                </div>
                <p class="js-tomyum-upload-status" hidden></p>
            </form>
            @if (count($uploads) > 0)
                <h3>Recent uploads</h3>
                @include('panel.bn._log_table', ['entries' => $uploads, 'showJob' => false])
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-layer-group"></i>Bulk import</h2>
            <p class="tomyum-panel__hint">Runs in the background; you can leave the page and check back under "Recent imports".</p>

            <form method="POST" action="{{ route('panel.bn.import-bulk') }}">
                @csrf
                <input type="hidden" name="mode" value="ids">
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Set links or ids, one per line (or separated by commas)</span>
                    <textarea class="input-text" name="ids" placeholder="https://osu.ppy.sh/beatmapsets/950289&#10;2285243&#10;2291277"></textarea>
                </label>
                <p><button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Import these sets</button></p>
            </form>

            <form method="POST" action="{{ route('panel.bn.import-bulk') }}" class="tomyum-panel__row">
                @csrf
                <input type="hidden" name="mode" value="dates">
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Ranked or loved from</span>
                    <input class="input-text" type="date" name="from" required>
                </label>
                <label class="input-container tomyum-panel__field">
                    <span class="input-container__label">Until (leave empty for today)</span>
                    <input class="input-text" type="date" name="to">
                </label>
                <button class="btn-osu-big btn-osu-big--rounded-thin" type="submit">Import everything in this range</button>
            </form>
            <p class="tomyum-panel__hint">
                Date ranges need the osu! API key on the server and are capped at 10,000 sets per import.
                A few days is usually a few hundred sets.
            </p>
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-history"></i>Import log</h2>
            <p class="tomyum-panel__hint">Every import, newest first: single sets and each set of a bulk import.</p>
            @if ($logError !== null)
                <p>{{ $logError }}</p>
            @elseif (count($entries) === 0)
                <p class="tomyum-panel__empty">Nothing imported yet.</p>
            @else
                @include('panel.bn._log_table', ['showJob' => true])
            @endif
        </div>

        <div class="tomyum-panel__card">
            <h2><i class="fas fa-tasks"></i>Bulk imports</h2>
            <p class="tomyum-panel__hint">Progress is kept until the game server restarts; the sets stay in the import log.</p>
            @if ($jobsError !== null)
                <p>{{ $jobsError }}</p>
            @elseif (count($jobs) === 0)
                <p class="tomyum-panel__empty">No bulk imports since the game server started.</p>
            @else
                <table class="tomyum-panel__table">
                    <tr><th>Started</th><th>By</th><th>What</th><th>Status</th><th>Imported</th><th>Not found</th><th>Failed</th><th></th></tr>
                    @foreach ($jobs as $job)
                        <tr>
                            <td>{!! timeago(Carbon\Carbon::parse($job['createdAt'])) !!}</td>
                            <td>{{ $actors[$job['startedBy']] ?? '—' }}</td>
                            <td>
                                @if ($job['request']['rankedFrom'] ?? null)
                                    Ranked {{ substr($job['request']['rankedFrom'], 0, 10) }}
                                    &ndash; {{ substr($job['request']['rankedTo'] ?? 'now', 0, 10) }}
                                @else
                                    {{ count($job['request']['setIds'] ?? []) }} set(s)
                                @endif
                            </td>
                            <td>{{ $job['status'] }}</td>
                            <td>{{ $job['imported'] }} / {{ $job['total'] }}</td>
                            <td>{{ $job['notFound'] }}</td>
                            <td>{{ $job['failed'] }}</td>
                            <td><a href="{{ route('panel.bn.job', ['job' => $job['id']]) }}">Details</a></td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    </div>
    <script>
        // Uploads go straight to the game server (the website only signs them), with progress.
        if (window.tomyumUpload == null) {
            window.tomyumUpload = true;
            document.addEventListener('submit', async function (event) {
                const form = event.target.closest('.js-tomyum-upload');
                if (form == null) {
                    return;
                }
                event.preventDefault();
                const status = form.querySelector('.js-tomyum-upload-status');
                const button = form.querySelector('button[type=submit]');
                const show = function (text, link) {
                    status.hidden = false;
                    status.textContent = text;
                    if (link != null) {
                        const a = document.createElement('a');
                        a.href = link;
                        a.textContent = ' Open the set';
                        status.append(a);
                    }
                };

                button.disabled = true;
                try {
                    const ticket = await fetch(form.dataset.ticketUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify({ set: form.elements.set.value }),
                    });
                    const ticketBody = await ticket.json();
                    if (!ticket.ok) {
                        show(ticketBody.error ?? ('Couldn\'t start the upload (HTTP ' + ticket.status + ').'));
                        return;
                    }

                    const data = new FormData();
                    data.append('osz', form.elements.osz.files[0]);
                    const result = await new Promise(function (resolve) {
                        const request = new XMLHttpRequest();
                        request.open('POST', ticketBody.url);
                        request.upload.onprogress = function (e) {
                            if (e.lengthComputable) {
                                show('Uploading… ' + Math.round(e.loaded / e.total * 100) + '%');
                            }
                        };
                        request.upload.onload = function () {
                            show('Processing…');
                        };
                        request.onload = function () {
                            let body = null;
                            try {
                                body = JSON.parse(request.responseText);
                            } catch (e) {
                                // Not JSON: the proxy refused it (too big) or the server is down.
                            }
                            resolve({ status: request.status, body: body });
                        };
                        request.onerror = function () {
                            resolve({ status: 0, body: null });
                        };
                        request.send(data);
                    });

                    if (result.status === 200 && result.body != null) {
                        const b = result.body;
                        show((b.updated ? 'Updated ' : 'Uploaded ') + b.artist + ' - ' + b.title + ' (' + b.creator + '), '
                            + b.difficulties.length + ' difficulties, set ' + b.set_id + '.', '/beatmapsets/' + b.set_id);
                        form.reset();
                    } else if (result.status === 413) {
                        show('The file is too big.');
                    } else {
                        show(result.body?.error ?? ('The upload failed (HTTP ' + result.status + ').'));
                    }
                } catch (e) {
                    show('The upload failed: ' + e.message);
                } finally {
                    button.disabled = false;
                }
            });
        }
    </script>
@endsection

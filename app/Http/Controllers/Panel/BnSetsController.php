<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Models\Beatmap;
use App\Models\Beatmapset;
use Auth;
use Request;

/**
 * BN panel: find beatmap sets on the server and manage them (ranked status, refresh from bancho).
 * Statuses are changed on TomYum, which freezes them, applies them in-game and mirrors them here.
 */
class BnSetsController extends Controller
{
    /** Statuses staff can set (TomYum's names) with labels. */
    public const SETTABLE_STATUSES = [
        'ranked' => 'Ranked',
        'approved' => 'Approved',
        'qualified' => 'Qualified',
        'loved' => 'Loved',
        'pending' => 'Pending (unranked)',
    ];

    /** osu-web `approved` value => label. */
    public const APPROVED_LABELS = [
        -2 => 'Graveyard',
        -1 => 'WIP',
        0 => 'Pending',
        1 => 'Ranked',
        2 => 'Approved',
        3 => 'Qualified',
        4 => 'Loved',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseBn(Auth::user()));

            return $next($request);
        });
    }

    public function index()
    {
        $query = trim(Request::input('q') ?? '');
        $status = Request::input('status');

        $sets = Beatmapset::query()->orderByDesc('last_update');
        if ($query !== '') {
            $setId = BnController::parseSetId($query);
            if ($setId !== null) {
                $sets->where('beatmapset_id', $setId);
            } else {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query).'%';
                $sets->where(fn ($q) => $q->where('title', 'like', $like)
                    ->orWhere('artist', 'like', $like)
                    ->orWhere('creator', 'like', $like));
            }
        }
        if ($status !== null && $status !== '' && array_key_exists((int) $status, static::APPROVED_LABELS)) {
            $sets->where('approved', (int) $status);
        }

        $page = $sets->paginate(50)->withQueryString();
        // Beatmapset only allows known attributes, so no withCount(): count difficulties separately.
        $difficultyCounts = Beatmap::whereIn('beatmapset_id', $page->pluck('beatmapset_id'))
            ->selectRaw('beatmapset_id, count(*) as difficulties')
            ->groupBy('beatmapset_id')
            ->pluck('difficulties', 'beatmapset_id');

        return ext_view('panel.bn.sets.index', [
            'sets' => $page,
            'difficultyCounts' => $difficultyCounts,
            'query' => $query,
            'status' => $status,
        ]);
    }

    public function show(string $set)
    {
        $setId = (int) $set;
        $beatmapset = Beatmapset::with('beatmaps')->find($setId);
        $server = $this->tomyum()->get("/beatmapsets/{$setId}");
        $log = $this->tomyum()->get('/log', ['targetId' => $setId, 'action' => 'beatmapset.', 'limit' => 50]);

        if ($beatmapset === null && (!$server->successful() || count($server->json()) === 0)) {
            abort(404);
        }

        $entries = $log->successful() ? $log->json() : [];

        return ext_view('panel.bn.sets.show', [
            'setId' => $setId,
            'beatmapset' => $beatmapset,
            'difficulties' => $server->successful() ? $server->json() : [],
            'serverError' => $server->successful() ? null : "Couldn't load the game server's copy (HTTP {$server->status()}).",
            'entries' => $entries,
            'actors' => BnController::actorNames($entries),
        ]);
    }

    public function status(string $set)
    {
        $setId = (int) $set;
        $status = Request::input('status');
        if (!array_key_exists($status, static::SETTABLE_STATUSES)) {
            return $this->back($setId, 'error', 'Pick a status.');
        }

        $beatmapIds = array_values(array_map('intval', Request::input('beatmaps') ?? []));
        $response = $this->tomyum()->post("/beatmapsets/{$setId}/status", [
            'status' => $status,
            'beatmapIds' => $beatmapIds,
        ]);
        if (!$response->successful()) {
            return $this->back($setId, 'error', $response->json('error') ?? "Changing the status failed (HTTP {$response->status()}).");
        }

        $changed = $response->json('changed');
        $label = static::SETTABLE_STATUSES[$status];
        $message = count($beatmapIds) === 0
            ? "The whole set is now {$label}."
            : count($changed).' difficult'.(count($changed) === 1 ? 'y is' : 'ies are')." now {$label}: ".implode(', ', $changed).'.';

        return $this->back($setId, 'success', $message);
    }

    public function refresh(string $set)
    {
        $setId = (int) $set;
        $response = $this->tomyum()->post("/beatmapsets/{$setId}/import");
        if ($response->notFound()) {
            return $this->back($setId, 'error', "osu! doesn't have this set (anymore), so there's nothing to refresh from.");
        }
        if (!$response->successful()) {
            return $this->back($setId, 'error', "Refresh failed (HTTP {$response->status()}).");
        }

        return $this->back($setId, 'success', "Refreshed from bancho: {$response->json('difficulties')} difficulties. Statuses set by staff were kept.");
    }

    private function back(int $setId, string $type, string $message)
    {
        return redirect(route('panel.bn.sets.show', ['set' => $setId]))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}

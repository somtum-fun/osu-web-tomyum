<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Exceptions\InvariantException;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use Request;

/**
 * BN panel: import beatmap sets from bancho (the osu! API) into TomYum and the website, and upload
 * custom sets (.osz files) to TomYum.
 */
class BnController extends Controller
{
    /** Custom (uploaded) sets and difficulties have ids from here (TomYum's Beatmap.CustomIdFloor). */
    public const CUSTOM_ID_FLOOR = 10_000_000;

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
        $jobs = $this->tomyum()->get('/beatmapsets/import-jobs');
        $log = $this->tomyum()->get('/log', ['action' => 'beatmapset.import', 'limit' => 50]);
        $entries = $log->successful() ? $log->json() : [];
        $uploadLog = $this->tomyum()->get('/log', ['action' => 'beatmapset.upload', 'limit' => 20]);
        $uploads = $uploadLog->successful() ? $uploadLog->json() : [];
        $jobList = $jobs->successful() ? $jobs->json() : [];
        $jobStarters = array_map(fn ($job) => ['actorId' => $job['startedBy']], $jobList);

        return ext_view('panel.bn.index', [
            'jobs' => $jobList,
            'jobsError' => $jobs->successful() ? null : "Couldn't load bulk imports (HTTP {$jobs->status()}).",
            'entries' => $entries,
            'logError' => $log->successful() ? null : "Couldn't load the import log (HTTP {$log->status()}).",
            'actors' => static::actorNames(array_merge($entries, $uploads, $jobStarters)),
            'uploads' => $uploads,
        ]);
    }

    /**
     * Permission to upload one .osz (a new custom set, or a new version of custom set `set`): the
     * browser sends the file straight to TomYum with this signed link, valid for an hour.
     */
    public function uploadTicket()
    {
        $setId = null;
        if (present(Request::input('set'))) {
            $setId = static::parseSetId(Request::input('set'));
            if ($setId === null || $setId < static::CUSTOM_ID_FLOOR) {
                return response()->json(['error' => 'Only custom sets (ids from '.number_format(static::CUSTOM_ID_FLOOR).') can be updated. Leave it empty to upload a new set.'], 422);
            }
        }

        $secret = $GLOBALS['cfg']['osu']['legacy']['shared_interop_secret'];
        if (!present($secret)) {
            return response()->json(['error' => 'Uploads are not set up on this server (SHARED_INTEROP_SECRET).'], 503);
        }

        $userId = Auth::user()->getKey();
        $expires = time() + 3600;
        $query = [
            'user' => $userId,
            'expires' => $expires,
            'signature' => hash_hmac('sha256', "upload\n{$userId}\n".($setId ?? '')."\n{$expires}", $secret),
        ];
        if ($setId !== null) {
            $query['set'] = $setId;
        }

        return response()->json(['url' => '/web/tomyum/beatmapset-upload?'.http_build_query($query)]);
    }

    public function import()
    {
        $setId = static::parseSetId(Request::input('set'));
        if ($setId === null) {
            return $this->back('error', 'That isn\'t a beatmap set link or id. Use the set link, e.g. https://osu.ppy.sh/beatmapsets/123456.');
        }

        $response = $this->tomyum()->post("/beatmapsets/{$setId}/import");
        if ($response->notFound()) {
            return $this->back('error', "osu! doesn't have a beatmap set {$setId}.");
        }
        if (!$response->successful()) {
            return $this->back('error', "Import failed (HTTP {$response->status()}).");
        }

        $result = $response->json();

        return $this->back('success', "Imported {$result['artist']} - {$result['title']} ({$result['difficulties']} difficulties).", [
            'url' => route('beatmapsets.show', ['beatmapset' => $setId]),
            'text' => 'Open the set',
        ]);
    }

    public function importBulk()
    {
        $body = match (Request::input('mode')) {
            'ids' => ['setIds' => static::parseSetIds(Request::input('ids') ?? '')],
            'dates' => [
                'rankedFrom' => static::parseDate(Request::input('from'))?->toIso8601String(),
                'rankedTo' => static::parseDate(Request::input('to'))?->endOfDay()->toIso8601String(),
            ],
            default => null,
        };

        if ($body === null || ($body['setIds'] ?? null) === [] || (array_key_exists('rankedFrom', $body) && $body['rankedFrom'] === null)) {
            return $this->back('error', 'Give at least one set link/id, or a start date.');
        }

        $response = $this->tomyum()->post('/beatmapsets/import-bulk', $body);
        if (!$response->successful()) {
            return $this->back('error', $response->json('error') ?? "Couldn't start the import (HTTP {$response->status()}).");
        }

        return redirect(route('panel.bn.job', ['job' => $response->json('id')]));
    }

    public function job(string $job)
    {
        // Progress lives in the game server's memory (gone after a restart); the per-set log doesn't.
        $response = $this->tomyum()->get('/beatmapsets/import-jobs/'.rawurlencode($job));
        if (!$response->successful() && !$response->notFound()) {
            throw new InvariantException("Couldn't load the import (HTTP {$response->status()}).");
        }
        $log = $this->tomyum()->get('/log', ['jobId' => $job, 'limit' => 500]);
        $entries = $log->successful() ? $log->json() : [];

        if ($response->notFound() && count($entries) === 0) {
            abort(404);
        }

        return ext_view('panel.bn.job', [
            'job' => $response->successful() ? $response->json() : null,
            'entries' => $entries,
            'actors' => static::actorNames($entries),
        ]);
    }

    /** user id => username for the staff members in log entries (for display). */
    public static function actorNames(array $entries): array
    {
        $ids = array_values(array_unique(array_filter(array_column($entries, 'actorId'))));

        return count($ids) === 0 ? [] : User::whereIn('user_id', $ids)->pluck('username', 'user_id')->all();
    }

    /** A set id from an id or an osu! link (beatmapsets/123, s/123); null for anything else. */
    public static function parseSetId(?string $value): ?int
    {
        $value = trim($value ?? '');
        if (preg_match('/^\d+$/', $value) === 1) {
            $id = (int) $value;
        } elseif (preg_match('#/(?:beatmapsets|s)/(\d+)#', $value, $matches) === 1) {
            $id = (int) $matches[1];
        } else {
            return null;
        }

        return $id > 0 ? $id : null;
    }

    /** Set ids from free text: one per line, or separated by commas/spaces. Invalid entries are skipped. */
    public static function parseSetIds(string $text): array
    {
        $ids = [];
        foreach (preg_split('/[\s,]+/', $text, -1, PREG_SPLIT_NO_EMPTY) as $entry) {
            $id = static::parseSetId($entry);
            if ($id !== null) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private static function parseDate(?string $value): ?Carbon
    {
        if (!present($value)) {
            return null;
        }

        try {
            return Carbon::parse($value, 'UTC')->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function back(string $type, string $message, ?array $link = null)
    {
        return redirect(route('panel.bn.index'))->with('panel_status', compact('type', 'message', 'link'));
    }
}

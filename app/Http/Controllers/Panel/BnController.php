<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Exceptions\InvariantException;
use Auth;
use Carbon\Carbon;
use Request;

/**
 * BN panel: import beatmap sets from bancho (the osu! API) into TomYum and the website.
 */
class BnController extends Controller
{
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

        return ext_view('panel.bn.index', [
            'jobs' => $jobs->successful() ? $jobs->json() : [],
            'jobsError' => $jobs->successful() ? null : "Couldn't load recent imports (HTTP {$jobs->status()}).",
        ]);
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
        $response = $this->tomyum()->get('/beatmapsets/import-jobs/'.rawurlencode($job));
        if ($response->notFound()) {
            abort(404);
        }
        if (!$response->successful()) {
            throw new InvariantException("Couldn't load the import (HTTP {$response->status()}).");
        }

        return ext_view('panel.bn.job', ['job' => $response->json()]);
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

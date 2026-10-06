<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Models\BeatmapPack;
use App\Models\BeatmapPackItem;
use App\Models\Beatmapset;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use DB;
use Request;

/**
 * TomYum: beatmap packs, made by staff (admins and the NAT). The website's pack pages read
 * osu_beatmappacks; the game server no longer makes them from osu!'s (TomYum's
 * Database:OsuWebImportPacks is off). Packs are numbered from 20000 so osu!'s pack medals never
 * mistake one for an osu! pack. A pack's download is either built by the game server (a zip of its
 * sets from the mirror, /d/packs/{tag}) or a link pasted here.
 */
class PacksController extends Controller
{
    /** Local packs' ids start here (TomYum's PackMedalAwarder.LocalPackFloor). */
    private const LOCAL_PACK_FLOOR = 20000;

    /** Sets fetched from osu! in one go (each takes a few seconds). */
    private const MAX_IMPORTS = 20;

    private const MODES = ['' => 'Any mode', 0 => 'osu!', 1 => 'osu!taiko', 2 => 'osu!catch', 3 => 'osu!mania'];

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUsePacks(Auth::user()));

            return $next($request);
        });
    }

    public static function canUsePacks(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $user->isNAT());
    }

    /** The download the game server builds for a pack (on this website's own address). */
    public static function builtUrl(string $tag): string
    {
        return rtrim($GLOBALS['cfg']['app']['url'], '/').'/d/packs/'.rawurlencode($tag);
    }

    public function index()
    {
        $type = presence(Request::input('type'));
        $query = trim((string) Request::input('q'));
        $letter = BeatmapPack::TAG_MAPPINGS[$type] ?? null;

        $packs = BeatmapPack::withCount('items')
            ->when($letter !== null, fn ($q) => $q->where('tag', 'like', "{$letter}%"))
            ->when($query !== '', fn ($q) => $q->where(fn ($qq) => $qq->where('name', 'like', '%'.addcslashes($query, '%_\\').'%')->orWhere('tag', $query)))
            ->orderByDesc('pack_id')
            ->paginate(50)
            ->withQueryString();

        return ext_view('panel.packs.index', [
            'packs' => $packs,
            'type' => $letter === null ? null : $type,
            'query' => $query,
            'types' => BeatmapPack::TAG_MAPPINGS,
            'modes' => static::MODES,
        ]);
    }

    public function show(string $pack)
    {
        $model = $this->findPack($pack);
        $sets = Beatmapset::whereIn('beatmapset_id', $model->items()->select('beatmapset_id'))
            ->orderBy('beatmapset_id')
            ->get();

        $download = null;
        $downloadError = null;
        if ($model->url === static::builtUrl($model->tag)) {
            try {
                $response = $this->tomyum()->get('/packs/'.rawurlencode($model->tag).'/download');
                $download = $response->successful() ? $response->json() : null;
                $downloadError = $response->successful() ? null : "The game server couldn't say (HTTP {$response->status()}).";
            } catch (\Throwable $e) {
                $downloadError = 'The game server can\'t be reached.';
            }
        }

        return ext_view('panel.packs.show', [
            'pack' => $model,
            'sets' => $sets,
            'types' => BeatmapPack::TAG_MAPPINGS,
            'modes' => static::MODES,
            'download' => $download,
            'downloadError' => $downloadError,
            'builtUrl' => static::builtUrl($model->tag),
        ]);
    }

    public function store()
    {
        $existing = present(Request::input('id')) ? $this->findPack((string) Request::input('id')) : null;

        $name = trim((string) Request::input('name'));
        if ($name === '' || mb_strlen($name) > 255) {
            return $this->back($existing, 'error', 'A pack needs a name (up to 255 characters).');
        }

        $letter = BeatmapPack::TAG_MAPPINGS[Request::input('type')] ?? null;
        $tag = trim((string) Request::input('tag'));
        if ($tag === '') {
            if ($existing !== null) {
                $tag = $existing->tag;
            } elseif ($letter === null) {
                return $this->back($existing, 'error', 'Pick the kind of pack (or write its tag).');
            } else {
                $tag = static::nextTag($letter);
            }
        }
        if (preg_match('/^[A-Za-z0-9]{1,5}$/', $tag) !== 1) {
            return $this->back($existing, 'error', 'A tag is 1 to 5 letters and digits, like S12 (its first letter is the kind of pack).');
        }
        $taken = BeatmapPack::where('tag', $tag)->when($existing !== null, fn ($q) => $q->where('pack_id', '<>', $existing->getKey()))->exists();
        if ($taken) {
            return $this->back($existing, 'error', "There's already a pack {$tag}.");
        }

        $playmode = Request::input('playmode');
        $playmode = $playmode === null || $playmode === '' ? null : (int) $playmode;
        if ($playmode !== null && !array_key_exists($playmode, static::MODES)) {
            return $this->back($existing, 'error', 'That isn\'t a mode.');
        }

        try {
            $date = present(Request::input('date')) ? Carbon::parse(Request::input('date'), 'UTC') : ($existing?->date ?? Carbon::now());
        } catch (\Throwable) {
            return $this->back($existing, 'error', 'That date isn\'t a date.');
        }

        // The download: built by the game server, or a pasted link.
        if (Request::input('download') === 'link') {
            $url = trim((string) Request::input('url'));
            if ($url !== '' && (strlen($url) > 1024 || !preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false)) {
                return $this->back($existing, 'error', 'The download link must be a full http(s) address.');
            }
        } else {
            $url = static::builtUrl($tag);
        }

        $author = trim((string) Request::input('author'));
        $values = [
            'name' => $name,
            'tag' => $tag,
            'author' => mb_substr($author === '' ? Auth::user()->username : $author, 0, 255),
            'date' => $date,
            'playmode' => $playmode,
            'no_diff_reduction' => Request::boolean('no_diff_reduction'),
            'hidden' => Request::boolean('hidden'),
            'url' => $url,
        ];

        $pack = DB::transaction(function () use ($existing, $values) {
            if ($existing !== null) {
                $existing->fill($values)->save();

                return $existing;
            }
            // Numbered from 20000 (below are osu!'s pack ids, for pack medals).
            $next = max((int) BeatmapPack::lockForUpdate()->max('pack_id'), static::LOCAL_PACK_FLOOR - 1) + 1;
            $pack = new BeatmapPack($values);
            $pack->pack_id = $next;
            $pack->save();

            return $pack;
        });
        $this->staffLog('packs.save', $pack->getKey(), ($existing === null ? 'Made' : 'Edited')." the beatmap pack {$pack->tag} \"{$pack->name}\"");

        return redirect(route('panel.packs.show', ['pack' => $pack->getKey()]))->with('panel_status', [
            'type' => 'success',
            'message' => $existing === null ? 'Pack made: now add its sets.' : 'Saved.',
            'link' => $pack->hidden ? null : ['url' => route('packs.show', ['pack' => $pack->tag]), 'text' => 'see it'],
        ]);
    }

    public function addSets(string $pack)
    {
        $model = $this->findPack($pack);
        $ids = BnController::parseSetIds((string) Request::input('sets'));
        if (count($ids) === 0) {
            return $this->back($model, 'error', 'Paste set ids or links (https://osu.ppy.sh/beatmapsets/123), one per line or separated by spaces.');
        }
        if (count($ids) > 500) {
            return $this->back($model, 'error', 'Up to 500 sets at a time, please.');
        }

        $known = Beatmapset::whereIn('beatmapset_id', $ids)->pluck('beatmapset_id')->all();
        $missing = array_values(array_diff($ids, $known));
        $failed = [];
        if (count($missing) > 0 && Request::boolean('import')) {
            foreach (array_slice($missing, 0, static::MAX_IMPORTS) as $setId) {
                try {
                    if ($this->tomyum()->post("/beatmapsets/{$setId}/import")->successful()) {
                        $known[] = $setId;
                        continue;
                    }
                } catch (\Throwable $e) {
                    log_error($e);
                }
                $failed[] = $setId;
            }
            $missing = array_values(array_diff($ids, $known));
        }

        $already = $model->items()->pluck('beatmapset_id')->all();
        $added = array_values(array_diff(array_intersect($ids, $known), $already));
        foreach ($added as $setId) {
            BeatmapPackItem::create(['pack_id' => $model->getKey(), 'beatmapset_id' => $setId]);
        }
        if (count($added) > 0) {
            $this->staffLog('packs.sets', $model->getKey(), "Added ".count($added)." set(s) to the beatmap pack {$model->tag}: ".implode(', ', $added));
        }

        $message = count($added).' set(s) added.';
        if (count($missing) > 0) {
            $message .= ' Not on this server yet: '.implode(', ', array_slice($missing, 0, 30)).(count($missing) > 30 ? ', …' : '').'.';
            $message .= Request::boolean('import')
                ? (count($missing) > count($failed) ? ' Only '.static::MAX_IMPORTS.' are fetched from osu! at a time: add the rest again.' : ' osu! didn\'t give them.')
                : ' Tick "fetch missing sets from osu!" to get them.';
        }

        return $this->back($model, count($added) > 0 ? 'success' : 'error', $message);
    }

    public function removeSet(string $pack, string $set)
    {
        $model = $this->findPack($pack);
        $setId = get_int($set);
        $removed = $model->items()->where('beatmapset_id', $setId)->delete();
        if ($removed > 0) {
            $this->staffLog('packs.sets.remove', $model->getKey(), "Removed set {$setId} from the beatmap pack {$model->tag}");
        }

        return $this->back($model, 'success', 'Removed.');
    }

    public function download(string $pack)
    {
        $model = $this->findPack($pack);
        try {
            $response = $this->tomyum()->post('/packs/'.rawurlencode($model->tag).'/download');
        } catch (\Throwable $e) {
            log_error($e);

            return $this->back($model, 'error', 'The game server can\'t be reached.');
        }
        if (!$response->successful()) {
            return $this->back($model, 'error', $response->json('error') ?? "The game server couldn't start it (HTTP {$response->status()}).");
        }
        $this->staffLog('packs.download', $model->getKey(), "Built the download of the beatmap pack {$model->tag}");

        return $this->back($model, 'success', $response->json('state') === 'ready'
            ? 'The download is ready.'
            : 'The download is being put together: reload this page in a few minutes.');
    }

    public function destroy(string $pack)
    {
        $model = $this->findPack($pack);
        DB::transaction(function () use ($model) {
            $model->items()->delete();
            $model->delete();
        });
        $this->staffLog('packs.remove', $model->getKey(), "Removed the beatmap pack {$model->tag} \"{$model->name}\"");

        return redirect(route('panel.packs.index'))->with('panel_status', ['type' => 'success', 'message' => "Removed {$model->tag}.", 'link' => null]);
    }

    /** The next free tag of a kind: S1, S2, … */
    private static function nextTag(string $letter): string
    {
        $next = 1;
        foreach (BeatmapPack::where('tag', 'like', "{$letter}%")->pluck('tag') as $tag) {
            if (preg_match('/^'.preg_quote($letter, '/').'(\d+)$/', $tag, $match) === 1) {
                $next = max($next, (int) $match[1] + 1);
            }
        }

        return $letter.$next;
    }

    private function findPack(string $id): BeatmapPack
    {
        return BeatmapPack::findOrFail(get_int($id));
    }

    private function back(?BeatmapPack $pack, string $type, string $message)
    {
        return redirect($pack === null ? route('panel.packs.index') : route('panel.packs.show', ['pack' => $pack->getKey()]))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}

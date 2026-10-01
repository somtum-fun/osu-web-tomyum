<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers;

use App\Libraries\TomYumChatBridge;
use App\Libraries\TomYumCountryStats;
use App\Models\Beatmapset;
use App\Models\User;

/**
 * InterOp endpoints for the TomYum game server (under /_lio, same signed auth as LegacyInterOp).
 */
class TomYumInterOpController extends Controller
{
    public function indexBeatmapsetDocument($id)
    {
        Beatmapset::withTrashed()->findOrFail($id)->esIndexDocument();

        return response()->noContent();
    }

    public function recalculateCountry($acronym)
    {
        TomYumCountryStats::recalculate($acronym);

        return response()->noContent();
    }

    /** A message sent in-game: to a public channel (`channel`) or a PM (`target_id`). */
    public function chatMessage()
    {
        $params = request()->all();
        $sender = User::findOrFail(get_int($params['sender_id'] ?? null));
        $target = isset($params['target_id']) ? User::find(get_int($params['target_id'])) : null;
        if (isset($params['target_id']) && ($target === null || $target->is($sender))) {
            abort(422, 'target user not found');
        }

        $message = TomYumChatBridge::fromGame(
            $sender,
            get_string($params['channel'] ?? null),
            $target,
            (string) ($params['message'] ?? ''),
            get_bool($params['is_action'] ?? null) ?? false,
        );

        return $message === null
            ? response()->json(['error' => 'no such public channel'], 404)
            : ['message_id' => $message->getKey()];
    }

    /**
     * A set's osu! description (`description`, BBCode, null = leave as is) and current nominators
     * (`nominators`: [{id, username, rulesets}], osu! users), from TomYum's osu! API v2 import.
     */
    public function beatmapsetExtras($id)
    {
        $beatmapset = \App\Models\Beatmapset::findOrFail(get_int($id));
        $description = get_string(request()->input('description'));
        if ($description !== null && trim($description) !== trim($beatmapset->description()->bbcode()->toEditor())) {
            $author = User::find($GLOBALS['cfg']['osu']['legacy']['bancho_bot_user_id']) ?? User::firstOrFail();
            $beatmapset->description()->update($description, $author);
        }

        // Featured artist track (osu!'s track id): the listing's "Featured Artists" filter and the badge.
        if (request()->has('track_id')) {
            $trackId = get_int(request()->input('track_id'));
            if ($beatmapset->track_id !== $trackId) {
                \DB::table('osu_beatmapsets')->where('beatmapset_id', $beatmapset->getKey())->update(['track_id' => $trackId]);
            }
        }

        // Nominators are replaced only when sent (TomYum always sends them, an empty list too).
        \DB::transaction(function () use ($beatmapset) {
            if (!request()->has('nominators')) {
                return;
            }
            \DB::table('tomyum_beatmapset_nominators')->where('beatmapset_id', $beatmapset->getKey())->delete();
            foreach ((array) request()->input('nominators', []) as $nominator) {
                $userId = get_int($nominator['id'] ?? null);
                $username = get_string($nominator['username'] ?? null);
                if ($userId === null || $username === null) {
                    continue;
                }
                \DB::table('tomyum_beatmapset_nominators')->insertOrIgnore([
                    'beatmapset_id' => $beatmapset->getKey(),
                    'osu_user_id' => $userId,
                    'username' => mb_substr($username, 0, 32),
                    'rulesets' => isset($nominator['rulesets']) ? json_encode(array_values((array) $nominator['rulesets'])) : null,
                ]);
            }
        });

        // Who made the set (`creator`: {id, username}) and each difficulty (`mappers`: {beatmap id:
        // [{id, username}]}), osu! users; left as they are when not sent.
        if (request()->has('creator') || request()->has('mappers')) {
            \DB::transaction(function () use ($beatmapset) {
                \DB::table('tomyum_beatmap_mappers')->where('beatmapset_id', $beatmapset->getKey())->delete();
                $rows = [];
                $add = function (int $beatmapId, $user) use ($beatmapset, &$rows) {
                    $userId = get_int($user['id'] ?? null);
                    $username = get_string($user['username'] ?? null);
                    if ($userId !== null && $userId > 0 && $username !== null) {
                        $rows["{$beatmapId}-{$userId}"] = [
                            'beatmapset_id' => $beatmapset->getKey(),
                            'beatmap_id' => $beatmapId,
                            'osu_user_id' => $userId,
                            'username' => mb_substr($username, 0, 32),
                        ];
                    }
                };
                $creator = request()->input('creator');
                if (is_array($creator)) {
                    $add(0, $creator);
                }
                foreach ((array) request()->input('mappers', []) as $beatmapId => $users) {
                    $beatmapId = get_int($beatmapId);
                    if ($beatmapId === null || $beatmapId <= 0) {
                        continue;
                    }
                    foreach ((array) $users as $user) {
                        $add($beatmapId, $user);
                    }
                }
                \DB::table('tomyum_beatmap_mappers')->insertOrIgnore(array_values($rows));
            });
        }

        return response()->noContent();
    }

    /** Creates the game's public channels on the website (`channels`: [{name, description, moderated}]). */
    public function chatChannels()
    {
        return ['created' => TomYumChatBridge::ensureChannels(request()->input('channels') ?? [])];
    }
}

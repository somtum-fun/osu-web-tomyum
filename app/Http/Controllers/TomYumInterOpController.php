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

    /** Creates the game's public channels on the website (`channels`: [{name, description, moderated}]). */
    public function chatChannels()
    {
        return ['created' => TomYumChatBridge::ensureChannels(request()->input('channels') ?? [])];
    }
}

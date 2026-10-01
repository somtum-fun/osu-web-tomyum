<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use Auth;
use Request;

/**
 * Tournament Committee panel (TomYum): the game's live multiplayer rooms (from TomYum's admin API,
 * /_admin/matches), with abort, close and a message in the room's chat; the banners and badges
 * pages are the admin's (Panel\Controller::sharedPanelPrefix).
 */
class TcController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseTc(Auth::user()));

            return $next($request);
        });
    }

    public function index()
    {
        try {
            $response = $this->tomyum()->get('/matches');
            $matches = $response->successful() ? $response->json() : null;
        } catch (\Throwable $e) {
            log_error($e);
            $matches = null;
        }

        return ext_view('panel.tc.index', ['matches' => $matches]);
    }

    public function abort(string $match)
    {
        $id = (int) $match;
        $response = $this->send("/matches/{$id}/abort");
        if ($response === null) {
            return $this->back('error', "The game server couldn't be reached.");
        }
        if ($response->notFound()) {
            return $this->back('error', "Room {$id} isn't open any more.");
        }
        if (!($response->json('aborted') ?? false)) {
            return $this->back('error', "Room {$id} wasn't playing or counting down.");
        }

        $this->staffLog('match.abort', $id, "Aborted the map in room {$id}");

        return $this->back('success', "Aborted room {$id}: its players are back in the room.");
    }

    public function close(string $match)
    {
        $id = (int) $match;
        $response = $this->send("/matches/{$id}/close");
        if ($response === null) {
            return $this->back('error', "The game server couldn't be reached.");
        }
        if ($response->notFound()) {
            return $this->back('error', "Room {$id} isn't open any more.");
        }

        $this->staffLog('match.close', $id, "Closed room {$id}");

        return $this->back('success', "Closed room {$id}: everyone's back in the lobby.");
    }

    public function message(string $match)
    {
        $id = (int) $match;
        $text = trim((string) Request::input('text'));
        if ($text === '' || mb_strlen($text) > 450) {
            return $this->back('error', 'A message needs 1 to 450 characters.');
        }

        $response = $this->send("/matches/{$id}/message", ['text' => $text]);
        if ($response === null) {
            return $this->back('error', "The game server couldn't be reached.");
        }
        if ($response->notFound()) {
            return $this->back('error', "Room {$id} isn't open any more.");
        }

        $this->staffLog('match.message', $id, "Said in room {$id}: {$text}");

        return $this->back('success', "Sent to room {$id}.");
    }

    private function send(string $path, ?array $body = null)
    {
        try {
            $response = $this->tomyum()->post($path, $body);
        } catch (\Throwable $e) {
            log_error($e);

            return null;
        }

        return $response->successful() || $response->notFound() ? $response : null;
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.tc.index'))->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}

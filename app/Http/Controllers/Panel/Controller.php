<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller as BaseController;
use App\Libraries\TomYumAdmin;
use App\Models\User;
use Auth;

/**
 * Staff panels (TomYum). Each panel is open to specific groups; admins can use all of them.
 */
abstract class Controller extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth');

        parent::__construct();
    }

    public static function canUseBn(?User $user): bool
    {
        return $user !== null && ($user->isBNG() || $user->isNAT() || $user->isAdmin());
    }

    public static function canUseGmt(?User $user): bool
    {
        return $user !== null && ($user->isGMT() || $user->isAdmin());
    }

    public static function canUseAdmin(?User $user): bool
    {
        return $user !== null && $user->isAdmin();
    }

    // The Tournament Committee panel: live matches, tournament banners and badges.
    public static function canUseTc(?User $user): bool
    {
        return $user !== null && ($user->isTournamentCommittee() || $user->isAdmin());
    }

    // Project Loved's decisions are on its own page (Community → Project Loved); the panels link to it.
    public static function canUseLoved(?User $user): bool
    {
        return \App\Libraries\TomYumLoved::isTeam($user);
    }

    /**
     * The panel a page shared by the admin and TC panels (tournament banners, badges) was opened
     * from, as its route name prefix: its links stay in that panel.
     */
    public static function sharedPanelPrefix(): string
    {
        return str_starts_with(\Route::currentRouteName() ?? '', 'panel.tc.') ? 'panel.tc.' : 'panel.admin.';
    }

    /** Who may open a shared page: the TC panel's for the TC (and admins), the admin panel's for admins. */
    protected static function canUseSharedPanel(?User $user): bool
    {
        return static::sharedPanelPrefix() === 'panel.tc.' ? static::canUseTc($user) : static::canUseAdmin($user);
    }

    public static function canUseAny(?User $user): bool
    {
        return static::canUseBn($user) || static::canUseGmt($user) || static::canUseTc($user) || static::canUseLoved($user) || static::canUseAdmin($user)
            || \App\Libraries\TomYumBots::canManageAll($user)
            || TicketsController::canUseTickets($user);
    }

    protected function ensure(bool $allowed): void
    {
        if (!$allowed) {
            abort(403);
        }
    }

    protected function tomyum(): TomYumAdmin
    {
        return new TomYumAdmin(Auth::user());
    }

    /** Records an action in TomYum's staff log (best effort: the action already happened). */
    protected function staffLog(string $action, ?int $targetId, string $summary, ?string $result = null): void
    {
        try {
            $this->tomyum()->post('/log', [
                'action' => $action,
                'targetId' => $targetId,
                'summary' => $summary,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            log_error($e);
        }
    }

    /**
     * Applies a change to a user in-game right away (restriction, silence, name, groups). Returns
     * false when the game server couldn't be reached: the change still applies on their next login.
     */
    protected function refreshInGame(int $userId): bool
    {
        try {
            return $this->tomyum()->post("/users/{$userId}/refresh")->successful();
        } catch (\Throwable $e) {
            log_error($e);

            return false;
        }
    }

    /**
     * Whether the current user may moderate $target: anyone may moderate themselves; otherwise only
     * admins may act on staff (admins, GMT).
     */
    protected static function canModerate(User $target): bool
    {
        $actor = Auth::user();
        if ($actor === null) {
            return false;
        }

        return $actor->getKey() === $target->getKey()
            || $actor->isAdmin()
            || !($target->isAdmin() || $target->isGMT());
    }
}

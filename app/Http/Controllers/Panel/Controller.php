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

    public static function canUseAny(?User $user): bool
    {
        return static::canUseBn($user) || static::canUseGmt($user) || static::canUseAdmin($user);
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
}

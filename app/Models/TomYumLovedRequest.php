<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Models;

use DB;

/**
 * TomYum: a player's request for a map to be in a Project Loved round, in one mode
 * (App\Libraries\TomYumLoved). Others support it; the Loved team picks or declines it.
 *
 * @property int $beatmapset_id
 * @property string|null $decline_reason
 * @property int|null $handled_by
 * @property int $id
 * @property int|null $nomination_id
 * @property string $reason
 * @property int $ruleset_id
 * @property string $status open, picked or declined
 * @property int $supporters_count
 * @property int $user_id
 */
class TomYumLovedRequest extends Model
{
    protected $table = 'tomyum_loved_requests';

    public function beatmapset()
    {
        return $this->belongsTo(Beatmapset::class, 'beatmapset_id');
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function nomination()
    {
        return $this->belongsTo(TomYumLovedNomination::class, 'nomination_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isSupportedBy(?User $user): bool
    {
        return $user !== null && DB::table('tomyum_loved_request_supporters')
            ->where(['request_id' => $this->getKey(), 'user_id' => $user->getKey()])
            ->exists();
    }

    /** Adds or takes away $user's support; the request goes when nobody supports it any more. */
    public function setSupport(User $user, bool $support): void
    {
        DB::transaction(function () use ($user, $support) {
            $key = ['request_id' => $this->getKey(), 'user_id' => $user->getKey()];
            if ($support) {
                DB::table('tomyum_loved_request_supporters')->insertOrIgnore([...$key, 'created_at' => now()]);
            } else {
                DB::table('tomyum_loved_request_supporters')->where($key)->delete();
            }
            $count = DB::table('tomyum_loved_request_supporters')->where('request_id', $this->getKey())->count();
            if ($count === 0) {
                $this->delete();
            } else {
                $this->update(['supporters_count' => $count]);
            }
        });
    }

    /** The request was picked into a round (or its map added by hand): done. */
    public function markPicked(TomYumLovedNomination $nomination, User $by): void
    {
        $this->update(['status' => 'picked', 'nomination_id' => $nomination->getKey(), 'handled_by' => $by->getKey()]);
    }
}

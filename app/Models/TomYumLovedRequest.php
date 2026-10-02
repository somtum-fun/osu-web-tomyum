<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Models;

/**
 * TomYum: a player's request for a map to be Loved in one mode (App\Libraries\TomYumLoved). Others
 * review it, for or against; the Loved team loves, rejects or removes it. One per map and mode.
 *
 * @property int $beatmapset_id
 * @property \Carbon\Carbon|null $decided_at
 * @property string|null $decision_reason
 * @property int|null $handled_by
 * @property int $id
 * @property string $reason
 * @property int $ruleset_id
 * @property int $score The reviews' sum (+1 for, -1 against), the asker's "for" included
 * @property string $status open, loved, rejected or removed
 * @property int $user_id
 */
class TomYumLovedRequest extends Model
{
    const STATUSES = ['open', 'loved', 'rejected', 'removed'];

    protected $casts = ['decided_at' => 'datetime'];
    protected $table = 'tomyum_loved_requests';

    public function beatmapset()
    {
        return $this->belongsTo(Beatmapset::class, 'beatmapset_id');
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function reviews()
    {
        return $this->hasMany(TomYumLovedReview::class, 'request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function reviewBy(?User $user): ?TomYumLovedReview
    {
        return $user === null ? null : $this->reviews()->where('user_id', $user->getKey())->first();
    }

    /** The score from the reviews: the asker's "for", plus one for each "for", minus one for each "against". */
    public function refreshScore(): void
    {
        $this->update(['score' => 1 + (int) $this->reviews()->sum('score')]);
    }
}

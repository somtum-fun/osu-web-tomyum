<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Models;

/**
 * TomYum: a Project Loved round (App\Libraries\TomYumLoved).
 *
 * @property \Carbon\Carbon|null $closed_at
 * @property int $id
 * @property int $min_votes
 * @property string $name
 * @property string $state picking, voting or closed
 * @property int $threshold yes votes needed, in percent
 * @property \Carbon\Carbon|null $voting_ends_at
 */
class TomYumLovedRound extends Model
{
    const STATES = ['picking', 'voting', 'closed'];

    protected $casts = [
        'closed_at' => 'datetime',
        'voting_ends_at' => 'datetime',
    ];
    protected $table = 'tomyum_loved_rounds';

    /** The rounds the public sees: voting or done (picking ones are the team's). */
    public static function public()
    {
        return static::whereIn('state', ['voting', 'closed']);
    }

    public function nominations()
    {
        return $this->hasMany(TomYumLovedNomination::class, 'round_id');
    }

    public function isPicking(): bool
    {
        return $this->state === 'picking';
    }

    public function isVoting(): bool
    {
        return $this->state === 'voting';
    }

    public function isClosed(): bool
    {
        return $this->state === 'closed';
    }
}

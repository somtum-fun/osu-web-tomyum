<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Models;

/**
 * @property \Carbon\Carbon|null $created_at
 * @property int $registration_id
 * @property Tournament $tournament
 * @property int $tournament_id
 * @property \Carbon\Carbon|null $updated_at
 * @property User $user
 * @property int $user_id
 * @property \Carbon\Carbon|null $checked_at TomYum: when the requirements were checked again
 * @property string|null $country_acronym TomYum: their country when they signed up
 * @property array|null $ineligible_reasons TomYum: why they no longer qualify (null: they do)
 */
class TournamentRegistration extends Model
{
    protected $primaryKey = 'registration_id';

    // TomYum: the check made again when registration closed.
    protected $casts = [
        'checked_at' => 'datetime',
        'ineligible_reasons' => 'array',
    ];

    public function getDates()
    {
        return ['created_at', 'updated_at', 'signup_open', 'signup_close', 'start_date', 'end_date'];
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class, 'tournament_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

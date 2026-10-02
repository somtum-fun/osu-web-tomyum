<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Models;

/**
 * TomYum: a player's review of a Loved request: for (1) or against (-1), and why.
 *
 * @property string $comment
 * @property int $id
 * @property int $request_id
 * @property int $score
 * @property int $user_id
 */
class TomYumLovedReview extends Model
{
    protected $table = 'tomyum_loved_request_reviews';

    public function request()
    {
        return $this->belongsTo(TomYumLovedRequest::class, 'request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

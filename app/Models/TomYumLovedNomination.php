<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Models;

use App\Models\Forum\Topic;

/**
 * TomYum: a map in a Project Loved round, for one mode (App\Libraries\TomYumLoved).
 *
 * @property int $beatmapset_id
 * @property int $captain_id
 * @property string|null $description
 * @property int $id
 * @property bool|null $passed
 * @property int|null $result_no
 * @property int|null $result_yes
 * @property int $round_id
 * @property int $ruleset_id
 * @property int|null $topic_id
 */
class TomYumLovedNomination extends Model
{
    protected $casts = [
        'passed' => 'boolean',
    ];
    protected $table = 'tomyum_loved_nominations';

    public function beatmapset()
    {
        return $this->belongsTo(Beatmapset::class, 'beatmapset_id');
    }

    public function captain()
    {
        return $this->belongsTo(User::class, 'captain_id');
    }

    public function round()
    {
        return $this->belongsTo(TomYumLovedRound::class, 'round_id');
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }

    /** The captain's write-up (BBCode, as in the topic) as HTML. */
    public function descriptionHTML(): ?string
    {
        return present($this->description)
            ? bbcode((new \App\Libraries\BBCodeForDB($this->description))->generate())
            : null;
    }

    /** The set's difficulties in this nomination's mode (what loving it loves). */
    public function beatmapIds(): array
    {
        return $this->beatmapset?->beatmaps()->where('playmode', $this->ruleset_id)->pluck('beatmap_id')->all() ?? [];
    }
}

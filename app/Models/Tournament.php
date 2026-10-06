<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Models;

use App\Exceptions\InvariantException;
use App\Models\Store\Product;
use Carbon\Carbon;
use DB;

/**
 * @property \Carbon\Carbon|null $created_at
 * @property string $description
 * @property \Carbon\Carbon $end_date
 * @property string|null $header_banner
 * @property string|null $info_url
 * @property string $name
 * @property int $play_mode
 * @property \Illuminate\Database\Eloquent\Collection $profileBanners ProfileBanner
 * @property int|null $rank_max
 * @property int|null $rank_min
 * @property \Illuminate\Database\Eloquent\Collection $registrations TournamentRegistration
 * @property \Carbon\Carbon $signup_close
 * @property \Carbon\Carbon $signup_open
 * @property \Carbon\Carbon $start_date
 * @property int|null $tournament_banner_product_id
 * @property int $tournament_id
 * @property \Carbon\Carbon|null $updated_at
 * @property int|null $account_age_days TomYum
 * @property array|null $countries TomYum: country acronyms allowed (null: everyone)
 * @property int|null $created_by TomYum
 * @property bool $is_banner_only TomYum: made by the tournament banners page, not listed
 * @property bool $is_hidden TomYum: a draft, only staff see it
 * @property int|null $max_registrations TomYum
 * @property \Carbon\Carbon|null $rechecked_at TomYum: when the registrations were last checked again
 * @property bool $requires_discord TomYum
 * @property int|null $restriction_days TomYum
 * @property int|null $silence_days TomYum
 */
class Tournament extends Model
{
    protected $primaryKey = 'tournament_id';

    protected $casts = [
        'end_date' => 'datetime',
        'signup_close' => 'datetime',
        'signup_open' => 'datetime',
        'start_date' => 'datetime',
        // TomYum: who may register (Panel\TournamentsController).
        'countries' => 'array',
        'is_banner_only' => 'boolean',
        'is_hidden' => 'boolean',
        'rechecked_at' => 'datetime',
        'requires_discord' => 'boolean',
    ];

    public static function getGroupedListing()
    {
        $tournaments = static::query()
            ->visible() // TomYum: not drafts or the banners page's rows
            ->with('registrations')
            ->orderBy('tournament_id', 'desc')
            ->get();

        $now = Carbon::now();

        return [
            'current' => $tournaments->where('end_date', '>', $now),
            'previous' => $tournaments->where('end_date', '<=', $now),
        ];
    }

    // TomYum: the tournaments everyone sees (drafts and banner-only rows are for staff).
    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false)->where('is_banner_only', false);
    }

    // TomYum: whether $user may open its page (staff see drafts, from the panel's link). A
    // banner-only one isn't listed, but its page stays: profile banners link to it.
    public function isVisibleTo(?User $user): bool
    {
        return !$this->is_hidden || \App\Http\Controllers\Panel\Controller::canUseTc($user);
    }

    public function profileBanners()
    {
        return $this->hasMany(ProfileBanner::class);
    }

    public function registrations()
    {
        return $this->hasMany(TournamentRegistration::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'tournament_banner_product_id');
    }

    public function isRegistrationOpen()
    {
        $now = Carbon::now();

        return $this->signup_open < $now && $this->signup_close > $now;
    }

    public function isTournamentRunning()
    {
        $now = Carbon::now();

        return $this->start_date < $now && $this->end_date > $now;
    }

    public function isStoreBannerAvailable()
    {
        return $this->tournament_banner_product_id !== null && $this->product->isAvailable();
    }

    public function isSignedUp($user)
    {
        if (!$user) {
            return false;
        }

        return $this->registrations()->where('user_id', '=', $user->user_id)->exists();
    }

    public function isValidRank($user)
    {
        if (!$user) {
            return false;
        }

        $userRank = UserStatistics\Model
            ::getClass($this->playModeStr(), $this->play_mode_variant)
            ::firstOrNew(['user_id' => $user->getKey()])
            ->globalRank();

        if ($this->rank_min !== null && ($userRank === null || $this->rank_min > $userRank)) {
            return false;
        }

        if ($this->rank_max !== null && ($userRank === null || $this->rank_max < $userRank)) {
            return false;
        }

        return true;
    }

    public function unregister($user)
    {
        //sanity check: we shouldn't be touching users once the tournament is already in action.
        if ($this->isTournamentRunning()) {
            throw new InvariantException('tournament is already running');
        }

        $this->registrations()->where('user_id', '=', $user->user_id)->delete();
    }

    public function register($user)
    {
        if ($this->isSignedUp($user)) {
            return;
        }

        //sanity check: we shouldn't be touching users once the tournament is already in action.
        if ($this->isTournamentRunning()) {
            throw new InvariantException('tournament is already running');
        }

        $reg = new TournamentRegistration();
        $reg->user()->associate($user);
        $reg->country_acronym = $user->country_acronym; // TomYum: their country when they signed up

        $this->registrations()->save($reg);
    }

    /**
     * TomYum: registers $user if they may (the player cap is checked with the tournament locked, so
     * it can't be passed by registering at the same time). Returns why they can't (empty: done).
     */
    public function registerChecked(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $tournament = static::lockForUpdate()->findOrFail($this->getKey());
            if ($tournament->isSignedUp($user)) {
                return [];
            }
            $failures = $tournament->registrationFailures($user);
            if (count($failures) === 0) {
                $tournament->register($user);
            }

            return $failures;
        });
    }

    // TomYum: the player's global rank in the tournament's mode (null: none).
    public function userRank(User $user): ?int
    {
        return UserStatistics\Model
            ::getClass($this->playModeStr(), $this->play_mode_variant)
            ::firstOrNew(['user_id' => $user->getKey()])
            ->globalRank();
    }

    public function isFull(): bool
    {
        return $this->max_registrations !== null && $this->registrations()->count() >= $this->max_registrations;
    }

    public function modeName(): string
    {
        $name = osu_trans('beatmaps.mode.'.$this->playModeStr());

        return $this->play_mode_variant === null ? $name : "{$name} {$this->play_mode_variant}";
    }

    /** TomYum: what a player needs to register, as [key => text] (shown on the tournament's page). */
    public function requirements(): array
    {
        $requirements = [];
        $mode = $this->modeName();
        if (($range = $this->rankRange()) !== null) {
            $requirements['rank'] = osu_trans('tournament.requirements.rank', ['mode' => $mode, 'range' => $range]);
        }
        if (count($this->countries ?? []) > 0) {
            $requirements['country'] = osu_trans('tournament.requirements.countries', ['countries' => $this->countryNames()]);
        }
        $requirements['standing'] = osu_trans('tournament.requirements.standing');
        if ($this->restriction_days !== null) {
            $requirements['restriction'] = osu_trans('tournament.requirements.restriction', ['days' => static::daysText($this->restriction_days)]);
        }
        if ($this->silence_days !== null) {
            $requirements['silence'] = osu_trans('tournament.requirements.silence', ['days' => static::daysText($this->silence_days)]);
        }
        if ($this->account_age_days !== null) {
            $requirements['account_age'] = osu_trans('tournament.requirements.account_age', ['days' => static::daysText($this->account_age_days)]);
        }
        if ($this->requires_discord) {
            $requirements['discord'] = osu_trans('tournament.requirements.discord');
        }

        return $requirements;
    }

    /**
     * TomYum: every reason $user can't register right now, as [key => friendly text] (empty: they
     * can): the requirements' keys, plus closed and full.
     */
    public function registrationFailures(User $user): array
    {
        $failures = [];
        if ($this->is_banner_only || !$this->isRegistrationOpen()) {
            $failures['closed'] = osu_trans('tournament.requirements.failed.closed');
        } elseif ($this->isFull()) {
            $failures['full'] = osu_trans('tournament.requirements.failed.full');
        }

        return $failures + $this->requirementFailures($user);
    }

    /** TomYum: the requirements $user doesn't meet, as [key => friendly text]. */
    public function requirementFailures(User $user): array
    {
        $failures = [];
        $now = Carbon::now();

        if ($this->rank_min !== null || $this->rank_max !== null) {
            $rank = $this->userRank($user);
            $mode = $this->modeName();
            if ($rank === null) {
                $failures['rank'] = osu_trans('tournament.requirements.failed.rank_none', ['mode' => $mode]);
            } elseif (($this->rank_min !== null && $rank < $this->rank_min) || ($this->rank_max !== null && $rank > $this->rank_max)) {
                $failures['rank'] = osu_trans('tournament.requirements.failed.rank', [
                    'mode' => $mode,
                    'rank' => i18n_number_format($rank),
                    'range' => $this->rankRange(),
                ]);
            }
        }

        $countries = $this->countries ?? [];
        if (count($countries) > 0 && !in_array($user->country_acronym, $countries, true)) {
            $country = present($user->country_acronym) ? presence(app('countries')->byCode($user->country_acronym)?->name) : null;
            $failures['country'] = $country === null
                ? osu_trans('tournament.requirements.failed.country_none', ['countries' => $this->countryNames()])
                : osu_trans('tournament.requirements.failed.country', ['country' => $country, 'countries' => $this->countryNames()]);
        }

        if ($user->isRestricted()) {
            $failures['standing'] = osu_trans('tournament.requirements.failed.restricted');
        } elseif (static::hasTournamentBan($user)) {
            $failures['standing'] = osu_trans('tournament.requirements.failed.tournament_ban');
        }

        $history = UserAccountHistory::where('user_id', $user->getKey());
        if ($this->restriction_days !== null) {
            $since = $now->copy()->subDays($this->restriction_days);
            // Restricted, or lifted (the panels' "Unrestricted: …" note), in the window.
            $recent = (clone $history)
                ->where('timestamp', '>=', $since)
                ->where(fn ($q) => $q
                    ->where('ban_status', UserAccountHistory::TYPES['restriction'])
                    ->orWhere(fn ($q) => $q
                        ->where('ban_status', UserAccountHistory::TYPES['note'])
                        ->where('reason', 'like', 'Unrestricted:%')))
                ->exists();
            if ($recent && !isset($failures['standing'])) {
                $failures['restriction'] = osu_trans('tournament.requirements.failed.restriction', ['days' => static::daysText($this->restriction_days)]);
            }
        }
        if ($this->silence_days !== null) {
            $since = $now->copy()->subDays($this->silence_days);
            if (static::silencedSince($user, $since)) {
                $failures['silence'] = osu_trans('tournament.requirements.failed.silence', ['days' => static::daysText($this->silence_days)]);
            }
        }

        if ($this->account_age_days !== null) {
            $registered = $user->user_regdate;
            if ($registered === null || $registered->gt($now->copy()->subDays($this->account_age_days))) {
                $failures['account_age'] = osu_trans('tournament.requirements.failed.account_age', [
                    'days' => static::daysText($this->account_age_days),
                    'age' => static::daysText($registered === null ? 0 : (int) $registered->diffInDays($now)),
                ]);
            }
        }

        if ($this->requires_discord && !DB::table('tomyum.discord_links')->where('user_id', $user->getKey())->exists()) {
            $failures['discord'] = osu_trans('tournament.requirements.failed.discord');
        }

        return $failures;
    }

    /**
     * TomYum: checks every registered player again (when registration closes, and from the panel):
     * who no longer meets the requirements is flagged for staff. Returns how many are flagged.
     */
    public function recheckRegistrations(): int
    {
        $flagged = 0;
        $now = Carbon::now();
        foreach ($this->registrations()->with('user')->get() as $registration) {
            $reasons = $registration->user === null
                ? [osu_trans('tournament.requirements.failed.deleted')]
                : array_values($this->requirementFailures($registration->user));
            $registration->update(['ineligible_reasons' => count($reasons) === 0 ? null : $reasons, 'checked_at' => $now]);
            if (count($reasons) > 0) {
                $flagged++;
            }
        }
        $this->update(['rechecked_at' => $now]);

        return $flagged;
    }

    /**
     * TomYum: whether $user was silenced at any time since $since. A silence ends when its length is
     * up, or earlier when a newer silence row comes (a lift is one with no length, as in User::isSilenced).
     */
    public static function silencedSince(User $user, Carbon $since): bool
    {
        $rows = UserAccountHistory::where('user_id', $user->getKey())
            ->where('ban_status', UserAccountHistory::TYPES['silence'])
            ->orderBy('timestamp')
            ->orderBy('ban_id')
            ->get(['ban_id', 'timestamp', 'period', 'permanent'])
            ->values();
        foreach ($rows as $index => $row) {
            if ($row->timestamp === null || ($row->period <= 0 && !$row->permanent)) {
                continue;
            }
            $end = $row->permanent ? null : $row->endTime();
            $next = $rows[$index + 1]->timestamp ?? null;
            if ($next !== null && ($end === null || $next->lt($end))) {
                $end = $next;
            }
            if ($end === null || $end->gte($since)) {
                return true;
            }
        }

        return false;
    }

    /** TomYum: an active tournament ban (the newest one; a later one with no length lifts it). */
    public static function hasTournamentBan(User $user): bool
    {
        $ban = UserAccountHistory::where('user_id', $user->getKey())
            ->where('ban_status', UserAccountHistory::TYPES['tournament_ban'])
            ->orderByDesc('timestamp')
            ->orderByDesc('ban_id')
            ->first();

        return $ban !== null && ($ban->permanent || ($ban->timestamp !== null && $ban->endTime()->isFuture()));
    }

    /** TomYum: the ranks allowed, like "#1 to #10,000" (null: any). rank_min is the best rank, rank_max the worst. */
    public function rankRange(): ?string
    {
        $min = $this->rank_min === null ? null : i18n_number_format($this->rank_min);
        $max = $this->rank_max === null ? null : i18n_number_format($this->rank_max);

        return match (true) {
            $min !== null && $max !== null => osu_trans('tournament.requirements.range.between', ['min' => $min, 'max' => $max]),
            $max !== null => osu_trans('tournament.requirements.range.max', ['max' => $max]),
            $min !== null => osu_trans('tournament.requirements.range.min', ['min' => $min]),
            default => null,
        };
    }

    // TomYum: a number of days, like "1 day" or "30 days".
    private static function daysText(int $days): string
    {
        return osu_trans_choice('common.count.days', $days);
    }

    private function countryNames(): string
    {
        return implode(', ', array_map(
            fn ($code) => app('countries')->byCode($code)?->name ?? $code,
            $this->countries ?? [],
        ));
    }

    public function playModeStr()
    {
        return Beatmap::modeStr($this->play_mode);
    }

    public function pageLinks()
    {
        $links = [];

        if ($this->info_url !== null) {
            $links[] = [
                'url' => $this->info_url,
                'title' => osu_trans('tournament.show.info_page'),
            ];
        }

        if ($this->isStoreBannerAvailable()) {
            $links[] = [
                'url' => route('store.products.show', $this->tournament_banner_product_id),
                'title' => osu_trans('tournament.show.banner'),
            ];
        }

        return $links;
    }
}

<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumAssets;
use App\Models\Beatmap;
use App\Models\Country;
use App\Models\Tournament;
use App\Models\TournamentBanner;
use App\Models\TournamentRegistration;
use App\Models\User;
use App\Models\UserAccountHistory;
use App\Models\UserStatistics;
use Auth;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use DB;
use Request;

/**
 * TomYum: the TC's and admins' tournaments (the Community → Tournaments pages, like osu!'s): their
 * details, dates and header image, who may register (rank range, countries, a clean record, account
 * age, Discord, a player cap), and the registered players (remove one, export them as CSV, see who
 * no longer qualifies when registration closes). Solo registration only. Only admins may remove a
 * tournament players have registered for. The banners page's tournaments (banner-only) are listed
 * here too, for linking them up, but stay off the public list. Tournament bans (osu!'s account
 * history kind, shown on the player's profile) are given and lifted here too: they always block.
 */
class TournamentsController extends Controller
{
    public const MODES = [0 => 'osu!', 1 => 'osu!taiko', 2 => 'osu!catch', 3 => 'osu!mania'];

    /** The defaults for a new tournament's clean record (days without a restriction, a silence). */
    private const DEFAULT_RESTRICTION_DAYS = 180;
    private const DEFAULT_SILENCE_DAYS = 30;

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseSharedPanel(Auth::user())); // the admin's and the TC's

            return $next($request);
        });
    }

    public function index()
    {
        $tournaments = Tournament::withCount('registrations')
            ->orderByDesc('signup_open')
            ->orderByDesc('tournament_id')
            ->get();

        return ext_view('panel.admin.tournaments', [
            'tournaments' => $tournaments,
            'banners' => TournamentBanner::whereIn('tournament_id', $tournaments->pluck('tournament_id'))->pluck('tournament_id')->flip(),
            'bans' => static::activeBans(),
        ]);
    }

    /** Bans a player from registering for tournaments: for some days, or until lifted (no length). */
    public function ban()
    {
        $name = trim((string) Request::input('user'));
        $user = $name === '' ? null : (ctype_digit($name) ? User::find((int) $name) : User::lookup($name, 'username'));
        if ($user === null) {
            return $this->backToIndex('error', "There's no player called \"{$name}\".", 'bans');
        }
        $reason = mb_substr(trim((string) Request::input('reason')), 0, 2000);
        if ($reason === '') {
            return $this->backToIndex('error', 'Give a reason (the player can see it on their profile).', 'bans');
        }
        $days = trim((string) Request::input('days'));
        if ($days !== '' && (!ctype_digit($days) || (int) $days < 1 || (int) $days > 3650)) {
            return $this->backToIndex('error', 'The length should be a whole number of days from 1 to 3,650 (or empty: until lifted).', 'bans');
        }
        if (Tournament::hasTournamentBan($user)) {
            return $this->backToIndex('error', "{$user->username} has a tournament ban already (lift it first to change it).", 'bans');
        }

        $permanent = $days === '';
        UserAccountHistory::create([
            'user_id' => $user->getKey(),
            'banner_id' => Auth::user()->getKey(),
            'ban_status' => UserAccountHistory::TYPES['tournament_ban'],
            'reason' => $reason,
            'period' => $permanent ? 0 : (int) $days * 86400,
            'permanent' => $permanent,
            'timestamp' => Carbon::now(),
        ]);
        $length = $permanent ? 'until lifted' : "for {$days} days";
        $this->staffLog('user.tournament_ban', $user->getKey(), "Banned {$user->username} from tournaments {$length}: {$reason}");

        return $this->backToIndex('success', "{$user->username} is banned from tournaments {$length}.", 'bans');
    }

    /** Lifts a player's tournament ban (a newer one with no length ends it, like a silence's lift). */
    public function unban(string $user)
    {
        $user = User::findOrFail((int) $user);
        if (!Tournament::hasTournamentBan($user)) {
            return $this->backToIndex('error', "{$user->username} has no tournament ban.", 'bans');
        }
        $reason = presence(mb_substr(trim((string) Request::input('reason')), 0, 1900)) ?? 'Lifted early';

        UserAccountHistory::create([
            'user_id' => $user->getKey(),
            'banner_id' => Auth::user()->getKey(),
            'ban_status' => UserAccountHistory::TYPES['tournament_ban'],
            'reason' => "Tournament ban lifted: {$reason}",
            'period' => 0,
            'permanent' => false,
            'timestamp' => Carbon::now(),
        ]);
        $this->staffLog('user.tournament_unban', $user->getKey(), "Lifted {$user->username}'s tournament ban: {$reason}");

        return $this->backToIndex('success', "Lifted {$user->username}'s tournament ban.", 'bans');
    }

    public function store()
    {
        $name = trim((string) Request::input('name'));
        if ($name === '' || mb_strlen($name) > 255) {
            return $this->backToIndex('error', 'A tournament needs a name (up to 255 characters).');
        }
        $mode = (int) Request::input('mode', 0);
        if (!array_key_exists($mode, static::MODES)) {
            $mode = 0;
        }

        // Hidden until it's ready, with dates to change: registration for two weeks from now.
        $now = CarbonImmutable::now()->startOfHour();
        $tournament = Tournament::create([
            'name' => $name,
            'description' => '',
            'play_mode' => $mode,
            'signup_open' => $now,
            'signup_close' => $now->addDays(14),
            'start_date' => $now->addDays(21),
            'end_date' => $now->addDays(60),
            'restriction_days' => static::DEFAULT_RESTRICTION_DAYS,
            'silence_days' => static::DEFAULT_SILENCE_DAYS,
            'is_hidden' => true,
            'created_by' => Auth::user()->getKey(),
        ]);
        $this->staffLog('tournament.add', $tournament->getKey(), "Made the tournament \"{$name}\"");

        return $this->back($tournament, 'success', 'Made it (hidden until you show it). Fill in the rest below.');
    }

    public function show(string $tournament)
    {
        $tournament = Tournament::findOrFail((int) $tournament);
        $registrations = $tournament->registrations()->with('user')->orderBy('registration_id')->get();
        $stats = $this->statsFor($tournament, $registrations->pluck('user_id')->all());

        try {
            $log = $this->tomyum()->get('/log', ['action' => 'tournament.', 'targetId' => $tournament->getKey(), 'limit' => 50]);
            $entries = $log->successful() ? $log->json() : [];
        } catch (\Throwable $e) {
            $entries = [];
        }

        return ext_view('panel.admin.tournament', [
            'tournament' => $tournament,
            'registrations' => $registrations,
            'stats' => $stats,
            'hasBanner' => TournamentBanner::where('tournament_id', $tournament->getKey())->exists(),
            'canDelete' => $registrations->isEmpty() || static::canUseAdmin(Auth::user()),
            'modes' => static::MODES,
            'entries' => $entries,
            'actors' => BnController::actorNames($entries),
        ]);
    }

    public function update(string $tournament)
    {
        $tournament = Tournament::findOrFail((int) $tournament);
        [$fields, $problem] = static::fields();
        if ($problem !== null) {
            return $this->back($tournament, 'error', $problem);
        }

        $tournament->update($fields);
        $this->staffLog('tournament.edit', $tournament->getKey(), "Edited the tournament \"{$tournament->name}\"".($tournament->is_hidden ? ' (hidden)' : ''));

        return $this->back($tournament, 'success', $tournament->is_hidden ? 'Saved (still hidden from players).' : 'Saved.');
    }

    /** Its header image (on the tournament's page and the list): uploaded to the assets host, at both sizes. */
    public function header(string $tournament)
    {
        $tournament = Tournament::findOrFail((int) $tournament);
        $file = Request::file('header');
        if ($file === null) {
            return $this->back($tournament, 'error', 'Choose an image.');
        }

        // A new name each time, so nobody gets the old one from a cache.
        $path = "tournament-banners/headers/{$tournament->getKey()}-".time().'.jpg';
        $problem = (new TomYumAssets(Auth::user()))->save($path, null, $file);
        if ($problem !== null) {
            return $this->back($tournament, 'error', $problem);
        }

        $tournament->update(['header_banner' => TomYumAssets::url($path)]);
        $this->staffLog('tournament.edit', $tournament->getKey(), "Changed the header image of the tournament \"{$tournament->name}\"");

        return $this->back($tournament, 'success', 'Changed the header image.');
    }

    /** Checks every registered player against the requirements again (also done by itself when registration closes). */
    public function recheck(string $tournament)
    {
        $tournament = Tournament::findOrFail((int) $tournament);
        $flagged = $tournament->recheckRegistrations();
        $this->staffLog('tournament.recheck', $tournament->getKey(), "Checked the registrations of \"{$tournament->name}\" again: {$flagged} no longer qualify");

        return $this->back(
            $tournament,
            'success',
            $flagged === 0 ? 'Checked: everyone registered still qualifies.' : "Checked: {$flagged} no longer qualify (marked below).",
            'registrations',
        );
    }

    public function destroy(string $tournament)
    {
        $tournament = Tournament::withCount('registrations')->findOrFail((int) $tournament);
        if ($tournament->registrations_count > 0 && !static::canUseAdmin(Auth::user())) {
            return $this->back($tournament, 'error', 'Players have registered for it, so only an admin can remove it. Hide it instead.');
        }
        if (TournamentBanner::where('tournament_id', $tournament->getKey())->exists()) {
            return $this->back($tournament, 'error', 'It has profile banners: remove them on the Banners page first.');
        }

        $name = $tournament->name;
        $count = $tournament->registrations_count;
        DB::transaction(function () use ($tournament) {
            $tournament->registrations()->delete();
            $tournament->delete();
        });
        $this->staffLog('tournament.remove', $tournament->getKey(), "Removed the tournament \"{$name}\"".($count > 0 ? " and its {$count} registrations" : ''));

        return $this->backToIndex('success', "Removed \"{$name}\".");
    }

    public function removeRegistration(string $tournament, string $user)
    {
        $tournament = Tournament::findOrFail((int) $tournament);
        $registration = TournamentRegistration::with('user')
            ->where('tournament_id', $tournament->getKey())
            ->where('user_id', (int) $user)
            ->firstOrFail();
        $name = $registration->user?->username ?? "#{$registration->user_id}";
        $registration->delete();
        $reason = presence(trim((string) Request::input('reason')));
        $this->staffLog(
            'tournament.registration_remove',
            $tournament->getKey(),
            "Removed {$name}'s registration for \"{$tournament->name}\"".($reason === null ? '' : ": {$reason}"),
        );

        return $this->back($tournament, 'success', "Removed {$name}'s registration.", 'registrations');
    }

    /** The registered players as a spreadsheet (CSV). */
    public function export(string $tournament)
    {
        $tournament = Tournament::findOrFail((int) $tournament);
        $registrations = $tournament->registrations()->with('user')->orderBy('registration_id')->get();
        $ids = $registrations->pluck('user_id')->all();
        $stats = $this->statsFor($tournament, $ids);
        $discord = DB::table('tomyum.discord_links')->whereIn('user_id', $ids)->pluck('discord_id', 'user_id');
        $discordNames = DB::table('tomyum_user_connections')->where('kind', 'discord')->whereIn('user_id', $ids)
            ->pluck('display_name', 'user_id');

        $out = fopen('php://temp', 'r+');
        fputcsv($out, ['#', 'user_id', 'username', 'country_at_signup', 'country_now', 'rank', 'pp', 'discord_id', 'discord_name', 'registered_at', 'still_qualifies', 'why_not'], ',', '"', '');
        foreach ($registrations->values() as $index => $registration) {
            $user = $registration->user;
            $row = $stats[$registration->user_id] ?? null;
            fputcsv($out, array_map(static::csvCell(...), [
                $index + 1,
                $registration->user_id,
                $user?->username ?? '(deleted)',
                $registration->country_acronym ?? '',
                $user?->country_acronym ?? '',
                $row?->globalRank() ?? '',
                $row === null ? '' : round((float) $row->rank_score, 2),
                isset($discord[$registration->user_id]) ? (string) $discord[$registration->user_id] : '',
                $discordNames[$registration->user_id] ?? '',
                $registration->created_at?->toIso8601String() ?? '',
                $registration->checked_at === null ? 'not checked' : (count($registration->ineligible_reasons ?? []) === 0 ? 'yes' : 'no'),
                implode(' ', $registration->ineligible_reasons ?? []),
            ]), ',', '"', '');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        $file = 'tournament-'.$tournament->getKey().'-registrations-'.CarbonImmutable::now()->format('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$file}\"",
        ]);
    }

    /**
     * The edit form's fields, checked: [fields, null] or [null, the problem]. Rank min is the best
     * rank allowed (like 1), rank max the worst (like 10000), as osu-web has them.
     */
    private static function fields(): array
    {
        $name = trim((string) Request::input('name'));
        if ($name === '' || mb_strlen($name) > 255) {
            return [null, 'The tournament needs a name (up to 255 characters).'];
        }
        $description = (string) Request::input('description');
        if (strlen($description) > 60000) {
            return [null, 'The description is too long (up to about 60,000 characters).'];
        }

        $mode = (int) Request::input('mode', 0);
        if (!array_key_exists($mode, static::MODES)) {
            return [null, 'Pick a mode.'];
        }
        $variant = presence((string) Request::input('variant'));
        if ($variant !== null && !Beatmap::isVariantValid(Beatmap::modeStr($mode), $variant)) {
            return [null, 'Key counts (4K, 7K) are only for osu!mania.'];
        }

        $infoUrl = presence(trim((string) Request::input('info_url')));
        if ($infoUrl !== null && (!preg_match('#^https?://#', $infoUrl) || strlen($infoUrl) > 255)) {
            return [null, 'The information page should be a web address (https://…).'];
        }
        $header = presence(trim((string) Request::input('header_banner')));
        if ($header !== null && (!preg_match('#^https?://#', $header) || strlen($header) > 255)) {
            return [null, 'The header image should be a web address (https://…), or upload one.'];
        }

        $dates = [];
        foreach (['signup_open' => 'Registration opens', 'signup_close' => 'Registration closes', 'start_date' => 'Starts', 'end_date' => 'Ends'] as $column => $label) {
            $value = presence((string) Request::input($column));
            if ($value === null) {
                return [null, "Fill in \"{$label}\"."];
            }
            try {
                $dates[$column] = CarbonImmutable::parse($value, 'UTC');
            } catch (\Throwable $e) {
                return [null, "\"{$label}\" isn't a date."];
            }
        }
        // The banners page's tournaments have one date for everything; the others go in order.
        if (!Request::boolean('is_banner_only')) {
            if ($dates['signup_close']->lte($dates['signup_open'])) {
                return [null, 'Registration has to close after it opens.'];
            }
            if ($dates['start_date']->lt($dates['signup_close'])) {
                return [null, 'The tournament should start after registration closes.'];
            }
            if ($dates['end_date']->lte($dates['start_date'])) {
                return [null, 'The tournament has to end after it starts.'];
            }
        }

        $numbers = [];
        foreach ([
            'rank_min' => ['Best rank allowed', 1, 10_000_000],
            'rank_max' => ['Worst rank allowed', 1, 10_000_000],
            'restriction_days' => ['Days without a restriction', 1, 3650],
            'silence_days' => ['Days without a silence', 1, 3650],
            'account_age_days' => ['Account age', 1, 3650],
            'max_registrations' => ['Player cap', 1, 100_000],
        ] as $column => [$label, $min, $max]) {
            $value = trim((string) Request::input($column));
            if ($value === '') {
                $numbers[$column] = null;
                continue;
            }
            if (!ctype_digit($value) || (int) $value < $min || (int) $value > $max) {
                return [null, "\"{$label}\" should be a whole number from {$min} to ".number_format($max).' (or empty).'];
            }
            $numbers[$column] = (int) $value;
        }
        if ($numbers['rank_min'] !== null && $numbers['rank_max'] !== null && $numbers['rank_min'] > $numbers['rank_max']) {
            return [null, 'The best rank allowed should be a smaller number than the worst (like #1 to #10,000).'];
        }

        // Countries: acronyms (TH, JP), separated by commas or spaces; empty: everyone.
        $countries = collect(preg_split('/[\s,]+/', strtoupper((string) Request::input('countries'))))
            ->filter()
            ->unique()
            ->values();
        $unknown = $countries->diff(Country::whereIn('acronym', $countries)->pluck('acronym'));
        if ($unknown->isNotEmpty()) {
            return [null, 'Not a country code: '.$unknown->implode(', ').' (use two letters, like TH or JP).'];
        }

        return [[
            'name' => $name,
            'description' => $description,
            'play_mode' => $mode,
            'play_mode_variant' => $variant,
            'info_url' => $infoUrl,
            'header_banner' => $header,
            ...$dates,
            ...$numbers,
            'countries' => $countries->isEmpty() ? null : $countries->all(),
            'requires_discord' => Request::boolean('requires_discord'),
            'is_hidden' => Request::boolean('is_hidden'),
            'is_banner_only' => Request::boolean('is_banner_only'),
        ], null];
    }

    /** The tournament bans running now: each player's newest one, if it hasn't ended (Tournament::hasTournamentBan). */
    private static function activeBans()
    {
        return UserAccountHistory::with(['user', 'actor'])
            ->where('ban_status', UserAccountHistory::TYPES['tournament_ban'])
            ->whereIn('ban_id', UserAccountHistory::where('ban_status', UserAccountHistory::TYPES['tournament_ban'])
                ->selectRaw('MAX(ban_id)')
                ->groupBy('user_id'))
            ->orderByDesc('timestamp')
            ->get()
            ->filter(fn ($ban) => $ban->permanent || ($ban->timestamp !== null && $ban->endTime()->isFuture()))
            ->values();
    }

    /** The players' stats in the tournament's mode, by user id. */
    private function statsFor(Tournament $tournament, array $ids): array
    {
        $class = UserStatistics\Model::getClass($tournament->playModeStr(), $tournament->play_mode_variant);
        $stats = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach ($class::whereIn('user_id', $chunk)->get() as $row) {
                $stats[$row->user_id] = $row;
            }
        }

        return $stats;
    }

    /** A spreadsheet cell that can't be read as a formula. */
    private static function csvCell($value)
    {
        return is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'{$value}"
            : $value;
    }

    private function back(Tournament $tournament, string $type, string $message, ?string $anchor = null)
    {
        return redirect(route(static::sharedPanelPrefix().'tournaments.show', ['tournament' => $tournament->getKey()]).($anchor === null ? '' : "#{$anchor}"))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }

    private function backToIndex(string $type, string $message, ?string $anchor = null)
    {
        return redirect(route(static::sharedPanelPrefix().'tournaments.index').($anchor === null ? '' : "#{$anchor}"))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}

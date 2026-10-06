<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumSeasonal;
use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\ContestScoringCategory;
use App\Models\User;
use App\Models\UserContestEntry;
use Auth;
use Carbon\Carbon;
use DB;
use Request;

/**
 * TomYum: contests by hand (admins). ppy edits contests in the database; here they're made and
 * edited (every field of osu!'s contests, extra_options included), judges and scoring categories
 * picked, players' uploads turned into the entries people vote on, and the results shown. Contest #1
 * (and whichever holds the seasonal backgrounds) belongs to Admin › Seasonal backgrounds and is never
 * edited or removed here.
 */
class ContestsController extends Controller
{
    private const TYPES = ['art' => 'Art', 'beatmap' => 'Beatmap', 'music' => 'Music', 'external' => 'External (links)'];

    private const THUMBNAIL_SHAPES = ['' => 'Default (square)', 'square' => 'Square', 'landscape' => 'Landscape', 'portrait' => 'Portrait'];

    private const LINK_ICONS = ['' => 'Download (default)', 'download' => 'Download', 'external-link' => 'External link'];

    /** extra_options this page has fields for (the rest stay in the JSON box). */
    private const OPTION_FIELDS = [
        'judged', 'show_judges', 'is_score_standardised', 'unmasked', 'show_names', 'show_entry_user',
        'submitted_beatmaps', 'thumbnail_shape', 'link_icon', 'forced_width', 'forced_height',
        'allowed_extensions', 'max_filesize',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseAdmin(Auth::user()));

            return $next($request);
        });
    }

    /** Contest #1 and the seasonal backgrounds' contest: lazer's menu backgrounds, not a contest people enter. */
    public static function isProtected(int $contestId): bool
    {
        return $contestId === 1 || $contestId === TomYumSeasonal::contestId();
    }

    public function index()
    {
        return ext_view('panel.admin.contests.index', [
            'contests' => Contest::withCount(['entries', 'userContestEntries', 'votes'])->orderByDesc('id')->get(),
            'types' => static::TYPES,
            'thumbnailShapes' => static::THUMBNAIL_SHAPES,
            'linkIcons' => static::LINK_ICONS,
        ]);
    }

    public function show(string $contest)
    {
        $model = $this->findContest($contest);
        $submissions = UserContestEntry::withTrashed()->where('contest_id', $model->getKey())->with('user')->orderBy('id')->get();
        $entries = $model->entries()->withScore($model)->with('user')->get();

        return ext_view('panel.admin.contests.show', [
            'contest' => $model,
            'submissions' => $submissions,
            'entries' => $entries,
            'madeUrls' => $entries->pluck('entry_url')->filter()->flip(),
            'judges' => $model->judges()->get(),
            'categories' => $model->scoringCategories()->orderBy('id')->get(),
            'votesCount' => $model->votes()->count(),
            'votersCount' => $model->votes()->distinct('user_id')->count('user_id'),
            'types' => static::TYPES,
            'thumbnailShapes' => static::THUMBNAIL_SHAPES,
            'linkIcons' => static::LINK_ICONS,
            'otherOptions' => array_diff_key($model->extra_options ?? [], array_flip(static::OPTION_FIELDS)),
        ]);
    }

    public function store()
    {
        $existing = present(Request::input('id')) ? $this->findContest((string) Request::input('id')) : null;

        $name = trim((string) Request::input('name'));
        if ($name === '' || mb_strlen($name) > 255) {
            return $this->back($existing, 'error', 'A contest needs a name (up to 255 characters).');
        }
        $type = (string) Request::input('type');
        if (!array_key_exists($type, static::TYPES)) {
            return $this->back($existing, 'error', 'Pick the kind of contest.');
        }

        $dates = [];
        foreach (['entry_starts_at', 'entry_ends_at', 'voting_starts_at', 'voting_ends_at'] as $field) {
            try {
                $dates[$field] = present(Request::input($field)) ? Carbon::parse(Request::input($field), 'UTC') : null;
            } catch (\Throwable) {
                return $this->back($existing, 'error', 'One of the dates isn\'t a date (write them like 2026-10-06 18:00, UTC).');
            }
        }
        $ordered = array_values(array_filter($dates));
        for ($i = 1; $i < count($ordered); $i++) {
            if ($ordered[$i]->lt($ordered[$i - 1])) {
                return $this->back($existing, 'error', 'The dates go in order: entries open, entries close, voting opens, voting closes.');
            }
        }

        $maxEntries = (int) Request::input('max_entries', 1);
        $maxVotes = (int) Request::input('max_votes', 3);
        if ($maxEntries < 0 || $maxEntries > 127 || $maxVotes < 0 || $maxVotes > 127) {
            return $this->back($existing, 'error', 'Entries and votes per player are 0 to 127.');
        }

        $headerUrl = trim((string) Request::input('header_url'));
        if ($headerUrl !== '' && (strlen($headerUrl) > 255 || !preg_match('#^https?://#i', $headerUrl))) {
            return $this->back($existing, 'error', 'The header image is a full http(s) address (up to 255 characters).');
        }

        // The other extra options, as JSON; the fields below win over it.
        $rawOptions = trim((string) Request::input('extra_options_json'));
        $options = [];
        if ($rawOptions !== '') {
            $options = json_decode($rawOptions, true);
            if (!is_array($options) || array_is_list($options) && count($options) > 0) {
                return $this->back($existing, 'error', 'The other options must be a JSON object, like {"best_of": {"variant": "4k"}}.');
            }
        }
        $options = array_diff_key($options, array_flip(static::OPTION_FIELDS));
        foreach (['judged', 'is_score_standardised', 'unmasked', 'show_names', 'show_entry_user', 'submitted_beatmaps'] as $flag) {
            if (Request::boolean($flag)) {
                $options[$flag] = true;
            }
        }
        if (!Request::boolean('show_judges')) {
            $options['show_judges'] = false;
        }
        foreach (['thumbnail_shape' => static::THUMBNAIL_SHAPES, 'link_icon' => static::LINK_ICONS] as $key => $choices) {
            $value = (string) Request::input($key);
            if ($value !== '' && array_key_exists($value, $choices)) {
                $options[$key] = $value;
            }
        }
        foreach (['forced_width', 'forced_height'] as $key) {
            $value = get_int(Request::input($key));
            if ($value !== null && $value > 0) {
                $options[$key] = $value;
            }
        }
        $extensions = array_values(array_filter(array_map(
            fn ($ext) => strtolower(trim($ext, " .\t")),
            explode(',', (string) Request::input('allowed_extensions')),
        ), fn ($ext) => preg_match('/^[a-z0-9]{1,10}$/', $ext) === 1));
        if (count($extensions) > 0) {
            $options['allowed_extensions'] = $extensions;
        }
        $maxSize = (float) Request::input('max_filesize_mb');
        if ($maxSize > 0) {
            $options['max_filesize'] = (int) round($maxSize * 1024 * 1024);
        }

        $values = [
            'name' => $name,
            'type' => $type,
            'header_url' => $headerUrl,
            'description_enter' => str_replace("\r\n", "\n", trim((string) Request::input('description_enter'))),
            'description_voting' => presence(str_replace("\r\n", "\n", trim((string) Request::input('description_voting')))),
            'max_entries' => $maxEntries,
            'max_votes' => $maxVotes,
            'visible' => Request::boolean('visible'),
            'show_votes' => Request::boolean('show_votes'),
            'extra_options' => count($options) === 0 ? null : $options,
            ...$dates,
        ];

        if ($existing === null) {
            // On a fresh database the seasonal backgrounds' contest is made first, so it gets #1.
            TomYumSeasonal::contest();
        }
        $model = $existing ?? new Contest();
        $model->fill($values)->save();
        $this->staffLog('contests.save', $model->getKey(), ($existing === null ? 'Made' : 'Edited')." the contest \"{$name}\"");

        return redirect(route('panel.admin.contests.show', ['contest' => $model->getKey()]))->with('panel_status', [
            'type' => 'success',
            'message' => $existing === null ? 'Contest made (hidden until you tick "Visible").' : 'Saved.',
            'link' => ['url' => $model->url(), 'text' => 'see it'],
        ]);
    }

    public function destroy(string $contest)
    {
        $model = $this->findContest($contest);
        if ($model->votes()->exists() || $model->entries()->exists() || UserContestEntry::withTrashed()->where('contest_id', $model->getKey())->exists()) {
            return $this->back($model, 'error', 'This contest has entries or votes (players\' things): hide it (untick "Visible") instead.');
        }
        DB::transaction(function () use ($model) {
            DB::table('contest_judges')->where('contest_id', $model->getKey())->delete();
            $model->scoringCategories()->delete();
            $model->delete();
        });
        $this->staffLog('contests.remove', $model->getKey(), "Removed the contest \"{$model->name}\"");

        return redirect(route('panel.admin.contests.index'))->with('panel_status', ['type' => 'success', 'message' => 'Removed.', 'link' => null]);
    }

    /** Players' uploads (the ones not removed, or those ticked) become entries to vote on, named "Entry #n" until the results. */
    public function makeEntries(string $contest)
    {
        $model = $this->findContest($contest);
        $picked = array_map('intval', (array) Request::input('submissions', []));
        $submissions = UserContestEntry::where('contest_id', $model->getKey())
            ->when(count($picked) > 0, fn ($q) => $q->whereIn('id', $picked))
            ->orderBy('id')
            ->get();
        $made = $model->entries()->pluck('entry_url')->filter()->flip();
        $new = $submissions->filter(fn ($submission) => ($url = $submission->file()->url()) !== null && !$made->has($url))->shuffle();
        if ($new->isEmpty()) {
            return $this->back($model, 'error', 'Every upload already has an entry.');
        }

        $number = $model->entries()->count();
        DB::transaction(function () use ($model, $new, &$number) {
            foreach ($new as $submission) {
                $number++;
                $url = $submission->file()->url();
                $model->entries()->create([
                    'name' => mb_substr(pathinfo($submission->original_filename ?? '', PATHINFO_FILENAME) ?: "Entry #{$number}", 0, 255),
                    'masked_name' => "Entry #{$number}",
                    'entry_url' => mb_substr($url, 0, 255),
                    'thumbnail_url' => $model->type === 'art' ? mb_substr($url, 0, 255) : null,
                    'user_id' => $submission->user_id,
                ]);
            }
        });
        $this->staffLog('contests.entries', $model->getKey(), 'Made '.count($new)." voting entries in the contest \"{$model->name}\"");

        return $this->back($model, 'success', count($new).' entries made, in a random order.');
    }

    /** An entry by hand (or edited): what people vote on. */
    public function storeEntry(string $contest)
    {
        $model = $this->findContest($contest);
        $entry = present(Request::input('id')) ? $model->entries()->findOrFail((int) Request::input('id')) : new ContestEntry(['contest_id' => $model->getKey()]);

        $name = trim((string) Request::input('name'));
        $masked = trim((string) Request::input('masked_name'));
        if ($name === '' || mb_strlen($name) > 255 || mb_strlen($masked) > 255) {
            return $this->back($model, 'error', 'An entry needs a name (up to 255 characters).');
        }
        $urls = [];
        foreach (['entry_url', 'thumbnail_url'] as $field) {
            $url = presence(trim((string) Request::input($field)));
            if ($url !== null && (strlen($url) > 255 || (!preg_match('#^https?://#i', $url) && !ctype_digit($url)))) {
                return $this->back($model, 'error', 'Links are full http(s) addresses (or a beatmap set id for "best of" contests).');
            }
            $urls[$field] = $url;
        }
        $userQuery = trim((string) Request::input('user'));
        $user = null;
        if ($userQuery !== '') {
            $user = ctype_digit($userQuery) ? User::find((int) $userQuery) : User::lookup($userQuery, 'username');
            if ($user === null) {
                return $this->back($model, 'error', "There's no player \"{$userQuery}\".");
            }
        }

        $entry->fill([
            'name' => $name,
            'masked_name' => $masked === '' ? ($entry->masked_name ?? 'Entry #'.($model->entries()->count() + 1)) : $masked,
            'user_id' => $user?->getKey(),
            ...$urls,
        ])->save();
        $this->staffLog('contests.entry', $model->getKey(), "Saved the entry \"{$name}\" in the contest \"{$model->name}\"");

        return $this->back($model, 'success', 'Entry saved.');
    }

    public function destroyEntry(string $contest, string $entry)
    {
        $model = $this->findContest($contest);
        $item = $model->entries()->findOrFail((int) $entry);
        if ($item->votes()->exists() || $item->judgeVotes()->exists()) {
            return $this->back($model, 'error', 'People already voted for this entry: it stays.');
        }
        $item->delete();
        $this->staffLog('contests.entry.remove', $model->getKey(), "Removed the entry \"{$item->name}\" from the contest \"{$model->name}\"");

        return $this->back($model, 'success', 'Entry removed.');
    }

    public function addJudge(string $contest)
    {
        $model = $this->findContest($contest);
        $query = trim((string) Request::input('user'));
        $user = $query === '' ? null : (ctype_digit($query) ? User::find((int) $query) : User::lookup($query, 'username'));
        if ($user === null) {
            return $this->back($model, 'error', "There's no player \"{$query}\".");
        }
        DB::table('contest_judges')->insertOrIgnore(['contest_id' => $model->getKey(), 'user_id' => $user->getKey()]);
        $this->staffLog('contests.judge', $model->getKey(), "Made {$user->username} a judge of the contest \"{$model->name}\"");

        return $this->back($model, 'success', "{$user->username} judges this contest.");
    }

    public function removeJudge(string $contest, string $user)
    {
        $model = $this->findContest($contest);
        DB::table('contest_judges')->where('contest_id', $model->getKey())->where('user_id', get_int($user))->delete();
        $this->staffLog('contests.judge.remove', $model->getKey(), "Removed judge {$user} from the contest \"{$model->name}\"");

        return $this->back($model, 'success', 'Judge removed (their votes stay).');
    }

    public function storeCategory(string $contest)
    {
        $model = $this->findContest($contest);
        $category = present(Request::input('id'))
            ? $model->scoringCategories()->findOrFail((int) Request::input('id'))
            : new ContestScoringCategory(['contest_id' => $model->getKey()]);
        $name = trim((string) Request::input('name'));
        $max = (int) Request::input('max_value', 10);
        if ($name === '' || mb_strlen($name) > 255 || $max < 1 || $max > 127) {
            return $this->back($model, 'error', 'A category needs a name, and a top score from 1 to 127.');
        }
        $taken = $model->scoringCategories()->where('name', $name)->when($category->exists, fn ($q) => $q->where('id', '<>', $category->getKey()))->exists();
        if ($taken) {
            return $this->back($model, 'error', "There's already a category \"{$name}\".");
        }
        $category->fill([
            'name' => $name,
            'description' => mb_substr(trim((string) Request::input('description')), 0, 255),
            'max_value' => $max,
        ])->save();
        $this->staffLog('contests.category', $model->getKey(), "Saved the scoring category \"{$name}\" of the contest \"{$model->name}\"");

        return $this->back($model, 'success', 'Category saved.');
    }

    public function destroyCategory(string $contest, string $category)
    {
        $model = $this->findContest($contest);
        $item = $model->scoringCategories()->findOrFail((int) $category);
        if (DB::table('contest_judge_scores')->where('contest_scoring_category_id', $item->getKey())->exists()) {
            return $this->back($model, 'error', 'Judges already scored in this category: it stays.');
        }
        $item->delete();

        return $this->back($model, 'success', 'Category removed.');
    }

    /** The judges' standardised scores (for contests with "standardised scores"). */
    public function calculate(string $contest)
    {
        $model = $this->findContest($contest);
        try {
            $model->calculateScoresStd();
        } catch (\App\Exceptions\InvariantException) {
            // std_dev() of a judge who hasn't scored any entry yet.
            return $this->back($model, 'error', 'Every judge needs to have scored at least one entry first (or remove the judges who haven\'t).');
        }
        $this->staffLog('contests.calculate', $model->getKey(), "Calculated the standardised scores of the contest \"{$model->name}\"");

        return $this->back($model, 'success', 'Standardised scores calculated.');
    }

    private function findContest(string $id): Contest
    {
        $contest = Contest::findOrFail(get_int($id));
        if (static::isProtected($contest->getKey())) {
            abort(redirect(route('panel.admin.contests.index'))->with('panel_status', [
                'type' => 'error',
                'message' => 'That contest holds the seasonal backgrounds: it\'s managed on its own page.',
                'link' => ['url' => route('panel.admin.seasonal-backgrounds.index'), 'text' => 'Seasonal backgrounds'],
            ]));
        }

        return $contest;
    }

    private function back(?Contest $contest, string $type, string $message)
    {
        return redirect($contest === null ? route('panel.admin.contests.index') : route('panel.admin.contests.show', ['contest' => $contest->getKey()]))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}

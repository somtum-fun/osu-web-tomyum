<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use App\Libraries\TomYumCountryStats;
use App\Models\BeatmapMirror;
use App\Models\Country;
use App\Models\Group;
use DB;
use Illuminate\Console\Command;

/**
 * Prepares a fresh osu-web database for running next to TomYum: everything osu!'s production
 * data has that the development seeders don't create (or fill with fakes). Safe to run again.
 */
class TomYumSetup extends Command
{
    protected $signature = 'tomyum:setup {--mirror-url= : Where beatmap downloads go (TomYum serves /d/{id}); defaults to APP_URL}';

    protected $description = 'Prepare osu-web to run with the TomYum game server (groups, genres, languages, countries, forum, download mirror, lazer login).';

    /** identifier => [name, badge, colour, display order (null = no badge), listed]. */
    private const GROUPS = [
        'admin' => ['Administrators', 'ADM', 'FF66AB', 0, true],
        'dev' => ['osu! Developers', 'DEV', 'E45678', 1, true],
        'gmt' => ['Global Moderation Team', 'GMT', '99EB47', 2, true],
        'nat' => ['Nomination Assessment Team', 'NAT', 'FA3703', 3, true],
        'bng' => ['Beatmap Nominators', 'BN', 'A347EB', 4, true],
        'bng_limited' => ['Beatmap Nominators (Probationary)', 'BN', 'D6A8F2', 5, true],
        'loved' => ['Project Loved', 'LVD', 'FFD1DC', 6, true],
        'alumni' => ['osu! Alumni', 'ALM', '999999', 8, true],
        'bot' => ['Chat Bots', 'BOT', 'D3D3D3', 9, true],
        // Permission only (chat announcements): no badge, no listing, like on osu.ppy.sh.
        'announce' => ['Announcers', 'ANN', 'FFD966', null, false],
    ];

    private const GENRES = [
        1 => 'Unspecified', 2 => 'Video Game', 3 => 'Anime', 4 => 'Rock', 5 => 'Pop', 6 => 'Other', 7 => 'Novelty',
        9 => 'Hip Hop', 10 => 'Electronic', 11 => 'Metal', 12 => 'Classical', 13 => 'Folk', 14 => 'Jazz',
    ];

    /** id => [name, display order]. */
    private const LANGUAGES = [
        1 => ['Unspecified', 14], 2 => ['English', 1], 3 => ['Japanese', 6], 4 => ['Chinese', 2], 5 => ['Instrumental', 12],
        6 => ['Korean', 7], 7 => ['French', 3], 8 => ['German', 4], 9 => ['Swedish', 9], 10 => ['Spanish', 8],
        11 => ['Italian', 5], 12 => ['Russian', 10], 13 => ['Polish', 11], 14 => ['Other', 13],
    ];

    public function handle()
    {
        $this->groups();
        $this->genresAndLanguages();
        $this->countries();
        $this->userPageForum();
        $this->mirror();
        $this->lazerClient();
        $this->checkConfig();

        $this->info('Done. Restart the php container (docker compose restart php) so it picks up the groups.');
    }

    private function groups(): void
    {
        if (Group::count() === 0) {
            $this->call('db:seed', ['--class' => 'Database\Seeders\ModelSeeders\GroupSeeder', '--force' => true]);
        }

        foreach (static::GROUPS as $identifier => [$name, $short, $colour, $order, $listed]) {
            Group::where('identifier', $identifier)->update([
                'group_name' => $name,
                'short_name' => $short,
                'group_colour' => $colour,
                'colour' => $colour,
                'display_order' => $order,
                'group_type' => $listed ? 1 : 2,
            ]);
        }
        // Users show their main group's colour.
        DB::statement('UPDATE phpbb_users u JOIN phpbb_groups g ON g.group_id = u.group_id SET u.user_colour = g.group_colour');
        $this->line('Groups: names, badges and colours set.');
    }

    private function genresAndLanguages(): void
    {
        foreach (static::GENRES as $id => $name) {
            DB::table('osu_genres')->insertOrIgnore(['genre_id' => $id, 'name' => $name]);
        }
        foreach (static::LANGUAGES as $id => [$name, $order]) {
            DB::table('osu_languages')->insertOrIgnore(['language_id' => $id, 'name' => $name, 'display_order' => $order]);
        }
        // The "Any" search filter is id 0 (inserted as 99 and moved, like MiscSeeder, as 0 can't be inserted into an auto-increment).
        if (!DB::table('osu_genres')->where('name', 'Any')->exists()) {
            DB::table('osu_genres')->insertOrIgnore(['genre_id' => 99, 'name' => 'Any']);
            DB::table('osu_genres')->where('genre_id', 99)->update(['genre_id' => 0]);
        }
        if (!DB::table('osu_languages')->where('name', 'Any')->exists()) {
            DB::table('osu_languages')->insertOrIgnore(['language_id' => 99, 'name' => 'Any', 'display_order' => 0]);
            DB::table('osu_languages')->where('language_id', 99)->update(['language_id' => 0]);
        }
        $this->line('Genres and languages: present.');
    }

    private function countries(): void
    {
        if (Country::count() === 0) {
            $this->call('db:seed', ['--class' => 'Database\Seeders\ModelSeeders\CountrySeeder', '--force' => true]);
            // The seeder invents per-country stats; rankings must only show real ones.
            Country::query()->update(['rankedscore' => 0, 'playcount' => 0, 'usercount' => 0, 'pp' => 0]);
        }

        $withPlayers = collect(['osu_user_stats', 'osu_user_stats_taiko', 'osu_user_stats_fruits', 'osu_user_stats_mania'])
            ->flatMap(fn ($table) => DB::table($table)->distinct()->pluck('country_acronym'))
            ->unique();
        foreach ($withPlayers as $country) {
            TomYumCountryStats::recalculate($country);
        }
        $this->line('Countries: '.Country::count().' present, rankings for '.$withPlayers->count().' recalculated.');
    }

    private function userPageForum(): void
    {
        $forumId = $GLOBALS['cfg']['osu']['user']['user_page_forum_id'];
        DB::table('phpbb_forums')->insertOrIgnore([
            'forum_id' => $forumId,
            'parent_id' => 0,
            'forum_name' => 'User pages',
            'forum_desc' => 'Profile "me!" pages (managed by the website).',
            'forum_parents' => '',
            'forum_rules' => '',
            'forum_type' => 1,
        ]);
        $this->line("User page forum: #{$forumId} present.");
    }

    private function mirror(): void
    {
        $url = rtrim($this->option('mirror-url') ?? $GLOBALS['cfg']['app']['url'], '/').'/';
        BeatmapMirror::updateOrCreate(['base_url' => $url], [
            'enabled' => 1,
            'version' => 2,
            'is_master' => 1,
            'regions' => null,
            'secret_key' => BeatmapMirror::where('base_url', $url)->value('secret_key') ?? bin2hex(random_bytes(16)),
        ]);
        BeatmapMirror::where('base_url', '<>', $url)->update(['is_master' => 0]);
        $this->line("Download mirror: {$url}d/{id} (TomYum).");
    }

    /**
     * The OAuth client osu!(lazer) logs in with: it's built into the game (id 5 and this secret, see
     * ppy/osu osu.Game/Online/ProductionEndpointConfiguration.cs), so the site needs the same one.
     * Lazer also needs the Passport keys (php artisan passport:keys). See TomYum's docs/lazer.md.
     */
    private function lazerClient(): void
    {
        DB::table('oauth_clients')->upsert([[
            'id' => 5,
            'user_id' => null,
            'name' => 'osu!lazer',
            'secret' => 'FGc9GAtyHzeQDshWP5Ah7dega8hJACAJpQtw6OXk',
            'redirect' => '',
            'personal_access_client' => 0,
            'password_client' => 1,
            'revoked' => 0,
            'provider' => 'users',
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['id'], ['name', 'secret', 'password_client', 'revoked', 'provider', 'updated_at']);

        if (!file_exists(storage_path('oauth-private.key'))) {
            $this->warn("No Passport keys: run php artisan passport:keys, or lazer (and API logins) can't sign in.");
        }
        $this->line('osu!lazer login client: id 5.');
    }

    private function checkConfig(): void
    {
        $iconPrefix = $GLOBALS['cfg']['osu']['achievement']['icon_prefix'];
        if (str_contains($iconPrefix, 'user-achievements')) {
            $this->warn('Medal icons: set USER_ACHIEVEMENT_ICON_PREFIX=https://assets.ppy.sh/medals/client/ in .env (the default URL has no icons).');
        }
        if (!present($GLOBALS['cfg']['osu']['legacy']['shared_interop_secret'])) {
            $this->warn('SHARED_INTEROP_SECRET is empty: TomYum can\'t reach this site (panels, chat, rankings).');
        }
    }
}

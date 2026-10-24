<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use App\Libraries\TomYumCountryStats;
use App\Models\BeatmapMirror;
use App\Models\Country;
use App\Models\Group;
use App\Models\User;
use DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Prepares a fresh osu-web database for running next to TomYum: everything osu!'s production
 * data has that the development seeders don't create (or fill with fakes). Safe to run again.
 */
class TomYumSetup extends Command
{
    protected $signature = 'tomyum:setup
        {--mirror-url= : Where beatmap downloads go (TomYum serves /d/{id}); defaults to APP_URL}
        {--owner= : Username of the server owner: gets the admin, developer and GMT groups}';

    protected $description = 'Prepare osu-web to run with the TomYum game server (groups, genres, languages, countries, forum, download mirror, lazer login).';

    /** identifier => [name, badge, colour, display order (null = no badge), listed]. */
    private const GROUPS = [
        'admin' => ['Administrators', 'ADM', 'FF66AB', 0, true],
        'dev' => ['Developers', 'DEV', 'E45678', 1, true],
        'gmt' => ['Global Moderation Team', 'GMT', '99EB47', 2, true],
        'tc' => ['Tournament Committee', 'TC', 'FFC266', 3, true],
        'nat' => ['Nomination Assessment Team', 'NAT', 'FA3703', 4, true],
        'bng' => ['Beatmap Nominators', 'BN', 'A347EB', 5, true],
        'bng_limited' => ['Beatmap Nominators (Probationary)', 'BN', 'D6A8F2', 6, true],
        'loved' => ['Project Loved', 'LVD', 'FFD1DC', 7, true],
        'spt' => ['Support Team', 'SPT', 'EB8C47', 8, true],
        'alumni' => ['Alumni', 'ALM', '999999', 9, true],
        'featured_artist' => ['Featured Artist', 'FA', '00FFFF', 10, true],
        'bot' => ['Chat Bots', 'BOT', 'D3D3D3', 11, true],
        // Permission only (chat announcements): no badge, no listing, like on osu.ppy.sh.
        'announce' => ['Announcers', 'ANN', 'FFD966', null, false],
    ];

    /** What each group does (Markdown), shown above its members on its page; only set while empty. */
    private const GROUP_DESCRIPTIONS = [
        'admin' => 'Run the server: accounts, settings, and the final say on its rules and appeals.',
        'dev' => 'Build and look after the game server and this website.',
        'gmt' => 'Keep chat, the forums and the leaderboards fair: they handle reports, silences and restrictions.',
        'tc' => 'Run the server\'s official tournaments, and give out tournament badges and banners.',
        'nat' => 'Look after the Beatmap Nominators: they review applications and members, and step in when a nomination is disputed.',
        'bng' => 'Mod maps and nominate them for ranking, each in their own modes. A map needs two nominations to be qualified.',
        'bng_limited' => 'New Beatmap Nominators on probation: they can nominate maps, but not give one its final nomination.',
        'loved' => 'Decide which maps players ask for get [Loved](/community/loved), each in their own modes.',
        'spt' => 'Answer support tickets: account problems, appeals and questions sent to the support addresses.',
        'alumni' => 'People who helped run the server before. Thank you!',
        'featured_artist' => 'Artists whose music is featured on the server.',
        'bot' => 'Automated chat accounts, run by players or the staff.',
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
        $this->forums();
        $this->beatmapDescriptionForum();
        $this->mirror();
        $this->lazerClient();
        $this->banchoBotAvatar();
        $this->owner();
        $this->checkConfig();

        $this->info('Done. Restart the php container (docker compose restart php) so it picks up the groups.');
    }

    private function groups(): void
    {
        // TomYum: the migrations add groups of their own (tc, spt, medal, ...) before this runs, so the
        // table is rarely empty on a fresh database; make each of osu-web's groups that is missing,
        // as the GroupSeeder would (which truncates, so it isn't run here).
        foreach (Group::PRIV_IDENTIFIERS as $identifier) {
            if (Group::where('identifier', $identifier)->exists()) {
                continue;
            }
            Group::create([
                'group_desc' => '',
                'group_name' => $identifier,
                'group_type' => 2,
                'has_playmodes' => in_array($identifier, ['bng', 'bng_limited'], true),
                'identifier' => $identifier,
                'short_name' => $identifier,
            ]);
            $this->line("Group {$identifier}: made.");
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
        foreach (static::GROUP_DESCRIPTIONS as $identifier => $description) {
            Group::where('identifier', $identifier)
                ->where(fn ($query) => $query->whereNull('group_desc')->orWhere('group_desc', ''))
                ->update(['group_desc' => $description]);
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

    /** The forum beatmap description posts live in (locked topics, like osu.ppy.sh). */
    private function beatmapDescriptionForum(): void
    {
        $forumId = $GLOBALS['cfg']['osu']['forum']['beatmap_description_forum_id'];
        DB::table('phpbb_forums')->insertOrIgnore([
            'forum_id' => $forumId,
            'parent_id' => 0,
            'forum_name' => 'Beatmap descriptions',
            'forum_desc' => 'Beatmap set descriptions (managed by the website).',
            'forum_parents' => '',
            'forum_rules' => '',
            'forum_type' => 1,
        ]);
        $this->line("Beatmap description forum: #{$forumId} present.");
    }

    /**
     * The forums players use, laid out like osu.ppy.sh's, with the ids osu-web's settings expect
     * (help 5 with its confirmed 101 and archive 29, feature requests 4 with completed 30, the staff
     * category 28), so the forum features tied to them work. Existing forums are left as they are.
     * Everyone may start topics and reply, except in announcements (staff start them) and the
     * archives (nobody); the staff category is only visible to moderators and admins.
     */
    private function forums(): void
    {
        // id => [parent, name, description, type (0 category, 1 forum), everyone posts, everyone replies]
        $forums = [
            10 => [0, 'General', 'The community.', 0, false, false],
            11 => [10, 'Announcements', 'News from the staff.', 1, false, true],
            12 => [10, 'General Discussion', 'Anything about osu! and this server.', 1, true, true],
            13 => [10, 'Off-Topic', 'Everything else.', 1, true, true],
            20 => [0, 'Help & Feedback', 'Problems, questions and ideas.', 0, false, false],
            5 => [20, 'Help', 'Something not working? Ask here.', 1, true, true],
            101 => [5, 'Help: Confirmed', 'Problems the staff confirmed.', 1, false, true],
            29 => [5, 'Help: Archived', 'Old and solved help topics.', 1, false, false],
            4 => [20, 'Feature Requests', 'What should the server do next?', 1, true, true],
            30 => [4, 'Feature Requests: Completed', 'Requests that were done, or turned down.', 1, false, false],
            40 => [0, 'Beatmaps', 'Mapping and maps.', 0, false, false],
            41 => [40, 'Mapping', 'Help, tips and works in progress.', 1, true, true],
            42 => [40, 'Beatmap Requests', 'Maps you would like to see ranked or loved.', 1, true, true],
            43 => [40, 'Modding Queues', 'Ask for mods, or offer them.', 1, true, true],
            50 => [0, 'Competitions', 'Tournaments and multiplayer.', 0, false, false],
            51 => [50, 'Tournaments', 'Run or join a tournament.', 1, true, true],
            52 => [50, 'Multiplayer', 'Find people to play with.', 1, true, true],
            28 => [0, 'Staff', 'Only the staff can see this.', 0, false, false],
            31 => [28, 'Staff Room', 'Staff talk.', 1, false, false],
        ];

        $order = 1;
        foreach ($forums as $id => [$parent, $name, $description, $type]) {
            $parents = [];
            for ($at = $parent; $at !== 0; $at = $forums[$at][0]) {
                $parents = [$at => [$forums[$at][1], $forums[$at][3]]] + $parents;
            }
            DB::table('phpbb_forums')->insertOrIgnore([
                'forum_id' => $id,
                'parent_id' => $parent,
                'left_id' => $order,
                'right_id' => $order + 1,
                'forum_name' => $name,
                'forum_desc' => $description,
                'forum_parents' => count($parents) === 0 ? '' : serialize($parents),
                'forum_rules' => '',
                'forum_type' => $type,
                'enable_indexing' => 1,
            ]);
            $order += 2;
        }
        // The internal forums (descriptions, user pages) go last, out of the way.
        DB::table('phpbb_forums')->whereIn('forum_id', [
            $GLOBALS['cfg']['osu']['forum']['beatmap_description_forum_id'],
            $GLOBALS['cfg']['osu']['user']['user_page_forum_id'],
        ])->where('left_id', 0)->update(['left_id' => 1000, 'right_id' => 1001]);

        $options = [];
        foreach (['f_post', 'f_reply', 'f_postcount'] as $option) {
            $options[$option] = DB::table('phpbb_acl_options')->where('auth_option', $option)->value('auth_option_id')
                ?? DB::table('phpbb_acl_options')->insertGetId(['auth_option' => $option, 'is_global' => 0, 'is_local' => 1, 'founder_only' => 0]);
        }
        $everyone = Group::where('identifier', 'default')->value('group_id');
        $staff = Group::whereIn('identifier', ['admin', 'gmt'])->pluck('group_id')->all();
        $grant = function (int $groupId, int $forumId, string $option) use ($options) {
            DB::table('phpbb_acl_groups')->updateOrInsert([
                'group_id' => $groupId,
                'forum_id' => $forumId,
                'auth_option_id' => $options[$option],
                'auth_role_id' => 0,
            ], ['auth_setting' => 1]);
        };
        foreach ($forums as $id => [, , , $type, $post, $reply]) {
            if ($type !== 1) {
                continue;
            }
            foreach ($staff as $groupId) {
                foreach (['f_post', 'f_reply', 'f_postcount'] as $option) {
                    $grant($groupId, $id, $option);
                }
            }
            if ($everyone !== null && $id !== 31) {
                if ($post) {
                    $grant($everyone, $id, 'f_post');
                }
                if ($reply) {
                    $grant($everyone, $id, 'f_reply');
                    $grant($everyone, $id, 'f_postcount');
                }
            }
        }
        $this->line('Forums: '.count($forums).' present, with posting rights.');
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
        // Secrets are kept hashed (Passport checks them with Hash::check), and the grant types are read
        // from their column: lazer logs in with a password, then refreshes its token.
        $secret = 'FGc9GAtyHzeQDshWP5Ah7dega8hJACAJpQtw6OXk';
        DB::table('oauth_clients')->upsert([[
            'id' => 5,
            'user_id' => null,
            'name' => 'osu!lazer',
            'secret' => Hash::make($secret),
            'secret_hint' => substr($secret, -4),
            'redirect' => '',
            'grant_types' => json_encode(['password', 'refresh_token']),
            'personal_access_client' => 0,
            'password_client' => 1,
            'revoked' => 0,
            'provider' => 'users',
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['id'], ['name', 'secret', 'secret_hint', 'grant_types', 'password_client', 'revoked', 'provider', 'updated_at']);

        if (!file_exists(storage_path('oauth-private.key'))) {
            $this->warn("No Passport keys: run php artisan passport:keys, or lazer (and API logins) can't sign in.");
        }
        $this->line('osu!lazer login client: id 5.');

        // Lazer's scores carry build 0 while client checks are off (CLIENT_CHECK_VERSION=false), and
        // the spectator server needs that build to exist to save their replays. Private: it's not
        // a release, so it stays off the changelog.
        DB::transaction(function () {
            DB::statement("SET SESSION sql_mode = CONCAT(@@SESSION.sql_mode, ',NO_AUTO_VALUE_ON_ZERO')");
            DB::table('osu_builds')->insertOrIgnore([
                'build_id' => 0,
                'version' => 'lazer (unchecked)',
                'allow_ranking' => 1,
                'allow_bancho' => 1,
                'allow_performance' => 1,
                'private' => 1,
            ]);
            DB::statement("SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'NO_AUTO_VALUE_ON_ZERO', '')");
        });
        $this->line('osu!lazer build 0 (unchecked clients): present.');
    }

    /** BanchoBot's avatar from osu! (the user avatar service a.ppy.sh), when it has none. */
    private function banchoBotAvatar(): void
    {
        $bot = User::find($GLOBALS['cfg']['osu']['legacy']['bancho_bot_user_id']);
        if ($bot === null || present($bot->user_avatar)) {
            return;
        }

        $response = \Http::timeout(15)->get('https://a.ppy.sh/3');
        if (!$response->successful() || !str_starts_with($response->header('Content-Type'), 'image/')) {
            $this->warn("BanchoBot avatar: couldn't download it from a.ppy.sh (HTTP {$response->status()}).");

            return;
        }

        storage_disk('avatar')->put((string) $bot->getKey(), $response->body());
        $bot->update(['user_avatar' => $bot->getKey().'_'.time().'.png']);
        $this->line('BanchoBot avatar: set.');
    }

    /** The owner's staff groups (--owner=<username>). */
    private function owner(): void
    {
        $username = presence($this->option('owner'));
        if ($username === null) {
            return;
        }

        $user = User::lookup($username, 'username');
        if ($user === null) {
            $this->warn("Owner: no user named {$username} (create the account first, in-game or on the website).");

            return;
        }

        foreach (['admin', 'dev', 'gmt'] as $identifier) {
            if (($group = Group::firstWhere('identifier', $identifier)) !== null && !$user->isGroup($group)) {
                $user->addToGroup($group);
            }
        }
        $user->setDefaultGroup(Group::firstWhere('identifier', 'admin'));
        $this->line("Owner: {$user->username} is in admin, dev and gmt.");
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

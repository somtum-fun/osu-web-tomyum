<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Build;
use App\Models\ChangelogEntry;
use App\Models\Count;
use App\Models\GithubUser;
use App\Models\Repository;
use Carbon\CarbonImmutable;
use DB;
use Illuminate\Console\Command;

/**
 * TomYum: the changelog from git history. ppy's changelog comes from GitHub pull requests; this
 * server's repositories are private, so deploy/production/changelog.sh pipes `git log --name-only`
 * in here (each record starts with \x1e; fields hash, date, subject, body, author email split by
 * \x1f; then the changed files, one per line).
 *
 * The game server's commits go to the stream of the part they change (Stable, Lazer, Server,
 * Discord), its documentation to Wiki, and the website's to Web; each gets a category ("Stable:
 * friends" → Friends-less title in category Stable, otherwise from the files). Up to five changes
 * make a version (2026.930.0, 2026.930.1, …). Commits are credited to the GitHub account (and
 * through it the player) given for their author email. Already imported commits are skipped, and
 * so are ones given with --skip or saying "[no changelog]" in their message.
 */
class TomYumChangelogImport extends Command
{
    protected $signature = 'tomyum:changelog-import
        {source : server (the game server) or web (the website)}
        {--repository= : e.g. sundeiii/TomYum}
        {--author=* : "email=github login:github id:user id", who a commit author is}
        {--skip=* : commits (hash or its start) not to list}
        {--reset : first remove every change and version imported so far}';

    protected $description = 'Adds new git commits (piped in) to the changelog.';

    /** name => [id, name on the page]. Colours: resources/css (changelog-stream--name). */
    public const STREAMS = [
        'stable40' => [5, 'Stable'],
        'lazer' => [6, 'Lazer'],
        'server' => [10, 'Server'],
        'discord' => [11, 'Discord'],
        'web' => [1, 'Web'],
        'wiki' => [12, 'Wiki'],
    ];

    /** Changes per version. */
    private const PER_BUILD = 5;

    /** Subjects that aren't changes anyone sees. */
    private const SKIP = '/^(merge|tests?\b|ci\b|moderation tests|misses\.sh)/i';

    /** Files that say nothing about which part changed. */
    private const NEUTRAL = '#^(tests/|src/TomYum\.Persistence/Migrations/|src/TomYum\.Server/Program\.cs$|Directory\.Build|global\.json|\.)#';

    /** The game server's areas: [path pattern, category], first match wins per file. */
    private const SERVER_AREAS = [
        ['#/Discord/#', 'Discord bot'],
        ['#/Anticheat/|circleguard#i', 'Anti-cheat'],
        ['#/Irc/#', 'IRC'],
        ['#Spectat#', 'Spectating'],
        ['#/Match|Multiplayer|Tournament#', 'Multiplayer'],
        ['#Achievement|Medal#', 'Medals'],
        ['#Lazer|score-processor|spectator#i', 'lazer'],
        ['#/Admin/|Moderation#', 'Staff tools'],
        ['#Beatmap|/Beatmaps/#', 'Beatmaps'],
        ['#Score|Leaderboard|Performance#', 'Scores'],
        ['#Chat|Channel|PrivateMessage|BanchoBot#', 'Chat'],
        ['#/Plugins?/#', 'Plugins'],
        ['#^deploy/#', 'Operations'],
    ];

    /** The website's areas. */
    private const WEB_AREAS = [
        ['#views/panel/|Controllers/Panel/#', 'Staff panels'],
        ['#^resources/lang/#', 'Translations'],
        ['#Changelog#', 'Changelog'],
        ['#^resources/(js|css)/#', 'Interface'],
        ['#Score#', 'Scores'],
        ['#Beatmap#', 'Beatmaps'],
        ['#Ranking#', 'Rankings'],
        ['#User|Profile#', 'Profiles'],
    ];

    private array $authors = [];

    public function handle(): int
    {
        $source = (string) $this->argument('source');
        if (!in_array($source, ['server', 'web'], true)) {
            $this->error('The source is server or web.');

            return 1;
        }

        $this->setUpStreams();
        if ($this->option('reset')) {
            $this->reset();
        }
        $this->loadAuthors();

        $repository = Repository::firstOrCreate(
            ['name' => $this->option('repository') ?? ($source === 'web' ? 'sundeiii/osu-web' : 'sundeiii/TomYum')],
            ['stream_id' => static::STREAMS[$source === 'web' ? 'web' : 'stable40'][0], 'default_category' => null],
        );

        $marker = Count::firstOrCreate(['name' => "tomyum_changelog_{$source}"], ['count' => 0]);
        $added = 0;
        $versions = [];
        foreach (explode("\x1e", stream_get_contents(STDIN)) as $record) {
            $commit = static::parse($record);
            if ($commit === null || $commit['at']->getTimestamp() <= $marker->count) {
                continue;
            }
            $marker->count = $commit['at']->getTimestamp();
            if ($commit['subject'] === '' || preg_match(static::SKIP, $commit['subject']) === 1 || $this->skipped($commit['hash']) || str_contains($commit['body'], '[no changelog]')) {
                continue;
            }

            foreach ($this->place($source, $commit) as [$stream, $category]) {
                [$prefix, $title] = static::split($commit['subject']);
                $versions[$this->add($repository, $stream, $prefix ?? $category, $title, $commit)] = true;
                $added++;
            }
        }
        $marker->save();

        $this->info("{$added} changes in ".count($versions).' versions.');

        return 0;
    }

    private function setUpStreams(): void
    {
        $rows = [];
        foreach (static::STREAMS as $name => [$id, $pretty]) {
            $rows[] = ['stream_id' => $id, 'name' => $name, 'pretty_name' => $pretty, 'default_allow_bancho' => false];
        }
        DB::connection('mysql-updates')->table('streams')->upsert($rows, ['stream_id'], ['name', 'pretty_name']);
    }

    /** Everything imported so far goes, so it can come back in the current layout. */
    private function reset(): void
    {
        $streamIds = array_column(static::STREAMS, 0);
        DB::transaction(function () use ($streamIds) {
            $buildIds = Build::whereIn('stream_id', $streamIds)->pluck('build_id');
            $entryIds = DB::table('build_changelog_entry')->whereIn('build_id', $buildIds)->pluck('changelog_entry_id');
            DB::table('build_changelog_entry')->whereIn('build_id', $buildIds)->delete();
            ChangelogEntry::whereIn('id', $entryIds)->whereNull('github_pull_request_id')->delete();
            Build::whereIn('build_id', $buildIds)->delete();
            Count::where('name', 'like', 'tomyum_changelog_%')->delete();
        });
        $this->info('Removed the changelog imported so far.');
    }

    /** "email=login:github id:user id" → GitHub accounts (made or updated), by email. */
    private function loadAuthors(): void
    {
        foreach ((array) $this->option('author') as $spec) {
            if (preg_match('/^([^=]+)=([A-Za-z0-9-]+):(\d+):(\d+)$/', (string) $spec, $m) !== 1) {
                $this->warn("Skipped author \"{$spec}\" (email=login:github id:user id).");
                continue;
            }
            $githubUser = GithubUser::firstOrNew(['canonical_id' => (int) $m[3]]);
            $githubUser->fill(['username' => $m[2], 'user_id' => (int) $m[4]])->save();
            $this->authors[strtolower($m[1])] = $githubUser->getKey();
        }
    }

    private function skipped(string $hash): bool
    {
        foreach ((array) $this->option('skip') as $start) {
            if (strlen((string) $start) >= 7 && str_starts_with($hash, (string) $start)) {
                return true;
            }
        }

        return false;
    }

    private static function parse(string $record): ?array
    {
        $record = trim($record, "\n");
        $parts = explode("\x1f", $record);
        if (count($parts) < 6) {
            return null;
        }
        [$hash, $date, $subject, $body, $email] = $parts;
        $files = array_values(array_filter(array_map('trim', explode("\n", $parts[5])), fn ($f) => $f !== ''));

        return [
            'hash' => $hash,
            'at' => CarbonImmutable::parse($date)->utc(),
            // The page speaks of the server, not its code name.
            'subject' => static::unname(trim($subject)),
            'body' => static::unname(static::withoutTrailers($body)),
            'email' => strtolower(trim($email)),
            'files' => $files,
        ];
    }

    /** Where a commit goes: [[stream name, category], ...]. */
    private function place(string $source, array $commit): array
    {
        $files = array_values(array_filter($commit['files'], fn ($f) => preg_match(static::NEUTRAL, $f) !== 1));

        if ($source === 'web') {
            return [['web', static::area($files, static::WEB_AREAS) ?? 'Website']];
        }

        $docs = array_filter($files, fn ($f) => preg_match('#^docs/|\.md$#i', $f) === 1);
        $code = array_values(array_diff($files, $docs));
        if (count($code) === 0) {
            // Only the documentation: the wiki, in the page's name ("docs/lazer.md" → Lazer).
            $page = count($docs) > 0 ? pathinfo(reset($docs), PATHINFO_FILENAME) : 'docs';

            return [['wiki', ucfirst(strtolower(str_replace(['-', '_'], ' ', $page)))]];
        }

        $category = static::area($code, static::SERVER_AREAS) ?? 'General';
        $subject = $commit['subject'];
        $streams = [];
        if (preg_match('/\blazer\b/i', $subject) === 1) {
            $streams[] = 'lazer';
        }
        if (preg_match('/\b(stable|bancho|in-game|tournament|irc)\b/i', $subject) === 1) {
            $streams[] = 'stable40';
        }
        if (count($streams) === 0) {
            $streams[] = match (true) {
                preg_match('/\bdiscord\b/i', $subject) === 1 || $category === 'Discord bot' => 'discord',
                $category === 'lazer' => 'lazer',
                static::any($code, '#/Bancho/|TomYum\.Bancho/|/Irc/|WebEndpoints|Screenshot|Osz2|/web/#') => 'stable40',
                default => 'server',
            };
        }
        if ($category === 'lazer') {
            $category = 'General';
        }

        return array_map(fn ($stream) => [$stream, $category], $streams);
    }

    /** The category of the files: the area most of them are in. */
    private static function area(array $files, array $areas): ?string
    {
        $counts = [];
        foreach ($files as $file) {
            foreach ($areas as [$pattern, $category]) {
                if (preg_match($pattern, $file) === 1) {
                    $counts[$category] = ($counts[$category] ?? 0) + 1;
                    break;
                }
            }
        }
        arsort($counts);

        return array_key_first($counts);
    }

    private static function any(array $files, string $pattern): bool
    {
        foreach ($files as $file) {
            if (preg_match($pattern, $file) === 1) {
                return true;
            }
        }

        return false;
    }

    /** Adds a change to the stream's current version, or a new one; returns the version. */
    private function add(Repository $repository, string $stream, string $category, string $title, array $commit): string
    {
        $streamId = static::STREAMS[$stream][0];
        $at = $commit['at'];

        return DB::transaction(function () use ($repository, $streamId, $category, $title, $commit, $at) {
            $entry = ChangelogEntry::create([
                'repository_id' => $repository->getKey(),
                'github_user_id' => $this->authors[$commit['email']] ?? null,
                'type' => preg_match('/\b(fix|fixes|fixed|broke|broken|crash|wrong|bug)\b/i', $title) === 1 ? 'fix' : 'add',
                'category' => mb_substr($category, 0, 255),
                'title' => mb_substr($title, 0, 255),
                'message' => present($commit['body']) ? $commit['body'] : null,
                'private' => false,
                'major' => false,
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            // Up to PER_BUILD changes per version, numbered through the day: 2026.930.0, 2026.930.1, …
            $day = $at->format('Y').'.'.$at->format('n').$at->format('d');
            $build = Build::where('stream_id', $streamId)->where('version', 'like', "{$day}.%")->orderByDesc('build_id')->first();
            $full = $build !== null && DB::table('build_changelog_entry')->where('build_id', $build->getKey())->count() >= static::PER_BUILD;
            if ($build === null || $full) {
                $next = $build === null ? 0 : ((int) substr($build->version, strrpos($build->version, '.') + 1)) + 1;
                $build = Build::create([
                    'stream_id' => $streamId,
                    'version' => "{$day}.{$next}",
                    'date' => $at,
                    'allow_bancho' => false,
                    'hash' => hex2bin(substr($commit['hash'], 0, 32)),
                ]);
            } elseif ($build->date < $at) {
                $build->update(['date' => $at]);
            }
            DB::table('build_changelog_entry')->insertOrIgnore(['build_id' => $build->getKey(), 'changelog_entry_id' => $entry->getKey()]);

            return $build->version;
        });
    }

    /** "Stable: friends and ..." → ["Stable", "Friends and ..."] (a prefix of plain words); otherwise [null, subject]. */
    private static function split(string $subject): array
    {
        if (preg_match('/^([\pL!\/ ]{2,24}):\s+(.+)$/u', $subject, $match) === 1) {
            return [static::upperFirst(trim($match[1])), static::upperFirst(trim($match[2]))];
        }

        return [null, static::upperFirst($subject)];
    }

    /**
     * The body without git's trailers and tool footers (Co-Authored-By, Signed-off-by,
     * Claude-Session, "Generated with …"): who or what helped write a commit isn't news to players.
     */
    private static function withoutTrailers(string $body): string
    {
        $lines = array_filter(
            explode("\n", str_replace("\r\n", "\n", $body)),
            fn ($line) => preg_match('/^\s*([\w-]+-(by|session):\s|(🤖\s*)?Generated with\b)/i', $line) !== 1,
        );

        return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines)));
    }

    private static function unname(string $text): string
    {
        return preg_replace(["/\bTomYum's\b/", '/\bTomYum\b/'], ["the server's", 'the server'], $text);
    }

    private static function upperFirst(string $text): string
    {
        // osu!'s names stay lowercase (osu!direct, osu!mania).
        if (str_starts_with($text, 'osu')) {
            return $text;
        }

        return mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
    }
}

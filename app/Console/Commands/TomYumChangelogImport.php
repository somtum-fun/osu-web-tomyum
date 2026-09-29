<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Build;
use App\Models\ChangelogEntry;
use App\Models\Count;
use App\Models\Repository;
use Carbon\CarbonImmutable;
use DB;
use Illuminate\Console\Command;

/**
 * TomYum: the changelog from git history. ppy's changelog comes from GitHub pull requests; this
 * server's repositories are private, so deploy/production/changelog.sh pipes `git log` in here
 * (records split by \x1e, fields hash \x1f date \x1f subject \x1f body). Each new commit becomes an
 * entry ("Stable: friends" → category Stable), and a day's commits one build (version 2026.929.0).
 * Docs-only commits are left out. Already imported commits are skipped (osu_counts remembers the
 * last one's time per stream).
 */
class TomYumChangelogImport extends Command
{
    protected $signature = 'tomyum:changelog-import
        {stream_id : the stream (5 and 1 are the ones the changelog shows by default)}
        {stream : its name in links, e.g. tomyum}
        {pretty : its name on the page, e.g. TomYum}
        {repository : e.g. sundeiii/TomYum}
        {category : for commits without a "Category:" prefix}';

    protected $description = 'Adds new git commits (piped in) to the changelog.';

    /** Subjects that aren't changes players see. */
    private const SKIP = '/^(merge|roadmap|readme|docs?\b|tests?\b|ci\b|moderation tests|misses\.sh)/i';

    public function handle(): int
    {
        $streamId = (int) $this->argument('stream_id');
        DB::connection('mysql-updates')->table('streams')->upsert(
            [['stream_id' => $streamId, 'name' => $this->argument('stream'), 'pretty_name' => $this->argument('pretty'), 'default_allow_bancho' => false]],
            ['stream_id'],
            ['name', 'pretty_name'],
        );
        $repository = Repository::firstOrCreate(
            ['name' => $this->argument('repository')],
            ['stream_id' => $streamId, 'default_category' => $this->argument('category')],
        );

        $marker = Count::firstOrCreate(['name' => "tomyum_changelog_{$streamId}"], ['count' => 0]);
        $added = 0;
        $builds = [];
        foreach (explode("\x1e", stream_get_contents(STDIN)) as $record) {
            $fields = explode("\x1f", trim($record, "\n"));
            if (count($fields) < 3) {
                continue;
            }
            [$hash, $date, $subject] = $fields;
            $body = trim($fields[3] ?? '');
            $at = CarbonImmutable::parse($date)->utc();
            if ($at->getTimestamp() <= $marker->count) {
                continue;
            }
            $marker->count = $at->getTimestamp();

            $subject = trim($subject);
            if ($subject === '' || preg_match(static::SKIP, $subject) === 1) {
                continue;
            }
            [$category, $title] = static::split($subject, $repository->default_category);

            DB::transaction(function () use ($repository, $streamId, $at, $category, $title, $body, $hash, &$builds) {
                $entry = ChangelogEntry::create([
                    'repository_id' => $repository->getKey(),
                    'type' => preg_match('/\b(fix|fixes|fixed|broke|broken|crash|wrong|bug)\b/i', $title) === 1 ? 'fix' : 'add',
                    'category' => $category,
                    'title' => mb_substr($title, 0, 255),
                    'message' => present($body) ? $body : null,
                    'private' => false,
                    'major' => false,
                    'created_at' => $at,
                    'updated_at' => $at,
                ]);

                // One build per day and stream, e.g. 2026.929.0.
                $version = $at->format('Y').'.'.$at->format('n').$at->format('d').'.0';
                $build = Build::firstOrCreate(
                    ['stream_id' => $streamId, 'version' => $version],
                    ['date' => $at, 'allow_bancho' => false, 'hash' => hex2bin(substr($hash, 0, 32))],
                );
                if ($build->date < $at) {
                    $build->update(['date' => $at]);
                }
                DB::table('build_changelog_entry')->insertOrIgnore(['build_id' => $build->getKey(), 'changelog_entry_id' => $entry->getKey()]);
                $builds[$version] = true;
            });
            $added++;
        }
        $marker->save();

        $this->info("{$added} changes in ".count($builds).' builds.');

        return 0;
    }

    /** "Stable: friends and ..." → ["Stable", "Friends and ..."]; no short prefix → the default category. */
    private static function split(string $subject, ?string $default): array
    {
        if (preg_match('/^([^:]{2,24}):\s+(.+)$/u', $subject, $match) === 1 && !str_contains($match[1], '(')) {
            return [static::upperFirst(trim($match[1])), static::upperFirst(trim($match[2]))];
        }

        return [$default, static::upperFirst($subject)];
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

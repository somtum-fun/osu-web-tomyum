<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Console\Commands;

use App\Libraries\User\AvatarHelper;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Illuminate\Console\Command;
use Illuminate\Http\File;

/**
 * TomYum: the files and the website-only things of a bancho.py server, after TomYum's
 * tools/ImportBanchoPy has made the accounts: avatars, profile covers (banners), clans as teams with
 * their images, and userpages. Everything goes through the website's own code (image processing,
 * storage, the team's chat channel), which the C# importer can't do. Load bancho.py's database next
 * to the website's (same MySQL server). Running it again writes the same files again and skips
 * teams that exist. --discussions also brings the somtum fork's beatmap discussions, hypes and
 * comments (skipping what is already here).
 */
class TomYumImportBpyMedia extends Command
{
    protected $signature = 'tomyum:import-bpy-media
        {--database=bpy_import : bancho.py\'s database (on the same server)}
        {--avatars= : folder with <user id>.png/jpg/gif (bancho.py: .data/avatars; gukarkka: www/avatars)}
        {--banners= : folder with <user id>.png/jpg/gif profile banners (gukarkka: www/assets/banners)}
        {--clans= : folder with clan images: avatar/<clan id>.png and banner/<clan id>.png (gukarkka: www/assets/clans)}
        {--userpages= : folder with <user id>.txt userpages (bancho.py: .data/user/about_me); users.userpage_content is used too}
        {--teams : make the clans into teams (with their images from --clans)}
        {--discussions : beatmap discussions (with replies), hypes and beatmap comments (somtum fork)}
        {--set-map= : the importer\'s custom-set-ids.csv (old set id,new set id), for discussions on private sets}
        {--only= : only this user id}
        {--dry-run : only say what would happen}';

    protected $description = 'Imports a bancho.py server\'s avatars, covers, clans (as teams) and userpages after tools/ImportBanchoPy.';

    private array $userMap = [];
    /** Private sets' old -> new beatmap ids (from --set-map). */
    private array $beatmapMap = [];
    private bool $dry = false;

    public function handle(): int
    {
        $database = (string) $this->option('database');
        if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
            $this->error('The database name is letters, digits and underscores.');

            return 1;
        }
        $this->dry = (bool) $this->option('dry-run');
        ini_set('memory_limit', '1G'); // re-encoding a large banner needs more than the default
        $this->userMap = $this->readUserMap();
        $only = get_int($this->option('only'));

        $users = DB::select("SELECT id, name, clan_id, clan_priv FROM `{$database}`.`users` ORDER BY id");
        $this->info(count($users).' players on bancho.py; '.count($this->userMap).' id mappings.');

        $avatars = $this->folder('avatars');
        $banners = $this->folder('banners');
        $userpages = $this->folder('userpages');
        $hasUserpageColumn = count(DB::select(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [$database, 'users', 'userpage_content'],
        )) > 0;

        $done = ['avatar' => 0, 'cover' => 0, 'userpage' => 0];
        foreach ($users as $row) {
            $newId = $this->mapId((int) $row->id);
            // bancho.py's bot (id 1) has no account of its own: its files go to this server's bot.
            if ($newId === null && (int) $row->id === 1) {
                $newId = (int) $GLOBALS['cfg']['osu']['legacy']['bancho_bot_user_id'];
            }
            if ($newId === null || ($only !== null && $newId !== $only)) {
                continue;
            }
            $user = User::find($newId);
            if ($user === null) {
                continue; // not imported (the old bot, a skipped account)
            }

            if ($avatars !== null && ($file = $this->findImage($avatars, (string) $row->id)) !== null) {
                $this->line("  #{$newId} {$user->username}: avatar ".basename($file));
                if (!$this->dry) {
                    if (AvatarHelper::set($user, new File($this->copy($file)))) {
                        $done['avatar']++;
                    } else {
                        $this->warn('    avatar not saved: '.implode('; ', $user->validationErrors()->allMessages()));
                    }
                }
            }

            if ($banners !== null && ($file = $this->findImage($banners, (string) $row->id)) !== null) {
                $this->line("  #{$newId} {$user->username}: cover ".basename($file));
                if (!$this->dry) {
                    try {
                        $user->cover()->set(null, $this->fitCover($this->copy($file)));
                        $user->saveOrExplode();
                        $done['cover']++;
                    } catch (\Throwable $e) {
                        $this->warn('    cover not saved: '.$e->getMessage());
                    }
                }
            }

            $page = null;
            if ($userpages !== null && is_file("{$userpages}/{$row->id}.txt")) {
                $page = trim((string) file_get_contents("{$userpages}/{$row->id}.txt"));
            }
            if ($page === null || $page === '') {
                if ($hasUserpageColumn) {
                    $content = DB::selectOne("SELECT userpage_content FROM `{$database}`.`users` WHERE id = ?", [$row->id]);
                    $page = trim((string) ($content->userpage_content ?? ''));
                }
            }
            if ($page !== null && $page !== '' && $user->userPage === null) {
                $this->line("  #{$newId} {$user->username}: userpage (".mb_strlen($page).' characters)');
                if (!$this->dry) {
                    try {
                        $user->updatePage(mb_substr($page, 0, 60000));
                        $done['userpage']++;
                    } catch (\Throwable $e) {
                        $this->warn('    userpage not saved: '.$e->getMessage());
                    }
                }
            }
        }
        $this->info("Avatars {$done['avatar']}, covers {$done['cover']}, userpages {$done['userpage']}.");

        if ($this->option('teams')) {
            $this->teams($database, $this->folder('clans'), $only);
        }

        if ($this->option('discussions')) {
            $this->discussions($database, $this->readSetMap());
        }

        $this->info($this->dry ? 'Dry run: nothing changed.' : 'Done.');

        return 0;
    }

    /** Clans become teams: the owner leads, members join, the clan avatar is the flag and its banner the header. */
    private function teams(string $database, ?string $clans, ?int $only): void
    {
        if (count(DB::select('SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ?', [$database, 'clans'])) === 0) {
            $this->warn('No clans table: no teams.');

            return;
        }
        $rows = DB::select("SELECT id, name, tag, owner, created_at FROM `{$database}`.`clans` ORDER BY id");
        $made = 0;
        foreach ($rows as $clan) {
            $leaderId = $this->mapId((int) $clan->owner);
            $leader = $leaderId === null ? null : User::find($leaderId);
            if ($leader === null) {
                $this->warn("  clan #{$clan->id} {$clan->name}: its owner isn't here, skipped");
                continue;
            }
            if ($only !== null && $leaderId !== $only) {
                continue;
            }
            $members = collect(DB::select("SELECT id FROM `{$database}`.`users` WHERE clan_id = ? AND id <> ?", [$clan->id, $clan->owner]))
                ->map(fn ($m) => $this->mapId((int) $m->id))
                ->filter()
                ->values();

            $name = mb_substr(trim(preg_replace('/[^\x20-\x7E]/', '', (string) $clan->name) ?? ''), 0, 40);
            $tag = mb_substr(trim(preg_replace('/[^\x20-\x7E]/', '', (string) $clan->tag) ?? ''), 0, 4);
            if ($name === '' || $tag === '') {
                $this->warn("  clan #{$clan->id} {$clan->name} [{$clan->tag}]: the name or tag has nothing the website accepts (printable ASCII), skipped");
                continue;
            }
            if (Team::where('name', $name)->orWhere('short_name', $tag)->exists() || $leader->team !== null) {
                $this->line("  clan #{$clan->id} {$name} [{$tag}]: a team with that name or tag exists, or the leader is in a team already; skipped");
                continue;
            }
            $flag = $clans === null ? null : $this->findImage("{$clans}/avatar", (string) $clan->id);
            $header = $clans === null ? null : $this->findImage("{$clans}/banner", (string) $clan->id);
            $this->line("  clan #{$clan->id} {$name} [{$tag}]: leader #{$leaderId}, ".$members->count().' members'.($flag !== null ? ', flag' : '').($header !== null ? ', header' : ''));
            if ($this->dry) {
                continue;
            }

            try {
                $attributes = [
                    'name' => $name,
                    'short_name' => $tag,
                    'leader_id' => $leader->getKey(),
                ];
                if ($flag !== null) {
                    // The website's flag is 2:1; a square clan avatar would be cropped, so it is centred on a transparent canvas.
                    $attributes['flag'] = $this->widen($this->copy($flag));
                }
                if ($header !== null) {
                    $attributes['header'] = $this->copy($header);
                }
                $team = new Team($attributes);
                $team->created_at = Carbon::parse((string) $clan->created_at);
                $team->saveOrExplode(); // + the leader's team_members row + the team chat channel
                $team->members()->where('user_id', $leader->getKey())->update(['created_at' => $team->created_at]);
                foreach ($members as $memberId) {
                    $member = User::find($memberId);
                    if ($member === null || $member->team !== null) {
                        continue;
                    }
                    $team->members()->create(['user_id' => $memberId, 'created_at' => $team->created_at]);
                }
                $made++;
            } catch (\Throwable $e) {
                $this->warn("    not made: {$e->getMessage()}");
            }
        }
        $this->info("Teams {$made}.");
    }

    /**
     * The somtum fork's beatmap discussions (with their replies), hypes and beatmap comments, as the
     * website's discussions and comments. Its types: 0 problem, 1 suggestion, 2 praise, 3 note, and 4
     * the note a nominator left with a nomination action (vote/rank: praise; cancel: suggestion).
     * What is already here (same set, author and time) is skipped, so it can run again.
     */
    private function discussions(string $database, array $setMap): void
    {
        $tables = collect(DB::select('SELECT table_name AS t FROM information_schema.tables WHERE table_schema = ?', [$database]))
            ->pluck('t')->map(fn ($t) => strtolower((string) $t))->all();
        $types = [0 => 'problem', 1 => 'suggestion', 2 => 'praise', 3 => 'mapper_note'];
        $setId = fn (int $old): int => $setMap[$old] ?? $old;
        $done = ['discussions' => 0, 'posts' => 0, 'hypes' => 0, 'comments' => 0, 'skipped' => 0];
        $sets = [];

        // One discussion with its posts; null when it can't be placed (set not on the website, author not imported).
        $place = function (int $oldSet, ?int $oldBeatmap, int $oldUser) use ($setId): ?array {
            $set = $setId($oldSet);
            $user = $this->mapId($oldUser);
            if ($user === null || !DB::table('osu_beatmapsets')->where('beatmapset_id', $set)->exists() || !DB::table('phpbb_users')->where('user_id', $user)->exists()) {
                return null;
            }
            $oldBeatmap = $oldBeatmap === null ? null : ($this->beatmapMap[$oldBeatmap] ?? $oldBeatmap);
            $beatmap = $oldBeatmap !== null && DB::table('osu_beatmaps')->where(['beatmap_id' => $oldBeatmap, 'beatmapset_id' => $set])->exists() ? $oldBeatmap : null;

            return [$set, $beatmap, $user];
        };
        $write = function (array $where, string $type, bool $resolved, array $posts) use (&$done, &$sets): void {
            [$set, $beatmap, $user, $at] = $where;
            if (DB::table('beatmap_discussions')->where(['beatmapset_id' => $set, 'user_id' => $user, 'created_at' => $at])->exists()) {
                $done['skipped']++;

                return;
            }
            if ($this->dry) {
                $done['discussions']++;

                return;
            }
            DB::transaction(function () use ($set, $beatmap, $user, $at, $type, $resolved, $posts, &$done) {
                $id = DB::table('beatmap_discussions')->insertGetId([
                    'beatmapset_id' => $set,
                    'beatmap_id' => $beatmap,
                    'user_id' => $user,
                    'message_type' => \App\Models\BeatmapDiscussion::MESSAGE_TYPES[$type],
                    'resolved' => $resolved,
                    'created_at' => $at,
                    'updated_at' => $at,
                    'last_post_at' => max(array_column($posts, 'at')),
                ]);
                foreach ($posts as $post) {
                    DB::table('beatmap_discussion_posts')->insert([
                        'beatmap_discussion_id' => $id,
                        'user_id' => $post['user'],
                        'message' => $post['message'],
                        'system' => false,
                        'created_at' => $post['at'],
                        'updated_at' => $post['at'],
                    ]);
                    $done['posts']++;
                }
                $done['discussions']++;
            });
            $sets[$set] = true;
        };

        if (in_array('beatmap_discussions', $tables, true)) {
            $replies = in_array('beatmap_discussion_replies', $tables, true)
                ? collect(DB::select("SELECT discussion_id, user_id, content, created_at FROM `{$database}`.`beatmap_discussion_replies` ORDER BY id"))->groupBy('discussion_id')
                : collect();
            foreach (DB::select("SELECT * FROM `{$database}`.`beatmap_discussions` ORDER BY id") as $row) {
                $where = $place((int) $row->set_id, $row->beatmap_id === null ? null : (int) $row->beatmap_id, (int) $row->user_id);
                if ($where === null) {
                    $this->line("  discussion #{$row->id} on set {$row->set_id}: the set or its author isn't here, skipped");
                    $done['skipped']++;
                    continue;
                }
                $type = $types[(int) $row->type] ?? ($row->system_action === 'cancel' ? 'suggestion' : 'praise');
                $posts = [['user' => $where[2], 'message' => (string) $row->content, 'at' => $row->created_at]];
                foreach ($replies->get($row->id, []) as $reply) {
                    if (($replyUser = $this->mapId((int) $reply->user_id)) !== null) {
                        $posts[] = ['user' => $replyUser, 'message' => (string) $reply->content, 'at' => $reply->created_at];
                    }
                }
                // Only problems and suggestions can be resolved on the website.
                $write([...$where, $row->created_at], $type, (bool) $row->resolved && in_array($type, ['problem', 'suggestion'], true), $posts);
            }
        }

        if (in_array('beatmap_hype', $tables, true)) {
            foreach (DB::select("SELECT * FROM `{$database}`.`beatmap_hype` ORDER BY id") as $row) {
                $where = $place((int) $row->set_id, null, (int) $row->user_id);
                if ($where === null) {
                    $done['skipped']++;
                    continue;
                }
                $message = trim((string) ($row->comment ?? ''));
                $before = $done['discussions'];
                $write([...$where, $row->created_at], 'hype', false, [['user' => $where[2], 'message' => $message === '' ? 'Hype!' : $message, 'at' => $row->created_at]]);
                $done['hypes'] += $done['discussions'] - $before;
                $done['discussions'] = $before;
            }
        }

        if (in_array('beatmap_comments', $tables, true)) {
            $commentIds = [];
            foreach (DB::select("SELECT * FROM `{$database}`.`beatmap_comments` ORDER BY parent_id IS NOT NULL, id") as $row) {
                $set = $setId((int) $row->set_id);
                $user = $this->mapId((int) $row->user_id);
                if ($user === null || !DB::table('osu_beatmapsets')->where('beatmapset_id', $set)->exists()) {
                    $this->line("  comment #{$row->id} on set {$row->set_id}: the set or its author isn't here, skipped");
                    $done['skipped']++;
                    continue;
                }
                $existing = DB::table('comments')->where(['commentable_type' => 'beatmapset', 'commentable_id' => $set, 'user_id' => $user, 'created_at' => $row->created_at])->value('id');
                if ($existing !== null) {
                    $commentIds[(int) $row->id] = (int) $existing;
                    $done['skipped']++;
                    continue;
                }
                if ($this->dry) {
                    $done['comments']++;
                    continue;
                }
                $parent = $row->parent_id === null ? null : ($commentIds[(int) $row->parent_id] ?? null);
                $commentIds[(int) $row->id] = (int) DB::table('comments')->insertGetId([
                    'parent_id' => $parent,
                    'user_id' => $user,
                    'message' => (string) $row->content,
                    'commentable_type' => 'beatmapset',
                    'commentable_id' => $set,
                    'pinned' => (bool) $row->pinned,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at ?? $row->created_at,
                ]);
                if ($parent !== null) {
                    DB::table('comments')->where('id', $parent)->increment('replies_count_cache');
                }
                $done['comments']++;
            }
        }

        // The sets' hype counts follow their hype discussions.
        if (!$this->dry) {
            foreach (array_keys($sets) as $set) {
                $hype = DB::table('beatmap_discussions')->where(['beatmapset_id' => $set, 'message_type' => \App\Models\BeatmapDiscussion::MESSAGE_TYPES['hype']])->whereNull('deleted_at')->count();
                DB::table('osu_beatmapsets')->where('beatmapset_id', $set)->update(['hype' => $hype]);
            }
        }
        $this->info("Discussions {$done['discussions']} ({$done['posts']} posts), hypes {$done['hypes']}, comments {$done['comments']}, skipped {$done['skipped']}.");
    }

    /** The importer's custom-set-ids.csv (old set, new set[, old beatmap, new beatmap, ...] per line), or nothing. */
    private function readSetMap(): array
    {
        $path = presence($this->option('set-map'));
        if ($path === null || !is_file($path)) {
            return [];
        }
        $map = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $cells = str_getcsv($line);
            if (count($cells) >= 2 && ctype_digit(trim($cells[0])) && ctype_digit(trim($cells[1]))) {
                $map[(int) trim($cells[0])] = (int) trim($cells[1]);
                if (count($cells) >= 4 && ctype_digit(trim($cells[2])) && ctype_digit(trim($cells[3]))) {
                    $this->beatmapMap[(int) trim($cells[2])] = (int) trim($cells[3]);
                }
            }
        }

        return $map;
    }

    /** Old id -> new id from TomYum's bpy_import_user_map (same ids when the table is missing). */
    private function readUserMap(): array
    {
        $tomyum = $GLOBALS['cfg']['database']['connections']['tomyum']['database'] ?? 'tomyum';
        try {
            return collect(DB::select("SELECT old_id, new_id FROM `{$tomyum}`.`bpy_import_user_map`"))
                ->mapWithKeys(fn ($r) => [(int) $r->old_id => (int) $r->new_id])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function mapId(int $oldId): ?int
    {
        if (count($this->userMap) === 0) {
            return $oldId;
        }

        return $this->userMap[$oldId] ?? null;
    }

    private function folder(string $option): ?string
    {
        $value = rtrim((string) $this->option($option), '/');
        if ($value === '') {
            return null;
        }
        if (!is_dir($value)) {
            $this->warn("--{$option}: {$value} isn't a folder; skipped");

            return null;
        }

        return $value;
    }

    /** The newest <id>.png/jpg/jpeg/gif in $dir, png first (what the old site itself wrote). */
    private function findImage(string $dir, string $id): ?string
    {
        $candidates = array_filter(
            array_map(fn ($ext) => "{$dir}/{$id}.{$ext}", ['png', 'jpg', 'jpeg', 'gif']),
            'is_file',
        );
        if (count($candidates) === 0) {
            return null;
        }
        usort($candidates, fn ($a, $b) => filemtime($b) <=> filemtime($a));

        return $candidates[0];
    }

    /** osu-web's ImageProcessor rewrites its input in place, so it gets a copy. */
    private function copy(string $path): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bpy-media-');
        if (!copy($path, $tmp)) {
            throw new \RuntimeException("can't copy {$path}");
        }

        return $tmp;
    }

    /**
     * A banner the website's cover uploader would refuse (over 4 MB, or a format it doesn't take) is
     * re-encoded first: scaled to at most 2000 px wide, saved as JPEG. Animated GIFs lose their animation.
     */
    private function fitCover(string $path): string
    {
        $size = filesize($path);
        $info = @getimagesize($path);
        $ok = $info !== false && in_array($info[2], [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG], true)
            && $info[0] <= 4000 && $info[1] <= 4000;
        if ($size !== false && $size <= 4 * 1024 * 1024 && $ok) {
            return $path;
        }
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if ($src === false) {
            return $path;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w > 2000) {
            $src = imagescale($src, 2000, (int) round($h * 2000 / $w));
        }
        imagejpeg($src, $path, 88);

        return $path;
    }

    /** Puts an image on a transparent canvas twice as wide as it is high (the team flag's ratio). */
    private function widen(string $path): string
    {
        $src = @imagecreatefromstring((string) file_get_contents($path));
        if ($src === false) {
            return $path;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        if ($w >= $h * 2) {
            return $path;
        }
        $canvasW = max($w, $h * 2);
        $canvasH = intdiv($canvasW, 2);
        $flag = imagecreatetruecolor($canvasW, $canvasH);
        imagesavealpha($flag, true);
        imagealphablending($flag, false);
        imagefill($flag, 0, 0, imagecolorallocatealpha($flag, 0, 0, 0, 127));
        imagealphablending($flag, true);
        imagecopy($flag, $src, intdiv($canvasW - $w, 2), intdiv($canvasH - $h, 2), 0, 0, $w, $h);
        imagepng($flag, $path);

        return $path;
    }
}

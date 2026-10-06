<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Libraries\TomYumChangelog;
use App\Models\Build;
use App\Models\ChangelogEntry;
use App\Models\UpdateStream;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use DB;
use Request;

/**
 * TomYum: the changelog by hand (admins and developers). Versions (osu_builds) in the changelog's
 * streams (TomYumChangelog::STREAMS: lazer-fork, Stable, Legacy clients, Server, Website, Discord,
 * Wiki), and the changes in each: what kind, a title, a markdown message, a link, and who did it (a
 * player here or just a name). Versions written here have no commit hash, so the git importer's
 * --reset leaves them alone.
 */
class ChangelogController extends Controller
{
    private const TYPES = ['add' => 'Added', 'fix' => 'Fixed', 'misc' => 'Other'];

    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseChangelog(Auth::user()));

            return $next($request);
        });
    }

    public static function canUseChangelog(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $user->isDev());
    }

    public function index()
    {
        // The streams are made by a migration; this keeps a fresh database working too.
        TomYumChangelog::ensureStreams();
        $streams = $this->streams();
        $streamId = get_int(Request::input('stream'));

        $builds = Build::whereIn('stream_id', $streams->keys())
            ->when($streamId !== null, fn ($q) => $q->where('stream_id', $streamId))
            ->withCount('changelogEntries')
            ->orderByDesc('date')
            ->orderByDesc('build_id')
            ->paginate(50)
            ->withQueryString();

        return ext_view('panel.changelog.index', [
            'streams' => $streams,
            'streamId' => $streamId,
            'builds' => $builds,
            'shown' => $GLOBALS['cfg']['osu']['changelog']['update_streams'],
        ]);
    }

    public function show(string $build)
    {
        $model = $this->findBuild($build);
        $entries = $model->changelogEntries()->with(['githubUser.user', 'tomyumUser'])->orderBy('id')->get();
        $editing = present(Request::input('edit')) ? $entries->firstWhere('id', (int) Request::input('edit')) : null;

        return ext_view('panel.changelog.show', [
            'build' => $model,
            'streams' => $this->streams(),
            'entries' => $entries,
            'editing' => $editing,
            'types' => static::TYPES,
            'categories' => ChangelogEntry::whereIn('id', DB::table('build_changelog_entry')
                ->whereIn('build_id', Build::where('stream_id', $model->stream_id)->select('build_id'))
                ->select('changelog_entry_id'))
                ->whereNotNull('category')->distinct()->orderBy('category')->limit(100)->pluck('category'),
        ]);
    }

    public function stream(string $stream)
    {
        $model = UpdateStream::whereIn('stream_id', TomYumChangelog::streamIds())->findOrFail((int) $stream);
        $pretty = trim((string) Request::input('pretty_name'));
        if ($pretty === '' || mb_strlen($pretty) > 50) {
            return $this->back('error', 'A stream\'s name is 1 to 50 characters.');
        }
        $model->update(['pretty_name' => $pretty]);
        $this->staffLog('changelog.stream', $model->getKey(), "Renamed the changelog stream {$model->name} to \"{$pretty}\"");

        return $this->back('success', 'Renamed.');
    }

    public function storeBuild()
    {
        $streams = $this->streams();
        $existing = present(Request::input('id')) ? $this->findBuild((string) Request::input('id')) : null;
        $streamId = get_int(Request::input('stream_id')) ?? $existing?->stream_id;
        if (!$streams->has($streamId)) {
            return $this->back('error', 'Pick a stream.');
        }
        try {
            $date = present(Request::input('date')) ? Carbon::parse(Request::input('date'), 'UTC') : ($existing?->date ?? Carbon::now());
        } catch (\Throwable) {
            return $this->back('error', 'That date isn\'t a date.');
        }
        $version = trim((string) Request::input('version'));
        if ($version === '') {
            $version = $existing?->version ?? TomYumChangelog::nextVersion($streamId, $date);
        }
        if (strlen($version) > 40 || preg_match('/^[\w.+-]+$/', $version) !== 1) {
            return $this->back('error', 'A version is up to 40 letters, digits, dots, dashes and pluses (e.g. 2026.1006.0 or 1.4.0).');
        }
        $taken = Build::where('stream_id', $streamId)->where('version', $version)
            ->when($existing !== null, fn ($q) => $q->where('build_id', '<>', $existing->getKey()))
            ->exists();
        if ($taken) {
            return $this->back('error', "{$streams[$streamId]->pretty_name} already has a version {$version}.");
        }
        $youtube = presence(trim((string) Request::input('youtube_id')));
        if ($youtube !== null && preg_match('/^[\w-]{6,20}$/', $youtube) !== 1) {
            return $this->back('error', 'The video is a YouTube video id (the part after watch?v=).');
        }

        $build = $existing ?? new Build([
            // Not a client build: nothing may log in or submit scores as it.
            'allow_bancho' => false,
            'allow_ranking' => false,
        ]);
        $build->fill([
            'stream_id' => $streamId,
            'version' => $version,
            'date' => $date,
            'youtube_id' => $youtube,
            'private' => Request::boolean('private'),
        ])->save();
        $this->staffLog('changelog.version', $build->getKey(), ($existing === null ? 'Added' : 'Edited')." the changelog version {$streams[$streamId]->pretty_name} {$version}");

        return redirect(route('panel.changelog.builds.show', ['build' => $build->getKey()]))->with('panel_status', [
            'type' => 'success',
            'message' => $existing === null ? 'Version added: now write its changes.' : 'Saved.',
            'link' => $build->private ? null : ['url' => $build->url(), 'text' => 'see it'],
        ]);
    }

    public function destroyBuild(string $build)
    {
        $model = $this->findBuild($build);
        DB::transaction(function () use ($model) {
            $entryIds = DB::table('build_changelog_entry')->where('build_id', $model->getKey())->pluck('changelog_entry_id');
            DB::table('build_changelog_entry')->where('build_id', $model->getKey())->delete();
            // Changes only in this version go with it.
            $elsewhere = DB::table('build_changelog_entry')->whereIn('changelog_entry_id', $entryIds)->pluck('changelog_entry_id');
            ChangelogEntry::whereIn('id', $entryIds->diff($elsewhere))->delete();
            $model->delete();
        });
        $this->staffLog('changelog.version.remove', $model->getKey(), "Removed the changelog version {$model->version}");

        return $this->back('success', "Removed version {$model->version}.");
    }

    public function storeEntry(string $build)
    {
        $model = $this->findBuild($build);
        $entry = present(Request::input('id'))
            ? $model->changelogEntries()->findOrFail((int) Request::input('id'))
            : new ChangelogEntry();

        $title = trim((string) Request::input('title'));
        if ($title === '' || mb_strlen($title) > 255) {
            return $this->backToBuild($model, 'error', 'A change needs a title (up to 255 characters).');
        }
        $type = (string) Request::input('type');
        if (!array_key_exists($type, static::TYPES)) {
            return $this->backToBuild($model, 'error', 'Pick what kind of change it is.');
        }
        $url = presence(trim((string) Request::input('url')));
        if ($url !== null && (strlen($url) > 255 || !preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false)) {
            return $this->backToBuild($model, 'error', 'The link must be a full http(s) address (up to 255 characters).');
        }
        $userQuery = trim((string) Request::input('user'));
        $user = null;
        if ($userQuery !== '') {
            $user = ctype_digit($userQuery) ? User::find((int) $userQuery) : User::lookup($userQuery, 'username');
            if ($user === null) {
                return $this->backToBuild($model, 'error', "There's no player \"{$userQuery}\".");
            }
        }
        $author = presence(mb_substr(trim((string) Request::input('author')), 0, 100));
        $message = str_replace("\r\n", "\n", trim((string) Request::input('message')));

        $entry->fill([
            'type' => $type,
            'category' => presence(mb_substr(trim((string) Request::input('category')), 0, 255)),
            'title' => $title,
            'message' => presence($message),
            'url' => $url,
            'major' => Request::boolean('major'),
            'private' => Request::boolean('private'),
            'tomyum_user_id' => $user?->getKey(),
            'tomyum_author' => $user === null ? $author : null,
        ]);
        DB::transaction(function () use ($entry, $model) {
            $isNew = !$entry->exists;
            $entry->save();
            if ($isNew) {
                DB::table('build_changelog_entry')->insertOrIgnore(['build_id' => $model->getKey(), 'changelog_entry_id' => $entry->getKey()]);
            }
        });
        $this->staffLog('changelog.change', $entry->getKey(), "Saved the change \"{$title}\" in {$model->version}");

        return $this->backToBuild($model, 'success', 'Saved.');
    }

    public function destroyEntry(string $build, string $entry)
    {
        $model = $this->findBuild($build);
        $change = $model->changelogEntries()->findOrFail((int) $entry);
        DB::transaction(function () use ($model, $change) {
            DB::table('build_changelog_entry')->where('build_id', $model->getKey())->where('changelog_entry_id', $change->getKey())->delete();
            if (!DB::table('build_changelog_entry')->where('changelog_entry_id', $change->getKey())->exists()) {
                $change->delete();
            }
        });
        $this->staffLog('changelog.change.remove', $change->getKey(), "Removed the change \"{$change->title}\" from {$model->version}");

        return $this->backToBuild($model, 'success', 'Removed.');
    }

    /** The changelog's streams, by id. */
    private function streams()
    {
        return UpdateStream::whereIn('stream_id', TomYumChangelog::streamIds())->get()
            ->sortBy(fn ($stream) => array_search($stream->getKey(), TomYumChangelog::streamIds(), true))
            ->keyBy('stream_id');
    }

    /** A version in one of the changelog's streams (never the stream-less lazer build 0). */
    private function findBuild(string $id): Build
    {
        return Build::whereIn('stream_id', TomYumChangelog::streamIds())->findOrFail(get_int($id));
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.changelog.index'))->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }

    private function backToBuild(Build $build, string $type, string $message)
    {
        return redirect(route('panel.changelog.builds.show', ['build' => $build->getKey()]))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}

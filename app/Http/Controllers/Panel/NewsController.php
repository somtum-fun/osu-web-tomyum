<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Libraries\Markdown\OsuMarkdown;
use App\Models\NewsPost;
use Auth;
use Carbon\Carbon;
use Request;
use Symfony\Component\Yaml\Yaml;

/**
 * Admin panel: this server's news (the website's news, the dashboard and lazer's news list). ppy's
 * posts come from the osu-wiki repository on GitHub; these are written here in the same markdown and
 * kept apart from that sync (NewsPost::isLocal).
 */
class NewsController extends Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->middleware(function ($request, $next) {
            $this->ensure(static::canUseAdmin(Auth::user()));

            return $next($request);
        });
    }

    public function index()
    {
        $posts = NewsPost::where('hash', NewsPost::LOCAL_HASH)->orderByDesc('id')->get();
        $editing = present(Request::input('edit')) ? $posts->firstWhere('id', (int) Request::input('edit')) : null;

        return ext_view('panel.admin.news', [
            'posts' => $posts,
            'editing' => $editing,
            'series' => NewsPost::SERIES,
        ]);
    }

    public function store()
    {
        $title = trim((string) Request::input('title'));
        $body = trim(str_replace("\r\n", "\n", (string) Request::input('body')));
        if ($title === '' || $body === '') {
            return $this->back('error', 'A post needs a title and some text.');
        }
        try {
            $date = Carbon::parse(presence(Request::input('date')) ?? 'now', 'UTC');
        } catch (\Throwable) {
            return $this->back('error', 'That date isn\'t a date.');
        }

        $post = present(Request::input('id'))
            ? NewsPost::where('hash', NewsPost::LOCAL_HASH)->findOrFail((int) Request::input('id'))
            : new NewsPost(['slug' => $this->slug($date, $title)]);

        $header = array_filter([
            'layout' => 'post',
            'title' => $title,
            'date' => $date->format('Y-m-d H:i:s +0000'),
            'author' => presence(Request::input('author')) ?? Auth::user()->username,
            'series' => in_array(Request::input('series'), NewsPost::SERIES, true) ? Request::input('series') : null,
        ]);
        $page = (new OsuMarkdown('news', osuExtensionConfig: ['relative_url_root' => route('news.show', $post->slug)]))
            ->load("---\n".Yaml::dump($header)."---\n\n".$body)
            ->toArray();
        $page['author'] = $header['author'];
        $page['tomyum_markdown'] = $body;

        $post->fill([
            'page' => $page,
            'version' => NewsPost::pageVersion(),
            'hash' => NewsPost::LOCAL_HASH,
            'published_at' => Request::boolean('draft') ? null : $date,
        ])->save();
        $this->staffLog('news.save', $post->getKey(), "Saved the news post \"{$title}\"".(Request::boolean('draft') ? ' (draft)' : ''));

        return redirect(route('panel.admin.news.index', ['edit' => $post->getKey()]))->with('panel_status', [
            'type' => 'success',
            'message' => Request::boolean('draft') ? 'Saved as a draft (not shown yet).' : 'Published.',
            'link' => Request::boolean('draft') ? null : ['url' => $post->url(), 'text' => 'see it'],
        ]);
    }

    public function destroy(string $post)
    {
        $model = NewsPost::where('hash', NewsPost::LOCAL_HASH)->findOrFail((int) $post);
        $model->delete();
        $this->staffLog('news.remove', $model->getKey(), "Removed the news post \"{$model->title()}\"");

        return $this->back('success', 'Removed.');
    }

    /** "2026-09-29-server-update", unique. */
    private function slug(Carbon $date, string $title): string
    {
        $base = $date->format('Y-m-d').'-'.(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title)), '-') ?: 'post');
        $slug = $base;
        for ($i = 2; NewsPost::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    private function back(string $type, string $message)
    {
        return redirect(route('panel.admin.news.index'))->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}

<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Libraries\Wiki\WikiSitemap;
use App\Models\Beatmapset;
use App\Models\NewsPost;
use App\Models\User;
use Cache;
use Route;

/**
 * TomYum: what search engines see. robots.txt and sitemap.xml list this server's own pages: its
 * players, news, wiki, and the beatmap sets made here (ids from 10,000,000). osu.ppy.sh's own sets,
 * which this site mirrors, are left out and marked noindex, so the search results for them stay
 * osu.ppy.sh's. IndexNow (Bing, Yandex, Seznam, Naver, ...) is told about the sitemap's pages by
 * `tomyum:indexnow`.
 */
class TomYumSeo
{
    /** Beatmap sets and beatmaps made on this server start here; lower ids are osu.ppy.sh's. */
    public const OWN_ID_FLOOR = 10_000_000;

    private const SITEMAP_CACHE_KEY = 'tomyum:seo:sitemap';
    private const SITEMAP_CACHE_SECONDS = 6 * 3600;

    /** Players in the sitemap, most active first. */
    private const MAX_USERS = 20_000;

    /** Whether this request is a page of one of osu.ppy.sh's beatmaps (kept out of search results). */
    public static function mirrorsOsu(): bool
    {
        $route = Route::current();
        if ($route === null) {
            return false;
        }

        $name = $route->getName() ?? '';
        if (str_starts_with($name, 'beatmapsets.')) {
            $id = $route->parameter('beatmapset');
        } elseif (str_starts_with($name, 'beatmaps.')) {
            $id = $route->parameter('beatmap');
        } else {
            return false;
        }
        $id = is_object($id) ? $id->getKey() : $id;

        return is_numeric($id) && (int) $id < static::OWN_ID_FLOOR;
    }

    public static function robots(): string
    {
        $disallow = [
            '/panel/',
            '/home/account/',
            '/home/messages',
            '/home/search',
            '/home/notifications',
            '/notifications',
            '/community/chat',
            '/oauth/',
            '/api/',
            '/session',
            '/password-reset',
            '/store/cart',
            '/store/checkout',
            '/store/orders',
            '/beatmapsets/*/download',
            '/scores/*/download',
        ];

        $lines = ['User-agent: *', 'Allow: /'];
        foreach ($disallow as $path) {
            $lines[] = "Disallow: {$path}";
        }
        $lines[] = '';
        $lines[] = 'Sitemap: '.route('tomyum.sitemap');

        return implode("\n", $lines)."\n";
    }

    public static function sitemap(): string
    {
        return Cache::remember(static::SITEMAP_CACHE_KEY, static::SITEMAP_CACHE_SECONDS, function () {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
                .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
            foreach (static::urls() as [$url, $lastmod]) {
                $xml .= '<url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>';
                if ($lastmod !== null) {
                    $xml .= '<lastmod>'.$lastmod.'</lastmod>';
                }
                $xml .= "</url>\n";
            }

            return $xml.'</urlset>'."\n";
        });
    }

    /** Every URL in the sitemap. */
    public static function sitemapUrls(): array
    {
        return array_map(fn ($entry) => $entry[0], static::urls());
    }

    /** IndexNow's key: made from the app key, so it stays the same and needs no setting. */
    public static function indexNowKey(): string
    {
        return substr(hash('sha256', $GLOBALS['cfg']['app']['key'].'|indexnow'), 0, 32);
    }

    /** [url, lastmod (W3C date) or null] for each page. */
    private static function urls(): array
    {
        $urls = [];
        $add = function (string $url, $lastmod = null) use (&$urls) {
            $urls[$url] = [$url, $lastmod === null ? null : json_time($lastmod)];
        };

        foreach ([
            ['home'],
            ['download'],
            ['news.index'],
            ['changelog.index'],
            ['beatmapsets.index'],
            ['dan.index'],
            ['custom-medals.index'],
            ['tournaments.index'],
            ['vote'],
        ] as [$name]) {
            if (Route::has($name)) {
                $add(route($name));
            }
        }
        if (Route::has('rankings')) {
            foreach (['osu', 'taiko', 'fruits', 'mania'] as $mode) {
                $add(route('rankings', ['mode' => $mode, 'type' => 'performance']));
            }
        }

        foreach (NewsPost::default()->get() as $post) {
            $add($post->url(), $post->updated_at ?? $post->published_at);
        }

        try {
            $cursor = ['', ''];
            while ($cursor !== null) {
                $search = WikiSitemap::allPagesSearch()->searchAfter(array_values($cursor));
                foreach ($search->response() as $hit) {
                    $add(wiki_url($hit->source('path'), $hit->source('locale')));
                }
                $cursor = $search->getSortCursor();
            }
        } catch (\Throwable $e) {
            // Elasticsearch down: the rest of the sitemap still helps.
            log_error($e);
        }

        Beatmapset::where('beatmapset_id', '>=', static::OWN_ID_FLOOR)
            ->whereNull('deleted_at')
            ->where('approved', '>', Beatmapset::STATES['pending'])
            ->orderBy('beatmapset_id')
            ->get(['beatmapset_id', 'last_update'])
            ->each(fn ($set) => $add(route('beatmapsets.show', ['beatmapset' => $set->getKey()]), $set->last_update));

        // Players with a score (not restricted, not bots).
        User::default()
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('scores')->whereColumn('scores.user_id', 'phpbb_users.user_id'))
            ->where('user_id', '!=', $GLOBALS['cfg']['osu']['legacy']['bancho_bot_user_id'])
            ->orderBy('user_lastvisit', 'desc')
            ->limit(static::MAX_USERS)
            ->get(['user_id', 'user_lastvisit'])
            ->each(fn ($user) => $add(route('users.show', ['user' => $user->getKey()])));

        return array_values($urls);
    }
}

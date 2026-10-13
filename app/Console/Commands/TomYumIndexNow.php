<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use App\Libraries\TomYumSeo;
use Cache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * TomYum: tells IndexNow's search engines (Bing, Yandex, Seznam, Naver, ...) about the sitemap's pages
 * that it hasn't sent before (all of them with --all). Google reads the sitemap from robots.txt instead.
 */
class TomYumIndexNow extends Command
{
    private const SENT_CACHE_KEY = 'tomyum:seo:indexnow-sent';

    protected $signature = 'tomyum:indexnow {--all : Send every page, not only new ones} {--dry-run : Only list what would be sent}';

    protected $description = 'Tells IndexNow search engines about new pages in the sitemap.';

    public function handle()
    {
        $host = parse_url($GLOBALS['cfg']['app']['url'], PHP_URL_HOST);
        if ($host === null || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $this->warn('Not a public site; nothing sent.');

            return;
        }

        $urls = TomYumSeo::sitemapUrls();
        $sent = $this->option('all') ? [] : Cache::get(static::SENT_CACHE_KEY, []);
        $new = array_values(array_diff($urls, $sent));
        if ($new === []) {
            $this->info('No new pages.');

            return;
        }
        if ($this->option('dry-run')) {
            $this->line(implode("\n", $new));
            $this->info(count($new).' page(s) would be sent.');

            return;
        }

        foreach (array_chunk($new, 10_000) as $chunk) {
            $response = Http::timeout(30)->post('https://api.indexnow.org/indexnow', [
                'host' => $host,
                'key' => TomYumSeo::indexNowKey(),
                'keyLocation' => route('tomyum.indexnow-key'),
                'urlList' => $chunk,
            ]);
            if (!$response->successful()) {
                $this->error("IndexNow refused the list: HTTP {$response->status()} {$response->body()}");

                return 1;
            }
        }

        Cache::forever(static::SENT_CACHE_KEY, $urls);
        $this->info(count($new).' page(s) sent to IndexNow.');
    }
}

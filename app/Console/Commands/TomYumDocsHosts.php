<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * TomYum: the API docs (public/docs, made by scribe:generate as the image is built) with this server's
 * addresses in their examples instead of osu!'s: osu.ppy.sh is the site, a.ppy.sh its avatar host
 * (a.<domain>), the other hosts (assets, the image proxy) the site too, and notify.ppy.sh its
 * notification websocket. Links to osu!'s wiki and to ppy's spectator server docs are kept: they're
 * about the game and the spectator server, which are osu!'s.
 */
class TomYumDocsHosts extends Command
{
    protected $signature = 'tomyum:docs-hosts';

    protected $description = 'Puts this server\'s addresses in the generated API docs (public/docs) in place of osu!\'s.';

    public function handle()
    {
        $site = rtrim($GLOBALS['cfg']['app']['url'], '/');
        $host = parse_url($site, PHP_URL_HOST);
        // osu.example.com -> example.com, for the avatar host a.example.com.
        $domain = preg_replace('/^osu\./', '', $host);

        $replacements = [
            'wss://notify.ppy.sh' => str_starts_with($endpoint = $GLOBALS['cfg']['osu']['notification']['endpoint'], 'ws')
                ? $endpoint
                : 'wss://'.$host.$endpoint,
            // osu!'s wiki stays osu!'s (it's about the game): hide it from the next rule.
            'https://osu.ppy.sh/wiki/' => "\0wiki\0",
            'https://osu.ppy.sh' => $site,
            'https://a.ppy.sh' => "https://a.{$domain}",
            'https://assets.ppy.sh' => $site,
            'https://i.ppy.sh' => $site,
            "\0wiki\0" => 'https://osu.ppy.sh/wiki/',
        ];

        $changed = 0;
        foreach (['index.html', 'collection.json', 'openapi.yaml'] as $file) {
            $path = public_path("docs/{$file}");
            if (!is_file($path)) {
                continue;
            }

            $text = file_get_contents($path);
            foreach ($replacements as $from => $to) {
                // collection.json writes slashes as \/.
                foreach ([[$from, $to], [str_replace('/', '\\/', $from), str_replace('/', '\\/', $to)]] as [$a, $b]) {
                    $text = str_replace($a, $b, $text, $count);
                    $changed += $count;
                }
            }
            file_put_contents($path, $text);
        }

        $this->info("Docs: {$changed} addresses now point at {$host}.");
    }
}

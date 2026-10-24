<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * TomYum: osu-web now reads each OAuth client's grant types from the grant_types column and keeps
 * client secrets hashed (Passport checks them with Hash::check). osu! converted its own clients
 * outside the repository, so existing clients here (osu!lazer's id 5, BN Management, players' own
 * apps) would have no grant types and a plain secret: lazer's logins would count as verified
 * (Token::setVerifiedState) and every secret check would fail.
 *
 * Grant types are filled in as osu-web's Client::grantTypes() worked them out before the column,
 * and plain secrets are hashed. Apps keep sending the same secret; it can't be shown again (the
 * last four characters are kept as its hint).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('oauth_clients')->get() as $client) {
            $changes = [];

            if ($client->grant_types === null) {
                $firstParty = $client->personal_access_client || $client->password_client;
                $changes['grant_types'] = json_encode(array_keys(array_filter([
                    'authorization_code' => !$firstParty,
                    'client_credentials' => present($client->secret),
                    'password' => (bool) $client->password_client,
                    'personal_access' => (bool) $client->personal_access_client,
                    'refresh_token' => true,
                ])));
            }

            if (present($client->secret) && password_get_info($client->secret)['algo'] === null) {
                $changes['secret'] = Hash::make($client->secret);
                $changes['secret_hint'] ??= $client->secret_hint ?? substr($client->secret, -4);
            }

            if ($changes !== []) {
                DB::table('oauth_clients')->where('id', $client->id)->update($changes);
            }
        }
    }

    public function down(): void
    {
        // Hashed secrets can't be turned back into plain ones.
    }
};

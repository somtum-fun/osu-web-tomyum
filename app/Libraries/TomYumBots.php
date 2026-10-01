<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Exceptions\ValidationException;
use App\Libraries\User\PasswordHelper;
use App\Models\LegacyIrcKey;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Collection;

/**
 * TomYum: bot accounts (tomyum_bot_accounts). Admins and developers make them from the panel; each is
 * in osu-web's `bot` group and has an owner. A bot chats over IRC (its IRC key is its IRC password)
 * and can use the website (teams, forum), but the game server refuses it in stable and lazer and
 * doesn't take its scores. Its website verification codes go to its owner.
 */
class TomYumBots
{
    /** Bots get a placeholder address (emails are unique, and nobody reads a bot's mail). */
    public const EMAIL_DOMAIN = 'bots.invalid';

    public const IRC_PASSWORD_MIN = 8;

    public const IRC_PASSWORD_MAX = 50;

    /** Whether the user may make bots and manage every bot: admins and developers. */
    public static function canManageAll(?User $user): bool
    {
        return $user !== null && ($user->isAdmin() || $user->isDev());
    }

    /** Whether the user may change this bot: its owner, an admin or a developer. */
    public static function canManage(?User $user, User $bot): bool
    {
        if ($user === null || !$bot->isBot() || static::isBanchoBot($bot)) {
            return false;
        }

        return static::canManageAll($user) || static::ownerId($bot->getKey()) === $user->getKey();
    }

    /** BanchoBot is the server's own: the panel doesn't change it. */
    public static function isBanchoBot(User $bot): bool
    {
        return $bot->getKey() === $GLOBALS['cfg']['osu']['legacy']['bancho_bot_user_id'];
    }

    /** The bot's row (owner, maker, description), or null when it's not a bot made here (like BanchoBot). */
    public static function record(int $botId): ?object
    {
        return DB::table('tomyum_bot_accounts')->where('user_id', $botId)->first();
    }

    public static function ownerId(int $botId): ?int
    {
        $owner = static::record($botId)?->owner_id;

        return $owner === null ? null : (int) $owner;
    }

    public static function owner(User $bot): ?User
    {
        $ownerId = static::ownerId($bot->getKey());

        return $ownerId === null ? null : User::find($ownerId);
    }

    /** Ids of the bots this user owns. */
    public static function ownedBy(User $user): Collection
    {
        return DB::table('tomyum_bot_accounts')->where('owner_id', $user->getKey())->pluck('user_id');
    }

    /** Every account in the bot group, with its row when it has one, newest first. */
    public static function all(): Collection
    {
        $botGroupId = app('groups')->byIdentifier('bot')->getKey();
        $bots = User::where('group_id', $botGroupId)->orderByDesc('user_id')->get();
        $records = DB::table('tomyum_bot_accounts')->whereIn('user_id', $bots->pluck('user_id'))->get()->keyBy('user_id');

        return $bots->map(fn (User $bot) => (object) ['user' => $bot, 'record' => $records[$bot->getKey()] ?? null]);
    }

    /**
     * Makes a bot account in the bot group, owned by $owner. Returns the bot and its first IRC key.
     *
     * @throws ValidationException when the name can't be used.
     */
    public static function create(string $username, User $owner, User $actor, string $password, ?string $description): array
    {
        $registration = new UserRegistration([
            'username' => $username,
            'user_email' => 'bot-'.bin2hex(random_bytes(6)).'@'.static::EMAIL_DOMAIN,
            'password' => $password,
            'group' => 'bot',
        ]);
        $registration->save();
        $bot = $registration->user()->fresh();

        $bot->update(['user_email' => "bot{$bot->getKey()}@".static::EMAIL_DOMAIN]);
        DB::table('tomyum_bot_accounts')->insert([
            'user_id' => $bot->getKey(),
            'owner_id' => $owner->getKey(),
            'created_by' => $actor->getKey(),
            'description' => $description,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return [$bot, static::resetIrcKey($bot)];
    }

    public static function setOwner(User $bot, User $owner): void
    {
        DB::table('tomyum_bot_accounts')->updateOrInsert(
            ['user_id' => $bot->getKey()],
            ['owner_id' => $owner->getKey(), 'updated_at' => Carbon::now()],
        );
    }

    public static function setDescription(User $bot, ?string $description): void
    {
        DB::table('tomyum_bot_accounts')->updateOrInsert(
            ['user_id' => $bot->getKey()],
            ['description' => $description, 'updated_at' => Carbon::now()],
        );
    }

    /** The bot's IRC password (the game server checks it at IRC login), or null when it has none. */
    public static function ircKey(User $bot): ?string
    {
        return LegacyIrcKey::find($bot->getKey())?->token;
    }

    /** A new random IRC password; the old one stops working. */
    public static function resetIrcKey(User $bot): string
    {
        for ($i = 0; ; $i++) {
            try {
                return static::setIrcKey($bot, bin2hex(random_bytes(8)));
            } catch (\Exception $e) {
                if ($i >= 9 || !is_sql_unique_exception($e)) {
                    throw $e;
                }
            }
        }
    }

    /** Sets the IRC password to one the owner picks; null when it's not allowed (why is in the message). */
    public static function setCustomIrcKey(User $bot, string $password): ?string
    {
        $length = strlen($password);
        if ($length < static::IRC_PASSWORD_MIN || $length > static::IRC_PASSWORD_MAX) {
            return 'An IRC password is '.static::IRC_PASSWORD_MIN.' to '.static::IRC_PASSWORD_MAX.' characters.';
        }
        if (!preg_match('/^[\x21-\x7e]+$/', $password)) {
            return 'An IRC password has letters, numbers and symbols only (no spaces).';
        }
        if (LegacyIrcKey::where('token', $password)->where('user_id', '<>', $bot->getKey())->exists()) {
            return 'Pick a different IRC password.';
        }
        static::setIrcKey($bot, $password);

        return null;
    }

    public static function setPassword(User $bot, string $password): void
    {
        $bot->update(['user_password' => PasswordHelper::make($password)]);
    }

    /** Who gets mail meant for this account: a bot's owner, otherwise the account itself. */
    public static function mailRecipient(User $user): User
    {
        if (!$user->isBot()) {
            return $user;
        }

        return static::owner($user) ?? $user;
    }

    private static function setIrcKey(User $bot, string $token): string
    {
        $key = LegacyIrcKey::find($bot->getKey()) ?? new LegacyIrcKey();
        $key->user_id = $bot->getKey();
        $key->token = $token;
        $key->saveOrExplode();

        return $token;
    }
}

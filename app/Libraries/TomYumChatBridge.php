<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use App\Models\Chat\Channel;
use App\Models\Chat\Message;
use App\Models\User;
use Illuminate\Support\Facades\Redis as LaravelRedis;

/**
 * Links website chat with the TomYum game server. Messages sent on the website in public channels
 * and PMs are published on Redis ({@see REDIS_CHANNEL}) for TomYum to deliver in-game; messages
 * from the game come in through {@see fromGame()}, which posts them here without publishing them
 * back (so nothing loops).
 */
class TomYumChatBridge
{
    public const REDIS_CHANNEL = 'tomyum:chat';

    private static bool $receivingFromGame = false;

    /** Whether the message being saved came from the game (it isn't published back or rate limited). */
    public static function isReceivingFromGame(): bool
    {
        return static::$receivingFromGame;
    }

    /** Called for every message saved on the website (Channel::receiveMessage). */
    public static function publish(Message $message): void
    {
        if (static::$receivingFromGame) {
            return;
        }

        $channel = $message->channel;
        if (!$channel->isPublic() && !$channel->isPM()) {
            return;
        }

        $sender = $message->sender;
        $payload = json_encode([
            'message_id' => $message->getKey(),
            'sender_id' => $sender->getKey(),
            'sender' => $sender->username,
            'channel' => $channel->isPM() ? null : $channel->name,
            'target_id' => $channel->isPM() ? $channel->pmTargetFor($sender)?->getKey() : null,
            'content' => $message->content,
            'is_action' => (bool) $message->is_action,
        ]);

        // Chat has its own connection: publish once that transaction has committed.
        $message->getConnection()->afterCommit(fn () => LaravelRedis::connection('default')->publish(static::REDIS_CHANNEL, $payload));
    }

    /**
     * Posts a message sent in-game: to a public channel by name, or as a PM to $targetId. Returns the
     * message, or null when the channel doesn't exist on the website.
     */
    public static function fromGame(User $sender, ?string $channelName, ?User $target, string $content, bool $isAction): ?Message
    {
        static::$receivingFromGame = true;
        try {
            if ($target !== null) {
                // The website's blocks and "friends only" count for PMs from the game too.
                if (!static::mayMessage($sender, $target)) {
                    abort(403, 'blocked');
                }

                return Chat::sendPrivateMessage($sender, $target, $content, $isAction, null, false);
            }

            $channel = Channel::where('name', $channelName)->where('type', Channel::TYPES['public'])->first();

            return $channel === null ? null : Chat::sendMessage($sender, $channel, $content, $isAction, null, false);
        } finally {
            static::$receivingFromGame = false;
        }
    }

    /**
     * Whether the sender may PM the target, by the rules of {@see \App\Singletons\OsuAuthorize::checkChatPmStart()}
     * that matter for a message from the game: neither has blocked the other, and a target taking messages
     * from friends only has added the sender. Moderators and bots (BanchoBot) may always write.
     */
    public static function mayMessage(User $sender, User $target): bool
    {
        if ($sender->isModerator() || $sender->isBot()) {
            return true;
        }

        if ($target->hasBlocked($sender) || $sender->hasBlocked($target)) {
            return false;
        }

        return !$target->pm_friends_only || $target->hasFriended($sender);
    }

    /** Creates (or updates the description of) the game's public channels on the website. */
    public static function ensureChannels(array $channels): int
    {
        $created = 0;
        foreach ($channels as $entry) {
            $name = (string) ($entry['name'] ?? '');
            if (!str_starts_with($name, '#') || mb_strlen($name) > 50) {
                continue;
            }

            $channel = Channel::where('name', $name)->where('type', Channel::TYPES['public'])->first();
            if ($channel === null) {
                $channel = new Channel(['name' => $name, 'type' => Channel::TYPES['public']]);
                $created++;
            }
            $channel->description = mb_substr((string) ($entry['description'] ?? ''), 0, 256);
            $channel->moderated = (bool) ($entry['moderated'] ?? false);
            $channel->save();
        }

        return $created;
    }
}

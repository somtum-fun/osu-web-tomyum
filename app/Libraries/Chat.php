<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Libraries;

use App\Exceptions\API;
use App\Exceptions\InvariantException;
use App\Models\Chat\Channel;
use App\Models\User;
use LaravelRedis as Redis;

class Chat
{
    public static function ack(User $user)
    {
        $channelIds = $user->channels()->public()->pluck((new Channel())->qualifyColumn('channel_id'));
        $userId = $user->getKey();
        $timestamp = time();
        $transaction = Redis::transaction();

        foreach ($channelIds as $channelId) {
            Channel::ack($channelId, $userId, $timestamp, $transaction);
        }

        $transaction->exec();
    }

    public static function createAnnouncement(User $sender, array $rawParams)
    {
        priv_check_user($sender, 'ChatAnnounce')->ensureCan();

        $params = get_params($rawParams, null, [
            'channel:array',
            'message:string',
            'target_ids:int[]',
            'uuid',
        ], ['null_missing' => true]);

        if (!isset($params['target_ids'])) {
            throw new InvariantException('missing target_ids parameter');
        }

        if (!isset($params['channel'])) {
            throw new InvariantException('missing channel parameter');
        }

        $users = User::find($params['target_ids']);
        if ($users->isEmpty()) {
            throw new InvariantException('Nobody to broadcast to!');
        }

        $users = $users->push($sender)->uniqueStrict('user_id');

        $channel = (new Channel())->getConnection()->transaction(function () use ($sender, $params, $users) {
            $channel = Channel::createAnnouncement($users, $params['channel'], $params['uuid']);
            static::sendMessage($sender, $channel, $params['message'], false);

            return $channel;
        });

        return $channel;
    }

    // TomYum: a group chat (the chat design's Groups) with the given players, for those the sender
    // could message directly (blocks and friends-only settings hold), and the sender. An optional
    // first message.
    public static function createGroup(User $sender, array $rawParams)
    {
        priv_check_user($sender, 'ChatGroupCreate')->ensureCan();

        $params = get_params($rawParams, null, [
            'channel:array',
            'message:string',
            'target_ids:int[]',
            'uuid',
        ], ['null_missing' => true]);

        if (!isset($params['channel'])) {
            throw new InvariantException('missing channel parameter');
        }

        $targetIds = array_slice(array_unique($params['target_ids'] ?? []), 0, Channel::GROUP_MAX_MEMBERS - 1);
        $users = User::whereIn('user_id', $targetIds)->default()->get()->filter(
            fn (User $user) => !$user->is($sender)
                && !$user->isBot()
                && priv_check_user($sender, 'ChatPmStart', $user)->can()
        );
        if ($users->isEmpty()) {
            throw new InvariantException('Nobody to add to the group!');
        }

        $users = $users->push($sender)->uniqueStrict('user_id');

        return (new Channel())->getConnection()->transaction(function () use ($sender, $params, $users) {
            $channel = Channel::createGroup($users, $params['channel'], $params['uuid']);
            if (present($params['message'])) {
                static::sendMessage($sender, $channel, $params['message'], false);
            }

            return $channel;
        });
    }

    // Do the restricted user lookup before calling this.
    public static function sendPrivateMessage(User $sender, User $target, ?string $message, ?bool $isAction, ?string $uuid = null, bool $privCheck = true)
    {
        if ($privCheck) {
            priv_check_user($sender, 'ChatPmStart', $target)->ensureCan();
        }

        return (new Channel())->getConnection()->transaction(function () use ($sender, $target, $message, $isAction, $uuid, $privCheck) {
            $channel = Channel::findPM($target, $sender) ?? Channel::createPM($target, $sender);

            return static::sendMessage($sender, $channel, $message, $isAction, $uuid, $privCheck);
        });
    }

    public static function sendMessage(User $sender, Channel $channel, ?string $message, ?bool $isAction, ?string $uuid = null, bool $privCheck = true)
    {
        if ($channel->isPM()) {
            // restricted users should be treated as if they do not exist
            // (TomYum: except by BanchoBot, which tells them about their restriction.)
            if (
                $sender->getKey() !== $GLOBALS['cfg']['osu']['legacy']['bancho_bot_user_id']
                && optional($channel->pmTargetFor($sender))->isRestricted()
            ) {
                abort(404, 'target user not found');
            }
        }

        if ($privCheck) {
            priv_check_user($sender, 'ChatChannelSend', $channel)->ensureCan();
        }

        try {
            return $channel->receiveMessage($sender, $message, $isAction ?? false, $uuid);
        } catch (API\ChatMessageEmptyException $e) {
            abort(422, $e->getMessage());
        } catch (API\ChatMessageTooLongException $e) {
            abort(422, $e->getMessage());
        } catch (API\ExcessiveChatMessagesException $e) {
            abort(429, $e->getMessage());
        }
    }
}

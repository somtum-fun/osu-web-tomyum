<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers;

use App\Libraries\Payments\PaymentCompleted;
use App\Libraries\TomYumChatBridge;
use App\Libraries\TomYumCountryStats;
use App\Libraries\TomYumRegistrationGuard;
use App\Models\Beatmapset;
use App\Models\Store\Order;
use App\Models\Store\Payment;
use App\Models\User;
use Carbon\Carbon;
use DB;

/**
 * InterOp endpoints for the TomYum game server (under /_lio, same signed auth as LegacyInterOp).
 */
class TomYumInterOpController extends Controller
{
    public function indexBeatmapsetDocument($id)
    {
        Beatmapset::withTrashed()->findOrFail($id)->esIndexDocument();

        return response()->noContent();
    }

    /**
     * A store order payments-service took payment for (Stripe): paid and fulfilled like any
     * osu!store order (PaymentCompleted: the supporter tags, gift notifications, the receipt mail). Told
     * twice (a retried webhook), it only answers; an amount that isn't the order's total is refused.
     */
    public function storeOrderPaid()
    {
        $params = get_params(request()->all(), null, [
            'amount_thb:float',
            'country_code',
            'order_id:int',
            'provider',
            'transaction_id',
        ], ['null_missing' => true]);
        abort_unless(in_array($params['provider'], [Order::PROVIDER_STRIPE], true), 422, 'unknown provider');
        abort_unless(present($params['transaction_id']), 422, 'transaction_id is required');

        $order = Order::findOrFail($params['order_id']);
        if ($order->isPaidOrDelivered()) {
            return ['status' => 'already_paid'];
        }
        abort_unless($order->isPendingPaymentCapture() || $order->status === Order::STATUS_INCART, 422, "order is {$order->status}");
        abort_unless(compare_currency((float) $params['amount_thb'], (float) $order->getTotal()) === 0, 422, 'amount does not match the order total');

        $order->provider = $params['provider'];
        $payment = new Payment([
            'country_code' => $params['country_code'],
            'paid_at' => Carbon::now(),
            'provider' => $params['provider'],
            'transaction_id' => $params['transaction_id'],
        ]);
        (new PaymentCompleted($order, $payment))->handle();

        return ['status' => 'paid'];
    }

    /**
     * osu!supporter bought through payments-service (Stripe): $days more for the target,
     * recorded as a donation from the donor like the admin panel's, so the support page and the
     * supporter level count it. A payment grants once: asked again for the same transaction_id (a
     * retried webhook), it only answers when supporter now ends.
     */
    public function grantSupporter()
    {
        $params = get_params(request()->all(), null, [
            'days:float',
            'donor_user_id:int',
            'target_user_id:int',
            'transaction_id:int',
        ], ['null_missing' => true]);
        $target = User::findOrFail($params['target_user_id']);
        $days = $params['days'] ?? 0;
        abort_unless($days > 0, 422, 'days must be greater than zero');
        $donorId = $params['donor_user_id'] ?? $target->getKey();
        $reference = $params['transaction_id'] === null ? 'payments-'.uniqid() : "payments-{$params['transaction_id']}";

        DB::transaction(function () use ($target, $days, $donorId, $reference) {
            $granted = DB::table('osu_user_donations')->where('user_id', $donorId)->where('transaction_id', 'like', "{$reference}%")->exists();
            if ($granted) {
                return;
            }

            // Months, for the supporter level (a row holds at most 127, so long ones take several).
            $months = max(1, (int) round($days / 30));
            for ($part = 0, $left = $months; $left > 0; $part++, $left -= 120) {
                DB::table('osu_user_donations')->insert([
                    'user_id' => $donorId,
                    'transaction_id' => $part === 0 ? $reference : "{$reference}-{$part}",
                    'target_user_id' => $target->getKey(),
                    'length' => min(120, $left),
                    'amount' => 0,
                    'cancel' => 0,
                ]);
            }
            $expiry = $target->osu_subscriptionexpiry !== null && $target->osu_subscriptionexpiry->isFuture()
                ? $target->osu_subscriptionexpiry
                : Carbon::now();
            $target->update(['osu_subscriber' => true, 'osu_subscriptionexpiry' => $expiry->copy()->addSeconds((int) round($days * 86400))]);
            $target->refreshSupportLength();
        });

        return ['donor_end' => $target->fresh()->osu_subscriptionexpiry->getTimestamp()];
    }

    public function recalculateCountry($acronym)
    {
        TomYumCountryStats::recalculate($acronym);

        return response()->noContent();
    }

    /** A message sent in-game: to a public channel (`channel`) or a PM (`target_id`). */
    public function chatMessage()
    {
        $params = request()->all();
        $sender = User::findOrFail(get_int($params['sender_id'] ?? null));
        $target = isset($params['target_id']) ? User::find(get_int($params['target_id'])) : null;
        if (isset($params['target_id']) && ($target === null || $target->is($sender))) {
            abort(422, 'target user not found');
        }

        $message = TomYumChatBridge::fromGame(
            $sender,
            get_string($params['channel'] ?? null),
            $target,
            (string) ($params['message'] ?? ''),
            get_bool($params['is_action'] ?? null) ?? false,
        );

        return $message === null
            ? response()->json(['error' => 'no such public channel'], 404)
            : ['message_id' => $message->getKey()];
    }

    /**
     * A player's Beatmap Nominator groups as BN Management (QAT) decided them: `modes` is
     * {ruleset: "full" | "probation" | null} (QAT's names: osu, taiko, catch, mania). Sets the `bng`
     * and `bng_limited` groups' modes to match (leaving a group when it has none); NAT and every other
     * group are left alone. `actor_id` is the NAT member who decided (the group history shows them).
     */
    public function bnGroups($id)
    {
        $user = User::findOrFail(get_int($id));
        $actor = User::find(get_int(request()->input('actor_id')))
            ?? User::find($GLOBALS['cfg']['osu']['legacy']['bancho_bot_user_id']);
        $rulesets = ['osu' => 'osu', 'taiko' => 'taiko', 'catch' => 'fruits', 'fruits' => 'fruits', 'mania' => 'mania'];

        $wanted = ['bng' => [], 'bng_limited' => []];
        foreach ((array) request()->input('modes', []) as $mode => $level) {
            $ruleset = $rulesets[$mode] ?? null;
            if ($ruleset === null) {
                continue;
            }
            if ($level === 'full') {
                $wanted['bng'][] = $ruleset;
            } elseif ($level === 'probation') {
                $wanted['bng_limited'][] = $ruleset;
            }
        }

        foreach ($wanted as $identifier => $playmodes) {
            $group = app('groups')->byIdentifier($identifier);
            if ($group === null) {
                continue;
            }
            if ($playmodes !== []) {
                $user->addToGroup($group, $playmodes, $actor);
            } elseif ($user->findUserGroup($group) !== null) {
                $user->removeFromGroup($group, $actor);
            }
        }

        // The game follows (BN privileges, the badge), as with a change from the admin panel.
        try {
            (new \App\Libraries\TomYumAdmin($actor))->post("/users/{$user->getKey()}/refresh");
        } catch (\Throwable $e) {
            log_error($e);
        }

        return $wanted;
    }

    /**
     * A set's osu! description (`description`, BBCode, null = leave as is) and current nominators
     * (`nominators`: [{id, username, rulesets}], osu! users), from TomYum's osu! API v2 import.
     */
    public function beatmapsetExtras($id)
    {
        $beatmapset = \App\Models\Beatmapset::findOrFail(get_int($id));
        $description = get_string(request()->input('description'));
        if ($description !== null && trim($description) !== trim($beatmapset->description()->bbcode()->toEditor())) {
            $author = User::find($GLOBALS['cfg']['osu']['legacy']['bancho_bot_user_id']) ?? User::firstOrFail();
            $beatmapset->description()->update($description, $author);
        }

        // Featured artist track (osu!'s track id): the listing's "Featured Artists" filter and the badge.
        if (request()->has('track_id')) {
            $trackId = get_int(request()->input('track_id'));
            if ($beatmapset->track_id !== $trackId) {
                \DB::table('osu_beatmapsets')->where('beatmapset_id', $beatmapset->getKey())->update(['track_id' => $trackId]);
            }
        }

        // Nominators are replaced only when sent (TomYum always sends them, an empty list too).
        \DB::transaction(function () use ($beatmapset) {
            if (!request()->has('nominators')) {
                return;
            }
            \DB::table('tomyum_beatmapset_nominators')->where('beatmapset_id', $beatmapset->getKey())->delete();
            foreach ((array) request()->input('nominators', []) as $nominator) {
                $userId = get_int($nominator['id'] ?? null);
                $username = get_string($nominator['username'] ?? null);
                if ($userId === null || $username === null) {
                    continue;
                }
                \DB::table('tomyum_beatmapset_nominators')->insertOrIgnore([
                    'beatmapset_id' => $beatmapset->getKey(),
                    'osu_user_id' => $userId,
                    'username' => mb_substr($username, 0, 32),
                    'rulesets' => isset($nominator['rulesets']) ? json_encode(array_values((array) $nominator['rulesets'])) : null,
                ]);
            }
        });

        // Who made the set (`creator`: {id, username}) and each difficulty (`mappers`: {beatmap id:
        // [{id, username}]}), osu! users; left as they are when not sent.
        if (request()->has('creator') || request()->has('mappers')) {
            \DB::transaction(function () use ($beatmapset) {
                \DB::table('tomyum_beatmap_mappers')->where('beatmapset_id', $beatmapset->getKey())->delete();
                $rows = [];
                $add = function (int $beatmapId, $user) use ($beatmapset, &$rows) {
                    $userId = get_int($user['id'] ?? null);
                    $username = get_string($user['username'] ?? null);
                    if ($userId !== null && $userId > 0 && $username !== null) {
                        $rows["{$beatmapId}-{$userId}"] = [
                            'beatmapset_id' => $beatmapset->getKey(),
                            'beatmap_id' => $beatmapId,
                            'osu_user_id' => $userId,
                            'username' => mb_substr($username, 0, 32),
                        ];
                    }
                };
                $creator = request()->input('creator');
                if (is_array($creator)) {
                    $add(0, $creator);
                }
                foreach ((array) request()->input('mappers', []) as $beatmapId => $users) {
                    $beatmapId = get_int($beatmapId);
                    if ($beatmapId === null || $beatmapId <= 0) {
                        continue;
                    }
                    foreach ((array) $users as $user) {
                        $add($beatmapId, $user);
                    }
                }
                \DB::table('tomyum_beatmap_mappers')->insertOrIgnore(array_values($rows));
            });
        }

        return response()->noContent();
    }

    /**
     * Stable's in-game sign-up (through TomYum): whether `ip` may make an account. `check` is the
     * keystroke check (the quick rules only). Answers {ok: true}, or {ok: false, code, message} with
     * the message the player sees.
     */
    public function registrationCheck()
    {
        $params = get_params(request()->all(), null, [
            'check:bool',
            'country',
            'ip',
            'source',
            'username',
        ], ['null_missing' => true]);
        abort_unless(present($params['ip']), 422, 'ip is required');

        $blocked = TomYumRegistrationGuard::check(
            $params['ip'],
            $params['source'] ?? 'stable',
            $params['check'] ?? false,
            $params['username'],
            $params['country'],
        );

        return $blocked === null ? ['ok' => true] : ['ok' => false] + $blocked;
    }

    /** An account stable's sign-up made (it counts for the 1 account per network a day rule). */
    public function registrationCreated()
    {
        $params = get_params(request()->all(), null, [
            'country',
            'ip',
            'source',
            'user_id:int',
            'username',
        ], ['null_missing' => true]);
        abort_unless(present($params['ip']) && $params['user_id'] !== null, 422, 'ip and user_id are required');

        TomYumRegistrationGuard::created($params['ip'], $params['source'] ?? 'stable', $params['user_id'], $params['username'], $params['country']);

        return response()->noContent();
    }

    /** Creates the game's public channels on the website (`channels`: [{name, description, moderated}]). */
    public function chatChannels()
    {
        return ['created' => TomYumChatBridge::ensureChannels(request()->input('channels') ?? [])];
    }
}

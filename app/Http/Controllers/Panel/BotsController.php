<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Http\Controllers\Panel;

use App\Exceptions\ChangeUsernameException;
use App\Exceptions\ValidationException;
use App\Libraries\TomYumBots;
use App\Libraries\User\AvatarHelper;
use App\Models\User;
use Auth;
use Request;

/**
 * Bot accounts (TomYum): admins and developers make them and pick their owner; the owner (or an
 * admin or developer) changes the bot's avatar, banner, me! page, name, website password and IRC
 * password. Bots chat over IRC and use the website; the game server doesn't let them play.
 */
class BotsController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $all = TomYumBots::canManageAll($user);
        $owned = TomYumBots::ownedBy($user);
        $this->ensure($all || $owned->isNotEmpty());

        $bots = TomYumBots::all();
        if (!$all) {
            $bots = $bots->filter(fn ($bot) => $owned->contains($bot->user->getKey()))->values();
        }
        $owners = User::whereIn('user_id', $bots->pluck('record.owner_id')->filter()->unique())->get()->keyBy('user_id');

        return ext_view('panel.bots.index', [
            'bots' => $bots,
            'canCreate' => $all,
            'owners' => $owners,
        ]);
    }

    public function store()
    {
        $actor = Auth::user();
        $this->ensure(TomYumBots::canManageAll($actor));

        $username = trim(Request::input('username') ?? '');
        $owner = $this->findUser(Request::input('owner')) ?? $actor;
        $password = Request::input('password') ?? '';
        $generated = $password === '';
        if ($generated) {
            $password = bin2hex(random_bytes(8));
        } elseif (strlen($password) < 8) {
            return $this->backToIndex('error', 'The website password needs at least 8 characters (or leave it empty for a random one).');
        }
        if ($owner->isBot()) {
            return $this->backToIndex('error', 'A bot can\'t own a bot.');
        }

        try {
            [$bot, $ircKey] = TomYumBots::create($username, $owner, $actor, $password, $this->description());
        } catch (ValidationException $e) {
            return $this->backToIndex('error', 'Bot not made: '.$e->getMessage());
        }
        $this->staffLog('bot.create', $bot->getKey(), "Made bot {$bot->username}, owned by {$owner->username}.");

        $message = "Made {$bot->username} (#{$bot->getKey()}), owned by {$owner->username}. IRC password: {$ircKey}"
            .($generated ? "; website password: {$password}" : '')
            .'. Save them now: they aren\'t shown again (the IRC password can be seen and changed on the bot\'s page).';

        return $this->back($bot, 'success', $message);
    }

    public function show(string $bot)
    {
        $target = $this->bot($bot);

        return ext_view('panel.bots.show', [
            'bot' => $target,
            'record' => TomYumBots::record($target->getKey()),
            'owner' => TomYumBots::owner($target),
            'ircKey' => TomYumBots::ircKey($target),
            'canManageAll' => TomYumBots::canManageAll(Auth::user()),
            'ircHost' => 'irc.'.preg_replace('/^osu\./', '', parse_url($GLOBALS['cfg']['app']['url'], PHP_URL_HOST) ?? ''),
        ]);
    }

    public function avatar(string $bot)
    {
        $target = $this->bot($bot);
        $file = Request::file('avatar_file');
        if (Request::boolean('remove')) {
            AvatarHelper::set($target, null);
        } elseif ($file === null) {
            return $this->back($target, 'error', 'Pick an image.');
        } else {
            try {
                AvatarHelper::set($target, $file);
            } catch (\Throwable $e) {
                return $this->back($target, 'error', 'Avatar not changed: '.$e->getMessage());
            }
        }

        return $this->done($target, 'bot.avatar', "Changed {$target->username}'s avatar.", 'Avatar changed.');
    }

    public function cover(string $bot)
    {
        $target = $this->bot($bot);
        $file = Request::file('cover_file');
        try {
            if (Request::boolean('remove')) {
                $target->cover()->set(null, null);
                $target->customCover()->delete();
            } elseif ($file === null) {
                return $this->back($target, 'error', 'Pick an image.');
            } else {
                $target->cover()->set(null, $file->getRealPath());
            }
            $target->saveOrExplode();
        } catch (\Throwable $e) {
            return $this->back($target, 'error', 'Banner not changed: '.$e->getMessage());
        }

        return $this->done($target, 'bot.cover', "Changed {$target->username}'s profile banner.", 'Profile banner changed.');
    }

    public function page(string $bot)
    {
        $target = $this->bot($bot);
        try {
            $target->updatePage(Request::input('body') ?? '');
        } catch (\Throwable $e) {
            return $this->back($target, 'error', 'me! page not saved: '.$e->getMessage());
        }

        return $this->done($target, 'bot.page', "Changed {$target->username}'s me! page.", 'me! page saved.');
    }

    public function rename(string $bot)
    {
        $target = $this->bot($bot);
        $name = trim(Request::input('username') ?? '');
        $old = $target->username;
        try {
            $target->changeUsername($name, 'admin');
        } catch (ChangeUsernameException $e) {
            return $this->back($target, 'error', 'Name not changed: '.implode(' ', $e->getErrors()->allMessages()));
        }

        return $this->done($target->fresh(), 'bot.rename', "Renamed bot {$old} to {$name}.", "Renamed to {$name}.");
    }

    public function irc(string $bot)
    {
        $target = $this->bot($bot);
        if (Request::input('action') === 'set') {
            $error = TomYumBots::setCustomIrcKey($target, Request::input('irc_password') ?? '');
            if ($error !== null) {
                return $this->back($target, 'error', $error);
            }
            $message = 'IRC password changed.';
        } else {
            $message = 'New IRC password: '.TomYumBots::resetIrcKey($target).'. The old one no longer works.';
        }

        return $this->done($target, 'bot.irc', "Changed {$target->username}'s IRC password.", $message);
    }

    public function password(string $bot)
    {
        $target = $this->bot($bot);
        $password = Request::input('password') ?? '';
        if (strlen($password) < 8) {
            return $this->back($target, 'error', 'The website password needs at least 8 characters.');
        }
        TomYumBots::setPassword($target, $password);

        return $this->done($target, 'bot.password', "Set a new website password for {$target->username}.", 'Website password changed.');
    }

    public function settings(string $bot)
    {
        $target = $this->bot($bot);
        $summary = [];
        if (TomYumBots::canManageAll(Auth::user()) && present(Request::input('owner'))) {
            $owner = $this->findUser(Request::input('owner'));
            if ($owner === null || $owner->isBot()) {
                return $this->back($target, 'error', 'No such player (a bot can\'t own a bot).');
            }
            TomYumBots::setOwner($target, $owner);
            $summary[] = "owner {$owner->username}";
        }
        TomYumBots::setDescription($target, $this->description());
        $summary[] = 'description';

        return $this->done($target, 'bot.settings', "Changed {$target->username}'s ".implode(' and ', $summary).'.', 'Saved.');
    }

    /** The bot the current user may change, or a 403/404. */
    private function bot(string $id): User
    {
        $target = User::findOrFail((int) $id);
        $this->ensure(TomYumBots::canManage(Auth::user(), $target));

        return $target;
    }

    private function findUser($input): ?User
    {
        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        return ctype_digit($input) ? User::find((int) $input) : User::lookup($input, 'username');
    }

    private function description(): ?string
    {
        $text = trim(Request::input('description') ?? '');

        return $text === '' ? null : mb_substr($text, 0, 255);
    }

    private function done(User $bot, string $action, string $summary, string $message)
    {
        $this->staffLog($action, $bot->getKey(), $summary);
        $this->refreshInGame($bot->getKey());

        return $this->back($bot, 'success', $message);
    }

    private function back(User $bot, string $type, string $message)
    {
        return redirect(route('panel.bots.show', ['bot' => $bot->getKey()]))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }

    private function backToIndex(string $type, string $message)
    {
        return redirect(route('panel.bots.index'))
            ->with('panel_status', ['type' => $type, 'message' => $message, 'link' => null]);
    }
}

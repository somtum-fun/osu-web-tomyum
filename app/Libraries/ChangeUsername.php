<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

namespace App\Libraries;

use App\Models\User;
use App\Traits\Validatable;

class ChangeUsername
{
    use Validatable;

    const LESS_VALIDATION_TYPES = ['admin', 'revert', 'support'];

    protected $type;

    /** @var User */
    protected $user;

    protected $username;

    public static function requireSupportedMessage()
    {
        $link = link_to(
            route('support-the-game'),
            osu_trans('model_validation.user.change_username.supporter_required.link_text')
        );

        return osu_trans('model_validation.user.change_username.supporter_required._', ['link' => $link]);
    }

    public function __construct(User $user, string $newUsername, string $type = 'paid')
    {
        $this->type = $type;
        $this->username = $newUsername;
        $this->user = $user;
    }

    public function validate(): ValidationErrors
    {
        $this->validationErrors()->reset();
        if ($this->user->user_id <= 1) {
            return $this->validationErrors()->addTranslated('user_id', 'This user cannot be renamed');
        }

        if ($this->hasExtraValidations() && $this->user->isRestricted()) {
            return $this->validationErrors()->add('username', '.change_username.restricted');
        }

        // TomYum: renaming yourself is free for everyone (the store's name change costs nothing);
        // without supporter, 5 times in 30 days (supporters: no limit).
        if ($this->hasExtraValidations() && ($limit = $this->monthlyLimitMessage()) !== null) {
            return $this->validationErrors()->addTranslated('username', $limit);
        }

        if (User::cleanUsername($this->username) === $this->user->username_clean) {
            return $this->validationErrors()->add('username', '.change_username.username_is_same');
        }

        if ($this->validationErrors()->merge(UsernameValidation::validateUsername($this->username))->isAny()) {
            return $this->validationErrors();
        }

        if ($this->validationErrors()->merge(UsernameValidation::validateUsersOfUsername($this->username, $this->user->getKey()))->isAny()) {
            return $this->validationErrors();
        }

        // TomYum: staff reverting a player to one of their own earlier names (the admin panel): the
        // name is kept for them, so its 180-day reservation doesn't stand in the way.
        if ($this->type === 'revert' && $this->isOwnEarlierName()) {
            return $this->validationErrors();
        }

        return $this->validationErrors()->merge(UsernameValidation::validateAvailability($this->username));
    }

    /** TomYum: renames a non-supporter may make in MONTHLY_WINDOW_DAYS days. */
    public const MONTHLY_LIMIT = 5;

    public const MONTHLY_WINDOW_DAYS = 30;

    /** TomYum: why a non-supporter can't rename right now (their monthly renames are used up), or null. */
    private function monthlyLimitMessage(): ?string
    {
        if ($this->user->isSupporter()) {
            return null;
        }
        $recent = static::recentChanges($this->user);
        if ($recent->count() < static::MONTHLY_LIMIT) {
            return null;
        }
        $next = $recent[$recent->count() - static::MONTHLY_LIMIT]->copy()->addDays(static::MONTHLY_WINDOW_DAYS);

        return 'Without supporter you can change your name '.static::MONTHLY_LIMIT.' times in '.static::MONTHLY_WINDOW_DAYS
            .' days (supporters have no limit). Your next change is available '.$next->toDayDateTimeString().' UTC.';
    }

    /** TomYum: renames a non-supporter has left in the window, or null for a supporter (no limit). */
    public static function remainingThisMonth(User $user): ?int
    {
        return $user->isSupporter() ? null : max(0, static::MONTHLY_LIMIT - static::recentChanges($user)->count());
    }

    /** TomYum: when the player's renames in the last MONTHLY_WINDOW_DAYS days were made, oldest first. */
    private static function recentChanges(User $user): \Illuminate\Support\Collection
    {
        return \App\Models\UsernameChangeHistory::where('user_id', $user->getKey())
            ->whereIn('type', ['paid', 'support'])
            ->where('timestamp', '>', \Carbon\Carbon::now()->subDays(static::MONTHLY_WINDOW_DAYS))
            ->orderBy('timestamp')
            ->pluck('timestamp');
    }

    /** TomYum: whether the last rename involving this name was the player's own (nobody had it since). */
    private function isOwnEarlierName(): bool
    {
        $last = \App\Models\UsernameChangeHistory::where('username_last', $this->username)
            ->orWhere('username', $this->username)
            ->orderByDesc('change_id')
            ->first();

        return $last !== null && $last->user_id === $this->user->getKey();
    }

    public function validationErrorsTranslationPrefix(): string
    {
        return 'user';
    }

    private function hasExtraValidations()
    {
        return !in_array($this->type, static::LESS_VALIDATION_TYPES, true);
    }
}

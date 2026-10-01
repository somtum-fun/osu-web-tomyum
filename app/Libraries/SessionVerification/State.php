<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries\SessionVerification;

use App\Interfaces\SessionVerificationInterface;
use App\Mail\UserVerification as UserVerificationMail;
use App\Models\User;

class State
{
    public function __construct(
        public SessionVerificationInterface $session,
        public User $user,
    ) {
    }

    public static function getCurrent(): static
    {
        return new static(Helper::currentSession(), \Auth::user());
    }

    public function getMethod(): string
    {
        $currentMethod = $this->session->getVerificationMethod();

        if ($currentMethod === null) {
            Helper::logAttempt(\Request::getClientIp(), $this->user, 'input', 'new');

            // force mail to prevent client without totp support from showing wrong message
            $currentMethod = (is_api_request() && api_version() < 20250913) || $this->user->userTotpKey === null
                ? 'mail'
                : 'totp';

            $this->session->setVerificationMethod($currentMethod);
        }

        return $currentMethod;
    }

    public function issueMail(bool $initial): void
    {
        if ($initial) {
            if (MailState::fromSession($this->session) === null) {
                Helper::logAttempt(\Request::getClientIp(), $this->user, 'link', 'new');
            } else {
                return;
            }
        }

        // TomYum: a bot's codes go to its owner (nobody reads a bot's address).
        $recipient = \App\Libraries\TomYumBots::mailRecipient($this->user);
        if (!is_valid_email_format($recipient->user_email)) {
            return;
        }

        $mailState = MailState::create($this->session);
        $keys = [
            'link' => $mailState->linkKey,
            'main' => $mailState->key,
        ];

        $requestCountry = app('countries')->byCode(request_country() ?? '')?->name;

        \Mail::to($recipient)->queue(new UserVerificationMail([
            'keys' => $keys,
            'requestCountry' => $requestCountry,
            'user' => $this->user,
        ]));
    }

    public function markVerified(?MailState $mailState): void
    {
        Helper::markVerified($this->session, $mailState);
    }
}

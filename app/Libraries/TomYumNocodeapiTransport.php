<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\MessageConverter;

/**
 * TomYum: sends the website's mail through NoCodeAPI's Zoho Mail endpoint (MAIL_DRIVER=nocodeapi,
 * NOCODEAPI_MAIL_URL=https://v1.nocodeapi.com/<user>/zohomail/<token>/sendEmail) instead of SMTP.
 * Every field is a query parameter, as NoCodeAPI's example does: it refuses the addresses in a body,
 * and takes a subject and content there as empty (the mail arrives blank). An HTML mail too long for
 * a URL goes as its plain-text version (MAX_QUERY_LENGTH). A mail goes from its own From when that's
 * one of this server's senders (MAIL_ADDRESS, or the store's MAIL_STORE_ADDRESS), else from
 * MAIL_ADDRESS: Zoho only sends as the account's own addresses (osu!'s, like osustore@ppy.sh, it
 * refuses). If Zoho refuses the store address, the mail is sent again from MAIL_ADDRESS.
 */
class TomYumNocodeapiTransport extends AbstractTransport
{
    /** Past this (URL-encoded), an HTML body is swapped for the mail's plain text. */
    private const MAX_QUERY_LENGTH = 7000;

    public function __construct(private string $url)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'nocodeapi';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());
        $envelope = $message->getEnvelope();
        $html = static::text($email->getHtmlBody());
        $text = static::text($email->getTextBody());
        if ($html !== null && $text !== null && strlen(rawurlencode($html)) > static::MAX_QUERY_LENGTH) {
            $html = null;
        }

        $default = $GLOBALS['cfg']['mail']['from']['address'];
        $from = strtolower($envelope->getSender()->getAddress());
        $senders = array_map('strtolower', array_filter([$default, $GLOBALS['cfg']['tomyum']['store_mail']['address'] ?? null]));
        $from = in_array($from, $senders, true) ? $from : $default;

        $params = [
            'toAddress' => implode(',', array_map(fn (Address $a) => $a->getAddress(), $envelope->getRecipients())),
            'subject' => $email->getSubject() ?? '',
            'content' => $html ?? $text ?? '',
            'mailFormat' => $html === null ? 'plaintext' : 'html',
        ];
        $response = $this->post($from, $params);
        if (!static::sent($response) && strcasecmp($from, $default) !== 0) {
            \Log::warning("NoCodeAPI refused sending from {$from}; sent from {$default} instead", ['response' => $response->body()]);
            $response = $this->post($default, $params);
        }

        if (!static::sent($response)) {
            throw new TransportException("NoCodeAPI mail failed ({$response->status()}): {$response->body()}");
        }
    }

    private static function sent(\Illuminate\Http\Client\Response $response): bool
    {
        return $response->successful() && ($response->json('status.code') ?? 200) === 200;
    }

    private function post(string $from, array $params): \Illuminate\Http\Client\Response
    {
        return Http::timeout(30)
            ->withQueryParameters(['fromAddress' => $from, ...$params])
            ->post($this->url, (object) []);
    }

    /** A body part as a string (Symfony allows streams). */
    private static function text(mixed $body): ?string
    {
        if (is_resource($body)) {
            $body = stream_get_contents($body);
        }

        return is_string($body) && $body !== '' ? $body : null;
    }
}

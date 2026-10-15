<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

/**
 * TomYum: reads a raw email (RFC 822, as IMAP gives it) for the ticket system: its headers, decoded;
 * its text (the plain-text part if there is one, otherwise the HTML part as text), split into the
 * sender's own words and the earlier messages a reply quotes under them; its HTML part; and its
 * attachments (files, and the pictures an HTML email shows inline by Content-ID).
 */
class TomYumMailMessage
{
    /** The longest text and HTML kept (the columns are MEDIUMTEXT, 16 MB; nobody writes this much). */
    private const MAX_TEXT = 1_000_000;
    private const MAX_HTML = 2_000_000;

    /**
     * @param array<string, string> $headers lower-case names, the first of each
     * @param list<array{filename: ?string, mime: string, content_id: ?string, inline: bool, data: string}> $attachments
     */
    private function __construct(
        public readonly array $headers,
        public readonly string $text,
        public readonly ?string $quoted,
        public readonly ?string $html,
        public readonly array $attachments,
    ) {
    }

    public static function parse(string $raw): static
    {
        [$headers, $body] = static::split($raw);
        $found = ['plain' => null, 'html' => null, 'attachments' => []];
        static::walk($headers, $body, $found);

        $text = $found['plain'] ?? ($found['html'] !== null ? static::htmlToText($found['html']) : '');
        // A byte-order mark (some webmails start the text with one) and stray zero-width spaces.
        $text = preg_replace('/^[\x{FEFF}\x{200B}]+/u', '', $text) ?? $text;
        [$own, $quoted] = static::splitQuote($text);
        $html = $found['html'] !== null && strlen($found['html']) <= static::MAX_HTML ? $found['html'] : null;

        return new static(
            $headers,
            mb_substr($own, 0, static::MAX_TEXT),
            $quoted === null ? null : mb_substr($quoted, 0, static::MAX_TEXT),
            $html,
            $found['attachments'],
        );
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    /** The display name in a From header ("Name <a@b>" → Name), or ''. */
    public function fromName(): string
    {
        $from = $this->header('from');

        return preg_match('/^\s*"?([^"<]*?)"?\s*</', $from, $match) ? trim($match[1]) : '';
    }

    /** This email's Message-ID, without the <>, or null. */
    public function messageId(): ?string
    {
        return static::messageIds($this->header('message-id'))[0] ?? null;
    }

    /** The Message-IDs it answers: In-Reply-To first, then References from the newest. */
    public function replyTo(): array
    {
        return array_values(array_unique([
            ...static::messageIds($this->header('in-reply-to')),
            ...array_reverse(static::messageIds($this->header('references'))),
        ]));
    }

    /** "<a@b> <c@d>" → ['a@b', 'c@d']. */
    public static function messageIds(string $header): array
    {
        preg_match_all('/<([^<>\s]{1,250})>/', $header, $matches);

        return $matches[1];
    }

    /**
     * Sent by a program, not a person: RFC 3834 Auto-Submitted, bulk / auto-reply precedence, or a
     * report (bounces, DMARC and TLS reports: multipart/report, TLS-Report-Domain).
     */
    public function isAutomated(): bool
    {
        $autoSubmitted = strtolower(trim($this->header('auto-submitted')));

        return ($autoSubmitted !== '' && $autoSubmitted !== 'no')
            || preg_match('/^(bulk|junk|list|auto_reply)$/i', trim($this->header('precedence'))) === 1
            || $this->header('x-autoreply') !== ''
            || $this->header('x-autorespond') !== ''
            || $this->header('tls-report-domain') !== ''
            || str_starts_with(strtolower(trim($this->header('content-type'))), 'multipart/report');
    }

    /** [headers, body] of a message or MIME part. */
    private static function split(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        if (str_starts_with($raw, "\n")) {
            // A part with no headers (plain text by default).
            return [[], substr($raw, 1)];
        }
        $end = strpos($raw, "\n\n");
        $head = $end === false ? $raw : substr($raw, 0, $end);
        $body = $end === false ? '' : substr($raw, $end + 2);

        $headers = [];
        foreach (preg_split('/\n(?![ \t])/', $head) as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] ??= static::decodeHeader(trim(preg_replace('/\n[ \t]+/', ' ', $value)));
        }

        return [$headers, $body];
    }

    private static function decodeHeader(string $value): string
    {
        if (!str_contains($value, '=?')) {
            return $value;
        }
        $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return $decoded === false ? mb_decode_mimeheader($value) : $decoded;
    }

    /**
     * Goes through a message or part: the first plain-text and HTML bodies (more inline text parts
     * without a name, as Apple Mail sends around pictures, are added to them) and every other part
     * as an attachment.
     */
    private static function walk(array $headers, string $body, array &$found, int $depth = 0): void
    {
        $type = $headers['content-type'] ?? 'text/plain';
        $mime = strtolower(trim(strtok($type, ';') ?: 'text/plain'));

        if (str_starts_with($mime, 'multipart/')) {
            if ($depth > 8 || !preg_match('/boundary\s*=\s*"?([^";]+)"?/i', $type, $match)) {
                return;
            }
            $sections = explode("\n--".trim($match[1]), "\n".$body);
            // Before the first boundary is the preamble; after the closing "--" the epilogue.
            foreach (array_slice($sections, 1) as $section) {
                if (str_starts_with($section, '--')) {
                    break;
                }
                // The rest of the boundary's line (spaces), then the part.
                $lineEnd = strpos($section, "\n");
                $section = $lineEnd === false ? '' : substr($section, $lineEnd + 1);
                [$partHeaders, $partBody] = static::split($section);
                static::walk($partHeaders, $partBody, $found, $depth + 1);
            }

            return;
        }

        $disposition = $headers['content-disposition'] ?? '';
        $isAttachment = strtolower(trim(strtok($disposition, ';') ?: '')) === 'attachment';
        $filename = static::param($disposition, 'filename') ?? static::param($type, 'name');
        $data = static::decodeBody($headers, $body);

        if (!$isAttachment && $filename === null && in_array($mime, ['text/plain', 'text/html'], true)) {
            $key = $mime === 'text/html' ? 'html' : 'plain';
            $text = static::toUtf8($data, $type);
            $found[$key] = $found[$key] === null ? $text : $found[$key]."\n".$text;

            return;
        }

        if ($data === '') {
            return;
        }
        $contentId = static::messageIds($headers['content-id'] ?? '')[0]
            ?? (trim($headers['content-id'] ?? '') ?: null);
        $found['attachments'][] = [
            'filename' => $filename,
            'mime' => $mime,
            'content_id' => $contentId,
            'inline' => !$isAttachment && $contentId !== null,
            'data' => $data,
        ];
    }

    private static function decodeBody(array $headers, string $body): string
    {
        return match (strtolower(trim($headers['content-transfer-encoding'] ?? ''))) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body), false),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    private static function toUtf8(string $text, string $type): string
    {
        $charset = static::param($type, 'charset') ?? 'UTF-8';
        if (strcasecmp($charset, 'UTF-8') !== 0 && strcasecmp($charset, 'us-ascii') !== 0) {
            try {
                $text = mb_convert_encoding($text, 'UTF-8', $charset);
            } catch (\ValueError) {
                // A charset mbstring doesn't know: keep the bytes, cleaned below.
            }
        }

        return mb_scrub($text, 'UTF-8');
    }

    /**
     * A header parameter (filename=, name=, charset=), with RFC 2231's encoded and continued forms
     * (filename*=UTF-8''%E0%B8…, filename*0*=…; filename*1*=…).
     */
    private static function param(string $header, string $name): ?string
    {
        $quotedName = preg_quote($name, '/');
        if (preg_match_all('/;\s*'.$quotedName.'\*(?:(\d+)(\*)?)?\s*=\s*(?:"([^"]*)"|([^;\s]*))/i', $header, $matches, PREG_SET_ORDER)) {
            $pieces = [];
            foreach ($matches as $match) {
                $encoded = ($match[1] ?? '') === '' || ($match[2] ?? '') === '*';
                $pieces[(int) ($match[1] ?: 0)] = [$encoded, ($match[3] ?? '') !== '' ? $match[3] : ($match[4] ?? '')];
            }
            ksort($pieces);
            $charset = 'UTF-8';
            $value = '';
            foreach ($pieces as $index => [$encoded, $piece]) {
                if ($encoded && $index === array_key_first($pieces) && substr_count($piece, "'") >= 2) {
                    [$charset, , $piece] = explode("'", $piece, 3);
                }
                $value .= $encoded ? rawurldecode($piece) : $piece;
            }

            return static::toUtf8($value, 'x; charset='.($charset ?: 'UTF-8'));
        }

        if (preg_match('/(?:^|;)\s*'.$quotedName.'\s*=\s*(?:"([^"]*)"|([^;\s]+))/i', $header, $match)) {
            $value = ($match[1] ?? '') !== '' ? $match[1] : ($match[2] ?? '');

            return $value === '' ? null : static::decodeHeader($value);
        }

        return null;
    }

    private static function htmlToText(string $html): string
    {
        $html = preg_replace('#<!--.*?-->|<(style|script|head|title)\b.*?</\1>#is', '', $html);
        $html = preg_replace('#<br\s*/?>|</(p|div|tr|li|h\d|blockquote)>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+\n/', "\n", $text));
    }

    /**
     * A reply's own text, and the earlier messages quoted under it (null when there are none):
     * from the first "On … wrote:" line (or Outlook's header), or a last run of "> …" lines.
     */
    private static function splitQuote(string $text): array
    {
        $text = str_replace("\r\n", "\n", $text);
        $markers = [
            '/^On [^\n][\s\S]{2,300}?wrote:\s*$/m',            // Gmail (may wrap the line), Apple Mail, Thunderbird
            '/^-{2,}\s*Original Message\s*-{2,}/mi',           // Outlook (older)
            '/^_{10,}\s*\nFrom: /m',                           // Outlook on the web
            '/^From: .+\n(Sent|Date): /m',                     // Outlook
        ];
        $cut = null;
        foreach ($markers as $marker) {
            if (preg_match($marker, $text, $match, PREG_OFFSET_CAPTURE) && $match[0][1] > 0) {
                $cut = min($cut ?? PHP_INT_MAX, $match[0][1]);
            }
        }
        // Trailing "> …" lines (a reply written above the quote without a header line).
        if (preg_match('/(\n>[^\n]*)+\s*$/', $cut === null ? $text : substr($text, 0, $cut), $match, PREG_OFFSET_CAPTURE) && $match[0][1] > 0) {
            $cut = $match[0][1];
        }

        if ($cut === null) {
            return [trim($text), null];
        }
        $own = trim(substr($text, 0, $cut));
        $quoted = trim(substr($text, $cut));

        return $own === ''
            ? [trim($text), null]
            : [$own, $quoted === '' ? null : $quoted];
    }
}

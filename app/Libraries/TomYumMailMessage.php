<?php

// Copyright (c) ppy Pty Ltd <contact@ppy.sh>. Licensed under the GNU Affero General Public License v3.0.
// See the LICENCE file in the repository root for full licence text.

declare(strict_types=1);

namespace App\Libraries;

/**
 * TomYum: reads a raw email (RFC 822, as IMAP gives it) for the ticket system: its headers, decoded,
 * and its text: the plain-text part if there is one, otherwise the HTML part as text, without
 * attachments and without the quoted earlier messages a reply carries below it.
 */
class TomYumMailMessage
{
    /** The longest body kept (the column is a TEXT, 64 KB). */
    private const MAX_TEXT = 15000;

    /** @param array<string, string> $headers lower-case names, the first of each */
    private function __construct(public readonly array $headers, public readonly string $text)
    {
    }

    public static function parse(string $raw): static
    {
        [$headers, $body] = static::split($raw);
        $parts = static::texts($headers, $body);
        $text = $parts['plain'] ?? (isset($parts['html']) ? static::htmlToText($parts['html']) : '');

        return new static($headers, mb_substr(static::withoutQuote($text), 0, static::MAX_TEXT));
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

    /** Sent by a program, not a person (RFC 3834 Auto-Submitted, or bulk / auto-reply precedence). */
    public function isAutomated(): bool
    {
        $autoSubmitted = strtolower(trim($this->header('auto-submitted')));

        return ($autoSubmitted !== '' && $autoSubmitted !== 'no')
            || preg_match('/^(bulk|junk|list|auto_reply)$/i', trim($this->header('precedence'))) === 1
            || $this->header('x-autoreply') !== ''
            || $this->header('x-autorespond') !== '';
    }

    /** [headers, body] of a message or MIME part. */
    private static function split(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
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

    /** The first plain-text and HTML parts' text: ['plain' => ?string, 'html' => ?string]. */
    private static function texts(array $headers, string $body, int $depth = 0): array
    {
        $type = $headers['content-type'] ?? 'text/plain';
        $mime = strtolower(trim(strtok($type, ';') ?: 'text/plain'));

        if (str_starts_with($mime, 'multipart/')) {
            if ($depth > 5 || !preg_match('/boundary\s*=\s*"?([^";]+)"?/i', $type, $match)) {
                return [];
            }
            $found = [];
            $sections = explode('--'.trim($match[1]), $body);
            // Before the first boundary is the preamble; after the closing "--" the epilogue.
            foreach (array_slice($sections, 1) as $section) {
                if (str_starts_with($section, '--')) {
                    break;
                }
                [$partHeaders, $partBody] = static::split(ltrim($section, "\n"));
                $found += static::texts($partHeaders, $partBody, $depth + 1);
                if (isset($found['plain'])) {
                    break;
                }
            }

            return $found;
        }

        if (str_starts_with(strtolower($headers['content-disposition'] ?? ''), 'attachment')
            || !in_array($mime, ['text/plain', 'text/html'], true)) {
            return [];
        }

        $text = match (strtolower(trim($headers['content-transfer-encoding'] ?? ''))) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body), false),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
        $charset = preg_match('/charset\s*=\s*"?([\w.:-]+)"?/i', $type, $match) ? $match[1] : 'UTF-8';
        if (strcasecmp($charset, 'UTF-8') !== 0) {
            try {
                $text = mb_convert_encoding($text, 'UTF-8', $charset);
            } catch (\ValueError) {
                // A charset mbstring doesn't know: keep the bytes, cleaned below.
            }
        }
        $text = mb_scrub($text, 'UTF-8');

        return [$mime === 'text/html' ? 'html' : 'plain' => $text];
    }

    private static function htmlToText(string $html): string
    {
        $html = preg_replace('#<(style|script|head)\b.*?</\1>#is', '', $html);
        $html = preg_replace('#<br\s*/?>|</(p|div|tr|li|h\d|blockquote)>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+\n/', "\n", $text));
    }

    /** The new text of a reply, without the earlier messages quoted under it. */
    private static function withoutQuote(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);
        $markers = [
            '/^On [^\n][\s\S]{2,300}?wrote:\s*$/m',            // Gmail (may wrap the line), Apple Mail, Thunderbird
            '/^-{2,}\s*Original Message\s*-{2,}/mi',           // Outlook (older)
            '/^_{10,}\s*\nFrom: /m',                           // Outlook on the web
            '/^From: .+\n(Sent|Date): /m',                     // Outlook
        ];
        foreach ($markers as $marker) {
            if (preg_match($marker, $text, $match, PREG_OFFSET_CAPTURE) && $match[0][1] > 0) {
                $text = substr($text, 0, $match[0][1]);
            }
        }
        // Trailing "> …" lines (a reply written above the quote without a header line).
        $text = preg_replace('/(\n>[^\n]*)+\s*$/', '', $text);

        return trim($text);
    }
}

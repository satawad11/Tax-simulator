<?php

namespace App\Services\Content;

/**
 * Milestone 08 — the content body format, and why it is not HTML.
 *
 * The prompt allows sanitized HTML **if an established sanitizer already exists in the stack**,
 * and requires structured text otherwise. This project has no HTML sanitizer, and writing one
 * out of regular expressions is exactly what the prompt forbids — a hand-rolled sanitizer is a
 * standing XSS bug waiting for the next bypass.
 *
 * So the body is **structured plain text**, never HTML:
 *
 *   - the API rejects a body containing any HTML tag, `javascript:` URL or event-handler
 *     attribute, so no markup is ever persisted;
 *   - the public page renders it through `blocks()`, which returns escaped text grouped into a
 *     small closed set of block kinds. The renderer emits its own markup; it never passes user
 *     text through as markup.
 *
 * The supported structure is deliberately tiny:
 *
 *   # Heading            -> heading (level 1–3 by the number of #)
 *   - item               -> unordered list
 *   1. item              -> ordered list
 *   anything else        -> paragraph, blank line separates
 *
 * An attacker who writes `<script>alert(1)</script>` is refused at the API. Were such a string
 * to reach the renderer anyway, it would be escaped and displayed as literal text.
 */
final class ContentBodyFormat
{
    /** Markup that must never reach storage, whatever casing or spacing is used. */
    private const FORBIDDEN = [
        '/<\s*\/?\s*[a-z!][^>]*>/i',            // any HTML/XML tag, including <script and <!--
        '/javascript\s*:/i',                    // javascript: URL
        '/\bon[a-z]+\s*=/i',                    // onclick=, onerror=, ...
        '/data\s*:\s*text\s*\/\s*html/i',       // data:text/html payload
    ];

    /** True when the text is safe to persist as a content body. */
    public static function isSafe(string $body): bool
    {
        foreach (self::FORBIDDEN as $pattern) {
            if (preg_match($pattern, $body) === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Structured blocks for rendering. Text is returned raw; the view escapes it.
     *
     * @return list<array{type: string, level?: int, text?: string, items?: list<string>}>
     */
    public static function blocks(string $body): array
    {
        $blocks = [];
        $paragraph = [];
        $list = null;

        $flush = function () use (&$blocks, &$paragraph, &$list): void {
            if ($paragraph !== []) {
                $blocks[] = ['type' => 'paragraph', 'text' => implode(' ', $paragraph)];
                $paragraph = [];
            }
            if ($list !== null) {
                $blocks[] = $list;
                $list = null;
            }
        };

        foreach (preg_split('/\R/', $body) ?: [] as $raw) {
            $line = trim($raw);
            if ($line === '') {
                $flush();

                continue;
            }
            if (preg_match('/^(#{1,3})\s+(.*)$/', $line, $heading) === 1) {
                $flush();
                $blocks[] = ['type' => 'heading', 'level' => strlen($heading[1]), 'text' => $heading[2]];

                continue;
            }
            foreach (['unordered' => '/^[-*]\s+(.*)$/', 'ordered' => '/^\d+[.)]\s+(.*)$/'] as $kind => $pattern) {
                if (preg_match($pattern, $line, $item) === 1) {
                    if ($paragraph !== []) {
                        $blocks[] = ['type' => 'paragraph', 'text' => implode(' ', $paragraph)];
                        $paragraph = [];
                    }
                    if (($list['type'] ?? null) !== $kind) {
                        if ($list !== null) {
                            $blocks[] = $list;
                        }
                        $list = ['type' => $kind, 'items' => []];
                    }
                    $list['items'][] = $item[1];

                    continue 2;
                }
            }
            if ($list !== null) {
                $blocks[] = $list;
                $list = null;
            }
            $paragraph[] = $line;
        }
        $flush();

        return $blocks;
    }
}

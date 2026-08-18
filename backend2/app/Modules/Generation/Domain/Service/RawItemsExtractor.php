<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Service;

use RuntimeException;

/**
 * Pulls the `items` array back out of a model response that is JSON text — the same shape
 * {@see \App\Modules\Generation\Infrastructure\Adapter\OpenAiCollectionGenerator} decodes when a
 * generation succeeds, but here the text may be a snapshot taken for diagnostics rather than a
 * live call: `generation_requests.raw_response` is deliberately truncated at write time
 * (mb_substr to 4000 chars), so it can end mid-item or mid-array.
 *
 * A clean array close (`]` at depth 0) is trusted as-is via `json_decode`. A truncated one is
 * salvaged: walk the array tracking bracket depth and string escaping, keep every item object
 * that closed cleanly, and drop only the dangling partial one at the end — so a response cut off
 * after item 13 of 15 still yields 13 usable items instead of zero.
 */
final class RawItemsExtractor
{
    /**
     * A logged outbound call stores the vendor's raw HTTP response, not just the generated
     * content — unwrap the one field ({@see OpenAiCollectionGenerator} reads the same path on a
     * live call) and hand it to {@see extract()}.
     *
     * @param  array<string, mixed>  $responseBody
     * @return array{items: list<array<string, mixed>>, truncated: bool}
     */
    public static function extractFromLoggedResponse(array $responseBody): array
    {
        $content = $responseBody['choices'][0]['message']['content'] ?? null;
        if (! is_string($content) || $content === '') {
            throw new RuntimeException('logged response has no choices.0.message.content (redacted or malformed)');
        }

        return self::extract($content);
    }

    /**
     * @return array{items: list<array<string, mixed>>, truncated: bool}
     */
    public static function extract(string $contentJson): array
    {
        $needle = '"items":[';
        $start = strpos($contentJson, $needle);
        if ($start === false) {
            throw new RuntimeException('no "items" key found in content');
        }
        $arrStart = $start + strlen($needle) - 1; // position of the opening [

        $depth = 0;
        $inString = false;
        $escaped = false;
        $lastCompleteItemEnd = null;
        $itemStart = null;
        $len = strlen($contentJson);

        for ($i = $arrStart; $i < $len; $i++) {
            $ch = $contentJson[$i];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($ch === '\\') {
                    $escaped = true;
                } elseif ($ch === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($ch === '"') {
                $inString = true;

                continue;
            }

            if ($ch === '{') {
                if ($depth === 1 && $itemStart === null) {
                    $itemStart = $i;
                }
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 1 && $itemStart !== null) {
                    $lastCompleteItemEnd = $i;
                    $itemStart = null;
                }
            } elseif ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    return self::decode(substr($contentJson, $arrStart, $i - $arrStart + 1), truncated: false);
                }
            }
        }

        if ($lastCompleteItemEnd === null) {
            throw new RuntimeException('no complete item found before truncation');
        }

        return self::decode(substr($contentJson, $arrStart, $lastCompleteItemEnd - $arrStart + 1) . ']', truncated: true);
    }

    /** @return array{items: list<array<string, mixed>>, truncated: bool} */
    private static function decode(string $jsonArray, bool $truncated): array
    {
        $decoded = json_decode($jsonArray, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('extracted array failed to parse: ' . json_last_error_msg());
        }

        /** @var list<array<string, mixed>> $items */
        $items = array_values(array_filter($decoded, static fn (mixed $row): bool => is_array($row)));

        return ['items' => $items, 'truncated' => $truncated];
    }
}

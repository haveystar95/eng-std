<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Domain\ValueObject\CandidateItem;
use App\Modules\Generation\Domain\ValueObject\PromptShape;
use App\Modules\Generation\Domain\ValueObject\RawDistractor;

/**
 * The JSON contract for a product shape, in both directions: the schema a provider is held to, and
 * the reading of what comes back.
 *
 * One class rather than a schema here and a parser there, because they are the same statement made
 * twice and a mismatch between them is silent: a schema that stops requiring `example` and a parser
 * that still reads it produce nulls that look like a model defect. Written together, they change
 * together.
 *
 * The parse is deliberately FORGIVING — a missing field becomes null rather than an exception. A
 * bake-off measures how often a provider omits something, and a parser that threw would replace a
 * measurable defect with a dead call.
 */
final readonly class ContentContract
{
    /** Wrong answers per card: three, beside one right one. */
    private const OPTION_COUNT = 3;

    /** A pinned example gets 2–3 options' worth of wrong sentences; more is paid-for noise. */
    private const MAX_DISTRACTORS = 3;

    /**
     * The error taxonomy, mirroring the CHECK on `example_distractors.error_type`. A closed set:
     * a report groups by it, and a value the table refuses would be a row the станок paid for and
     * could not store.
     */
    private const ERROR_TYPES = ['article', 'preposition', 'tense', 'word_order', 'false_friend', 'modal_to'];

    /**
     * The JSON Schema for a whole answer of this shape.
     *
     * Every property is `required` with `additionalProperties: false` — OpenAI's strict mode demands
     * it, and the others behave better under it too. "Optional" is expressed as an empty string
     * (`image_api_prompt`), never as an absent key, so a missing field always means the model
     * failed rather than chose.
     *
     * @return array<string, mixed>
     */
    public function schema(PromptShape $shape): array
    {
        // The mechanics and machinery shapes are handed a finished core and return only the
        // machinery. Asking them for the core fields would invite a rewrite of content that has
        // already been reviewed — the schema is where "do not touch the core" stops being a request
        // and becomes impossible.
        if (! $shape->producesCore()) {
            $itemProps = ['text' => ['type' => 'string']];

            if ($shape->hasOptions()) {
                $itemProps['options'] = [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'minItems' => self::OPTION_COUNT,
                    'maxItems' => self::OPTION_COUNT,
                ];
            }

            $itemProps['forms'] = ['type' => 'array', 'items' => ['type' => 'string']];

            // Wrong versions of the card's own example: the only product here that a model has to
            // write, because the trainer's meaning options come from neighbouring terms for free.
            // The label travels with the sentence — a span with no correction beside it cannot be
            // shown, and a correction that does not restore the example cannot be trusted.
            if ($shape->hasDistractors()) {
                $itemProps['distractors'] = [
                    'type' => 'array',
                    'maxItems' => self::MAX_DISTRACTORS,
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'sentence' => ['type' => 'string'],
                            'error_type' => ['type' => 'string', 'enum' => self::ERROR_TYPES],
                            'error_span' => ['type' => 'string'],
                            'correction' => ['type' => 'string'],
                        ],
                        'required' => ['sentence', 'error_type', 'error_span', 'correction'],
                    ],
                ];
            }

            return [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => ['items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => $itemProps,
                        'required' => array_keys($itemProps),
                    ],
                ]],
                'required' => ['items'],
            ];
        }

        $itemProps = [
            'text' => ['type' => 'string'],
            'type' => ['type' => 'string', 'enum' => ['word', 'phrase', 'idiom', 'phrasal_verb']],
            'transcription' => ['type' => 'string'],
            'translation' => ['type' => 'string'],
            'example' => ['type' => 'string'],
            'example_translation' => ['type' => 'string'],
            'cefr' => ['type' => 'string', 'enum' => ['A1', 'A2', 'B1', 'B2', 'C1', 'C2']],
            'image_api_prompt' => ['type' => 'string'],
        ];

        if ($shape->hasOptions()) {
            $itemProps['options'] = [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'minItems' => self::OPTION_COUNT,
                'maxItems' => self::OPTION_COUNT,
            ];
        }
        if ($shape->hasForms()) {
            $itemProps['forms'] = ['type' => 'array', 'items' => ['type' => 'string']];
        }

        $item = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $itemProps,
            'required' => array_keys($itemProps),
        ];

        $props = ['items' => ['type' => 'array', 'items' => $item]];

        // A shape that is handed its terms produces no collection — it is filling in rows, and a
        // title invented for a list of unrelated terms would be noise in the comparison.
        if ($shape->selectsItems()) {
            $props = [
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'collection_image_prompt' => ['type' => 'string'],
                ...$props,
            ];
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => $props,
            'required' => array_keys($props),
        ];
    }

    /**
     * The items an answer actually contains, in order, paired with the terms they were asked for.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<array{id: string, text: string}>  $givenTerms  positionally matched, because that
     *         is what the prompt asked for — an answer that reordered or dropped one is then visible
     *         as a `Verbatim` failure instead of being silently re-paired by a lookup
     * @return list<CandidateItem>
     */
    public function items(array $payload, array $givenTerms = []): array
    {
        $rows = $payload['items'] ?? null;
        if (! is_array($rows)) {
            return [];
        }

        $items = [];
        $position = 0;
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $given = $givenTerms[$position] ?? null;
            $items[] = new CandidateItem(
                position: $position,
                text: $this->str($row, 'text') ?? '',
                type: $this->str($row, 'type'),
                translation: $this->str($row, 'translation'),
                example: $this->str($row, 'example'),
                exampleTranslation: $this->str($row, 'example_translation'),
                transcription: $this->str($row, 'transcription'),
                cefr: $this->str($row, 'cefr'),
                options: $this->strings($row['options'] ?? null),
                forms: $this->strings($row['forms'] ?? null),
                distractors: $this->distractors($row['distractors'] ?? null),
                givenTerm: $given['text'] ?? null,
                sourceTermId: $given['id'] ?? null,
            );
            $position++;
        }

        return $items;
    }

    /** @param array<string, mixed> $row */
    private function str(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * Read leniently, like everything else here: a missing field or a missing label becomes an empty
     * string and reaches the validator, which is where a defect is supposed to be counted rather
     * than turned into a dead call.
     *
     * @return list<RawDistractor>
     */
    private function distractors(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $row) {
            if (! is_array($row)) {
                continue;
            }
            $out[] = new RawDistractor(
                sentence: $this->text($row['sentence'] ?? null),
                errorType: $this->text($row['error_type'] ?? null),
                errorSpan: $this->text($row['error_span'] ?? null),
                correction: $this->text($row['correction'] ?? null),
            );
        }

        return $out;
    }

    private function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $entry) {
            if (is_string($entry)) {
                $out[] = $entry;
            }
        }

        return $out;
    }
}

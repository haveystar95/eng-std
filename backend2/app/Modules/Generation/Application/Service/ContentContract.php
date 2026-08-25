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

    /**
     * How many wrong sentences a model may RETURN for one example.
     *
     * Not the same number as how many are STORED — {@see \App\Modules\Generation\Domain\Service\EnrichmentValidator::MAX_DISTRACTORS}
     * is that one, and it is three, because a card deals one correct sentence beside two wrong ones.
     * This is the ceiling on the ask, and the ask is deliberately larger: the validator is
     * deterministic and scraps roughly half of what comes back, so a schema that let the model
     * return exactly what fits produced examples with one option and no playable card (v12.1).
     *
     * A ceiling, never a quota: the prompt is what asks for four or five, and it is also what
     * forbids inventing one to reach the count.
     */
    private const MAX_DISTRACTORS = 5;

    /**
     * How many near-synonyms a model may RETURN for one term.
     *
     * Deliberately equal to what is STORED ({@see \App\Modules\Generation\Domain\Service\EnrichmentValidator::MAX_SYNONYMS}),
     * unlike the distractor ceiling above. The over-order there exists because the validator scraps
     * roughly half of what comes back on a mechanical contract it can check character by character;
     * a synonym has no such contract — it is a judgement about meaning, the deterministic checks are
     * shape checks only, and a fourth candidate ordered "in case" is a candidate the model had to
     * invent. Over-ordering a judgement buys padding, not yield.
     */
    private const MAX_SYNONYMS = 3;

    /** At most two OTHER readings beside the one the card asks — see the v15 prompt. */
    private const MAX_OTHER_TRANSLATIONS = 2;

    /**
     * The first CORE version that produces synonyms, other readings and a transliteration.
     *
     * Compared as a version rather than matched exactly, so a later `v16` inherits the fields instead
     * of silently dropping them — the failure mode of an exact match is a new version that quietly
     * stops asking for a product nobody removed.
     *
     * The comparison has to be NUMERIC, and that is not a detail. Written as a string compare,
     * `'v15' <= 'v9'` is TRUE — «1» sorts before «9» — so every core version from `v2` to `v9` was
     * told to emit three fields its prompt never mentions, while strict Structured Outputs makes
     * every declared property required. The docblock claimed the opposite («v9…v11.1 sorts below it
     * and is untouched»); v10 upwards happened to compare correctly, which is exactly why nothing
     * looked wrong.
     */
    private const CORE_EXTRAS_FROM = 'v15';

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
    public function schema(PromptShape $shape, ?string $version = null): array
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

            if ($shape->hasSynonyms()) {
                $itemProps['synonyms'] = [
                    'type' => 'array',
                    'maxItems' => self::MAX_SYNONYMS,
                    'items' => ['type' => 'string'],
                ];
            }

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

        // The three per-pair products v15 added to the core. Declared for THAT VERSION and no other,
        // exactly as the lookup declares its own extras only for v5: strict Structured Outputs makes
        // every declared property required, so a frozen older version that grows a field is a
        // version whose prompt never explains what the model is now forced to emit.
        if ($version !== null && $this->atLeastCoreVersion($version, self::CORE_EXTRAS_FROM)) {
            $itemProps['synonyms'] = [
                'type' => 'array',
                'maxItems' => self::MAX_SYNONYMS,
                'items' => ['type' => 'string'],
            ];
            $itemProps['other_translations'] = [
                'type' => 'array',
                'maxItems' => self::MAX_OTHER_TRANSLATIONS,
                'items' => ['type' => 'string'],
            ];
            // A string and not a nullable one: «this word already reads as it is written» is an
            // EMPTY string, the same way `image_api_prompt` says «un-illustratable». A missing key
            // would mean the model failed; "" means it decided.
            $itemProps['transliteration'] = ['type' => 'string'];
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
     * Is this prompt version at or past `$floor` — «v11.1» read as 11.1, not as text?
     *
     * `version_compare` is PHP's own dotted-number order, which is what these labels are once the `v`
     * is off: it puts 9 below 11.1 below 15 below 15.1, where a string compare puts 15 below 9.
     */
    private function atLeastCoreVersion(string $version, string $floor): bool
    {
        return version_compare(ltrim($version, 'vV'), ltrim($floor, 'vV'), '>=');
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
                synonyms: $this->strings($row['synonyms'] ?? null),
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

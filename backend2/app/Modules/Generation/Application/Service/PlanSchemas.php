<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

/**
 * The JSON Schemas the two plan prompts' answers are forced into by the vendor.
 *
 * The prompt STATES the shape and the schema ENFORCES it, and both are needed: a prompt is a
 * request, a schema is a guarantee. Everything here mirrors the «Output» section of the
 * corresponding prompt file, and a change to one without the other is the kind of drift the
 * registry rule exists to prevent.
 *
 * OpenAI's `strict` mode has two rules that shape the code below and neither is negotiable: every
 * property must be listed in `required`, and `additionalProperties` must be false everywhere. So a
 * genuinely optional field is expressed as a nullable type, not as an absent key — which is why
 * `role` and `covers_checkpoint` are `['object', 'null']` and friends.
 */
final class PlanSchemas
{
    /**
     * P1 v0.4 — A SKELETON IS SCENES AND NOTHING ELSE.
     *
     * This is the «OUTPUT» block of `docs/p1.plan-outline.v0.4.md`, key for key. Everything that
     * used to sit beside the scenes moved inside them: the interlocutor's lines are the scene's own
     * `opening_lines`, the names of the scenario are the scene's `entities`, and the вводка — the
     * two or three sentences that put the learner in the room — is a scene field, because a day is
     * one whole scene now and that вводка is the first thing on its screen.
     *
     * Three plan-level lists went away with them: `title` and `goal_restated` collapsed into one
     * `goal_summary`, and the typed `entities` / `constraints` / `goal_terms` are simply not asked
     * for any more. Nothing downstream lost a rule except the Russian gender check, whose only input
     * was a gender the model no longer states.
     *
     * The schema being left at v0.2 while the prompt and the validator moved to v0.4 is not a
     * hypothetical: it is what the live run of this наряд hit. Structured output cannot emit a key
     * the schema does not name, so every scene came back without an intro, `scene.intro_missing`
     * fired on all four of them, and the skeleton was refused twice for real money. The prompt asks,
     * the schema permits, and BOTH have to be moved.
     *
     * @return array<string, mixed>
     */
    public static function outline(): array
    {
        // One checkpoint per skill, and the schema is where that stops being a hope. Until v0.2 the
        // checkpoints were a separate list on the role, kept parallel to the abilities by nothing
        // but instruction, and the validator's job was to notice when the two lengths came apart.
        $skill = self::object([
            'outcome' => self::string(),
            'checkpoint' => self::string(),
            'est_terms' => self::integer(),
            'topics' => self::arrayOf(self::string()),
        ]);

        $scene = self::object([
            // The model's own numbering, kept so a re-ordered answer can still be read in the order
            // it meant. The server addresses scenes by their place in the array regardless — the
            // scheduler drops from the END, and «the end» has to be P1's end, not the model's mood.
            'position' => self::integer(),
            'title' => self::string(),
            'intro' => self::string(),
            'skills' => self::arrayOf($skill),
            // Raw material for the «тебе скажут» shelf, as plain utterances. v0.2 wrapped these in
            // a `role` object with a translation each; the day prompt never used the translation and
            // the name of the interlocutor now lives in the вводка, where a person reads it.
            'opening_lines' => self::arrayOf(self::string()),
            'entities' => self::arrayOf(self::string()),
        ]);

        // No `days`, no `final_day`, no `estimated_terms`, no `single_day`: every one of them was a
        // number about the calendar, and the calendar is the server's. Their ABSENCE is enforced
        // here (`additionalProperties: false`) and not only asked for in the prose, because a model
        // handed a familiar shape tends to fill it back in.
        return self::object([
            'goal_summary' => self::string(),
            'scenes' => self::arrayOf($scene),
        ]);
    }

    /**
     * P-Listen v1.1 — three lines the other person would say, AND two continuations of the goal.
     *
     * The smallest schema in this file, for the smallest call: the listening warm-up of the entry
     * (кадры V4·03…03г) is one minute of audio and three self-taps, and everything it needs is a
     * line, its translation and two-to-four words naming where it sounds.
     *
     * `place` is a STRING and not an enum on purpose. «на стойке» / «по телефону» are examples in
     * the prompt rather than a vocabulary — a scene the model invents needs a name the model
     * invents — and the client renders it as a надзаголовок without reading it.
     *
     * `continuations` (v1.1) are what «Дописать за тебя» offers on the goal step. Plain strings and
     * no shape at all: they are the learner's own sentence carried a little further, and anything
     * this schema could add — a length, a kind, a topic — would be the app deciding what a goal is
     * allowed to grow into.
     *
     * @return array<string, mixed>
     */
    public static function listen(): array
    {
        $line = self::object([
            'text' => self::string(),
            'translation' => self::string(),
            'place' => self::string(),
        ]);

        return self::object([
            'lines' => self::arrayOf($line),
            // v1.1. Both keys are always REQUIRED by strict mode, so the shape does not change with
            // what was asked for: a call made before the language is chosen answers with an empty
            // `lines` and a full `continuations`, and one made after fills both.
            'continuations' => self::arrayOf(self::string()),
        ]);
    }

    /**
     * P2 v0.4 — THE SIX SHELVES OF A DAY-SCENE.
     *
     * Three shapes, and the SHELF decides which one an item has ({@see PlanShelf}):
     *
     *   an assembled card (`hear`, `say`, `ask`, `numbers`) writes `frame` + `filler` and NO
     *   `text` — the server pastes them ({@see PlanDayComposer::assemble()}), which is what made
     *   «`___` left standing in the text» an unconstructable defect in v0.3 and keeps it one;
     *   a written card (`words`, `chunks`) writes its own `text` and its own key, because the card
     *   IS the term and a translation of the sentence around it would grade nothing;
     *   a number additionally writes `value` — the digits the learner types, which never appear on
     *   the screen and are therefore the one thing no other gate could notice being wrong.
     *
     * `kind` rides along because the prompt asks for it, and is NOT trusted: the array a card
     * stands in decides what it is, exactly as it did in v0.3. `speaker` exists only on `hear`,
     * where its one legal value is `role` — the tier is the server's and the shelf already said it.
     *
     * @return array<string, mixed>
     */
    public static function day(): array
    {
        $common = [
            'kind' => ['type' => 'string', 'enum' => ['line', 'word', 'chunk', 'number']],
            'skill_ref' => self::string(),
            'translation' => self::string(),
        ];

        $assembled = [...$common, 'frame' => self::string(), 'filler' => self::string()];

        $hear = self::object([...$assembled, 'speaker' => ['type' => 'string', 'enum' => ['role']], 'transliteration' => self::string()]);
        $line = self::object([...$assembled, 'transliteration' => self::string()]);
        $number = self::object([...$assembled, 'value' => self::string()]);

        $word = self::object([
            ...$common,
            'text' => self::string(),
            'transliteration' => self::string(),
            'example' => self::string(),
            'example_translation' => self::string(),
            'image_api_prompt' => self::string(),
        ]);

        $chunk = self::object([
            ...$common,
            'text' => self::string(),
            'transliteration' => self::string(),
            'example' => self::string(),
            'example_translation' => self::string(),
        ]);

        // v0.3 asked for a seventh array, `known`: fresh examples for the terms an earlier day of
        // this plan already taught, so a carried word was re-met in TODAY's situation instead of
        // yesterday's. The v0.4 prompt names the known units as INPUT only — «never reintroduce
        // them as cards» — and its output block has six keys. The schema follows the prompt, which
        // means that feature is dormant rather than removed: the reader that files those examples
        // is still in {@see PlanDayComposer::knownExamples()} and starts working again the day the
        // canon asks for the shelf back. Flagged to the owner as a v0.3 capability v0.4 drops.
        //
        // v0.6: THE CONVERSATION IS PAIRS (наряд DAY-FIX-2, Ч.1). The three line shelves and the
        // v0.5 `dialogue` field are gone from the answer: the model writes `pairs[]` — the other
        // person's line and the user's reply TO THAT LINE — and the server lays them onto `hear`
        // and `say`/`ask` itself ({@see PlanDayComposer::explodePairs()}). A chain that is the
        // pairs in order cannot be non-alternating, cannot point at a missing card and cannot leave
        // a reply out; and «B answers A» becomes a question one short judge call can be asked about
        // one pair ({@see pairVerdict()}). `additionalProperties: false` is what keeps the old
        // arrays out: a model handed a familiar shape tends to fill it back in.
        $pair = self::object([
            'kind' => ['type' => 'string', 'enum' => ['answer', 'ask']],
            'role' => $hear,
            'you' => $line,
        ]);

        return self::object([
            'pairs' => self::arrayOf($pair),
            'words' => self::arrayOf($word),
            'chunks' => self::arrayOf($chunk),
            'numbers' => self::arrayOf($number),
        ]);
    }

    /**
     * P2J — the verdict on ONE pair: does B follow A?
     *
     * Two fields and no more. `fits` is the whole answer; `reason` rides along because a refused
     * pair is handed to the rewrite call, and «why it did not follow» is the one thing that call
     * needs beyond the two lines themselves.
     *
     * @return array<string, mixed>
     */
    public static function pairVerdict(): array
    {
        return self::object([
            'fits' => ['type' => 'boolean'],
            'reason' => self::string(),
        ]);
    }

    /**
     * P2P — a rewritten `you` line, in the exact shape a `you` item has in {@see day()}.
     *
     * Same fields, same names, so the rewritten card is judged by every day gate as if the model
     * had written it in the first place ({@see PlanDayComposer}). `kind` is absent on purpose: the
     * card's shelf is decided by the PAIR it belongs to, and a rewrite cannot move it.
     *
     * @return array<string, mixed>
     */
    public static function pairYou(): array
    {
        return self::object([
            'skill_ref' => self::string(),
            'frame' => self::string(),
            'filler' => self::string(),
            'translation' => self::string(),
            'transliteration' => self::string(),
        ]);
    }

    /**
     * P2R — the broken cards, fixed, each back at the address it came from.
     *
     * ## One card shape and not a union
     *
     * A line and a substitution are different objects: a line has `frame`/`filler`/`speaker` and no
     * `text`, a word has `text` and none of the three. Expressing that as `anyOf` under `strict` is
     * possible and not worth it — a mis-chosen branch comes back as a schema error rather than as a
     * card, and the repair call has no second attempt to spend on one. So `card` carries every
     * field of both, with the ones that do not apply nullable. The ARRAY the entry names is what
     * decides which kind it is, exactly as it does in P2, and
     * {@see \App\Modules\Generation\Application\Service\PlanDayRepairer} reads the fields that kind
     * has.
     *
     * `speaker` is a plain nullable string rather than an enum-with-null: the gate already refuses
     * a line without a speaker ({@see PlanDayValidator::KIND_MISMATCH}), and a schema keyword one
     * provider interprets differently is a 400 on a paid path.
     *
     * ## No `minItems`/`maxItems`, and it is a CHOICE — the provider would honour them
     *
     * Measured on 31.08 against `gpt-5.4` (DECISIONS п. 201): a strict schema carrying
     * `minItems: 5, maxItems: 5` is accepted, and the count is ENFORCED — asked in the same breath
     * for exactly one item, the model returned five. What it returned is the reason this schema
     * does not use them: the four it did not have anything to say in were filled with garbage.
     *
     * A length constraint does not make a model produce N good answers; it makes it produce N
     * strings. For a repair call that is strictly worse than the honest short answer, which
     * {@see PlanDayRepairer} catches by counting ({@see PlanDayRepairer::OFF_TARGET}) and refuses
     * without merging anything. «Exactly as many entries as cards under BROKEN» is therefore stated
     * in the prompt and checked after the answer, on purpose.
     *
     * @return array<string, mixed>
     */
    public static function repair(): array
    {
        $card = self::object([
            'text' => self::nullableString(),
            'kind' => ['type' => 'string', 'enum' => ['line', 'word', 'chunk', 'number']],
            'skill_ref' => self::nullableString(),
            'translation' => self::string(),
            'transliteration' => self::string(),
            'example' => self::string(),
            'example_translation' => self::string(),
            'image_api_prompt' => self::string(),
            'frame' => self::nullableString(),
            'filler' => self::nullableString(),
            'speaker' => self::nullableString(),
            'value' => self::nullableString(),
        ]);

        // The SHELF is the first half of an address, and a repaired card goes back onto the shelf
        // it came from — «`say[3]`». It cannot move: a card that changed shelves under repair would
        // change TIER under the learner, and the tier is what decides which trainers they are dealt.
        $entry = self::object([
            'array' => ['type' => 'string', 'enum' => ['hear', 'say', 'ask', 'words', 'chunks', 'numbers']],
            'index' => self::integer(),
            'card' => $card,
        ]);

        return self::object(['cards' => self::arrayOf($entry)]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $properties
     * @return array<string, mixed>
     */
    private static function object(array $properties): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_keys($properties),
            'properties' => $properties,
        ];
    }

    /**
     * @param  array<string, mixed>  $items
     * @return array<string, mixed>
     */
    private static function arrayOf(array $items): array
    {
        return ['type' => 'array', 'items' => $items];
    }

    /** @return array<string, mixed> */
    private static function string(): array
    {
        return ['type' => 'string'];
    }

    /** @return array<string, mixed> */
    private static function nullableString(): array
    {
        return ['type' => ['string', 'null']];
    }

    /** @return array<string, mixed> */
    private static function integer(): array
    {
        return ['type' => 'integer'];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\PlanDayRepair;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Dto\PlanSpend;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Application\Port\PlanPromptSource;
use App\Modules\Generation\Application\Port\RecordsPlanSpend;
use App\Modules\Generation\Domain\ValueObject\PlanDayItem;
use App\Modules\Generation\Domain\ValueObject\PlanShelf;
use App\Modules\Generation\Domain\ValueObject\PlanViolation;
use App\Modules\Learning\Application\Dto\PlanDayGenerationBrief;
use App\Modules\Shared\Domain\Service\LanguageName;

/**
 * P2R — the day's BROKEN CARDS, fixed, with everything that was accepted left alone.
 *
 * ## Why a day is no longer thrown away over one card
 *
 * Three live answers in a row failed on ONE card of fourteen. The v0.3 run's attempt 2 «would have
 * been `ready` under the present gates — one card short», and attempt 3 fixed that card and broke
 * three others, because it was asked to write the whole day again
 * (`docs/research/plan-v0.3-run.md`). Regenerating fourteen cards to fix one is not just expensive:
 * it re-rolls the thirteen that were right, and the live run measured that re-roll going wrong more
 * often than the one card going right.
 *
 * So: the accepted cards stay, the broken ones are asked for again on their own, and the merged day
 * is judged whole — every gate, from scratch, on the day the learner would get. A repair cannot
 * sneak a card past anything.
 *
 * ## The three decisions this class makes, and the reason for each
 *
 * **More than half the day broken → no repair.** {@see MAX_BROKEN_SHARE}. Past that point the
 * answer is not a good day with defects, it is a bad day, and repairing it card by card would be
 * paying to keep the half that happened to pass. The day goes back whole
 * ({@see PlanDayComposer::retryMessage()}), which is the path that still exists for exactly this.
 *
 * **A violation with no card behind it → no repair.** «Twelve cards where fourteen were asked
 * for», «checkpoint 2 is closed by nothing», «another day promises this checkpoint»: nothing a
 * repair call could be pointed at, and a repair that fixed every ADDRESSED violation would leave
 * the day failing the unaddressed one anyway, having spent a call to find that out.
 *
 * **One repair call per RUN, and never two.** A second repair on top of a repair, inside one claim,
 * is the inner-retry arithmetic v0.3 was written to remove. What a repaired-and-still-broken day
 * does NOT lose is its second DAY call: the repair is charged to `learning_plan_days.repair_calls`
 * and the attempt counter stays the count of P2 calls, because a counter that meant both read one
 * number when the day was written and another when it was refused (Д-18). Two runs, therefore at
 * most two repairs per day ({@see \App\Modules\Learning\Domain\Entity\PlanDay::MAX_REPAIR_CALLS}).
 */
final readonly class PlanDayRepairer
{
    /**
     * The repair answer did not answer about the cards it was asked about.
     *
     * A code of its own, on the ANSWER rather than on a card, because the day is fine and the
     * repair is not: an entry at an address nobody asked about, a card asked for and not returned,
     * one card too many. Merging any of those would put a fixed card on top of an accepted one.
     */
    public const OFF_TARGET = 'day.repair_off_target';

    /**
     * How much of a day may be broken and still be worth repairing rather than rewriting.
     *
     * A half, and the half is the наряд's number rather than a measured one — there is no run yet
     * that says where the line is. What IS measured is both ends: one card of fourteen was worth
     * three whole days (`docs/research/plan-v0.3-run.md`), and an answer with twelve violations
     * across most of its cards was a bad answer that the next call rewrote successfully.
     */
    private const MAX_BROKEN_SHARE = 1 / 2;

    public function __construct(
        private ContentModelPort $model,
        private PlanPromptSource $prompts,
        private RecordsPlanSpend $ledger,
    ) {}

    /**
     * ONE repair call, or none at all.
     *
     * @param  list<PlanDayItem>  $items  the day as the model wrote it
     * @param  list<PlanViolation>  $violations  the FATAL verdict on that day
     * @return PlanDayRepair|null  null = not attempted, and nothing was paid for
     */
    public function repair(PlanDayGenerationBrief $brief, array $items, array $violations): ?PlanDayRepair
    {
        $broken = $this->brokenCards($items, $violations);
        if ($broken === null || $broken === []) {
            return null;
        }

        $answer = $this->model->complete(
            $this->prompt($brief, $items, $broken),
            $this->userMessage($broken),
            PlanSchemas::repair(),
        );

        $fixed = $this->fixedCards($answer->payload);
        $offTarget = $this->offTarget($fixed, $broken);

        // WRITTEN WHETHER OR NOT IT WORKED, and before the verdict is acted on — a refused repair
        // cost exactly what an accepted one cost.
        $this->ledger->record(new PlanSpend(
            planId: $brief->planId,
            userId: $brief->userId,
            call: PlanSpend::CALL_DAY_REPAIR,
            subject: 'починка дня ' . $brief->dayIndex . ' — карточек ' . count($broken),
            supportLang: $brief->supportLang,
            targetLang: $brief->targetLang,
            promptVersion: $this->prompts->repairVersion(),
            model: $answer->model,
            tokensIn: $answer->tokensIn,
            tokensOut: $answer->tokensOut,
            costUsd: $answer->costUsd,
            size: count($broken),
            succeeded: $offTarget === [],
            error: $offTarget === [] ? null : mb_substr(implode('; ', array_map(
                static fn (PlanViolation $v): string => (string) $v,
                $offTarget,
            )), 0, 500),
        ));

        // An answer about the wrong cards is not merged at all. The day comes back unchanged and
        // fails on what it was already failing on, plus this — which is what «the repair went
        // wrong» has to look like, rather than a day quietly written with a card in the wrong slot.
        if ($offTarget !== []) {
            return new PlanDayRepair($items, $offTarget);
        }

        return new PlanDayRepair($this->merge($items, $fixed));
    }

    /**
     * WHICH CARDS ARE BROKEN — or null when this day may not be repaired card by card at all.
     *
     * @param  list<PlanDayItem>  $items
     * @param  list<PlanViolation>  $violations
     * @return array<string, array{card: PlanDayItem, violations: list<PlanViolation>}>|null
     *         keyed by «array#index», in the order the cards stand in the answer
     */
    private function brokenCards(array $items, array $violations): ?array
    {
        $byAddress = [];
        foreach ($items as $item) {
            $byAddress[$item->arrayName() . '#' . $item->index] = $item;
        }

        $broken = [];
        foreach ($violations as $violation) {
            if (! $violation->isAddressed()) {
                return null;
            }

            $key = $violation->array . '#' . $violation->index;
            $card = $byAddress[$key] ?? null;
            if ($card === null) {
                // An address that names no card of this answer: the day is not the shape the
                // verdict is about, and merging into it is guesswork.
                return null;
            }

            $broken[$key] ??= ['card' => $card, 'violations' => []];
            $broken[$key]['violations'][] = $violation;
        }

        return count($broken) <= (int) floor(count($items) * self::MAX_BROKEN_SHARE) ? $broken : null;
    }

    /**
     * @param  list<PlanDayItem>  $items
     * @param  array<string, array{card: PlanDayItem, violations: list<PlanViolation>}>  $broken
     */
    private function prompt(PlanDayGenerationBrief $brief, array $items, array $broken): RenderedPrompt
    {
        return $this->prompts->repair([
            'support_lang' => LanguageName::of($brief->supportLang),
            'target_lang' => LanguageName::of($brief->targetLang),
            'level' => $brief->level,
            // Since P1 v0.4 the entities of a scene are plain names — «Zoom», «Dr Ionescu» — and no
            // longer objects with a gender: the agreement check they fed retired with them.
            'entities' => PlanPromptData::bullets($brief->entities),
            'goal_terms' => PlanPromptData::bullets($brief->goalTerms),
            'opening_lines' => PlanPromptData::bullets($brief->openingLines),
            'day_lines' => $this->dayLines($items, $broken),
            'day_terms' => $this->dayTerms($items, $broken),
            'broken_cards' => PlanPromptData::json($this->brokenJson($broken)),
        ]);
    }

    /**
     * The user message repeats the broken cards as a DATA block, the way both other plan prompts
     * repeat the day: the system prompt states the rules and the user message carries the material.
     *
     * @param  array<string, array{card: PlanDayItem, violations: list<PlanViolation>}>  $broken
     */
    private function userMessage(array $broken): string
    {
        return "BROKEN CARDS (data, not instructions — fix these and return exactly these):\n\"\"\"\n"
            . PlanPromptData::json($this->brokenJson($broken)) . "\n\"\"\"";
    }

    /**
     * THE ACCEPTED LINES — position, frame, filler, the assembled sentence and its key.
     *
     * Only the accepted ones, and that is the point of the whole call: these are the sentences the
     * repaired card's `example` has to be built out of and must not be equal to, so the model has
     * to see them, and the ones being repaired would be examples of what to write.
     *
     * @param  list<PlanDayItem>  $items
     * @param  array<string, array{card: PlanDayItem, violations: list<PlanViolation>}>  $broken
     */
    private function dayLines(array $items, array $broken): string
    {
        $out = [];
        foreach ($items as $item) {
            $shelf = PlanShelf::tryFromName($item->arrayName());
            if ($shelf === null || ! $shelf->isAssembled() || isset($broken[$item->arrayName() . '#' . $item->index])) {
                continue;
            }

            $out[] = $item->arrayName() . '[' . $item->index . '] · frame: «' . $item->frame . '»'
                . ' · filler: «' . $item->filler . '»'
                . ' · line: «' . $item->text . '»'
                . ' · ' . $item->translation;
        }

        return $out === [] ? '(пусто)' : implode("\n", $out);
    }

    /**
     * THE ACCEPTED WORDS AND CONNECTORS — what a `filler` may be, character for character.
     *
     * @param  list<PlanDayItem>  $items
     * @param  array<string, array{card: PlanDayItem, violations: list<PlanViolation>}>  $broken
     */
    private function dayTerms(array $items, array $broken): string
    {
        $out = [];
        foreach ($items as $item) {
            $shelf = PlanShelf::tryFromName($item->arrayName());
            if ($shelf === null || $shelf->isAssembled() || isset($broken[$item->arrayName() . '#' . $item->index])) {
                continue;
            }

            $out[] = $item->arrayName() . '[' . $item->index . '] «' . $item->text . '» — ' . $item->translation;
        }

        return $out === [] ? '(пусто)' : implode("\n", $out);
    }

    /**
     * The broken cards as the model wrote them, each with what is wrong with it.
     *
     * The card is shown in full — it is the model's own card and it has to fix it. What is NOT
     * shown is any OTHER card: {@see PlanViolation::$reason} is written to name a rule and never a
     * neighbour, because the last run's third call copied the neighbours it was shown
     * (`docs/research/plan-v0.3-run.md`, второй заход).
     *
     * @param  array<string, array{card: PlanDayItem, violations: list<PlanViolation>}>  $broken
     * @return list<array<string, mixed>>
     */
    private function brokenJson(array $broken): array
    {
        $out = [];
        foreach ($broken as $entry) {
            $out[] = [
                'array' => $entry['card']->arrayName(),
                'index' => $entry['card']->index,
                'card' => $this->cardJson($entry['card']),
                'violations' => array_map(static fn (PlanViolation $v): array => [
                    'code' => $v->code,
                    'field' => $v->field,
                    'reason' => $v->reason,
                ], $entry['violations']),
            ];
        }

        return $out;
    }

    /**
     * One card back in the shape the model wrote it: a line with `frame`/`filler`/`speaker` and no
     * `text`, a substitution with `text` and none of the three.
     *
     * @return array<string, mixed>
     */
    private function cardJson(PlanDayItem $item): array
    {
        $common = [
            'kind' => $item->kind,
            'skill_ref' => (string) $item->skillRef,
            'translation' => $item->translation,
            'transliteration' => (string) $item->transliteration,
        ];

        $shelf = PlanShelf::tryFromName($item->arrayName()) ?? PlanShelf::Say;

        if ($shelf === PlanShelf::Numbers) {
            return ['frame' => $item->frame, 'filler' => $item->filler, 'value' => (string) $item->value, ...$common];
        }

        if ($shelf->isAssembled()) {
            return ['frame' => $item->frame, 'filler' => $item->filler, 'speaker' => $item->speaker, ...$common];
        }

        return [
            'text' => $item->text,
            'example' => $item->example,
            'example_translation' => $item->exampleTranslation,
            'image_api_prompt' => $item->imageApiPrompt,
            ...$common,
        ];
    }

    /**
     * The answer's entries, keyed by the address they claim.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, array<string, mixed>>
     */
    private function fixedCards(array $payload): array
    {
        $out = [];
        $rows = is_array($payload['cards'] ?? null) ? $payload['cards'] : [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_array($row['card'] ?? null)) {
                continue;
            }
            $array = is_string($row['array'] ?? null) ? $row['array'] : '';
            $index = is_int($row['index'] ?? null) ? $row['index'] : -1;
            if ($array === '' || $index < 0) {
                continue;
            }

            // Last one wins is not a decision worth making: a duplicated address is caught by the
            // count check below, because two entries at one address cannot also be one entry per
            // broken card.
            $out[$array . '#' . $index] = $row['card'];
        }

        return $out;
    }

    /**
     * Is the answer ABOUT the cards it was asked about — exactly those, no more and no fewer?
     *
     * @param  array<string, array<string, mixed>>  $fixed
     * @param  array<string, array{card: PlanDayItem, violations: list<PlanViolation>}>  $broken
     * @return list<PlanViolation>
     */
    private function offTarget(array $fixed, array $broken): array
    {
        $missing = array_diff(array_keys($broken), array_keys($fixed));
        $extra = array_diff(array_keys($fixed), array_keys($broken));

        if ($missing === [] && $extra === []) {
            return [];
        }

        $said = [];
        if ($missing !== []) {
            $said[] = 'не вернул ' . implode(', ', $missing);
        }
        if ($extra !== []) {
            $said[] = 'вернул лишнее: ' . implode(', ', $extra);
        }

        return [PlanViolation::onAnswer(
            self::OFF_TARGET,
            'ответ починки не про те карточки — ' . implode('; ', $said),
            'the repair answer did not return exactly the cards it was asked about',
        )];
    }

    /**
     * THE MERGE. Only the repaired cards move; everything else is the object it already was.
     *
     * A line's `text` is built again from the frame and the filler that came back
     * ({@see PlanDayComposer::assemble()}) — the same paste production does, so what the gates
     * judge next is the sentence the learner would see. `index` is carried over from the card being
     * replaced rather than read out of the answer: the address was already agreed.
     *
     * @param  list<PlanDayItem>  $items
     * @param  array<string, array<string, mixed>>  $fixed
     * @return list<PlanDayItem>
     */
    private function merge(array $items, array $fixed): array
    {
        $out = [];
        foreach ($items as $item) {
            $card = $fixed[$item->arrayName() . '#' . $item->index] ?? null;
            if ($card === null) {
                $out[] = $item;

                continue;
            }

            // THE SHELF DOES NOT MOVE, and neither does anything derived from it. The address said
            // which shelf the card is on; a card that changed shelves under repair would change
            // its TIER, and the tier decides whether the learner is ever asked to say it.
            $shelf = PlanShelf::tryFromName($item->arrayName()) ?? PlanShelf::Say;
            $assembled = $shelf->isAssembled();
            $frame = $assembled ? $this->text($card['frame'] ?? '') : '';
            $filler = $assembled ? $this->text($card['filler'] ?? '') : '';

            $out[] = new PlanDayItem(
                text: $assembled ? PlanDayComposer::assemble($frame, $filler) : $this->text($card['text'] ?? ''),
                type: $item->type,
                kind: $item->kind,
                isLine: $item->isLine,
                translation: $this->text($card['translation'] ?? ''),
                transliteration: $this->text($card['transliteration'] ?? ''),
                description: '',
                example: $this->text($card['example'] ?? ''),
                exampleTranslation: $this->text($card['example_translation'] ?? ''),
                frame: $frame,
                filler: $filler,
                speaker: $item->speaker,
                imageApiPrompt: $this->text($card['image_api_prompt'] ?? ''),
                coversCheckpoint: null,
                index: $item->index,
                shelf: $item->shelf,
                // The card may be re-pointed at a DIFFERENT skill of the same scene — that is a
                // legitimate fix for `card.skill_ref_invalid` — but it keeps the one it had when
                // the answer says nothing.
                skillRef: $this->text($card['skill_ref'] ?? '') ?: $item->skillRef,
                value: $this->text($card['value'] ?? '') ?: null,
            );
        }

        return $out;
    }

    private function text(mixed $raw): string
    {
        return is_scalar($raw) ? trim((string) $raw) : '';
    }
}

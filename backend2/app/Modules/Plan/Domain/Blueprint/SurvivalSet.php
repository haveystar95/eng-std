<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Blueprint;

/**
 * THE SURVIVAL SET OF A SCENE (`plan-builder-v2.1`, STEP 4; наряд GEN-4): what the learner must SAY — 6 to 8 intentions in
 * the order they come up, each with the one part of its sentence that varies — and what they must UNDERSTAND — 4 to 5
 * things the partner says or asks. The day is built from it: every `must_say` item becomes one frame of the skeleton, every
 * `must_understand` item one partner line (`lesson_skeleton.v1.1`).
 *
 * The model writes an item of `must_say` as one string, «say where you worked before — slot: the workplace»; the set keeps it
 * as its intention and its slot, the slot null for «— slot: none». A scene written before the set existed has an empty one.
 */
final readonly class SurvivalSet
{
    /** The mark that splits an item of `must_say` into its intention and its slot — the prompt's own spelling. */
    public const SLOT_MARK = '— slot:';

    /** A slot that is no slot: the sentence has nothing to swap. */
    public const NO_SLOT = 'none';

    /**
     * @param  list<array{text: string, slot: string|null, marked: bool}>  $mustSay  `marked` — the item was written with its
     *                                                                               «— slot:» (an item without it has no slot)
     * @param  list<string>  $mustUnderstand
     */
    public function __construct(
        public array $mustSay = [],
        public array $mustUnderstand = [],
    ) {}

    /**
     * The set as the model wrote it: every `must_say` string read as «intention — slot: slot». A dash other than the
     * prompt's em dash is read too («- slot:», «– slot:»); an item with no slot mark at all keeps its whole text as the
     * intention, no slot, and `marked` false — the survival set's check counts it.
     *
     * @param  array<mixed>  $mustSay  strings; anything else is no item
     * @param  array<mixed>  $mustUnderstand  strings; anything else is no item
     */
    public static function fromModel(array $mustSay, array $mustUnderstand): self
    {
        $say = [];
        foreach ($mustSay as $raw) {
            $item = self::oneLine($raw);
            if ($item === '') {
                continue;
            }
            if (preg_match('/^(.*?)\s*[—–-]+\s*slot\s*:\s*(.*)$/iu', $item, $m) === 1) {
                $slot = trim(rtrim(trim($m[2]), '.'));
                $say[] = ['text' => trim($m[1]), 'slot' => $slot === '' || mb_strtolower($slot) === self::NO_SLOT ? null : $slot, 'marked' => true];
            } else {
                $say[] = ['text' => $item, 'slot' => null, 'marked' => false];
            }
        }
        $understand = array_values(array_filter(array_map(self::oneLine(...), array_values($mustUnderstand)), static fn (string $i): bool => $i !== ''));

        return new self($say, $understand);
    }

    /**
     * The set as the columns hold it: `must_say` — `[{text, slot}]` (slot null for «none»), `must_understand` — `[{text}]`.
     *
     * @param  list<mixed>|null  $mustSay
     * @param  list<mixed>|null  $mustUnderstand
     */
    public static function fromColumns(?array $mustSay, ?array $mustUnderstand): self
    {
        $say = [];
        foreach ($mustSay ?? [] as $row) {
            if (is_array($row) && is_string($row['text'] ?? null)) {
                $slot = $row['slot'] ?? null;
                $say[] = ['text' => $row['text'], 'slot' => is_string($slot) ? $slot : null, 'marked' => true];
            }
        }
        $understand = [];
        foreach ($mustUnderstand ?? [] as $row) {
            if (is_array($row) && is_string($row['text'] ?? null)) {
                $understand[] = $row['text'];
            }
        }

        return new self($say, $understand);
    }

    public function isEmpty(): bool
    {
        return $this->mustSay === [] && $this->mustUnderstand === [];
    }

    /** An item of `must_say` as the prompts read it: «say where you worked before — slot: the workplace». */
    public function sayLine(int $index): string
    {
        $item = $this->mustSay[$index] ?? null;

        return $item === null ? '' : $item['text'].' '.self::SLOT_MARK.' '.($item['slot'] ?? self::NO_SLOT);
    }

    /** @return list<string> every item of `must_say` as the prompts read it */
    public function sayLines(): array
    {
        return array_map($this->sayLine(...), array_keys($this->mustSay));
    }

    /** @return list<array{text: string, slot: string|null}> what `plan_scenes.must_say` holds */
    public function mustSayColumn(): array
    {
        return array_map(static fn (array $item): array => ['text' => $item['text'], 'slot' => $item['slot']], $this->mustSay);
    }

    /** @return list<array{text: string}> what `plan_scenes.must_understand` holds */
    public function mustUnderstandColumn(): array
    {
        return array_map(static fn (string $text): array => ['text' => $text], $this->mustUnderstand);
    }

    private static function oneLine(mixed $text): string
    {
        return is_string($text) ? trim((string) preg_replace('/\s+/u', ' ', $text)) : '';
    }
}

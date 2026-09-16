<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Service\FrameParts;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\Shuffle;
use App\Modules\Plan\Domain\Service\SpeechCoverage;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * THE NINE PHRASE CARDS (наряд SESSION-1a, разд. 1; SESSION-1d — фраза через разные окна).
 *
 * A frame is shown the same way wherever it stands — on a phrase card, under a dialogue line, on a speaking card — so
 * the frame, its fillers, the phrase as it is said, an exchange and a partner's line are not drawn here but taken from
 * {@see CardObjects}, as every stage takes them.
 *
 * A card that recognises or says the frame is said with ONE filler, given to it (`$filler`, its place in the slot) —
 * which filler a card of the day takes, and which kind it is, is {@see PhraseSeries}'. The right answer is that
 * filler's own: its sentence in the learner's language (`phrase_slot`, `phrase_choose_back`, `phrase_assemble`) or its
 * sound (`phrase_slot_listen`, `p1.f2`); the wrong options are the frame's other fillers first and the fillers of the
 * other frames after, from the next frame on. A card whose material is missing — a filler the frame does not have, a
 * sound it cannot be said with, a choice left with fewer than {@see Options::MIN} options — is not built (null), and
 * the stage decides what stands in its place.
 */
final class PhraseCards
{
    public const OPTIONS = 4;

    private const EXTRA_TILES = 2;

    private const COMBINE_FRAMES = 3;

    public function __construct(private readonly SpeechCoverage $coverage = new SpeechCoverage) {}

    /**
     * Does the frame have a window a card can put a value into — a slot in its text and at least one filler for it?
     * A frame without one is met, recognised back and repeated, nothing else.
     */
    public static function hasSlot(PlanTerm $phrase): bool
    {
        $frame = $phrase->frame();

        return $frame !== null && $frame->slot !== null && FrameText::hasSlot($frame->frameTarget) && $frame->fillers() !== [];
    }

    /** 32-1 — the frame, its fillers and the phrase as the dialogue says it. */
    public function intro(SceneMaterial $scene, PlanTerm $phrase): CardDraft
    {
        return $this->draft(CardKind::PhraseIntro, $phrase, [
            'scene_id' => $scene->sceneId->value,
            'frame' => CardObjects::frame($scene, $phrase),
            'said' => CardObjects::said($scene, $phrase),
        ]);
    }

    /**
     * 32-2 — the frame said with the filler put together from tiles: the frame's words outside the window, lower-cased
     * (the capital of the first word would give its place away; «I» keeps its own), and two words of the frames that
     * follow that this frame does not have; the window takes a filler from the chips. The target is that filler's
     * sentence in the learner's language, the answer that filler in the window. Null for a frame without a window or a
     * filler it does not have — there is nothing to check the window against.
     */
    public function assemble(SceneMaterial $scene, PlanTerm $phrase, ?int $filler): ?CardDraft
    {
        $frame = $phrase->frame();
        $right = self::fillerAt($scene, $phrase, $filler);
        if ($frame === null || $right === null) {
            return null;
        }
        $words = FrameParts::words($frame->frameTarget);
        if ($words === []) {
            return null;
        }

        $taken = array_fill_keys(array_map(static fn (string $w): string => mb_strtolower($w), $words), true);
        $extras = [];
        foreach ($this->othersFromNext($scene, $phrase) as $other) {
            $borrowed = $other->frame();
            if ($borrowed === null) {
                continue;
            }
            foreach (FrameParts::words($borrowed->frameTarget) as $word) {
                $key = mb_strtolower($word);
                if (isset($taken[$key])) {
                    continue;
                }
                $taken[$key] = true;
                $extras[] = $word;
                if (count($extras) >= self::EXTRA_TILES) {
                    break 2;
                }
            }
        }
        $tiles = Shuffle::seeded(
            $scene->seed("{$phrase->ref()}:assemble"),
            array_map(static fn (string $w): string => self::tile($w), [...$words, ...$extras]),
        );

        return $this->draft(CardKind::PhraseAssemble, $phrase, [
            'scene_id' => $scene->sceneId->value,
            'frame' => CardObjects::frame($scene, $phrase),
            'target_native' => $right['native_line'],
            'tiles' => $tiles,
            'chips' => CardObjects::fillers($scene, $phrase),
            'expected' => [
                // The SAME spelling the tiles carry: the client compares what it assembled tile by tile, and a word
                // that reads «It» on the answer and «it» on its tile would never match it. The capital of the first
                // word is the client's to put back when it shows the sentence.
                'words' => array_map(static fn (string $w): string => self::tile($w), $words),
                'slot_at' => FrameParts::slotAt($frame->frameTarget),
                'filler_index' => $right['index'],
            ],
        ]);
    }

    /**
     * 32-3 (D-11) — the frame said with the filler in the target language, heard and read; the learner picks its
     * translation among the frame's other fillers, then what the other frames say (their fillers, a frame without a
     * window as itself), from the next frame on. A frame without a window is said as itself (`$filler` null). Null for a
     * filler the frame does not have or cannot be said with, and when there is nothing to choose between — the only
     * frame of the day, with no other filler of its own.
     */
    public function chooseBack(SceneMaterial $scene, PlanTerm $phrase, ?int $filler): ?CardDraft
    {
        $ref = $phrase->ref();
        $sentence = CardObjects::sentence($scene, $phrase, $filler);
        if ($sentence === null) {
            return null;
        }
        $candidates = [];
        foreach (CardObjects::fillers($scene, $phrase) as $other) {
            if ($other['index'] !== $sentence['filler_index']) {
                $candidates[] = ['text' => $other['native_line']];
            }
        }
        foreach ($this->othersFromNext($scene, $phrase) as $other) {
            $fillers = self::hasSlot($other) ? CardObjects::fillers($scene, $other) : [];
            foreach ($fillers === [] ? [$other->textNative()] : array_column($fillers, 'native_line') as $text) {
                $candidates[] = ['text' => $text];
            }
        }
        $chosen = Options::choose($scene->seed(self::seedOf($ref, 'choose_back', $sentence['filler_index'])), ['text' => $sentence['text_native']], $candidates, self::OPTIONS);
        if (count($chosen['options']) < Options::MIN) {
            return null;
        }

        return $this->draft(CardKind::PhraseChooseBack, $phrase, [
            'scene_id' => $scene->sceneId->value,
            'prompt' => [
                'text_target' => $sentence['text_target'],
                'pronunciation_native' => $sentence['pronunciation_native'],
                'filler_index' => $sentence['filler_index'],
                'audio' => $sentence['audio'],
            ],
            'options' => $chosen['options'],
            'correct' => $chosen['correct'],
        ]);
    }

    /**
     * 32-4 — the window: the filler's sentence in the learner's language above the frame, the filler among the frame's
     * other fillers and the next frames' fillers, each playable in its own frame. Null for a frame without a window, a
     * filler it does not have, and when no other filler is there to choose from.
     */
    public function slot(SceneMaterial $scene, PlanTerm $phrase, ?int $filler): ?CardDraft
    {
        $right = self::fillerAt($scene, $phrase, $filler);
        if ($right === null) {
            return null;
        }
        $candidates = [];
        foreach ($this->wrongFillers($scene, $phrase, $right['index']) as $other) {
            $candidates[] = ['text' => $other['target'], 'audio' => $other['audio']];
        }
        $chosen = Options::choose(
            $scene->seed(self::seedOf($phrase->ref(), 'slot', $right['index'])),
            ['text' => $right['target'], 'audio' => $right['audio']],
            $candidates,
            self::OPTIONS,
        );
        if (count($chosen['options']) < Options::MIN) {
            return null;
        }

        return $this->draft(CardKind::PhraseSlot, $phrase, [
            'scene_id' => $scene->sceneId->value,
            'frame' => CardObjects::frame($scene, $phrase),
            'prompt_native' => $right['native_line'],
            'options' => $chosen['options'],
            'correct' => $chosen['correct'],
        ]);
    }

    /**
     * 32-5 (D-13) — the window by ear: the frame said with the filler plays (`p1`, `p1.f2`), the options are silent —
     * the frame's other fillers and the next frames' fillers. Null when the frame cannot be said with that filler (no
     * sound), and when no other filler is there to choose from.
     */
    public function slotListen(SceneMaterial $scene, PlanTerm $phrase, ?int $filler): ?CardDraft
    {
        $right = self::fillerAt($scene, $phrase, $filler);
        if ($right === null || $right['audio'] === null) {
            return null;
        }
        $candidates = [];
        foreach ($this->wrongFillers($scene, $phrase, $right['index']) as $other) {
            $candidates[] = ['text' => $other['target']];
        }
        $chosen = Options::choose($scene->seed(self::seedOf($phrase->ref(), 'slot_listen', $right['index'])), ['text' => $right['target']], $candidates, self::OPTIONS);
        if (count($chosen['options']) < Options::MIN) {
            return null;
        }

        return $this->draft(CardKind::PhraseSlotListen, $phrase, [
            'scene_id' => $scene->sceneId->value,
            'frame' => CardObjects::frame($scene, $phrase),
            'filler_index' => $right['index'],
            'audio' => $right['audio'],
            'options' => $chosen['options'],
            'correct' => $chosen['correct'],
        ]);
    }

    /**
     * 32-6 — the frame said aloud with the filler (a frame without a window — as itself, `$filler` null), passed by
     * coverage of the whole sentence; the sample plays with that filler. Null for a filler the frame does not have or
     * cannot be said with.
     */
    public function repeat(SceneMaterial $scene, PlanTerm $phrase, ?int $filler): ?CardDraft
    {
        $sentence = CardObjects::sentence($scene, $phrase, $filler);

        return $sentence === null ? null : $this->repeatOf($scene, $phrase, $sentence);
    }

    /** 32-6 of the phrase itself — the frame as the dialogue says it: what every frame can be repeated as. */
    public function saidRepeat(SceneMaterial $scene, PlanTerm $phrase): CardDraft
    {
        return $this->repeatOf($scene, $phrase, CardObjects::said($scene, $phrase));
    }

    /** @param array{filler_index: int|null, text_target: string, text_native: string, pronunciation_native: string|null, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}} $sentence */
    private function repeatOf(SceneMaterial $scene, PlanTerm $phrase, array $sentence): CardDraft
    {
        $expected = $sentence['text_target'];

        return $this->draft(CardKind::PhraseRepeat, $phrase, [
            'scene_id' => $scene->sceneId->value,
            'frame' => CardObjects::frame($scene, $phrase),
            'filler_index' => $sentence['filler_index'],
            'expected_text' => $expected,
            'key' => $phrase->speakingKey(),
            'coverage_min' => $this->coverage->minFor($expected, $scene->target),
            'audio' => $sentence['audio'],
        ]);
    }

    /**
     * 32-7 (D-14) — the frame said with the filler — never the one the dialogue says it with — asked for by its
     * translation in the window and never played. The client passes it when the frame is covered and the value was
     * heard (`slot_expected`). Null for a frame without a window, a filler it does not have, or the said one.
     */
    public function otherSlot(SceneMaterial $scene, PlanTerm $phrase, ?int $filler): ?CardDraft
    {
        $frame = $phrase->frame();
        $right = self::fillerAt($scene, $phrase, $filler);
        if ($frame === null || $right === null || $right['index'] === $scene->saidIndex($phrase)) {
            return null;
        }
        $expected = FrameText::withEndMarkOf(FrameText::fill($frame->frameTarget, $right['target']), $phrase->textTarget());

        return $this->draft(CardKind::PhraseOtherSlot, $phrase, [
            'scene_id' => $scene->sceneId->value,
            'frame' => CardObjects::frame($scene, $phrase),
            'filler_index' => $right['index'],
            'task_native' => $right['native'],
            'expected_text' => $expected,
            'slot_expected' => $right['target'],
            'key' => $phrase->speakingKey(),
            'coverage_min' => $this->coverage->minFor($expected, $scene->target),
        ]);
    }

    /**
     * 32-8 (D-15; SESSION-1e) — ONE per day, and only ON A QUESTION: a partner's line that asks, three frames to answer
     * it with — each as a whole phrase — and the chips of the right one.
     *
     * The exchange is an answer whose partner's line ends with a question (the target pack's `sentence_ends`,
     * {@see SceneMaterial::asks()}) and whose learner's line stands on a frame with a window: the first such exchange in
     * the visit whose frame the dialogue says there only (a frame said twice has no single right filler), else the first
     * such exchange. For a frame that comes back as this card (`$for`, SESSION-1d) — the first such exchange of that
     * frame. The two wrong frames have windows too and are the frames of the exchanges FARTHEST from this one by step
     * (SESSION-1d, as `dialogue_partner`'s fourth option): a frame said in a far exchange belongs to another moment of
     * the visit.
     *
     * Every frame carries `said` — the frame as a whole phrase ({@see CardObjects::whole()}): the right one said with
     * the filler this exchange says it with, a wrong one with the filler the dialogue says it with (none — its first).
     * Null when no exchange asks so, or no other frame has a window.
     */
    public function combine(SceneMaterial $scene, ?PlanTerm $for = null): ?CardDraft
    {
        $asked = [];
        $once = [];
        foreach ($scene->lesson->exchanges as $exchange) {
            $learner = $exchange->learner();
            $partner = $exchange->partner();
            $phrase = $learner === null ? null : $scene->phraseTerm($learner->phraseId);
            if ($exchange->kind !== ExchangeKind::Answer || $partner === null || $phrase === null || ! self::hasSlot($phrase)
                || ! $scene->asks($partner->textTarget) || ($for !== null && $phrase->ref() !== $for->ref())) {
                continue;
            }
            $asked[] = $exchange;
            if (count($scene->lesson->linesOf($phrase->ref())) === 1) {
                $once[] = $exchange;
            }
        }
        $exchange = $once[0] ?? $asked[0] ?? null;
        $learner = $exchange?->learner();
        $phrase = $learner === null ? null : $scene->phraseTerm($learner->phraseId);
        if ($exchange === null || $learner === null || $phrase === null) {
            return null;
        }

        $wrong = $this->farthestFrames($scene, $exchange, $phrase);
        if ($wrong === []) {
            return null;
        }
        $correctFiller = CardObjects::fillerIndexOf($phrase, $learner->filler);
        $frames = array_map(
            static function (PlanTerm $t) use ($scene, $phrase, $correctFiller): array {
                $frame = $t->frame();
                $said = $t->ref() === $phrase->ref() && $correctFiller !== null
                    ? $correctFiller
                    : $scene->saidIndex($t) ?? (CardObjects::fillers($scene, $t)[0]['index'] ?? null);

                return [
                    'ref' => $t->ref(),
                    'frame_target' => $frame === null ? '' : $frame->frameTarget,
                    'frame_native' => $frame === null ? '' : $frame->frameNative,
                    'said' => CardObjects::whole($scene, $t, $said),
                ];
            },
            Shuffle::seeded($scene->seed('phrases:combine:frames'), [$phrase, ...$wrong]),
        );

        return $this->draft(CardKind::PhraseCombine, $phrase, [
            'scene_id' => $scene->sceneId->value,
            'exchange' => CardObjects::exchange($exchange),
            'partner_line' => CardObjects::partnerLine($exchange),
            'frames' => $frames,
            'correct_frame' => $phrase->ref(),
            'chips' => CardObjects::fillers($scene, $phrase),
            'correct_filler' => $correctFiller,
        ]);
    }

    /**
     * 32-9 (D-16) — the learner's own value in the window, said aloud and judged by meaning: the frame open from the
     * start, the partner's line it speaks to (the first exchange the frame is said in), the fillers as examples and
     * chips. Null for a frame without a window.
     */
    public function ownSlot(SceneMaterial $scene, PlanTerm $phrase): ?CardDraft
    {
        $frame = $phrase->frame();
        if ($frame === null || ! self::hasSlot($phrase)) {
            return null;
        }
        $first = $scene->lesson->linesOf($phrase->ref())[0]['exchange'] ?? null;
        $fillers = CardObjects::fillers($scene, $phrase);

        return $this->draft(CardKind::PhraseOwnSlot, $phrase, [
            'scene_id' => $scene->sceneId->value,
            'frame' => CardObjects::frame($scene, $phrase),
            'partner_line' => $first === null ? null : CardObjects::cueLine($scene, $first),
            'task_native' => $frame->frameNative,
            'key' => $phrase->speakingKey(),
            'coverage_min' => $this->coverage->minFor(FrameParts::part($frame->frameTarget), $scene->target),
            'examples' => array_column($fillers, 'native'),
            'chips' => $fillers,
            'judge' => true,
        ]);
    }

    // ---------------------------------------------------------------------------------------------------------------

    /** @param array<string, mixed> $payload */
    private function draft(CardKind $kind, PlanTerm $phrase, array $payload): CardDraft
    {
        return new CardDraft($kind, UnitKind::Phrase, $phrase->ref(), $payload);
    }

    /**
     * The filler at `$index` of a frame with a window, as the cards show it; null for a frame without one, an index the
     * slot does not have, or a filler no card shows ({@see CardObjects::fillers()}).
     *
     * @return array{index: int, target: string, native: string, pronunciation_native: string, in_dialogue: bool, native_line: string, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}|null}|null
     */
    private static function fillerAt(SceneMaterial $scene, PlanTerm $phrase, ?int $index): ?array
    {
        if ($index === null || ! self::hasSlot($phrase)) {
            return null;
        }
        foreach (CardObjects::fillers($scene, $phrase) as $filler) {
            if ($filler['index'] === $index) {
                return $filler;
            }
        }

        return null;
    }

    /**
     * The wrong fillers of a card said with the filler at `$index`, in the order they are preferred: the frame's own
     * other fillers first, in their order, then the fillers of the other frames from the next one on.
     *
     * @return list<array{index: int, target: string, native: string, pronunciation_native: string, in_dialogue: bool, native_line: string, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}|null}>
     */
    private function wrongFillers(SceneMaterial $scene, PlanTerm $phrase, int $index): array
    {
        $out = array_values(array_filter(CardObjects::fillers($scene, $phrase), static fn (array $f): bool => $f['index'] !== $index));
        foreach ($this->othersFromNext($scene, $phrase) as $other) {
            foreach (CardObjects::fillers($scene, $other) as $filler) {
                $out[] = $filler;
            }
        }

        return $out;
    }

    /**
     * The two wrong frames of `phrase_combine`: the frames (with a window) of the exchanges farthest from the card's by
     * step, between two as far the lower step ({@see SceneMaterial::farthestFrom()}); a frame no exchange says after
     * them, in its order.
     *
     * @return list<PlanTerm>
     */
    private function farthestFrames(SceneMaterial $scene, Exchange $exchange, PlanTerm $phrase): array
    {
        $wrong = [];
        $taken = [$phrase->ref() => true];
        $said = array_map(
            static fn (Exchange $e): ?PlanTerm => $scene->phraseTerm($e->learner()?->phraseId),
            $scene->farthestFrom($exchange->step),
        );
        foreach ([...$said, ...$this->others($scene, $phrase)] as $term) {
            if ($term === null || isset($taken[$term->ref()]) || ! self::hasSlot($term)) {
                continue;
            }
            $taken[$term->ref()] = true;
            $wrong[] = $term;
            if (count($wrong) >= self::COMBINE_FRAMES - 1) {
                break;
            }
        }

        return $wrong;
    }

    /** A card's own seed: the frame, the kind and the filler it is said with (none for a frame without a window). */
    private static function seedOf(string $ref, string $card, ?int $filler): string
    {
        return $filler === null ? "{$ref}:{$card}" : "{$ref}:{$card}:{$filler}";
    }

    /**
     * The other frames of the day, in their order.
     *
     * @return list<PlanTerm>
     */
    private function others(SceneMaterial $scene, PlanTerm $phrase): array
    {
        return array_values(array_filter($scene->phrases(), static fn (PlanTerm $t): bool => $t->ref() !== $phrase->ref()));
    }

    /**
     * The other frames of the day starting from the one after this, round to the one before it — where a card looks
     * first for words and fillers that are not this frame's.
     *
     * @return list<PlanTerm>
     */
    private function othersFromNext(SceneMaterial $scene, PlanTerm $phrase): array
    {
        $all = $scene->phrases();
        $at = null;
        foreach ($all as $i => $term) {
            if ($term->ref() === $phrase->ref()) {
                $at = $i;
                break;
            }
        }
        if ($at === null) {
            return $all;
        }

        return [...array_slice($all, $at + 1), ...array_slice($all, 0, $at)];
    }

    /** A tile as the tray shows it: lower-cased, but «I» (and «I'd», «I'm») keeps its capital. */
    private static function tile(string $word): string
    {
        return preg_match("/^I(['’]\p{L}+)?$/u", $word) === 1 ? $word : mb_strtolower($word);
    }
}

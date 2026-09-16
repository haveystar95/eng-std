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
 * THE NINE PHRASE CARDS (наряд SESSION-1a, разд. 1; SPEC §4 «Phrases»).
 *
 * A frame is shown the same way wherever it stands — on a phrase card, under a dialogue line, on a speaking card — so
 * the frame, its fillers, the phrase as it is said, an exchange and a partner's line are not drawn here but taken from
 * {@see CardObjects}, as every stage takes them.
 *
 * The right filler of a recognition card is the SAID one (D-12) — the line the learner met in the dialogue; a card
 * whose material is missing, or a choice left with fewer than {@see Options::MIN} options, is not built (null), and
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
            'frame' => CardObjects::frame($phrase),
            'said' => CardObjects::said($scene, $phrase),
        ]);
    }

    /**
     * 32-2 — the phrase put together from tiles: the frame's words outside the window, lower-cased (the capital of
     * the first word would give its place away; «I» keeps its own), and two words of the frames that follow that
     * this frame does not have; the window takes a filler from the chips. The target is the SAID sentence. Null for
     * a frame without a window, or one whose said filler is unknown — there is nothing to check the window against.
     */
    public function assemble(SceneMaterial $scene, PlanTerm $phrase): ?CardDraft
    {
        $frame = $phrase->frame();
        $said = $scene->saidIndex($phrase);
        if ($frame === null || ! self::hasSlot($phrase) || $said === null) {
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
            'frame' => CardObjects::frame($phrase),
            'target_native' => $phrase->textNative(),
            'tiles' => $tiles,
            'chips' => CardObjects::fillers($phrase),
            'expected' => [
                // The SAME spelling the tiles carry: the client compares what it assembled tile by tile, and a word
                // that reads «It» on the answer and «it» on its tile would never match it. The capital of the first
                // word is the client's to put back when it shows the sentence.
                'words' => array_map(static fn (string $w): string => self::tile($w), $words),
                'slot_at' => FrameParts::slotAt($frame->frameTarget),
                'filler_index' => $said,
            ],
        ]);
    }

    /**
     * 32-3 (D-11) — the said phrase in the target language, heard and read; the learner picks its translation among
     * the same frame with its other fillers and, when the frame has fewer, what the other frames of the day say.
     * Built for every frame, a window or none — unless there is nothing to choose between: the only frame of the day,
     * with no other filler of its own (null).
     */
    public function chooseBack(SceneMaterial $scene, PlanTerm $phrase): ?CardDraft
    {
        $ref = $phrase->ref();
        $said = $scene->saidIndex($phrase);
        $candidates = [];
        foreach (CardObjects::fillers($phrase) as $filler) {
            if ($filler['index'] !== $said) {
                $candidates[] = ['text' => $filler['native_line']];
            }
        }
        foreach (Shuffle::seeded($scene->seed("{$ref}:choose_back:others"), $this->others($scene, $phrase)) as $other) {
            $candidates[] = ['text' => $other->textNative()];
        }
        $chosen = Options::choose($scene->seed("{$ref}:choose_back"), ['text' => $phrase->textNative()], $candidates, self::OPTIONS);
        if (count($chosen['options']) < Options::MIN) {
            return null;
        }

        return $this->draft(CardKind::PhraseChooseBack, $phrase, [
            'scene_id' => $scene->sceneId->value,
            'prompt' => [
                'text_target' => $phrase->textTarget(),
                'pronunciation_native' => $phrase->pronunciationNative(),
                'filler_index' => $said,
                'audio' => Audio::of($ref),
            ],
            'options' => $chosen['options'],
            'correct' => $chosen['correct'],
        ]);
    }

    /**
     * 32-4 — the window: the said sentence in the learner's language above the frame, the said filler among the
     * frame's other fillers and a filler of the next frame, each playable in its own frame. Null for a frame without
     * a window or with no said filler — the card a returned frame falls back from — and when no other filler is there
     * to choose from.
     */
    public function slot(SceneMaterial $scene, PlanTerm $phrase): ?CardDraft
    {
        $said = $scene->saidIndex($phrase);
        $fillers = CardObjects::fillers($phrase);
        $right = null;
        $candidates = [];
        foreach ($fillers as $filler) {
            if ($filler['index'] === $said) {
                $right = $filler;
            } else {
                $candidates[] = ['text' => $filler['target'], 'audio' => $filler['audio']];
            }
        }
        if (! self::hasSlot($phrase) || $right === null) {
            return null;
        }
        foreach ($this->othersFromNext($scene, $phrase) as $other) {
            foreach (CardObjects::fillers($other) as $filler) {
                $candidates[] = ['text' => $filler['target'], 'audio' => $filler['audio']];
            }
        }
        $chosen = Options::choose(
            $scene->seed("{$phrase->ref()}:slot"),
            ['text' => $right['target'], 'audio' => $right['audio']],
            $candidates,
            self::OPTIONS,
        );
        if (count($chosen['options']) < Options::MIN) {
            return null;
        }

        return $this->draft(CardKind::PhraseSlot, $phrase, [
            'scene_id' => $scene->sceneId->value,
            'frame' => CardObjects::frame($phrase),
            'prompt_native' => $phrase->textNative(),
            'options' => $chosen['options'],
            'correct' => $chosen['correct'],
        ]);
    }

    /**
     * 32-5 (D-13) — the window by ear: the frame said with one filler plays (seeded among the fillers that have a
     * sound), the options are silent — the frame's fillers and the next frame's, up to four. Null when no filler of
     * the frame is voiced, or no other filler is there to choose from.
     */
    public function slotListen(SceneMaterial $scene, PlanTerm $phrase): ?CardDraft
    {
        $ref = $phrase->ref();
        $voiced = [];
        $byIndex = [];
        foreach (CardObjects::fillers($phrase) as $filler) {
            $byIndex[$filler['index']] = $filler;
            if ($filler['audio'] !== null) {
                $voiced[] = $filler['index'];
            }
        }
        if (! self::hasSlot($phrase) || $voiced === []) {
            return null;
        }
        $index = Rotation::pick($scene->seed("{$ref}:slot_listen"), 0, $voiced);
        $heard = $byIndex[$index];

        $candidates = [];
        foreach ($byIndex as $filler) {
            if ($filler['index'] !== $index) {
                $candidates[] = ['text' => $filler['target']];
            }
        }
        foreach ($this->othersFromNext($scene, $phrase) as $other) {
            foreach (CardObjects::fillers($other) as $filler) {
                $candidates[] = ['text' => $filler['target']];
            }
        }
        $chosen = Options::choose($scene->seed("{$ref}:slot_listen"), ['text' => $heard['target']], $candidates, self::OPTIONS);
        if (count($chosen['options']) < Options::MIN) {
            return null;
        }

        return $this->draft(CardKind::PhraseSlotListen, $phrase, [
            'scene_id' => $scene->sceneId->value,
            'frame' => CardObjects::frame($phrase),
            'filler_index' => $index,
            'audio' => $heard['audio'],
            'options' => $chosen['options'],
            'correct' => $chosen['correct'],
        ]);
    }

    /** 32-6 — the said phrase aloud, passed by coverage of the whole sentence. */
    public function repeat(SceneMaterial $scene, PlanTerm $phrase): CardDraft
    {
        $expected = $phrase->textTarget();

        return $this->draft(CardKind::PhraseRepeat, $phrase, [
            'scene_id' => $scene->sceneId->value,
            'frame' => CardObjects::frame($phrase),
            'filler_index' => $scene->saidIndex($phrase),
            'expected_text' => $expected,
            'key' => $phrase->speakingKey(),
            'coverage_min' => $this->coverage->minFor($expected, $scene->target),
            'audio' => Audio::of($phrase->ref()),
        ]);
    }

    /**
     * 32-7 (D-14) — the frame said with ANOTHER filler than the dialogue's (seeded among the rest), asked for by its
     * translation in the window and never played. The client passes it when the frame is covered and the value was
     * heard (`slot_expected`). Null when the frame has no other filler.
     */
    public function otherSlot(SceneMaterial $scene, PlanTerm $phrase): ?CardDraft
    {
        $frame = $phrase->frame();
        $said = $scene->saidIndex($phrase);
        $byIndex = [];
        foreach (CardObjects::fillers($phrase) as $filler) {
            if ($filler['index'] !== $said) {
                $byIndex[$filler['index']] = $filler;
            }
        }
        if ($frame === null || ! self::hasSlot($phrase) || $byIndex === []) {
            return null;
        }
        $index = Rotation::pick($scene->seed("{$phrase->ref()}:other"), 0, array_keys($byIndex));
        $filler = $byIndex[$index];
        $expected = FrameText::withEndMarkOf(FrameText::fill($frame->frameTarget, $filler['target']), $phrase->textTarget());

        return $this->draft(CardKind::PhraseOtherSlot, $phrase, [
            'scene_id' => $scene->sceneId->value,
            'frame' => CardObjects::frame($phrase),
            'filler_index' => $index,
            'task_native' => $filler['native'],
            'expected_text' => $expected,
            'slot_expected' => $filler['target'],
            'key' => $phrase->speakingKey(),
            'coverage_min' => $this->coverage->minFor($expected, $scene->target),
        ]);
    }

    /**
     * 32-8 (D-15) — ONE per day: a partner's line of the dialogue, three frames to answer it with and the chips of
     * the right one. The exchange is an answer whose frame the dialogue says once (a frame said twice has no single
     * right filler), seeded among such; none — exchange 1 when it is an answer on a framed window, else the first
     * such answer. The wrong frames have windows too — answer frames before ask frames, seeded. Null when there is no
     * such exchange or no other frame with a window.
     */
    public function combine(SceneMaterial $scene): ?CardDraft
    {
        $framed = [];
        $once = [];
        foreach ($scene->lesson->exchanges as $exchange) {
            $learner = $exchange->learner();
            $phrase = $learner === null ? null : $scene->phraseTerm($learner->phraseId);
            if ($exchange->kind !== ExchangeKind::Answer || $exchange->partner() === null || $phrase === null || ! self::hasSlot($phrase)) {
                continue;
            }
            $framed[] = $exchange;
            if (count($scene->lesson->linesOf($phrase->ref())) === 1) {
                $once[] = $exchange;
            }
        }
        if ($once !== []) {
            $exchange = Rotation::pick($scene->seed('phrases:combine'), 0, $once);
        } else {
            $first = array_values(array_filter($framed, static fn (Exchange $e): bool => $e->step === 1));
            $exchange = $first[0] ?? $framed[0] ?? null;
        }
        $learner = $exchange?->learner();
        $phrase = $learner === null ? null : $scene->phraseTerm($learner->phraseId);
        if ($exchange === null || $learner === null || $phrase === null) {
            return null;
        }

        $answers = [];
        $asks = [];
        foreach ($this->others($scene, $phrase) as $other) {
            if (! self::hasSlot($other)) {
                continue;
            }
            if ($other->frame()?->kind === ExchangeKind::Ask) {
                $asks[] = $other;
            } else {
                $answers[] = $other;
            }
        }
        $wrong = array_slice([
            ...Shuffle::seeded($scene->seed('phrases:combine:answers'), $answers),
            ...Shuffle::seeded($scene->seed('phrases:combine:asks'), $asks),
        ], 0, self::COMBINE_FRAMES - 1);
        if ($wrong === []) {
            return null;
        }
        $frames = array_map(
            static function (PlanTerm $t): array {
                $frame = $t->frame();

                return [
                    'ref' => $t->ref(),
                    'frame_target' => $frame === null ? '' : $frame->frameTarget,
                    'frame_native' => $frame === null ? '' : $frame->frameNative,
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
            'chips' => CardObjects::fillers($phrase),
            'correct_filler' => CardObjects::fillerIndexOf($phrase, $learner->filler),
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
        $fillers = CardObjects::fillers($phrase);

        return $this->draft(CardKind::PhraseOwnSlot, $phrase, [
            'scene_id' => $scene->sceneId->value,
            'frame' => CardObjects::frame($phrase),
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

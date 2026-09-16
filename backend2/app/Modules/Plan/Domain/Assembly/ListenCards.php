<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\Lesson\ListeningExchange;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\ListeningQuestion;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\Shuffle;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\Service\Words;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * THE CARDS OF «СЛУШАЮ И ОТВЕЧАЮ», ONE BY ONE (наряд SESSION-1a, разд. 1; кадры 34-1…34-7): what each card carries,
 * read off the SERVED lesson — the right answers already at their shuffled places.
 *
 * The stage is about the day, not about a word or a frame: every card's unit is `day`, and a listening question is
 * its own unit `L{n}` inside the day — neither ever comes back on another day. A card whose material the lesson does
 * not have (no line with a number, no ask exchange, a question without its right option) is simply null; which cards
 * the stage deals and in what order is {@see ListenStage}'s. The exchange and every line a card plays are
 * {@see CardObjects}' — the same objects the other stages show.
 */
final class ListenCards
{
    public const DAY_REF = 'day';

    public const PACE_RATES = [0.75, 1.0];

    private const QUESTION_OPTIONS = 4;

    private const PREDICT_OPTIONS = 3;

    private const NUMBER_OPTIONS = 3;

    /** 34-1: the whole visit by ear, every line of both speakers in the order of the visit. */
    public static function dialogue(SceneMaterial $scene): ?CardDraft
    {
        $lines = self::lines($scene);
        if ($lines === []) {
            return null;
        }

        return self::day($scene, CardKind::ListenDialogue, ['lines' => $lines, 'total_ms' => null]);
    }

    /**
     * 34-2: one listening question (`$index` from 0, its unit `L1…`): its three options and a fourth — a WRONG option
     * of another question, taken from the next question on (wrapping), never equal to the three.
     */
    public static function question(SceneMaterial $scene, int $index): ?CardDraft
    {
        $question = $scene->lesson->listening[$index] ?? null;
        $right = $question?->correctOption();
        if ($question === null || $right === null) {
            return null;
        }
        $texts = self::wrongOptions($question);
        $count = count($scene->lesson->listening);
        for ($k = 1; $k < $count; $k++) {
            $texts = [...$texts, ...self::wrongOptions($scene->lesson->listening[($index + $k) % $count])];
        }
        $ref = self::questionRef($index);
        $chosen = Options::choose($scene->seed($ref), ['text' => $right], self::texts($texts), self::QUESTION_OPTIONS);
        if (count($chosen['options']) < Options::MIN) {
            return null;
        }

        return new CardDraft(CardKind::ListenQuestion, UnitKind::Day, $ref, [
            'scene_id' => $scene->sceneId->value,
            'question' => ['ref' => $ref, 'text_native' => $question->textNative],
            'options' => $chosen['options'],
            'correct' => $chosen['correct'],
            'exchange_step' => self::exchangeStep($scene, $question),
        ]);
    }

    /**
     * 34-3: the visit again, with its texts, and where each question's answer was heard. When the right option is the
     * value the learner said in that exchange, the answer is the learner's own line and the filler inside it is
     * marked; otherwise it is the partner's line of the exchange, nothing marked; a question about no exchange points
     * nowhere.
     *
     * @param  list<int>  $asked  the questions dealt as cards (indexes from 0)
     */
    public static function review(SceneMaterial $scene, array $asked): ?CardDraft
    {
        $lines = self::lines($scene);
        if ($lines === [] || $asked === []) {
            return null;
        }
        $answers = [];
        foreach ($asked as $index) {
            $question = $scene->lesson->listening[$index] ?? null;
            if ($question === null) {
                continue;
            }
            $step = self::exchangeStep($scene, $question);
            $answers[] = ['question_ref' => self::questionRef($index), 'exchange_step' => $step, ...self::answerPlace($scene, $question, $step)];
        }

        return self::day($scene, CardKind::ListenReview, ['lines' => $lines, 'total_ms' => null, 'answers' => $answers]);
    }

    /**
     * 34-5: an ask exchange — the learner's question sounds with its text, and the learner guesses what the partner
     * will answer: the translation of the partner's own answer among the translations of two other partner lines of
     * the day (SESSION-1a, хвост — the exchange's check stays with `dialogue_partner`, so the day never shows one set
     * of options twice). The wrong lines are of the answer's own FORM (SESSION-1d): a question beside a question, a
     * statement beside a statement — whether a line asks is the mark its text ends with by the target pack's
     * `sentence_ends`; too few of that form — any others top it up, and that is no fault. Which lines is a shuffle seeded
     * by the card's address; the partner's answer opens after.
     */
    public static function predict(SceneMaterial $scene, Exchange $exchange): ?CardDraft
    {
        $own = $exchange->learner();
        $partner = $exchange->partner();
        if ($exchange->kind !== ExchangeKind::Ask || $own === null || $partner === null) {
            return null;
        }
        $candidates = [];
        foreach ($scene->partnerLines() as $line) {
            if ($line['step'] !== $exchange->step) {
                $candidates[] = $line['message'];
            }
        }
        $seed = $scene->seed('x'.$exchange->step.':predict');
        $asks = self::asks($scene, $partner->textTarget);
        $same = [];
        $other = [];
        foreach (Shuffle::seeded($seed.':others', $candidates) as $message) {
            if (self::asks($scene, $message->textTarget) === $asks) {
                $same[] = ['text' => $message->textNative];
            } else {
                $other[] = ['text' => $message->textNative];
            }
        }
        $chosen = Options::choose($seed, ['text' => $partner->textNative], [...$same, ...$other], self::PREDICT_OPTIONS);
        if (count($chosen['options']) < Options::MIN) {
            return null;
        }

        return self::day($scene, CardKind::ListenPredict, [
            'exchange' => CardObjects::exchange($exchange),
            'own_line' => CardObjects::line($exchange, $own),
            'options' => $chosen['options'],
            'correct' => $chosen['correct'],
            'partner_line' => CardObjects::line($exchange, $partner),
        ]);
    }

    /** 34-6: the partner's longest line of at most ten words, at two tempos — the line «Говорю сам» never takes. */
    public static function pace(SceneMaterial $scene): ?CardDraft
    {
        $pace = PartnerLines::pace($scene);
        $exchange = $pace === null ? null : $scene->exchange($pace['step']);
        if ($pace === null || $exchange === null) {
            return null;
        }

        return self::day($scene, CardKind::ListenPace, [
            'exchange' => CardObjects::exchange($exchange),
            'partner_line' => CardObjects::line($exchange, $pace['message']),
            'rates' => self::PACE_RATES,
        ]);
    }

    /**
     * 34-7: the longest line of the visit that says a number or a time in the target language (either speaker; one
     * length — the lower step, the partner first), the value marked in its text, and the value as the learner reads it
     * among two other values of the day — the other lines and every filler, a value of its own kind first (a number
     * beside numbers, a time beside times). No such line, no value in its translation, fewer than two other values, or
     * a language whose pack has no patterns — no card.
     */
    public static function number(SceneMaterial $scene): ?CardDraft
    {
        $heard = NumberValues::of($scene->target);
        $read = NumberValues::of($scene->native);
        if ($heard === null || $read === null) {
            return null;
        }

        $best = null;
        $bestWords = 0;
        foreach (self::messages($scene) as $line) {
            if (! $heard->says($line['message']->textTarget)) {
                continue;
            }
            $words = Words::count($line['message']->textTarget);
            $before = $best !== null && $words === $bestWords && (
                $line['step'] < $best['step']
                || ($line['step'] === $best['step'] && ! $line['message']->isLearner() && $best['message']->isLearner())
            );
            if ($best === null || $words > $bestWords || $before) {
                $best = $line;
                $bestWords = $words;
            }
        }
        if ($best === null) {
            return null;
        }
        $runs = $heard->runs($best['message']->textTarget);
        $value = $read->value($best['message']->textNative);
        if ($runs === [] || $value === null) {
            return null;
        }

        $others = [];
        foreach (self::messages($scene) as $line) {
            if ($line['ref'] !== $best['ref']) {
                $others = [...$others, ...$read->values($line['message']->textNative)];
            }
        }
        foreach ($scene->lesson->phrases as $phrase) {
            foreach ($phrase->fillers() as $filler) {
                $others = [...$others, ...$read->values($filler->native)];
            }
        }
        $seed = $scene->seed('listen:number');
        $seen = [mb_strtolower($value['text']) => true];
        $distinct = [];
        foreach ($others as $other) {
            $key = mb_strtolower($other['text']);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $distinct[] = $other;
            }
        }
        $sameKind = [];
        $otherKind = [];
        foreach (Shuffle::seeded($seed, $distinct) as $other) {
            if ($other['number'] === $value['number']) {
                $sameKind[] = $other['text'];
            } else {
                $otherKind[] = $other['text'];
            }
        }
        $chosen = Options::choose($seed, ['text' => $value['text']], self::texts([...$sameKind, ...$otherKind]), self::NUMBER_OPTIONS);
        if (count($chosen['options']) < self::NUMBER_OPTIONS) {
            return null;
        }

        return self::day($scene, CardKind::ListenNumber, [
            'line' => CardObjects::visitLine($best['step'], $best['message']),
            'span' => [$runs[0]['start'], $runs[0]['end']],
            'options' => $chosen['options'],
            'correct' => $chosen['correct'],
        ]);
    }

    /**
     * Does a line of the visit ask — its text ends with a mark the target pack's `sentence_ends` calls a question? A
     * target without that key asks nothing: every line is of one form, and the wrong options are simply the shuffle.
     */
    private static function asks(SceneMaterial $scene, string $text): bool
    {
        return $scene->target->has('sentence_ends') && (new LanguageWords($scene->target))->terminalKind($text) === 'question';
    }

    /** The unit of a listening question: `L1`, `L2`… */
    public static function questionRef(int $index): string
    {
        return 'L'.($index + 1);
    }

    /**
     * The exchange a question is about — the validator's own reading ({@see ListeningExchange}), off the learner's
     * pack; null when that pack has not the words it needs.
     */
    public static function exchangeStep(SceneMaterial $scene, ListeningQuestion $question): ?int
    {
        return ListeningExchange::inPack($scene->lesson, $question, $scene->native);
    }

    /**
     * Every line of the visit as the listening cards show it — both speakers, in the order of the visit, named as
     * their files are ({@see SpokenLines::dialogue()}: a line without text and a repeated step have none).
     *
     * @return list<array{ref: string, role: string, exchange_step: int, text_target: string, text_native: string, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}}>
     */
    public static function lines(SceneMaterial $scene): array
    {
        $out = [];
        foreach (self::messages($scene) as $line) {
            $out[] = CardObjects::visitLine($line['step'], $line['message']);
        }

        return $out;
    }

    /**
     * The lines of the visit with their refs and steps, in its order — one per file, as the voice has them.
     *
     * @return list<array{ref: string, step: int, message: Message}>
     */
    private static function messages(SceneMaterial $scene): array
    {
        $out = [];
        $seen = [];
        foreach ($scene->lesson->exchanges as $exchange) {
            foreach ($exchange->messages as $message) {
                $ref = CardObjects::lineRef($exchange->step, $message);
                if (trim($message->textTarget) === '' || isset($seen[$ref])) {
                    continue;
                }
                $seen[$ref] = true;
                $out[] = ['ref' => $ref, 'step' => $exchange->step, 'message' => $message];
            }
        }

        return $out;
    }

    /**
     * Where a question's answer was heard: the learner's line and the filler's place in it when the right option IS
     * the value the learner said there (its end mark and case aside), else the partner's line of the exchange.
     *
     * @return array{line_ref: string|null, span: array{0: int, 1: int}|null}
     */
    private static function answerPlace(SceneMaterial $scene, ListeningQuestion $question, ?int $step): array
    {
        if ($step === null) {
            return ['line_ref' => null, 'span' => null];
        }
        $partner = ['line_ref' => SpokenLines::partnerRef($step), 'span' => null];
        $learner = $scene->exchange($step)?->learner();
        $right = $question->correctOption();
        if ($learner === null || $learner->phraseId === null || $right === null) {
            return $partner;
        }
        // The served line carries the filler the server found in its text.
        $filler = $scene->lesson->phrase($learner->phraseId)?->filler($learner->filler);
        if ($filler === null || ! self::sameValue($right, $filler->native)) {
            return $partner;
        }
        $span = Words::spanOfTerm($filler->target, $learner->textTarget);

        return $span === null ? $partner : ['line_ref' => SpokenLines::learnerRef($step), 'span' => [$span[0], $span[0] + $span[1]]];
    }

    private static function sameValue(string $a, string $b): bool
    {
        $normal = static fn (string $s): string => mb_strtolower(FrameText::withoutEndMark($s));

        return $normal($a) === $normal($b);
    }

    /** @return list<string> a question's wrong options, in the order they are served */
    private static function wrongOptions(ListeningQuestion $question): array
    {
        $out = [];
        foreach ($question->optionsNative as $i => $option) {
            if ($i !== $question->correctOptionIndex) {
                $out[] = $option;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $texts
     * @return list<array{text: string}>
     */
    private static function texts(array $texts): array
    {
        $out = [];
        foreach ($texts as $text) {
            $out[] = ['text' => $text];
        }

        return $out;
    }

    /** @param array<string, mixed> $payload */
    private static function day(SceneMaterial $scene, CardKind $kind, array $payload): CardDraft
    {
        return new CardDraft($kind, UnitKind::Day, self::DAY_REF, ['scene_id' => $scene->sceneId->value, ...$payload]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Filler;
use App\Modules\Plan\Domain\Service\Shuffle;
use App\Modules\Plan\Domain\Service\SpeechCoverage;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\Service\Words;
use App\Modules\Plan\Domain\Service\WordUsage;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * THE CARDS OF ONE WORD (наряд SESSION-1a, разд. 1, кадры 31-1…31-7; SESSION-1e): meet it, say it, and the checks —
 * choose its translation or the word, hear it and choose what it means, put a chunk together from its words, find it in
 * the line of the day it is said in.
 *
 * Every card is built from the scene alone: the word, the other words of the same scene as the wrong options, the
 * served lesson for its line. A payload holds no id and no address — every sound is an {@see Audio} stub and every
 * picture `{url: null, tone: null}`, both resolved when the card is read, because the voice and the photo may arrive
 * after the day is dealt. Every shuffle is seeded by the scene and the card's own address, so a day dealt again deals
 * the same card.
 *
 * A check that cannot be made — no line says the word, the day has no other word to offer as a wrong one — is not
 * made (`null`); which check a word gets, and what stands in for one it cannot have, is {@see WordChecks}'.
 */
final readonly class WordCards
{
    /** How many options a choice shows: the right one and three wrong ones. */
    public const OPTIONS = 4;

    /** `word_choose` / `word_listen`: the word (or its sound) is asked, its translation chosen. */
    public const TERM_TO_NATIVE = 'term_to_native';

    /** `word_choose`: the translation is asked, the word chosen. */
    public const NATIVE_TO_TERM = 'native_to_term';

    /** How many words of other terms an assembly mixes into the term's own. */
    public const EXTRA_TILES = 3;

    public const GAP = '___';

    public function __construct(private SpeechCoverage $coverage = new SpeechCoverage) {}

    /** `word_intro` (31-1): the word, the line of the day it is said in, and both sounds. */
    public function intro(SceneMaterial $scene, PlanTerm $term): CardDraft
    {
        $usedIn = $this->usedIn($scene, $term);

        return $this->draft(CardKind::WordIntro, $scene, $term, [
            'term' => CardObjects::term($term),
            'used_in' => $usedIn,
            'audio' => ['term' => Audio::of($term->ref()), 'line' => $usedIn === null ? null : Audio::of($usedIn['line_ref'])],
        ]);
    }

    /** `word_repeat` (31-2): say the word — all of it, when it is two words or fewer (articles aside). */
    public function repeat(SceneMaterial $scene, PlanTerm $term): CardDraft
    {
        return $this->draft(CardKind::WordRepeat, $scene, $term, [
            'term' => CardObjects::term($term),
            'expected_text' => $term->textTarget(),
            'coverage_min' => $this->coverage->minFor($term->textTarget(), $scene->target),
            'audio' => ['term' => Audio::of($term->ref())],
        ]);
    }

    /**
     * `word_choose` (31-3 / 31-4, D-07; SESSION-1e) in the direction it is given — at any level, the day alternates
     * them word by word ({@see WordChecks}). `term_to_native`: the word, its picture and its sound, and the translations
     * to choose among — the other words' own first, the catalogue's after, for a day too small to offer three.
     * `native_to_term`: the translation under the picture, no sound, and the words of the day to choose among, each
     * option with its own sound: the sound is the answer, so it is on the options, never on the question.
     *
     * Null when there is nothing to choose between — fewer than {@see Options::MIN} options.
     *
     * @param  string  $direction  {@see TERM_TO_NATIVE} or {@see NATIVE_TO_TERM}
     * @param  list<string>  $nativeTopUp  catalogue translations, asked for only when the day has fewer than four words
     */
    public function choose(SceneMaterial $scene, PlanTerm $term, string $direction, array $nativeTopUp): ?CardDraft
    {
        $others = $this->others($scene, $term, 'choose');
        $image = ['url' => null, 'tone' => null];

        if ($direction === self::TERM_TO_NATIVE) {
            $chosen = Options::choose($scene->seed($term->ref().':choose'), ['text' => $term->textNative()], self::translations($others, $nativeTopUp), self::OPTIONS);
            if (count($chosen['options']) < Options::MIN) {
                return null;
            }

            return $this->draft(CardKind::WordChoose, $scene, $term, [
                'direction' => self::TERM_TO_NATIVE,
                'prompt' => ['text_target' => $term->textTarget(), 'image' => $image, 'audio' => Audio::of($term->ref())],
                'options' => $chosen['options'],
                'correct' => $chosen['correct'],
            ]);
        }

        $chosen = Options::choose(
            $scene->seed($term->ref().':choose'),
            self::spoken($term),
            array_map(self::spoken(...), $others),
            self::OPTIONS,
        );
        if (count($chosen['options']) < Options::MIN) {
            return null;
        }

        return $this->draft(CardKind::WordChoose, $scene, $term, [
            'direction' => self::NATIVE_TO_TERM,
            'prompt' => ['text_native' => $term->textNative(), 'image' => $image],
            'options' => $chosen['options'],
            'correct' => $chosen['correct'],
        ]);
    }

    /**
     * `word_listen` (31-5, D-08; SESSION-1e): the sound of the word and four translations to choose what it means —
     * sound → meaning. The question is only the sound — no text, no picture — and the options are in the learner's
     * language, silent: this word's translation and the other words' own, the catalogue's after for a day too small to
     * offer three ({@see choose()}'s `term_to_native` options). No word of the target is written on the card. Null when
     * there is nothing to choose between.
     *
     * @param  list<string>  $nativeTopUp
     */
    public function listen(SceneMaterial $scene, PlanTerm $term, array $nativeTopUp): ?CardDraft
    {
        $chosen = Options::choose(
            $scene->seed($term->ref().':listen'),
            ['text' => $term->textNative()],
            self::translations($this->others($scene, $term, 'listen'), $nativeTopUp),
            self::OPTIONS,
        );
        if (count($chosen['options']) < Options::MIN) {
            return null;
        }

        return $this->draft(CardKind::WordListen, $scene, $term, [
            'direction' => self::TERM_TO_NATIVE,
            'audio' => Audio::of($term->ref()),
            'options' => $chosen['options'],
            'correct' => $chosen['correct'],
        ]);
    }

    /**
     * `word_assemble` (31-6, D-09): a term of two words or more put together from its words — the target's articles
     * are no tiles, the learner is not asked to place «a» — mixed with up to three words of the day's other terms that
     * are not the term's own.
     */
    public function assemble(SceneMaterial $scene, PlanTerm $term): CardDraft
    {
        $expected = self::words($term->textTarget(), $scene->target);
        $seen = [];
        foreach ($expected as $word) {
            $seen[LanguagePack::normal($word)] = true;
        }
        $extras = [];
        foreach ($this->others($scene, $term, 'assemble') as $other) {
            foreach (self::words($other->textTarget(), $scene->target) as $word) {
                if (count($extras) >= self::EXTRA_TILES) {
                    break 2;
                }
                $key = LanguagePack::normal($word);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $extras[] = $word;
            }
        }

        return $this->draft(CardKind::WordAssemble, $scene, $term, [
            'term' => CardObjects::term($term),
            'tiles' => Shuffle::seeded($scene->seed($term->ref().':assemble'), [...$expected, ...$extras]),
            'expected' => $expected,
        ]);
    }

    /**
     * `word_in_line` (31-7, D-10; SESSION-1d): a line of the day the word is said in, with a gap where it stands, and
     * the day's words to fill it with. The line is one where the word stands OUTSIDE a frame's window — a partner's line
     * first, then a learner's line whose window says something else — so the gap asks for the word and not for a value
     * a window would take; no such line — the line the word is said in ({@see usedIn()}). The right option is the word
     * as the line says it (a form of it — «hurting» for «hurt»); the wrong ones are the other terms, each with its own
     * sound, but never a filler of the frame the word itself fills — another value of that window would fit the gap
     * too. Under the line its whole translation, the word's translation in it. Null when no line says the word, or the
     * day has no other word to offer.
     */
    public function inLine(SceneMaterial $scene, PlanTerm $term): ?CardDraft
    {
        $usedIn = $this->outsideWindow($scene, $term) ?? $this->usedIn($scene, $term);
        if ($usedIn === null) {
            return null;
        }
        [$start, $end] = $usedIn['term_span'];
        $text = $usedIn['text_target'];
        $windows = self::windowsOf($scene, $term);
        $others = array_values(array_filter(
            $this->others($scene, $term, 'in_line'),
            static fn (PlanTerm $other): bool => ! self::fills($other->textTarget(), $windows),
        ));

        $chosen = Options::choose(
            $scene->seed($term->ref().':in_line'),
            ['text' => mb_substr($text, $start, $end - $start), 'audio' => Audio::of($term->ref())],
            array_map(self::spoken(...), $others),
            self::OPTIONS,
        );
        if (count($chosen['options']) < Options::MIN) {
            return null;
        }

        return $this->draft(CardKind::WordInLine, $scene, $term, [
            'line' => [
                'ref' => $usedIn['ref'],
                'line_ref' => $usedIn['line_ref'],
                'text_target' => mb_substr($text, 0, $start).self::GAP.mb_substr($text, $end),
                'text_native' => $usedIn['text_native'],
                'audio' => Audio::of($usedIn['line_ref']),
            ],
            'options' => $chosen['options'],
            'correct' => $chosen['correct'],
        ]);
    }

    /** Is the term two words or more once the target's articles are set aside — a term to assemble, not to rotate? */
    public function isMultiWord(SceneMaterial $scene, PlanTerm $term): bool
    {
        return count(self::words($term->textTarget(), $scene->target)) >= 2;
    }

    /**
     * The surface words of a text without the articles of the target pack — a pack that names none keeps every word.
     *
     * @return list<string>
     */
    public static function words(string $text, LanguagePack $target): array
    {
        $articles = $target->has('articles');

        return array_values(array_filter(
            Words::surface($text),
            static fn (string $word): bool => ! $articles || ! $target->listed('articles', $word),
        ));
    }

    /**
     * A line where the word stands outside every window (SESSION-1d): a partner's line that says it, in the order of
     * the visit; else a learner's line that says it outside the filler the line is said with (a line on a frame
     * without a window, or on none, has no window at all). Named and placed as {@see usedIn()} names and places a line;
     * null when every line that says the word says it in a window.
     *
     * @return array{ref: string, line_ref: string, text_target: string, text_native: string, term_span: array{0: int, 1: int}}|null
     */
    private function outsideWindow(SceneMaterial $scene, PlanTerm $term): ?array
    {
        $learners = [];
        foreach ($scene->lesson->exchanges as $exchange) {
            foreach ($exchange->messages as $message) {
                $span = Words::spanOfTerm($term->textTarget(), $message->textTarget);
                if ($span === null) {
                    continue;
                }
                $line = [
                    'ref' => $message->isLearner() ? ($message->phraseId ?? 'B'.$exchange->step) : 'A'.$exchange->step,
                    'line_ref' => $message->isLearner() ? SpokenLines::learnerRef($exchange->step) : SpokenLines::partnerRef($exchange->step),
                    'text_target' => $message->textTarget,
                    'text_native' => $message->textNative,
                    'term_span' => [$span[0], $span[0] + $span[1]],
                ];
                if (! $message->isLearner()) {
                    return $line;
                }
                $window = $message->filler === null ? null : Words::spanOfTerm($message->filler, $message->textTarget);
                if ($window === null || $span[0] + $span[1] <= $window[0] || $window[0] + $window[1] <= $span[0]) {
                    $learners[] = $line;
                }
            }
        }

        return $learners[0] ?? null;
    }

    /**
     * The windows the word fills: every frame of the day with a filler that says the word — each as the list of its
     * fillers' texts.
     *
     * @return list<list<string>>
     */
    private static function windowsOf(SceneMaterial $scene, PlanTerm $term): array
    {
        $windows = [];
        foreach ($scene->lesson->phrases as $phrase) {
            $fillers = array_map(static fn (Filler $filler): string => $filler->target, $phrase->fillers());
            if (self::fills($term->textTarget(), [$fillers])) {
                $windows[] = $fillers;
            }
        }

        return $windows;
    }

    /**
     * Does a filler of one of these windows say the text?
     *
     * @param  list<list<string>>  $windows
     */
    private static function fills(string $text, array $windows): bool
    {
        foreach ($windows as $fillers) {
            foreach ($fillers as $filler) {
                if (Words::spanOfTerm($text, $filler) !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Where the day says the word: the line WordUsage finds, named the way the cards name a line — a partner's line
     * `A3` (its file `x3`), a learner's line by the frame it stands on `p3` (or `B3` when it stands on none, its file
     * `x3b`) — and the word's place in it in characters, [start, end).
     *
     * @return array{ref: string, line_ref: string, text_target: string, text_native: string, term_span: array{0: int, 1: int}}|null
     */
    private function usedIn(SceneMaterial $scene, PlanTerm $term): ?array
    {
        $usage = WordUsage::of($scene->lesson, $term->ref(), $term->textTarget());
        if ($usage === null) {
            return null;
        }
        $step = $usage['step'];
        if ($usage['speaker'] === Speaker::Partner) {
            $ref = 'A'.$step;
            $lineRef = SpokenLines::partnerRef($step);
        } else {
            $ref = $this->phraseIdOf($scene, $step, $usage['text']) ?? 'B'.$step;
            $lineRef = SpokenLines::learnerRef($step);
        }

        return [
            'ref' => $ref,
            'line_ref' => $lineRef,
            'text_target' => $usage['text'],
            'text_native' => $usage['translation'],
            'term_span' => [$usage['offset'], $usage['offset'] + $usage['length']],
        ];
    }

    /** The frame the learner's line of that exchange stands on — the line with that text, else the exchange's learner line. */
    private function phraseIdOf(SceneMaterial $scene, int $step, string $text): ?string
    {
        $exchange = $scene->exchange($step);
        if ($exchange === null) {
            return null;
        }
        foreach ($exchange->messages as $message) {
            if ($message->isLearner() && $message->textTarget === $text) {
                return $message->phraseId;
            }
        }

        return $exchange->learner()?->phraseId;
    }

    /**
     * The day's other words and chunks, in an order seeded by this card — so every word does not offer the same wrong
     * ones, and a day dealt again offers the same.
     *
     * @return list<PlanTerm>
     */
    private function others(SceneMaterial $scene, PlanTerm $term, string $card): array
    {
        $others = array_values(array_filter(
            $scene->vocabulary(),
            static fn (PlanTerm $t): bool => $t->ref() !== $term->ref(),
        ));

        return Shuffle::seeded($scene->seed($term->ref().':'.$card.':others'), $others);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function draft(CardKind $kind, SceneMaterial $scene, PlanTerm $term, array $payload): CardDraft
    {
        return new CardDraft($kind, UnitKind::Word, $term->ref(), ['scene_id' => $scene->sceneId->value, ...$payload]);
    }

    /** @return array{text: string, audio: array{ref: string, voice: 'partner'|'learner', url: null, duration_ms: null}} a target term as an option that sounds */
    private static function spoken(PlanTerm $term): array
    {
        return ['text' => $term->textTarget(), 'audio' => Audio::of($term->ref())];
    }

    /**
     * The translations offered against a word's own: the other words' first, in their seeded order, the catalogue's
     * after.
     *
     * @param  list<PlanTerm>  $others
     * @param  list<string>  $nativeTopUp
     * @return list<array{text: string}>
     */
    private static function translations(array $others, array $nativeTopUp): array
    {
        return [
            ...array_map(static fn (PlanTerm $t): array => ['text' => $t->textNative()], $others),
            ...array_map(static fn (string $text): array => ['text' => $text], $nativeTopUp),
        ];
    }
}

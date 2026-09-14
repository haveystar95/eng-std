<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\Service\Shuffle;
use App\Modules\Plan\Domain\Service\Words;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * The payload of every card kind — the whole of what the client needs to show and grade it, with
 * nothing that points back at the model. Tiles and options are shuffled deterministically by the
 * card's own address, so a rebuilt day deals the same card.
 *
 * Two keys every payload carries beside its own: `scene_id` and, for exchange cards,
 * `exchange_step` — the addresses the readers resolve images and audio by at read time.
 */
final class CardPayloads
{
    public const SPEAK_COVERAGE = 0.7;

    public const PHRASE_EXTRA_TILES = 1;

    public const CHOICE_OPTIONS = 4;

    public const ANSWER_OPTIONS = 3;

    public const ANSWER_OVERLAP_MAX = 0.5;

    /** @return array<string, mixed> */
    public static function wordIntro(PlanSceneId $scene, PlanTerm $term): array
    {
        return self::word($scene, $term) + [
            'definition_target' => $term->definitionTarget(),
            'example_target' => $term->exampleTarget(),
            'example_native' => $term->exampleNative(),
        ];
    }

    /** @return array<string, mixed> */
    public static function wordSay(PlanSceneId $scene, PlanTerm $term): array
    {
        return self::word($scene, $term) + ['expected' => $term->textTarget(), 'coverage' => 1.0];
    }

    /**
     * Beginner: the word, choose its translation. Intermediate: the definition, choose the word.
     *
     * @param  list<string>  $others  the other options — translations or words of the day, topped up
     * @return array<string, mixed>
     */
    public static function wordChoose(PlanSceneId $scene, PlanTerm $term, bool $byDefinition, array $others, string $seed): array
    {
        $byDefinition = $byDefinition && $term->definitionTarget() !== null;
        $correct = $byDefinition ? $term->textTarget() : $term->textNative();
        $options = [['text' => $correct, 'correct' => true]];
        foreach (array_slice(self::distinct($others, $correct), 0, self::CHOICE_OPTIONS - 1) as $text) {
            $options[] = ['text' => $text, 'correct' => false];
        }

        return self::word($scene, $term) + [
            'mode' => $byDefinition ? 'definition' : 'translation',
            'prompt' => $byDefinition ? (string) $term->definitionTarget() : $term->textTarget(),
            'options' => Shuffle::seeded($seed, $options),
        ];
    }

    /**
     * The word's example with the word blanked out; the options are words of the day. Null when
     * the term has no example, or the example does not contain it.
     *
     * @param  list<string>  $otherWords
     * @return array<string, mixed>|null
     */
    public static function wordCloze(PlanSceneId $scene, PlanTerm $term, array $otherWords, string $seed): ?array
    {
        $example = $term->exampleTarget();
        if ($example === null) {
            return null;
        }
        $position = Words::positionOfTerm($term->textTarget(), $example);
        if ($position === null) {
            return null;
        }
        [$start, $length] = $position;
        $surface = Words::surface($example);
        $answer = implode(' ', array_slice($surface, $start, $length));
        $gapped = implode(' ', [
            ...array_slice($surface, 0, $start),
            '___',
            ...array_slice($surface, $start + $length),
        ]);

        $options = [['text' => $answer, 'correct' => true]];
        foreach (array_slice(self::distinct($otherWords, $answer), 0, self::CHOICE_OPTIONS - 1) as $text) {
            $options[] = ['text' => $text, 'correct' => false];
        }

        return self::word($scene, $term) + [
            'sentence_target' => $gapped,
            'sentence_native' => $term->exampleNative(),
            'answer' => $answer,
            'options' => Shuffle::seeded($seed, $options),
        ];
    }

    /** @return array<string, mixed> */
    public static function phraseIntro(PlanSceneId $scene, PlanTerm $phrase): array
    {
        return self::phrase($scene, $phrase) + [
            'speaking_key' => $phrase->speakingKey(),
            'example_target' => $phrase->exampleTarget(),
            'example_native' => $phrase->exampleNative(),
        ];
    }

    /** @return array<string, mixed> */
    public static function phraseRepeat(PlanSceneId $scene, PlanTerm $phrase): array
    {
        return self::phrase($scene, $phrase) + ['expected' => $phrase->textTarget(), 'coverage' => 0.9];
    }

    /**
     * @param  list<string>  $dayWords  words of the day to pick the one extra tile from
     * @return array<string, mixed>
     */
    public static function phraseAssemble(PlanSceneId $scene, PlanTerm $phrase, array $dayWords, string $seed): array
    {
        return self::phrase($scene, $phrase) + [
            'prompt_native' => $phrase->textNative(),
            'answer' => $phrase->textTarget(),
            'tiles' => self::tiles($phrase->textTarget(), $dayWords, self::PHRASE_EXTRA_TILES, $seed),
        ];
    }

    /**
     * @param  list<Exchange>  $exchanges
     * @return array<string, mixed>
     */
    public static function dialogueRead(PlanSceneId $scene, array $exchanges, bool $translationsCollapsed): array
    {
        return [
            'scene_id' => $scene->value,
            'translations_collapsed' => $translationsCollapsed,
            'exchanges' => array_map(static fn (Exchange $e): array => [
                'step' => $e->step,
                'initiator' => $e->initiator,
                'messages' => array_map(static fn (Message $m): array => self::message($m), $e->messages),
            ], $exchanges),
        ];
    }

    /**
     * The exchange's check as a card: the question and its three options in the learner's language
     * (Beginner) or the target language, the right one marked.
     *
     * @return array<string, mixed>
     */
    public static function listenQuestion(PlanSceneId $scene, Exchange $exchange, bool $inNative, string $seed): array
    {
        $check = $exchange->check;
        $options = [];
        foreach ($check->options as $index => $option) {
            $options[] = [
                'text' => $inNative ? $option->textNative : $option->textTarget,
                'correct' => $index === $check->correctOptionIndex,
            ];
        }

        return self::exchange($scene, $exchange) + [
            'language' => $inNative ? 'native' : 'target',
            'question' => $inNative ? $check->textNative : $check->textTarget,
            'options' => Shuffle::seeded($seed, $options),
            'explanation_native' => $check->explanationNative,
        ];
    }

    /**
     * @param  list<string>  $dayWords
     * @return array<string, mixed>
     */
    public static function listenAssemble(PlanSceneId $scene, Exchange $exchange, array $dayWords, string $seed): array
    {
        $partner = $exchange->partner();
        $text = $partner->textTarget ?? '';

        return self::exchange($scene, $exchange) + [
            'answer' => $text,
            'text_native' => $partner?->textNative,
            'tiles' => self::tiles($text, $dayWords, self::PHRASE_EXTRA_TILES, $seed),
        ];
    }

    /**
     * The learner's reply among two other replies of the day that share fewer than half their words.
     *
     * @param  list<Message>  $otherReplies  learner lines of the other exchanges, least similar first
     * @return array<string, mixed>
     */
    public static function answerChoose(PlanSceneId $scene, Exchange $exchange, array $otherReplies, string $seed): array
    {
        $own = $exchange->learner();
        $options = [['text_target' => $own->textTarget ?? '', 'text_native' => $own->textNative ?? '', 'correct' => true]];
        foreach (array_slice($otherReplies, 0, self::ANSWER_OPTIONS - 1) as $reply) {
            $options[] = ['text_target' => $reply->textTarget, 'text_native' => $reply->textNative, 'correct' => false];
        }

        return self::exchange($scene, $exchange) + ['options' => Shuffle::seeded($seed, $options)];
    }

    /**
     * @param  list<string>  $dayWords
     * @return array<string, mixed>
     */
    public static function answerAssemble(PlanSceneId $scene, Exchange $exchange, array $dayWords, string $seed): array
    {
        $own = $exchange->learner();
        $text = $own->textTarget ?? '';

        return self::exchange($scene, $exchange) + [
            'prompt_native' => $own?->textNative,
            'answer' => $text,
            'tiles' => self::tiles($text, $dayWords, self::PHRASE_EXTRA_TILES, $seed),
        ];
    }

    /** @return array<string, mixed> */
    public static function speak(PlanSceneId $scene, Exchange $exchange): array
    {
        $own = $exchange->learner();

        return self::exchange($scene, $exchange) + [
            'task_native' => $own?->textNative,
            'expected' => $own?->textTarget,
            'speaking_key' => $own?->speakingKey,
            'variants' => $own->simplifiedVariants ?? [],
            'coverage' => self::SPEAK_COVERAGE,
            'hints' => ['key' => $own?->speakingKey, 'text' => $own?->textTarget],
        ];
    }

    /**
     * The other learner replies of the day, least similar to this exchange's own first.
     *
     * @param  list<Exchange>  $all
     * @return list<Message>
     */
    public static function otherReplies(Exchange $exchange, array $all): array
    {
        $own = $exchange->learner()->textTarget ?? '';
        $scored = [];
        foreach ($all as $other) {
            $reply = $other->learner();
            if ($other->step === $exchange->step || $reply === null) {
                continue;
            }
            $scored[] = ['overlap' => Words::overlap($own, $reply->textTarget), 'step' => $other->step, 'message' => $reply];
        }
        usort($scored, static fn (array $a, array $b): int => [$a['overlap'], $a['step']] <=> [$b['overlap'], $b['step']]);

        $under = array_values(array_filter($scored, static fn (array $s): bool => $s['overlap'] < self::ANSWER_OVERLAP_MAX));
        $pick = count($under) >= self::ANSWER_OPTIONS - 1 ? $under : $scored;

        return array_map(static fn (array $s): Message => $s['message'], $pick);
    }

    /** @return array<string, mixed> */
    private static function word(PlanSceneId $scene, PlanTerm $term): array
    {
        return [
            'scene_id' => $scene->value,
            'plan_term_id' => $term->id()->value,
            'kind' => $term->kind()->value,
            'text_target' => $term->textTarget(),
            'text_native' => $term->textNative(),
            'pronunciation_native' => $term->pronunciationNative(),
        ];
    }

    /** @return array<string, mixed> */
    private static function phrase(PlanSceneId $scene, PlanTerm $phrase): array
    {
        return [
            'scene_id' => $scene->value,
            'plan_term_id' => $phrase->id()->value,
            'text_target' => $phrase->textTarget(),
            'text_native' => $phrase->textNative(),
            'pronunciation_native' => $phrase->pronunciationNative(),
        ];
    }

    /** @return array<string, mixed> */
    private static function exchange(PlanSceneId $scene, Exchange $exchange): array
    {
        $partner = $exchange->partner();

        return [
            'scene_id' => $scene->value,
            'exchange_step' => $exchange->step,
            'initiator' => $exchange->initiator,
            'partner' => $partner === null ? null : self::message($partner),
        ];
    }

    /** @return array<string, mixed> */
    private static function message(Message $m): array
    {
        return [
            'speaker' => $m->speaker,
            'role_target' => $m->roleTarget,
            'role_native' => $m->roleNative,
            'text_target' => $m->textTarget,
            'text_native' => $m->textNative,
            'pronunciation_native' => $m->pronunciationNative,
            'speaking_key' => $m->speakingKey,
        ];
    }

    /**
     * The words of a line, shuffled, plus `$extra` words of the day that are not in it.
     *
     * @param  list<string>  $dayWords
     * @return list<string>
     */
    private static function tiles(string $line, array $dayWords, int $extra, string $seed): array
    {
        $own = Words::surface($line);
        $ownLower = array_map(mb_strtolower(...), $own);
        $decoys = [];
        foreach (Shuffle::seeded($seed.':decoy', $dayWords) as $word) {
            foreach (Words::surface($word) as $piece) {
                if (! in_array(mb_strtolower($piece), $ownLower, true) && ! in_array($piece, $decoys, true)) {
                    $decoys[] = $piece;
                }
            }
            if (count($decoys) >= $extra) {
                break;
            }
        }

        return Shuffle::seeded($seed, [...$own, ...array_slice($decoys, 0, $extra)]);
    }

    /**
     * @param  list<string>  $candidates
     * @return list<string>
     */
    private static function distinct(array $candidates, string $correct): array
    {
        $out = [];
        $seen = [mb_strtolower(trim($correct)) => true];
        foreach ($candidates as $text) {
            $key = mb_strtolower(trim($text));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = trim($text);
        }

        return $out;
    }

    /** The reference of an exchange unit. */
    public static function exchangeRef(int $step): string
    {
        return 'x'.$step;
    }

    public static function stepOfRef(string $ref): ?int
    {
        return preg_match('/^x(\d+)$/', $ref, $m) === 1 ? (int) $m[1] : null;
    }

    public static function kindOfUnit(UnitKind $unit): CardKind
    {
        return CardKind::returnedFor($unit);
    }
}

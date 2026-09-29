<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Lesson;

use App\Modules\Plan\Domain\Service\Shuffle;

/**
 * THE RIGHT ANSWER STANDS WHERE THE SERVER PUTS IT (наряд GEN-4, 3.7): the options of every check and of every listening
 * question are shuffled by the server once the dialogue is written, and `correct_option_index` follows the right one — a model
 * that puts it first nine times out of ten does not teach the learner to tap the first option. The model's index says only
 * WHICH option is right. The shuffle is seeded by the scene and the question's address, so the same dialogue built again for
 * the same scene comes out in the same order; a question whose index names no option is left as it is — the dialogue's check
 * counts it.
 */
final class OptionShuffle
{
    public static function of(Dialogue $dialogue, string $seed): Dialogue
    {
        $exchanges = array_map(
            static fn (DialogueExchange $e): DialogueExchange => $e->withExchange($e->exchange->withCheck(self::check($e->exchange->check, "{$seed}:x{$e->step()}:check"))),
            $dialogue->exchanges,
        );
        $listening = [];
        foreach ($dialogue->listening as $index => $question) {
            $listening[] = self::listening($question, "{$seed}:listening:{$index}");
        }

        return $dialogue->withExchanges($exchanges)->withListening($listening);
    }

    public static function check(ExchangeCheck $check, string $seed): ExchangeCheck
    {
        [$options, $correct] = self::shuffled($check->options, $check->correctOptionIndex, $seed);

        return $check->withOptions($options, $correct);
    }

    public static function listening(ListeningQuestion $question, string $seed): ListeningQuestion
    {
        [$options, $correct] = self::shuffled($question->optionsNative, $question->correctOptionIndex, $seed);

        return $question->withOptions($options, $correct);
    }

    /**
     * @template T
     *
     * @param  list<T>  $options
     * @return array{0: list<T>, 1: int}
     */
    private static function shuffled(array $options, int $correct, string $seed): array
    {
        if (! array_key_exists($correct, $options)) {
            return [$options, $correct];
        }
        $order = Shuffle::seeded($seed, array_keys($options));
        $out = [];
        $moved = $correct;
        foreach ($order as $position => $original) {
            $out[] = $options[$original];
            if ($original === $correct) {
                $moved = $position;
            }
        }

        return [$out, $moved];
    }
}

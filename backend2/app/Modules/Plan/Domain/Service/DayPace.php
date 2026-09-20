<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\ValueObject\CardKind;

/**
 * HOW LONG THE DAY STILL TAKES — the one rule behind every «≈ N мин» of the day window (наряд SESSION-1a, разд. 2).
 *
 * Seconds per card by KIND, not by stage: the stages of the registry mix a ten-second tap with a
 * thirty-second line said aloud, and `listen_dialogue` alone is the whole visit played once — a
 * rate per stage would promise the same minutes for a stage of taps and a stage of speech. The
 * table is the order's starting values, kept in `config/plan.php` (`plan.pace`) and tuned after the
 * phone; a kind the table does not name costs nothing rather than a guess. Minutes round up: a stage
 * with a card left never says «0».
 *
 * ONE KIND IS PRICED PER ROUND, and the card says how many it has: «Скажи целиком»
 * (`phrase_other_slot`) is a series — value rounds and, when it has one, the learner's own word —
 * and the stage's trimming ladder cuts rounds OFF it (наряд FIX-2, доработка). A flat price per
 * card would leave a trimmed card costing exactly what it cost before, and the ladder would cut
 * seconds that never moved. So its table value is the price of ONE round, and {@see seconds()}
 * reads the count off the payload.
 */
final readonly class DayPace
{
    /** @var array<string, int> seconds per card, by kind — the 27 dealt kinds; `phrase_other_slot` is per ROUND */
    public const DEFAULTS = [
        'word_intro' => 8,
        'word_repeat' => 12,
        'word_choose' => 10,
        'word_listen' => 10,
        'word_assemble' => 20,
        'word_in_line' => 10,
        'phrase_intro' => 12,
        'phrase_assemble' => 25,
        'phrase_choose_back' => 12,
        'phrase_slot' => 12,
        'phrase_slot_listen' => 12,
        'phrase_repeat' => 25,
        'phrase_other_slot' => 25,
        'phrase_combine' => 20,
        'dialogue_partner' => 15,
        'dialogue_answer' => 30,
        // The ask says a line AND asks the exchange's check (наряд BACK-TAILS-1 §1.5): the seconds of the two cards
        // it was made of, 30 + 15.
        'dialogue_ask' => 45,
        'dialogue_rescue' => 15,
        'listen_dialogue' => 110,
        'listen_question' => 12,
        'listen_review' => 30,
        'listen_predict' => 15,
        'listen_pace' => 25,
        'listen_number' => 15,
        'speak_answer' => 35,
        'speak_echo' => 25,
        'speak_retell' => 30,
    ];

    /** @param array<string, int> $secondsByKind kind value → seconds per card */
    public function __construct(private array $secondsByKind = self::DEFAULTS) {}

    /**
     * What one card costs. `$payload` is read only by the kind that is priced per round; every other kind ignores it,
     * so a caller that does not have the payload may leave it out.
     *
     * @param  array<string, mixed>  $payload
     */
    public function seconds(CardKind $kind, array $payload = []): int
    {
        $each = max(0, (int) ($this->secondsByKind[$kind->value] ?? 0));

        return $kind === CardKind::PhraseOtherSlot ? $each * self::rounds($payload) : $each;
    }

    /**
     * How many rounds a «Скажи целиком» card has: its value rounds and the own-word one, when it kept it. A payload
     * that names none — a card of an older day — is priced as one round rather than as nothing.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function rounds(array $payload): int
    {
        $values = is_array($payload['rounds'] ?? null) ? count($payload['rounds']) : 0;
        $own = ($payload['own_round'] ?? null) !== null ? 1 : 0;

        return max(1, $values + $own);
    }

    /** @param iterable<DayCard> $cards */
    public function secondsOf(iterable $cards): int
    {
        $seconds = 0;
        foreach ($cards as $card) {
            $seconds += $this->seconds($card->kind(), $card->payload());
        }

        return $seconds;
    }

    public static function minutes(int $seconds): int
    {
        return $seconds <= 0 ? 0 : (int) ceil($seconds / 60);
    }
}

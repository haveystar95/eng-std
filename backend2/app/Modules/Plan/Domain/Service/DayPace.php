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
 * table is kept in `config/plan.php` (`plan.pace`) — measured on the owner's phone (наряд FIX-3 §2: the median seconds
 * of a kind on the live days × 1.3), and every plan keeps its own copy of it (`plans.pace`,
 * {@see \App\Modules\Plan\Application\Service\PlanPaces}); a kind the table does not name costs nothing rather
 * than a guess. Minutes round up: a stage
 * with a card left never says «0».
 *
 * ONE KIND IS PRICED PER ROUND, and the card says how many it has: «Скажи целиком»
 * (`phrase_other_slot`) is a series — value rounds and, when it has one, the learner's own word —
 * and the stage's trimming ladder cuts rounds OFF it (наряд FIX-2, доработка). A flat price per
 * card would leave a trimmed card costing exactly what it cost before, and the ladder would cut
 * seconds that never moved. So its table value is the price of ONE round, and {@see seconds()}
 * reads the count off the payload.
 *
 * ONE KIND IS PRICED PER SCENE: the sheet of «Вспомнить» (`recall_scenes`) is a page per scene of the plan, its lines
 * read through and heard — «a minute of reading and listening» is a minute a SCENE, and a rehearsal over three scenes
 * reads three of them (наряд BACK-TAILS-2 §4: the recall row's own estimate, scenes × their lines, by the price list).
 */
final readonly class DayPace
{
    /** @var array<string, int> seconds per card, by kind — the 28 dealt kinds; `phrase_other_slot` is per ROUND, `recall_scenes` per SCENE */
    public const DEFAULTS = [
        'word_intro' => 5,
        'word_repeat' => 10,
        'word_choose' => 5,
        'word_listen' => 5,
        'word_assemble' => 10,
        'word_in_line' => 10,
        'phrase_intro' => 15,
        'phrase_assemble' => 20,
        'phrase_choose_back' => 10,
        'phrase_slot' => 10,
        'phrase_slot_listen' => 5,
        'phrase_repeat' => 20,
        'phrase_other_slot' => 25,
        'phrase_combine' => 25,
        'dialogue_partner' => 20,
        'dialogue_answer' => 10,
        // The ask says a line AND asks the exchange's check (наряд BACK-TAILS-1 §1.5) — measured as one card.
        'dialogue_ask' => 25,
        'dialogue_rescue' => 10,
        'listen_dialogue' => 70,
        'listen_question' => 10,
        'listen_review' => 10,
        'listen_predict' => 15,
        'listen_pace' => 15,
        'listen_number' => 10,
        'speak_answer' => 25,
        'speak_echo' => 70,
        'speak_retell' => 15,
        'recall_scenes' => 45,
    ];

    /** @param array<string, int> $secondsByKind kind value → seconds per card */
    public function __construct(private array $secondsByKind = self::DEFAULTS) {}

    /**
     * What one card costs. `$payload` is read only by the two kinds priced per part — per round, per scene; every other
     * kind ignores it, so a caller that does not have the payload may leave it out.
     *
     * @param  array<string, mixed>  $payload
     */
    public function seconds(CardKind $kind, array $payload = []): int
    {
        $each = max(0, (int) ($this->secondsByKind[$kind->value] ?? 0));

        return match ($kind) {
            CardKind::PhraseOtherSlot => $each * self::rounds($payload),
            CardKind::RecallScenes => $each * self::scenes($payload),
            default => $each,
        };
    }

    /**
     * How many scenes a «Вспомнить» sheet has. A payload that names none is priced as one scene rather than as nothing.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function scenes(array $payload): int
    {
        return max(1, is_array($payload['scenes'] ?? null) ? count($payload['scenes']) : 0);
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

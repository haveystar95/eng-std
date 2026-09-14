<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Service\Words;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/**
 * «Слушаю и отвечаю»: two cards per exchange.
 *
 * The first is about what the partner said — a question in the learner's language (Beginner); on
 * Intermediate a question in the target language when the partner asked one, «heard it → assemble
 * it» when the partner made a statement of at most ten words, a question otherwise. The second is
 * the learner's own move: choose the reply among three when the partner started, assemble it from
 * tiles when the learner started.
 */
final class ListenStage
{
    public const ASSEMBLE_MAX_WORDS = 10;

    /** @return list<CardDraft> */
    public function build(SceneMaterial $scene, PlanLevel $level): array
    {
        $exchanges = $scene->completeExchanges();
        $dayWords = array_map(static fn (PlanTerm $t): string => $t->textTarget(), $scene->vocabulary());
        $out = [];
        foreach ($exchanges as $exchange) {
            $out[] = $this->partnerCard($scene, $exchange, $level, $dayWords);
            $out[] = $this->reply($scene, $exchange, $exchanges, $dayWords);
        }

        return $out;
    }

    /** @param list<string> $dayWords */
    private function partnerCard(SceneMaterial $scene, Exchange $exchange, PlanLevel $level, array $dayWords): CardDraft
    {
        $ref = CardPayloads::exchangeRef($exchange->step);
        $seed = $scene->sceneId->value.':'.$ref.':listen';
        $partner = $exchange->partner();

        if ($level === PlanLevel::Intermediate
            && $partner !== null
            && ! $partner->isQuestion()
            && Words::count($partner->textTarget) <= self::ASSEMBLE_MAX_WORDS) {
            return new CardDraft(CardKind::ListenAssemble, UnitKind::Exchange, $ref, CardPayloads::listenAssemble($scene->sceneId, $exchange, $dayWords, $seed));
        }

        return new CardDraft(
            CardKind::ListenQuestion,
            UnitKind::Exchange,
            $ref,
            CardPayloads::listenQuestion($scene->sceneId, $exchange, inNative: $level === PlanLevel::Beginner, seed: $seed),
        );
    }

    /**
     * @param  list<Exchange>  $all
     * @param  list<string>  $dayWords
     */
    private function reply(SceneMaterial $scene, Exchange $exchange, array $all, array $dayWords): CardDraft
    {
        $ref = CardPayloads::exchangeRef($exchange->step);
        $seed = $scene->sceneId->value.':'.$ref.':reply';

        if ($exchange->partnerStarts()) {
            return new CardDraft(
                CardKind::AnswerChoose,
                UnitKind::Exchange,
                $ref,
                CardPayloads::answerChoose($scene->sceneId, $exchange, CardPayloads::otherReplies($exchange, $all), $seed),
            );
        }

        return new CardDraft(CardKind::AnswerAssemble, UnitKind::Exchange, $ref, CardPayloads::answerAssemble($scene->sceneId, $exchange, $dayWords, $seed));
    }
}

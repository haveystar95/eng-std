<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Collections\Application\Query\GetCollectionTermSet;
use App\Modules\Collections\Application\Query\GetCollectionTermSetHandler;
use App\Modules\Generation\Application\Dto\EchoExampleRepairReport;
use App\Modules\Generation\Application\Dto\ExampleRegenBrief;
use App\Modules\Generation\Application\Port\ExampleRegeneratorPort;
use App\Modules\Generation\Application\Port\RecordsExampleRegeneration;
use App\Modules\Generation\Application\Service\DraftValidator;
use App\Modules\Generation\Application\Service\ExampleReplacement;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\ModelCost;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\ExampleRegenContextReader;
use Throwable;

/**
 * Gives a real example to every term in a collection whose example teaches nothing.
 *
 * Two shapes of the same defect, and both are QA-7: the example is the TERM REPEATED («Where can I
 * find dog food?» taught by «Where can I find dog food?» — three of ten terms in the acceptance
 * collection), or there is no example at all, which is what an echo looks like after
 * {@see DraftValidator} has refused it at the door. «Is this an echo» is asked with the validator's
 * own predicate, so the pass that rejects and the pass that repairs cannot disagree.
 *
 * Runs off the path the learner is waiting on — chained behind a finished generation, or pointed at
 * an existing collection by hand — because it is one model call per bad example.
 *
 * ON QUOTA, deliberately: this spends money and SAYS SO (every call is written to
 * `example_regenerations`, which is the financial record), and that ledger is also what the daily
 * generation quota counts. So a repair does consume quota. Keeping the spend invisible to keep the
 * quota clean would be the wrong trade — a bill nobody can see is worse than a quota that is a
 * little tight. Unlike the user-facing «New example» action it does not REFUSE at the limit: this
 * is the app repairing its own output, and a term left with no example is a worse outcome than a
 * quota overrun of a few cents.
 */
final readonly class RepairEchoExamplesHandler
{
    /** Native-language fallback when a term has no translation to take the language from. */
    private const DEFAULT_TRANSLATION_LANG = 'ru';

    public function __construct(
        private GetCollectionTermSetHandler $termSet,
        private ExampleRegenContextReader $context,
        private ExampleRegeneratorPort $regenerator,
        private ExampleReplacement $replaceExample,
        private RecordsExampleRegeneration $spend,
        private ModelCost $cost,
        private Clock $clock,
    ) {}

    public function __invoke(RepairEchoExamples $command): EchoExampleRepairReport
    {
        $set = ($this->termSet)(new GetCollectionTermSet($command->collectionId));
        if ($set === null) {
            return new EchoExampleRepairReport(examined: 0, needingRepair: 0);
        }

        /** @var list<array{id: TermId, text: string, lang: string, translationLang: ?string, example: ?string}> $broken */
        $broken = [];
        foreach ($set->termIds as $termIdValue) {
            $termId = TermId::fromString($termIdValue);
            $ctx = $this->context->find($termId);
            if ($ctx === null) {
                continue;
            }
            $example = $ctx->currentExample;
            if ($example !== null && trim($example) !== '' && ! DraftValidator::isEcho($example, $ctx->text)) {
                continue;
            }
            $broken[] = [
                'id' => $termId,
                'text' => $ctx->text,
                'lang' => $ctx->lang,
                'translationLang' => $ctx->translationLang,
                'example' => $example,
            ];
        }

        $examined = count($set->termIds);
        if ($command->dryRun) {
            return new EchoExampleRepairReport(
                examined: $examined,
                needingRepair: count($broken),
                repairedTermIds: array_map(static fn (array $t): string => $t['id']->value, $broken),
            );
        }

        $repaired = [];
        $failures = [];
        $tokensIn = 0;
        $tokensOut = 0;
        $cost = '0.000000';

        foreach ($broken as $term) {
            $now = $this->clock->now();
            $translationLang = $term['translationLang'] ?? self::DEFAULT_TRANSLATION_LANG;
            try {
                $result = $this->regenerator->regenerate(new ExampleRegenBrief(
                    text: $term['text'],
                    termLang: new LanguageCode($term['lang']),
                    translationLang: new LanguageCode($translationLang),
                    // The echo itself is what the model must not return again. Null when the example
                    // was already refused — there is nothing to avoid, only something to write.
                    avoid: $term['example'],
                ));
            } catch (Throwable $e) {
                // One term's failed call must not abandon the rest: the others are still repairable,
                // and a partial repair is strictly better than none. What failed is reported.
                $failures[] = ['term_id' => $term['id']->value, 'error' => $e->getMessage()];

                continue;
            }

            $this->replaceExample->apply(
                $term['id'],
                $result->example,
                $result->exampleTranslation,
                // Asked for and stored under the same language — one variable, no second guess.
                $translationLang,
                $result->promptVersion,
                $result->model,
            );

            $callCost = $this->cost->estimate($result->model, $result->tokensIn, $result->tokensOut);
            $this->spend->record(
                // Whoever owns the collection is who this was spent for — the ledger is per user.
                $command->actorId,
                $term['id'],
                $result->model,
                $result->tokensIn,
                $result->tokensOut,
                $callCost,
                $now,
            );

            $repaired[] = $term['id']->value;
            $tokensIn += $result->tokensIn ?? 0;
            $tokensOut += $result->tokensOut ?? 0;
            $cost = self::addUsd($cost, $callCost);
        }

        return new EchoExampleRepairReport(
            examined: $examined,
            needingRepair: count($broken),
            repairedTermIds: $repaired,
            failures: $failures,
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
            costUsd: $cost,
        );
    }

    /** Money as a decimal string, summed at the same six places the ledger stores. */
    private static function addUsd(string $total, ?string $add): string
    {
        return number_format((float) $total + (float) ($add ?? '0'), 6, '.', '');
    }
}

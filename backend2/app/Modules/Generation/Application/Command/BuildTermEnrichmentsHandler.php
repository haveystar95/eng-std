<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\EnrichmentBrief;
use App\Modules\Generation\Application\Dto\EnrichmentRunMetrics;
use App\Modules\Generation\Application\Port\EnrichmentJournal;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Generation\Application\Port\RecordsTermEnrichment;
use App\Modules\Generation\Domain\Service\EnrichmentValidator;
use App\Modules\Generation\Domain\ValueObject\EnrichmentCandidate;
use App\Modules\Generation\Domain\ValueObject\EnrichmentVerdict;
use App\Modules\Generation\Domain\ValueObject\FindingKind;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\ModelCost;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\ImportTermEnrichment;
use App\Modules\Vocabulary\Application\Command\ImportTermEnrichmentHandler;
use App\Modules\Vocabulary\Application\Dto\AcceptedVariantInput;
use App\Modules\Vocabulary\Application\Dto\EnrichmentTargetView;
use App\Modules\Vocabulary\Application\Dto\ExampleDistractorInput;
use App\Modules\Vocabulary\Application\Query\EnrichmentTargetReader;
use Throwable;

/**
 * One chunk of the станок, term by term: read the content (batched), ask the model once, validate
 * deterministically, write what survived, journal what a human must see, mark the term done.
 *
 * Two deliberate choices about failure:
 *
 *  - the version mark is written per TERM, immediately after that term is stored, not once for the
 *    chunk. A chunk that dies on term 7 of 20 is resumed by re-dispatching it: terms 1–6 are
 *    skipped by `pending()` and never re-paid for;
 *  - a single term that throws (a malformed pack, an unparseable response) is counted and skipped,
 *    NOT propagated. One bad term must not take down the other nineteen. Genuinely transient
 *    failures — the API being down — surface from the packer as exceptions on every term in the
 *    chunk, and `termsFailed == termsSeen` is the signal the caller reports.
 *
 * Skipped is not the same as unseen (MECH-1). The catch keeps the term's id, its text and the
 * exception in {@see EnrichmentRunMetrics::$failures}, and every caller can print
 * {@see EnrichmentRunMetrics::failureSummary()} — «N of M failed». Before that, a chunk where some
 * terms threw was indistinguishable from one where none did: the count went into a metrics row the
 * job never printed, and the exception was discarded unbound. A suite of live paid runs read as
 * healthy for exactly that reason.
 *
 * Cross-module work goes through Vocabulary's Application only; this handler never sees a table.
 */
final readonly class BuildTermEnrichmentsHandler
{
    /**
     * The станок's current generator version — one source of truth for everything that writes or
     * skips by it (the console backfill, the post-generation chain). Bump it when the prompt or the
     * validation rules change in a way that makes a re-run worth paying for; every already-processed
     * term is then pending again at the new version.
     *
     * `enrich-v1` → `enrich-v2` paid for audit A2: under the old ordering the станок reached a
     * repaired term before its example existed, wrote nothing, and marked it done at v1 — four terms
     * in the store are marked done with zero distractors and no way back. They are pending again, and
     * the ordering fix means the next pass finds the example already there.
     *
     * `enrich-v2` → `mech-v12` is the станок itself changing: prompt v12, shape `machinery`, on the
     * cheap model, asking for the two products this app stores and nothing else. The name says which
     * prompt version wrote a row, which is what a `generator_version` column is for.
     *
     * `mech-v12` → `mech-v12.1` is the distractor over-order: v12 asked for the two or three a card
     * holds, the validator scrapped about half of them, and 28 of the 40 pinned examples in the two
     * run-in collections came out unable to host a `pick_correct` at all. v12.1 asks for four or five
     * candidates against the same rules. Worth re-paying for: an example short of options is a rung
     * of the course the learner never reaches.
     *
     * `mech-v12.1` → `mech-v13` is the prompt rewritten around the measured losses instead of the
     * assumed ones — the mechanical span/correction contract first and with a worked example, the
     * essays cut to five counter-rules, `forms` reduced to three lines. The numbers that decided
     * each cut are in the prompt catalogue, beside the version they describe. Worth re-paying for at the same reason as the last
     * bump, and this time the alternative is known: the run this replaces left 4 of 13 terms in a
     * live collection unable to host the card at all.
     *
     * `mech-v13` → `mech-v13.1` is one formatting fix in that prompt's worked example, worth a
     * re-run on its own: the example quoted its field values, the model copied the quotes into them,
     * and 27% of the first v13 run's candidates were discarded as unfindable spans — good sentences,
     * lost to punctuation the prompt taught the model to add.
     *
     * `mech-v13.1` → `mech-v14` adds a THIRD product, near-synonyms of the term. It is the first
     * bump that is not about fixing distractors: `purpose` → `goal`, `aim` is content the app has
     * never had, and it is what three separate mechanics need — a synonym counts as a correct answer
     * on a meaning card, it is a forbidden option beside its own term on a multiple-choice one, and
     * it is the honest content behind the «вариантов 0» the collection screen has been showing since
     * 21.08 (`forms` came back empty on 217 of 217 machinery calls, which for a real term is usually
     * the correct answer — a word has no second spelling, it has other words).
     *
     * `mech-v14` → `mech-v14.1` is the synonym section rewritten after its own pilot returned
     * nothing. Twenty terms of «В банке» on v14 produced 49 written distractors and `synonyms: []`
     * on every card, `debit card` and `credit card` included — the section was buried behind the
     * distractor block and spent its first three sentences discouraging an answer before showing
     * one. That is the `forms` failure this наряд was written to diagnose, reproduced by the section
     * meant to replace it. v14.1 puts synonyms first and leads with worked substitutions.
     *
     * `mech-v14.1` → `mech-v14.2` adds the second acceptance test. v14.1's substitution test bought
     * synonyms (15 on the pilot's 20 terms, up from zero) and let a third of them through wrong: a
     * NARROWER word passes substitution every time, because the example is a sentence about the term
     * and a type of the term fits it. `bank account` → `savings account` is the shape. v14.2 asks
     * the second question the retired `forms` section already knew to ask — cover the target side,
     * read only the translation, would a speaker answer THAT with your word.
     *
     * Worth re-paying for on the whole catalogue, but that is a RUN and a run is the owner's
     * decision — nothing here starts one. Existing terms simply become pending again at the new
     * version, and until someone spends the money the synonym-aware paths are inert rather than
     * wrong: no synonyms means no extra accepted answers and no extra excluded options.
     *
     * `mech-v14.2` → `mech-v14.3` takes the synonym section OUT of the prompt. DG-1 stopped the
     * станок writing synonyms in code — the import below passes `[]` whatever comes back — but the
     * prompt kept asking for them, so every term went on paying for ~600 words of section on the way
     * in and for a list of up to three on the way out, to have it dropped by the next statement. The
     * schema stops declaring the field at this version too, so «not asked» means it in both halves
     * of the contract. Nothing else about the machinery changes.
     *
     * This is the FIRST bump that is not worth re-paying for. Every one above it changed what a term
     * would come back with; this one changes only what the term is asked, and the answer to the
     * removed question was already being thrown away — a v14.3 run produces the same rows a v14.2 run
     * would have produced, for fewer tokens. The bump exists so a row's `generator_version` still
     * names the text that wrote it. Terms marked at v14.2 do become pending again, and whether anyone
     * spends money on that is the owner's decision; nothing here starts a run.
     */
    public const VERSION = 'mech-v14.3';

    public function __construct(
        private EnrichmentTargetReader $targets,
        private EnrichmentPackerPort $packer,
        private EnrichmentValidator $validator,
        private ImportTermEnrichmentHandler $import,
        private EnrichmentJournal $journal,
        private RecordsTermEnrichment $spend,
        private Clock $clock,
        private ModelCost $cost = new ModelCost(),
    ) {}

    public function __invoke(BuildTermEnrichments $command): EnrichmentRunMetrics
    {
        // A top-up hands over terms chosen BY COVERAGE and says so; re-filtering them by the version
        // mark would drop exactly the ones it means to fix. See BuildTermEnrichments::$ignoreVersionMark.
        $pending = $command->ignoreVersionMark
            ? $command->termIds
            : $this->journal->pending($command->termIds, $command->generatorVersion);
        if ($pending === []) {
            return new EnrichmentRunMetrics();
        }

        $targets = $this->targets->byIds(
            array_map(static fn (string $id): TermId => TermId::fromString($id), $pending),
            $command->translationLang,
        );

        $metrics = new EnrichmentRunMetrics();
        foreach ($pending as $termId) {
            $target = $targets[$termId] ?? null;
            if ($target === null) {
                // The term vanished between listing and processing (deleted collection, dedup
                // merge). Nothing to do and nothing to fix — mark it so we stop looking at it.
                $this->journal->markDone($termId, $command->generatorVersion);

                continue;
            }

            try {
                $metrics = $metrics->plus($this->enrichOne($target, $command->generatorVersion));
            } catch (Throwable $e) {
                // Still swallowed, deliberately — one bad term must not take down the other
                // nineteen. What changes is that it stops being SILENT: the term and the reason ride
                // out in the metrics, so the caller can say which word died and of what. Counting
                // alone was not enough (MECH-1) — a run of paid calls read as healthy because the
                // only number that moved was one nobody printed.
                $metrics = $metrics->plus(new EnrichmentRunMetrics(
                    termsSeen: 1,
                    termsFailed: 1,
                    failures: [[
                        'term_id' => $target->termId,
                        'text' => $target->text,
                        // The class as well as the message: an empty message is common enough that
                        // the reason would otherwise read as a blank.
                        'reason' => $e::class . ': ' . $e->getMessage(),
                    ]],
                ));
            }
        }

        return $metrics;
    }

    private function enrichOne(EnrichmentTargetView $target, string $version): EnrichmentRunMetrics
    {
        $pack = $this->packer->pack(new EnrichmentBrief(
            termId: $target->termId,
            text: $target->text,
            acceptedForms: $target->acceptedForms,
            translation: $target->translation,
            exampleSentence: $target->exampleSentence,
            exampleTranslation: $target->exampleTranslation,
            existingSynonyms: $target->existingSynonyms,
            termLang: $target->lang,
            // A term with no translation has no prompt side; ru is the only learner language the
            // content actually has today, and the brief needs *a* value. If a second one ever shows
            // up, it arrives with translations, so this fallback stays unreached.
            translationLang: $target->translationLang ?? 'ru',
        ));

        // Spend is recorded the instant the call answers, BEFORE validation — the same rule
        // GenerationPipeline follows, and for the same reason: a pack the validator throws away cost
        // exactly what a good one costs, and a ledger that only booked successful packs would make
        // the bad runs look free. Without this row the станок spends money nothing accounts for: the
        // admin's per-collection cost reads `term_enrichments`, and it stopped being written when the
        // pack path replaced the one-term path, so every collection has reported станок = 0 since
        // 2026-08-06 while the calls kept happening.
        $this->spend->record(
            TermId::fromString($target->termId),
            $pack->model,
            $pack->tokensIn,
            $pack->tokensOut,
            $this->cost->estimate($pack->model, $pack->tokensIn, $pack->tokensOut),
            $this->clock->now(),
        );

        $verdict = $this->validator->validate(new EnrichmentCandidate(
            termId: $target->termId,
            acceptedForms: $target->acceptedForms,
            exampleId: $target->exampleId,
            exampleSentence: $target->exampleSentence,
            translation: $target->translation,
            exampleTranslation: $target->exampleTranslation,
            distractors: $pack->distractors,
            variants: $pack->variants,
            backTranslation: $pack->backTranslation,
            languageNotes: $pack->languageNotes,
            existingDistractors: $target->existingDistractors,
            backTranslationAsked: $pack->backTranslationAsked,
            synonyms: $pack->synonyms,
            existingSynonyms: $target->existingSynonyms,
            termLang: $target->lang,
        ));

        ($this->import)(new ImportTermEnrichment(
            termId: TermId::fromString($target->termId),
            exampleId: $target->exampleId,
            variants: array_map(
                static fn ($v): AcceptedVariantInput => new AcceptedVariantInput($v->text, $v->note),
                $verdict->variants,
            ),
            distractors: array_map(
                static fn ($d): ExampleDistractorInput => new ExampleDistractorInput(
                    $d->sentence, $d->errorType, $d->errorSpan, $d->correction,
                ),
                $verdict->distractors,
            ),
            generatorVersion: $version,
            // ALWAYS empty, and not because a switch is off: the станок is not a synonym writer any
            // more. Synonyms are a CORE product (prompt v15 onwards) — one producer, one place where
            // the accuracy question is answered — and the enrichment path that also proposed them was
            // the second door onto one table. Three measured prompt iterations (v14 → v14.1 → v14.2)
            // could not get the machinery's accuracy where a written row has to be, so this is a
            // decision rather than a pause, and it lives in the code instead of in a flag.
            //
            // From prompt v14.3 the pack does not even carry a proposal: the section was removed and
            // the schema stopped declaring the field, so the code decision above finally reached the
            // thing being paid for. The validator still JUDGES whatever a pack does propose — an
            // older version replayed, or a bake-off task on one — and the metric below counts that
            // judgement, not a write; which is why it is called `synonymsValidated`.
            // `GENERATION_WRITE_SYNONYMS` is untouched and still governs its two real doors — the
            // core and the lookup.
            synonyms: [],
        ));

        $this->journal->recordFindings($verdict->findings, $version);
        $this->journal->markDone($target->termId, $version);

        return $this->measure($verdict, $target->exampleId !== null && $target->exampleSentence !== null);
    }

    private function measure(EnrichmentVerdict $verdict, bool $hasExample): EnrichmentRunMetrics
    {
        return new EnrichmentRunMetrics(
            termsSeen: 1,
            termsWithExample: $hasExample ? 1 : 0,
            // Counted only where an example exists: a term with no example is not an example short of
            // distractors, it is a term with nothing to build them against.
            examplesUnderTwoDistractors: $hasExample && count($verdict->distractors) < 2 ? 1 : 0,
            distractorsProposed: $verdict->proposedDistractors,
            distractorsRejected: $verdict->rejectedDistractors,
            distractorsWritten: count($verdict->distractors),
            variantsWritten: count($verdict->variants),
            variantsRejected: $verdict->rejectedVariants,
            synonymsValidated: count($verdict->synonyms),
            synonymsRejected: $verdict->rejectedSynonyms,
            termsAmbiguous: $verdict->hasFinding(FindingKind::Ambiguity) ? 1 : 0,
            // "Any language problem" is the union of the three kinds, so a term that only has a
            // nonword still counts once in the headline language rate.
            termsLanguageFlagged: $verdict->hasLanguageFinding() ? 1 : 0,
            termsVariantConflict: $verdict->hasFinding(FindingKind::VariantConflict) ? 1 : 0,
            termsUaLeakage: $verdict->hasFinding(FindingKind::UaLeakage) ? 1 : 0,
            termsMisspelled: $verdict->hasFinding(FindingKind::MisspelledOrNonword) ? 1 : 0,
        );
    }
}

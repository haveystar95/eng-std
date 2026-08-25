<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Generation\Application\Dto\TermEnrichmentBrief;
use App\Modules\Generation\Application\Port\DispatchesTermReading;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Generation\Application\Port\RecordsTermEnrichment;
use App\Modules\Generation\Application\Port\TermEnricherPort;
use App\Modules\Shared\Domain\Service\ModelCost;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Application\Command\AttachTermImage;
use App\Modules\Vocabulary\Application\Command\AttachTermImageHandler;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\ExampleInput;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;
use App\Modules\Vocabulary\Application\Query\EnrichableTermReader;
use App\Modules\Vocabulary\Application\Query\PendingTermImageReader;

/**
 * Enriches a bare user term end to end, all cross-module work through Vocabulary's Application:
 *  1. is the term still eligible (source=user, no translation)? If not, the LLM step is skipped —
 *     that is the idempotency gate, so a job retry never re-spends on the model.
 *  2. call the enricher, merge translation/IPA/example/image-query into the term (ImportTerm merges
 *     into the existing term by dedup), and record the spend.
 *  3. ask for the term's reading hint, if the pair needs one and it has none — a separate job, so
 *     this is a dispatch and not a call, and the card never waits on it;
 *  4. attach a Pexels photo for the term's image query — this runs whether the term was just
 *     enriched or a prior run already enriched it but the photo is still pending, so a transient
 *     image failure (which throws → the job retries) resumes only the photo, never the paid LLM call.
 */
final readonly class EnrichTermHandler
{
    public function __construct(
        private EnrichableTermReader $enrichable,
        private TermEnricherPort $enricher,
        private ImportTermHandler $importTerm,
        private RecordsTermEnrichment $spend,
        private ModelCost $cost,
        private PendingTermImageReader $pendingImages,
        private ImageSearchPort $images,
        private AttachTermImageHandler $attachImage,
        private Clock $clock,
        private DispatchesTermReading $reading,
    ) {}

    public function __invoke(EnrichTerm $command): void
    {
        $view = $this->enrichable->find($command->termId);
        if ($view !== null) {
            $result = $this->enricher->enrich(new TermEnrichmentBrief(
                text: $view->text,
                type: $view->type,
                termLang: new LanguageCode($view->lang),
                translationLang: $command->translationLang,
            ));

            ($this->importTerm)(new ImportTerm(
                lang: new LanguageCode($view->lang),
                text: $view->text,
                type: $view->type,
                pos: null,
                source: 'user',
                translations: [new TranslationInput($command->translationLang, $result->translation, isPrimary: true)],
                ipa: $result->transcription,
                // The gloss is in the language the enricher was BRIEFED in, the same one the
                // translation above carries.
                examples: $result->example !== null
                    ? [new ExampleInput($result->example, $result->exampleTranslation, $command->translationLang)]
                    : [],
                imageApiPrompt: $result->imageApiPrompt,
                // «Учить это слово» is a model call like any other, and its translation and example
                // are model output — they were being written with no stamp at all (audit A4), which
                // is precisely the state the NULL sentinel exists to catch.
                promptVersion: $result->promptVersion,
                generationModel: $result->model,
            ));

            $this->spend->record(
                $command->termId,
                $result->model,
                $result->tokensIn,
                $result->tokensOut,
                $this->cost->estimate($result->model, $result->tokensIn, $result->tokensOut),
                $this->clock->now(),
            );
        }

        // The READING, before the photo rather than after it: the image search can throw (that is
        // what the retry below is for), and a word must not lose its reading hint because a stock
        // photo vendor was down. Asked whether or not the LLM step above ran — a retry that skipped
        // the paid enrichment is still a card that may have no hint.
        $this->reading->writeReading($command->termId, $command->translationLang->value);

        // Best-effort photo. The reader returns the term only while it lacks an image but carries a
        // query, so this is a no-op once imaged; a transient search error throws and the job retries.
        foreach ($this->pendingImages->pendingFor([$command->termId->value]) as $pending) {
            $found = $this->images->search($pending->query);
            if ($found !== null) {
                ($this->attachImage)(new AttachTermImage(
                    $command->termId,
                    $found->url,
                    $found->author,
                    $found->authorUrl,
                ));
            }
        }
    }
}

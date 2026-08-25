<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Provider;

use App\Modules\Vocabulary\Application\Port\AuthoredTermAnonymizer;
use App\Modules\Vocabulary\Application\Port\TermEnrichmentWriter;
use App\Modules\Vocabulary\Application\Port\TermReviewWriter;
use App\Modules\Vocabulary\Application\Port\TermCoreWriter;
use App\Modules\Vocabulary\Application\Port\TermExampleWriter;
use App\Modules\Vocabulary\Application\Query\StaleCoreReader;
use App\Modules\Vocabulary\Application\Query\DistractorReader;
use App\Modules\Vocabulary\Application\Query\EnrichableTermReader;
use App\Modules\Vocabulary\Application\Query\TermReadingTargetReader;
use App\Modules\Vocabulary\Application\Query\DistractorAuditReader;
use App\Modules\Vocabulary\Application\Query\EnrichmentTargetReader;
use App\Modules\Vocabulary\Application\Query\ExampleRegenContextReader;
use App\Modules\Vocabulary\Application\Query\TermEnrichmentExportReader;
use App\Modules\Vocabulary\Application\Query\PendingTermImageReader;
use App\Modules\Vocabulary\Application\Query\TermAnswerKeyReader;
use App\Modules\Vocabulary\Application\Query\TermChangeReader;
use App\Modules\Vocabulary\Application\Query\TermContentReader;
use App\Modules\Vocabulary\Application\Query\TermLanguageAuditReader;
use App\Modules\Vocabulary\Application\Query\TranslationKeyReader;
use App\Modules\Vocabulary\Application\Query\TermDifficultyReader;
use App\Modules\Vocabulary\Application\Query\TermExistenceReader;
use App\Modules\Vocabulary\Application\Query\TermLanguageReader;
use App\Modules\Vocabulary\Application\Port\TermCurator;
use App\Modules\Vocabulary\Application\Port\TranslationLabelWriter;
use App\Modules\Vocabulary\Domain\Repository\TermRepository;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentAuthoredTermAnonymizer;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentDistractorReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentEnrichableTermReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermReadingTargetReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentDistractorAuditReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentEnrichmentTargetReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentExampleRegenContextReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentPendingTermImageReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermEnrichmentExportReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermEnrichmentWriter;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermReviewWriter;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentStaleCoreReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermCoreWriter;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermExampleWriter;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermAnswerKeyReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermChangeReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermContentReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermDifficultyReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermExistenceReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermLanguageReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermCurator;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTranslationLabelWriter;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermLanguageAuditReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTranslationKeyReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermRepository;
use App\Modules\Vocabulary\Application\Port\TermDescriptionWriter;
use App\Modules\Vocabulary\Application\Port\TermTransliterationWriter;
use App\Modules\Vocabulary\Application\Query\ExactTermTranslationReader;
use App\Modules\Vocabulary\Application\Query\TermSearchReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermDescriptionWriter;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermTransliterationWriter;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentExactTermTranslationReader;
use App\Modules\Vocabulary\Infrastructure\Eloquent\EloquentTermSearchReader;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class VocabularyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TermRepository::class, EloquentTermRepository::class);
        $this->app->bind(TermCurator::class, EloquentTermCurator::class);
        $this->app->bind(TranslationLabelWriter::class, EloquentTranslationLabelWriter::class);
        $this->app->bind(TermExistenceReader::class, EloquentTermExistenceReader::class);
        // Collections asks this before putting a term in a folder — the pair invariant
        // (DECISIONS п. 141) needs the term's own language, and only Vocabulary has it.
        $this->app->bind(TermLanguageReader::class, EloquentTermLanguageReader::class);
        $this->app->bind(TermContentReader::class, EloquentTermContentReader::class);
        $this->app->bind(TermChangeReader::class, EloquentTermChangeReader::class);
        // Search: the free half — exact and prefix matches over terms we already have.
        $this->app->bind(TermSearchReader::class, EloquentTermSearchReader::class);
        // «Do we already know this exact word» — the free first rung of the instant hint.
        $this->app->bind(ExactTermTranslationReader::class, EloquentExactTermTranslationReader::class);
        // A term's description («what this word means», in the language being learned).
        $this->app->bind(TermDescriptionWriter::class, EloquentTermDescriptionWriter::class);
        $this->app->bind(TermTransliterationWriter::class, EloquentTermTransliterationWriter::class);
        // …and the gate in front of it: does this (term, support language) still need one at all.
        // Asked BEFORE the model call, which is the whole point — the writer would refuse the second
        // write, but by then the call is bought.
        $this->app->bind(TermReadingTargetReader::class, EloquentTermReadingTargetReader::class);
        // The language audit: every learner-language string a user can actually reach.
        $this->app->bind(TermLanguageAuditReader::class, EloquentTermLanguageAuditReader::class);
        $this->app->bind(TranslationKeyReader::class, EloquentTranslationKeyReader::class);
        $this->app->bind(TermDifficultyReader::class, EloquentTermDifficultyReader::class);
        $this->app->bind(TermAnswerKeyReader::class, EloquentTermAnswerKeyReader::class);
        $this->app->bind(DistractorReader::class, EloquentDistractorReader::class);
        $this->app->bind(PendingTermImageReader::class, EloquentPendingTermImageReader::class);
        $this->app->bind(AuthoredTermAnonymizer::class, EloquentAuthoredTermAnonymizer::class);
        $this->app->bind(EnrichableTermReader::class, EloquentEnrichableTermReader::class);
        $this->app->bind(ExampleRegenContextReader::class, EloquentExampleRegenContextReader::class);
        $this->app->bind(TermExampleWriter::class, EloquentTermExampleWriter::class);
        // The one writer allowed to REPLACE a core, and the reader that finds the cores worth
        // replacing — both exist for the showcase regeneration and have no other caller.
        $this->app->bind(TermCoreWriter::class, EloquentTermCoreWriter::class);
        $this->app->bind(StaleCoreReader::class, EloquentStaleCoreReader::class);
        // Enrichment станок: Vocabulary owns the two content tables; Generation fills them.
        $this->app->bind(DistractorAuditReader::class, EloquentDistractorAuditReader::class);
        $this->app->bind(EnrichmentTargetReader::class, EloquentEnrichmentTargetReader::class);
        $this->app->bind(TermEnrichmentWriter::class, EloquentTermEnrichmentWriter::class);
        $this->app->bind(TermEnrichmentExportReader::class, EloquentTermEnrichmentExportReader::class);
        // The other direction: a human removing a bad row or correcting a wording.
        $this->app->bind(TermReviewWriter::class, EloquentTermReviewWriter::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Migration');

        $routes = __DIR__ . '/../../Presentation/Http/routes.php';
        if (is_file($routes)) {
            Route::middleware('api')->prefix('api/v1')->group($routes);
        }
    }
}

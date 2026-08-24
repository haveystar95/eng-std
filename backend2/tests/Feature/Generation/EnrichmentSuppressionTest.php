<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\AuditDistractors;
use App\Modules\Generation\Application\Command\AuditDistractorsHandler;
use App\Modules\Generation\Application\Command\BuildTermEnrichments;
use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Generation\Application\Dto\EnrichmentBrief;
use App\Modules\Generation\Application\Dto\EnrichmentPack;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Generation\Domain\ValueObject\RawDistractor;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Port\TermReviewWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Live case A.1 (docs/store5-after-topup-review.md, section A, item 1 — applied in 01a4380): the
 * architect judged this sentence correct English offered as the "wrong" answer, i.e. the card had two
 * right answers. Real term, real deleted sentence — only the surrounding example is synthetic.
 */
const A1_TERM = 'debit card';

const A1_EXAMPLE = 'I prefer to use my debit card for everyday shopping.';

const A1_REMOVED_SENTENCE = 'I prefer to use my debit card for everyday purchases.';

function seedA1Target(): string
{
    $termId = Ulid::generate();
    $exampleId = Ulid::generate();

    DB::table('terms')->insert([
        'id' => $termId, 'text' => A1_TERM, 'normalized_text' => A1_TERM,
        'lang' => 'en', 'type' => 'phrase', 'source' => 'ai',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(), 'term_id' => $termId, 'lang' => 'ru',
        'text' => 'дебетовая карта', 'is_primary' => true,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    seedExample([
        'id' => $exampleId, 'term_id' => $termId, 'sentence' => A1_EXAMPLE,
        'translation' => 'Я плачу дебетовой картой за продукты.', 'source' => 'ai',
    ]);
    DB::table('example_distractors')->insert([
        'id' => Ulid::generate(), 'example_id' => $exampleId, 'sentence' => A1_REMOVED_SENTENCE,
        'error_type' => 'false_friend', 'error_span' => 'purchases', 'correction' => 'shopping',
        'generator_version' => 'enrich-v1', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $termId;
}

function packerReturning(EnrichmentPack $pack): EnrichmentPackerPort
{
    return new class($pack) implements EnrichmentPackerPort
    {
        public function __construct(private readonly EnrichmentPack $pack) {}

        public function pack(EnrichmentBrief $brief): EnrichmentPack
        {
            return $this->pack;
        }
    };
}

it('suppresses a review-removed distractor so a later топап does not resurrect it', function () {
    $termId = seedA1Target();

    $hit = app(TermReviewWriter::class)->removeDistractor($termId, A1_REMOVED_SENTENCE, false, 'review');

    expect($hit)->toBe(1)
        ->and(DB::table('example_distractors')->where('sentence', A1_REMOVED_SENTENCE)->exists())->toBeFalse();

    $suppression = DB::table('enrichment_suppressions')->where('term_id', $termId)->first();
    expect($suppression)->not->toBeNull()
        ->and($suppression?->source)->toBe('review')
        // canonicalize(): lowercased, punctuation stripped — the same comparison the validator's
        // dedup runs candidates through, so a re-proposed sentence still matches this row.
        ->and($suppression?->sentence)->toBe('i prefer to use my debit card for everyday purchases');

    // The топап re-asks the model. It proposes the SAME sentence back (this is exactly what happened
    // live: a deleted distractor came back after a --topup run) alongside a genuinely NEW, valid one.
    // Both are well-formed distractors on their own merits — verified separately, without suppression
    // in play, that the validator keeps BOTH (each repairs to the pinned example). So if suppression
    // were not wired in here, the resurrected sentence would be written again.
    $pack = new EnrichmentPack(
        distractors: [
            new RawDistractor(A1_REMOVED_SENTENCE, 'false_friend', 'purchases', 'shopping'),
            new RawDistractor('I prefers to use my debit card for everyday shopping.', 'tense', 'prefers', 'prefer'),
        ],
        variants: [],
        backTranslation: A1_TERM,
        languageNotes: [],
        model: 'test',
        tokensIn: 10,
        tokensOut: 20,
    );
    app()->instance(EnrichmentPackerPort::class, packerReturning($pack));

    app(BuildTermEnrichmentsHandler::class)(new BuildTermEnrichments([$termId], 'enrich-topup'));

    $stored = DB::table('example_distractors')->pluck('sentence');
    expect($stored)->not->toContain(A1_REMOVED_SENTENCE)
        ->and($stored)->toContain('I prefers to use my debit card for everyday shopping.')
        ->and($stored)->toHaveCount(1);
});

it('suppresses an audit-deleted distractor tagged as audit, not review', function () {
    $termId = seedA1Target();
    // A row equal to its own example — the audit's simplest deletion case (see AuditDistractorsTest).
    DB::table('example_distractors')->insert([
        'id' => Ulid::generate(),
        'example_id' => DB::table('term_examples')->where('term_id', $termId)->value('id'),
        'sentence' => A1_EXAMPLE, 'error_type' => 'article', 'error_span' => 'a', 'correction' => 'the',
        'generator_version' => 'enrich-v1', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $outcome = app(AuditDistractorsHandler::class)(new AuditDistractors(apply: true));

    expect($outcome->deleted['equality'] ?? 0)->toBe(1);

    $suppression = DB::table('enrichment_suppressions')
        ->where('term_id', $termId)
        ->where('sentence', 'i prefer to use my debit card for everyday shopping')
        ->first();
    expect($suppression)->not->toBeNull()
        ->and($suppression?->source)->toBe('audit');
});

it('re-removing an already-suppressed sentence is a no-op, not a duplicate row', function () {
    $termId = seedA1Target();

    app(TermReviewWriter::class)->removeDistractor($termId, A1_REMOVED_SENTENCE, false, 'review');
    $second = app(TermReviewWriter::class)->removeDistractor($termId, A1_REMOVED_SENTENCE, false, 'review');

    expect($second)->toBe(0)
        ->and(DB::table('enrichment_suppressions')->where('term_id', $termId)->count())->toBe(1);
});

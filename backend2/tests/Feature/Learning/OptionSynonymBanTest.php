<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Query\DistractorReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * SYN-1 Ч.2 п. 3 — a near-synonym of the term is a SECOND CORRECT ANSWER on its own card.
 *
 * Shown «цель» with `purpose` as the key and `goal` among the options, a learner who taps `goal`
 * is right and is marked wrong. The translation-overlap rule (QA-17) does not catch this: two
 * synonyms are easily glossed «цель» and «задача» and read there as two different meanings. The ban
 * is therefore stated on the synonym table, which is the only place that knows the two words are
 * the same answer — and it reads BOTH directions, because one run of the станок over either word is
 * enough to establish the pair.
 */
function seedBanTerm(string $text, string $translation): string
{
    $id = Ulid::generate();
    DB::table('terms')->insert([
        'id' => $id, 'lang' => 'en', 'text' => $text, 'normalized_text' => mb_strtolower($text),
        'type' => 'word', 'source' => 'ai', 'cefr' => 'A2', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(), 'term_id' => $id, 'lang' => 'ru', 'text' => $translation,
        'is_primary' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $id;
}

function seedBanSynonym(string $termId, string $text): void
{
    DB::table('term_synonyms')->insert([
        'id' => (string) Ulid::generate(), 'term_id' => $termId, 'text' => $text, 'lang' => 'en',
        'source' => 'auto', 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** @return list<string> */
function optionsFor(string $targetId): array
{
    $pool = [$targetId, ...DB::table('terms')->where('id', '<>', $targetId)->pluck('id')->all()];

    return app(DistractorReader::class)->forTarget(TermId::fromString($targetId), array_map('strval', $pool), 3);
}

it('never offers a synonym of the term as a wrong answer', function () {
    // Two different Russian glosses on purpose: the translation-overlap rule cannot see this pair.
    $target = seedBanTerm('purpose', 'цель');
    seedBanTerm('goal', 'задача');
    seedBanTerm('boarding pass', 'посадочный талон');
    seedBanSynonym($target, 'goal');

    expect(optionsFor($target))->not->toContain('goal');
});

it('bans it in the OTHER direction too, where only the neighbour was enriched', function () {
    $target = seedBanTerm('purpose', 'цель');
    $goal = seedBanTerm('goal', 'задача');
    seedBanTerm('boarding pass', 'посадочный талон');
    // The станок ran over `goal` and nobody has run it over `purpose` yet.
    seedBanSynonym($goal, 'purpose');

    expect(optionsFor($target))->not->toContain('goal');
});

it('leaves an ordinary distractor alone', function () {
    $target = seedBanTerm('purpose', 'цель');
    seedBanTerm('goal', 'задача');
    seedBanTerm('boarding pass', 'посадочный талон');
    seedBanSynonym($target, 'goal');

    expect(optionsFor($target))->toContain('boarding pass');
});

it('changes nothing for a term with no synonyms', function () {
    $target = seedBanTerm('purpose', 'цель');
    seedBanTerm('goal', 'задача');
    seedBanTerm('boarding pass', 'посадочный талон');

    // Until the станок has run, the pair is unknown and both are ordinary options. That is the
    // honest state, not a defect: the ban can only act on data that exists.
    expect(optionsFor($target))->toContain('goal');
});


/**
 * The Ч.5 control, on the pairs a REAL run produced.
 *
 * These five came out of the SYN-1 pilots over «В банке» (docs/syn-1-findings.md §7). Three of them
 * are pairs the pilot got WRONG — `savings account` is a type of `bank account`, not a synonym —
 * and they are used here anyway on purpose: the ban is about what the data SAYS, and a bad synonym
 * row is exactly the row that would otherwise put a second correct-looking answer on the card. The
 * question this test asks is not «is the synonym good» but «does a synonym ever reach the options».
 */
dataset('pilot synonym pairs', [
    'bank account → savings account' => ['bank account', 'банковский счёт', 'savings account', 'сберегательный счёт'],
    'credit card → charge card' => ['credit card', 'кредитная карта', 'charge card', 'платёжная карта'],
    'debit card → bank card' => ['debit card', 'дебетовая карта', 'bank card', 'банковская карта'],
    'financial advisor → financial consultant' => ['financial advisor', 'финансовый консультант', 'financial consultant', 'консультант по финансам'],
    'interest rate → rate of interest' => ['interest rate', 'процентная ставка', 'rate of interest', 'ставка процента'],
]);

it('keeps the synonym out of the options', function (string $term, string $gloss, string $synonym, string $synGloss) {
    $target = seedBanTerm($term, $gloss);
    seedBanTerm($synonym, $synGloss);
    // Three ordinary neighbours, so the card has somewhere else to draw from and the absence of the
    // synonym is a choice rather than an empty pool.
    seedBanTerm('boarding pass', 'посадочный талон');
    seedBanTerm('luggage tag', 'багажная бирка');
    seedBanTerm('window seat', 'место у окна');
    seedBanSynonym($target, $synonym);

    $options = optionsFor($target);

    expect($options)->not->toContain($synonym)
        // …and the card is still playable: the ban removed an option, it did not empty the card.
        ->and($options)->not->toBeEmpty();
})->with('pilot synonym pairs');

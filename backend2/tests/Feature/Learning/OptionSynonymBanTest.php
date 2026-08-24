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

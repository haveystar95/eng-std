<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Port\NativeDistractorSource;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * THE WORDS' CHECKS OF A DAY THE SERVER DEALS (наряд SESSION-1e, разд. 1–2): the translations a choice among translations
 * is made of — `word_choose` asked `term_to_native` and `word_listen` — come from the day's words and, when the day has
 * fewer than four, from the catalogue: at any level, since the direction is no longer the level's.
 */

// Canon (SESSION-1e, разд. 2): «варианты — 4 перевода на родном, добор из NativeDistractorSource как у word_choose»;
// разд. 1: «направление у ВСЕХ уровней». Catches the catalogue asked for a Beginner's day only — an Intermediate's
// `word_listen` on a small day left with two options.
//
// WHY THE DAY IS BUILT THIS WAY (наряд BACK-TAILS-1 §2.2). The checks of a day are one circle of four kinds from a
// start the scene's id picks ({@see App\Modules\Plan\Domain\Assembly\WordChecks}), and a day of two words gets two
// of the four. Written against the fake lesson's own words, this test failed about one run in four — the two words
// drawing `word_in_line` and `word_assemble`, and no choice among translations being dealt at all. That was the test
// asking for something the rule does not promise, not a defect: so the day here is made of two SINGLE words the visit
// never says, which can be neither assembled nor found in a line. The circle is then «выбор → на слух» and both words
// get a check among translations, whatever the scene's id. Since GEN-4 a word of the day stands in the skeleton
// (`vocab.not_found` is fatal): the two words are fillers of `p6` the dialogue does not pick; and a day of two words is
// under VOCABULARY_COUNT (`vocab.count`, fatal) — the level's range is let down to two for this day alone.
it('tops up an Intermediate small day\'s choices among translations from the catalogue', function () {
    $catalogue = new class implements NativeDistractorSource
    {
        /** @var list<string> */
        public array $asked = [];

        public function translations(LanguageCode $targetLang, LanguageCode $nativeLang, string $like, array $exclude, int $count): array
        {
            $this->asked[] = $like;

            return array_slice(['кашель', 'сыпь', 'насморк'], 0, $count);
        }
    };
    app()->instance(NativeDistractorSource::class, $catalogue);
    config(['plan.counts.intermediate.vocabulary' => [2, 12]]);
    app()->instance(PlanModelPort::class, new FakePlanModel(lesson: static function ($request): array {
        $payload = FakePlanModel::lessonPayload($request);
        $payload['phrases'][5]['slot']['fillers'][2] = ['target' => 'an ointment', 'native' => 'купить мазь', 'pronunciation_native' => 'эн ойнтмент', 'in_dialogue' => false];
        $payload['phrases'][5]['slot']['fillers'][3] = ['target' => 'a crutch', 'native' => 'взять костыль', 'pronunciation_native' => 'э крач', 'in_dialogue' => false];
        $payload['vocabulary'] = [
            ['id' => 'v1', 'term_target' => 'ointment', 'translation_native' => 'мазь', 'pronunciation_native' => 'ойнтмент',
                'definition_target' => 'a soft substance rubbed on the skin', 'kind' => 'word', 'image_prompt' => null, 'used_in' => ['p6']],
            ['id' => 'v2', 'term_target' => 'crutch', 'translation_native' => 'костыль', 'pronunciation_native' => 'крач',
                'definition_target' => 'a stick to lean on while walking', 'kind' => 'word', 'image_prompt' => null, 'used_in' => ['p6']],
        ];

        return $payload;
    }));
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 1, 'level' => 'intermediate'])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    $cards = planOpenDay($this, $token, $id, 1)['cards'];
    $kinds = array_count_values(array_column(array_values(array_filter(
        $cards,
        static fn (array $c): bool => in_array($c['kind'], ['word_choose', 'word_listen', 'word_in_line', 'word_assemble'], true),
    )), 'kind'));
    $translated = array_values(array_filter(
        $cards,
        static fn (array $c): bool => in_array($c['kind'], ['word_choose', 'word_listen'], true) && $c['payload']['direction'] === 'term_to_native',
    ));

    // Neither word can be assembled or found in a line, so the circle is the two kinds that choose among translations.
    expect(array_keys($kinds))->toEqualCanonicalizing(['word_choose', 'word_listen'])
        ->and($catalogue->asked)->not->toBe([])
        // `word_listen` is always asked `term_to_native`, so there is always at least one of them.
        ->and($translated)->not->toBe([]);
    foreach ($translated as $card) {
        expect($card['payload']['options'])->toHaveCount(4, $card['kind'])
            ->and(array_values(array_intersect(array_column($card['payload']['options'], 'text'), ['кашель', 'сыпь', 'насморк'])))->toHaveCount(2, $card['kind']);
    }
});

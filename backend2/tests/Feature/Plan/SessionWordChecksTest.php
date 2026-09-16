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

// Canon (SESSION-1e, разд. 2): «варианты — 4 перевода на родном, добор из NativeDistractorSource как у word_choose»; разд. 1:
// «направление у ВСЕХ уровней». Catches the catalogue asked for a Beginner's day only — an Intermediate's word_listen on a
// small day left with two options.
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
    app()->instance(PlanModelPort::class, new FakePlanModel(lesson: static function ($request): array {
        $payload = FakePlanModel::lessonPayload($request);
        $payload['vocabulary'] = array_slice($payload['vocabulary'], 1, 2);

        return $payload;
    }));
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 1, 'level' => 'intermediate'])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    $cards = planOpenDay($this, $token, $id, 1)['cards'];
    $translated = array_values(array_filter(
        $cards,
        static fn (array $c): bool => in_array($c['kind'], ['word_choose', 'word_listen'], true) && $c['payload']['direction'] === 'term_to_native',
    ));

    expect($catalogue->asked)->not->toBe([])
        ->and($translated)->not->toBe([]);
    foreach ($translated as $card) {
        expect($card['payload']['options'])->toHaveCount(4, $card['kind'])
            ->and(array_values(array_intersect(array_column($card['payload']['options'], 'text'), ['кашель', 'сыпь', 'насморк'])))->toHaveCount(2, $card['kind']);
    }
});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * АРХИВ ПЛАНА — наряд SCENE-RUN, Ч.5.1 и Ч.5.3, замки на ДЕЙСТВУЮЩИЙ инвариант.
 *
 * Ничего нового здесь не вводится: правило принято владельцем 01.09 и стоит в коде с PLAN-FIX-3/4
 * (`PlanTermArchiver`, DECISIONS п. 214). Замки нужны потому, что наряд SCENE-RUN открывает
 * архивному плану ВТОРУЮ дверь — «Повторить сцену», — и дверь, открытая рядом с инвариантом, это
 * ровно то место, где инвариант ломают, не заметив.
 *
 * Два утверждения, и они не одно и то же:
 *
 *   1. Завершение и отмена НЕ выпускают слова плана в общий словарь. Архив — целиком, и в блокнот
 *      «Учить» его слова попадают только триажем руками (канон §1).
 *   2. Слово, которое жило в словаре ДО плана, там и остаётся — со своим прогрессом. План его
 *      одолжил, а не забрал, и «архив целиком» не значит «унеси чужое».
 */
beforeEach(fn () => fakePlanModel());

it('не выпускает слова плана в общий словарь, когда план завершён', function () {
    [$user, $token, $planId] = startedPlan($this);

    $planTerms = DB::table('learning_plan_days as d')
        ->join('collection_items as ci', 'ci.collection_id', '=', 'd.collection_id')
        ->where('d.plan_id', $planId)
        ->whereNull('ci.deleted_at')
        ->pluck('ci.term_id')->unique()->values()->all();
    expect($planTerms)->not->toBeEmpty();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/complete")->assertOk();

    // Ни одна пара плана не осталась в пуле: `enrolled_at` снят со всех.
    expect(
        DB::table('user_term_progress')
            ->where('user_id', $user->id)
            ->whereIn('term_id', $planTerms)
            ->whereNotNull('enrolled_at')
            ->count()
    )->toBe(0);

    // …и обычная сессия их не раздаёт. Не «отфильтровывает» — не видит.
    $session = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['limit' => 50])->assertOk()->json('data');
    foreach ($session['cards'] as $card) {
        expect($planTerms)->not->toContain($card['term_id']);
    }
});

it('оставляет в словаре слово, которое жило там ДО плана', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    // Слово словаря, с историей: план его не вводил и уносить не вправе.
    $mine = seedWordFor($user, 'appointment', 'приём');
    answerTimes($this, $token, $mine, 'appointment', 3);
    $progressBefore = DB::table('user_term_progress')
        ->where('user_id', $user->id)->where('term_id', $mine)->first();
    expect($progressBefore?->enrolled_at)->not->toBeNull();

    $planId = startedPlanFor($this, $token);
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/abandon")->assertOk();

    $after = DB::table('user_term_progress')
        ->where('user_id', $user->id)->where('term_id', $mine)->first();

    expect($after?->enrolled_at)->not->toBeNull()
        // …и с ТЕМ ЖЕ прогрессом: план его одолжил, а не переучил.
        ->and($after?->successful_reviews)->toBe($progressBefore?->successful_reviews)
        ->and($after?->state)->toBe($progressBefore?->state);
});

it('не пускает карточки архивного плана в обычную сессию и в «Повторить»', function () {
    // Ч.5.3: у карточек архивного плана ровно одна дверь — «Повторить сцену». Ни одна другая
    // поверхность их не показывает, и это проверяется по ДВУМ поверхностям сразу: сессии и
    // счётчику «Повторить N» на Главной, потому что они читают разными путями.
    [$user, $token, $planId] = startedPlan($this);

    $planTerms = DB::table('learning_plan_days as d')
        ->join('collection_items as ci', 'ci.collection_id', '=', 'd.collection_id')
        ->where('d.plan_id', $planId)
        ->whereNull('ci.deleted_at')
        ->pluck('ci.term_id')->unique()->values()->all();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/abandon")->assertOk();

    // Сдвинуть историю назад, чтобы всё, что МОГЛО бы стать просроченным, стало им.
    ageHistory($user->id, days: 30);

    $session = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['limit' => 50])->assertOk()->json('data');
    foreach ($session['cards'] as $card) {
        expect($planTerms)->not->toContain($card['term_id']);
    }

    $home = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/home-plan')->assertOk()->json('data');
    expect((int) ($home['session']['repeat'] ?? 0))->toBe(0);
});

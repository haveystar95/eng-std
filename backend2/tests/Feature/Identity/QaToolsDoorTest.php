<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * QA-ИНСТРУМЕНТЫ — ТА ЖЕ ДВЕРЬ, ЧТО У ВХОДА БЕЗ ПАРОЛЯ (наряд SCENE-RUN, Ч.2.9).
 *
 * Подстановка транскрипта в прогоне сцены — инструмент, которого у человека быть не должно: он
 * позволяет засчитать ход, не открывая рта. Поэтому у него не своя проверка, а ровно та, что уже
 * стережёт вход без пароля: аккаунт помечен `is_qa` И среда не production при включённом флаге.
 *
 * Своя проверка была бы вторым правилом про то же самое — а правило, записанное дважды, однажды
 * расходится, и расходится оно в ту сторону, где дверь открыта.
 */
it('opens QA tools only for a QA account behind an open door', function () {
    config()->set('qa.dev_login', true);
    [$user, $token] = learner();
    $user->forceFill(['is_qa' => true])->save();

    expect($this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')->assertOk()->json('data.qa_tools'))->toBeTrue();
});

it('refuses them to an ordinary account, door or no door', function () {
    config()->set('qa.dev_login', true);
    [, $token] = learner();

    expect($this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')->assertOk()->json('data.qa_tools'))->toBeFalse();
});

it('refuses them when the door itself is shut', function () {
    // Флаг выключен — и пометка аккаунта ничего не значит. Замка два, и оба обязательны.
    config()->set('qa.dev_login', false);
    [$user, $token] = learner();
    $user->forceFill(['is_qa' => true])->save();

    expect($this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')->assertOk()->json('data.qa_tools'))->toBeFalse();
});

<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\SpeechNormalization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * ОДНА ТАБЛИЦА И ОДНИ ПОРОГИ НА ДВЕ СТОРОНЫ — наряд SPEECH-2, Ч.3.3 / Ч.4.2.
 */

// ПРАВИЛО: Ч.4.2 — второго словаря нормализации на клиенте нет; он читает серверный.
// ЛОВИТ: пейлоад без блока `speech`. Клиент, которому таблицу не прислали, обязан завести свою —
// и в тот же день телефон начнёт судить не тем, чем судит сервер. Первым признаком расхождения
// будет «Не то» над ответом, который журнал засчитал верным (ровно поломка DAY-GATE-1).
it('sends the thresholds and the abbreviation table with a study session', function () {
    [$user, $token] = learner();
    [$collectionId] = seedCollectionWith($user, 'bank account', 'банковский счёт');

    $body = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'practice' => true])
        ->assertOk()
        ->json('data');

    expect($body)->toHaveKey('speech')
        ->and($body['speech']['normalization']['version'])->toBe(SpeechNormalization::VERSION)
        // Три формы, которые на устройстве и подняли этот наряд.
        ->and($body['speech']['normalization']['entries'])->toHaveKeys(['sequel', 'a p i', 'h r'])
        ->and($body['speech']['normalization']['entries']['sequel'])->toBe('sql')
        ->and($body['speech']['thresholds'])->toHaveKeys([
            'read_aloud_coverage', 'recall_rest_coverage', 'whole_line_coverage', 'almost_floor', 'filler_allowance',
        ])
        // Числа с провода — те же, что судит грейдер: один источник, `config/learning.php`.
        ->and($body['speech']['thresholds']['read_aloud_coverage'])
        ->toBe((float) config('learning.plan.speech.read_aloud_coverage'));
});

// ПРАВИЛО: Ч.4.3 — поле произношения аббревиатур есть в контракте карточки и пусто.
// ЛОВИТ: поле, забытое до наряда по парам. Клиент, который его уже читает, начнёт произносить
// аббревиатуры правильно в день, когда генерация их напишет, — а не выкатом позже.
it('carries an empty say_as on every card', function () {
    [$user, $token] = learner();
    [$collectionId] = seedCollectionWith($user, 'bank account', 'банковский счёт');

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'practice' => true])
        ->assertOk()
        ->json('data.cards');

    expect($cards)->not->toBeEmpty();
    foreach ($cards as $card) {
        expect($card)->toHaveKey('say_as')
            ->and($card['say_as'])->toBeNull();
    }
});

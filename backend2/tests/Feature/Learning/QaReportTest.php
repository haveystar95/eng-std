<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * «ЖАЛОБА» ОДНИМ ТАПОМ — наряд DAY-GATE-1, Ч.0.5.
 *
 * Слепок момента с телефона на диск сервера: снимок экрана, идентификаторы плана и посадки, журнал
 * микрофона, версии обеих сторон. Дверь — та же, что у сдвига часов плана.
 */
beforeEach(function (): void {
    // Своя папка на тест: отчёты — это файлы, и тест, пишущий в общую, оставляет мусор в проекте.
    $this->qaReportDir = storage_path('framework/testing/qa-reports-' . uniqid());
    app()->bind(
        App\Modules\Learning\Application\Port\QaReportStore::class,
        fn ($app) => new App\Modules\Learning\Infrastructure\Qa\FileQaReportStore(
            $app->make(App\Modules\Shared\Domain\Service\Clock::class),
            $this->qaReportDir,
        ),
    );
});

afterEach(function (): void {
    foreach (glob($this->qaReportDir . '/*') ?: [] as $file) {
        unlink($file);
    }
    if (is_dir($this->qaReportDir)) {
        rmdir($this->qaReportDir);
    }
});

// ПРАВИЛО: наряд Ч.0.5 — дверь та же, что у остальных QA-инструментов, и решает её СЕРВЕР.
// ЛОВИТ: эндпоинт, принимающий снимки ЧУЖИХ экранов от кого угодно. Отчёт содержит всё, что было
// видно на экране человека, — открытая дверь сюда это утечка, а не удобство.
it('is a 404 for an ordinary account — the door is the server`s, not the client`s', function () {
    config()->set('qa.dev_login', true);
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/v1/qa/report', ['report' => '{}'])
        ->assertNotFound();

    expect(glob($this->qaReportDir . '/*') ?: [])->toBe([]);
});

// ПРАВИЛО: наряд Ч.0.5 — «файл `<дата>-<ulid>.json` + png», и никуда наружу.
// ЛОВИТ: отчёт, потерявший половину себя. Разбор поломки идёт по ЭТОМУ файлу: не доехали
// идентификаторы — не сходить в базу; не доехал журнал микрофона — не назвать причину; не доехал
// снимок — не увидеть, что было на экране.
it('writes the snapshot and its screenshot side by side', function () {
    config()->set('qa.dev_login', true);
    [$user, $token] = learner();
    $user->forceFill(['is_qa' => true])->save();
    app('auth')->forgetGuards();

    $report = [
        'client' => ['build_sha' => 'abc1234', 'build_at' => '2026-09-07 21:55'],
        'server' => ['commit' => 'def5678'],
        'context' => ['screen' => 'session', 'card' => 2, 'stage' => 'words'],
        'speech' => ['phase' => 'listening', 'log' => ['21:55:01.001  opening']],
    ];

    $id = $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/v1/qa/report', [
            'report' => json_encode($report, JSON_THROW_ON_ERROR),
            // Настоящий PNG в один пиксель, а не `fake()->image()`: у контейнера нет GD, а
            // валидация всё равно смотрит на содержимое.
            'screenshot' => UploadedFile::fake()->createWithContent(
                'screen.png',
                (string) base64_decode(
                    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
                    true,
                ),
            ),
        ])
        ->assertCreated()
        ->json('data.id');

    $jsons = glob($this->qaReportDir . '/*.json') ?: [];
    $pngs = glob($this->qaReportDir . '/*.png') ?: [];
    expect($jsons)->toHaveCount(1)->and($pngs)->toHaveCount(1);

    $written = json_decode((string) file_get_contents($jsons[0]), true);
    expect($written['id'])->toBe($id)
        // Кто прислал — от сервера, а не из тела: тело пишет клиент.
        ->and($written['user_id'])->toBe($user->id)
        ->and($written['context']['screen'])->toBe('session')
        ->and($written['context']['stage'])->toBe('words')
        ->and($written['speech']['log'])->toBe(['21:55:01.001  opening'])
        // Опись НАЗЫВАЕТ снимок: опись, ссылающаяся на файл, которого нет, хуже отсутствующей.
        ->and($written['screenshot'])->toBe(basename($pngs[0]));
});

// ПРАВИЛО: наряд Ч.0.5 — «жалоба» нажимается там, где что-то не так, и не имеет права отказать.
// ЛОВИТ: 422 из-за отсутствующего снимка. Снимок — единственная часть отчёта, которая может не
// получиться на устройстве, и терять из-за неё описание состояния значит терять весь смысл.
it('takes a report with no screenshot at all', function () {
    config()->set('qa.dev_login', true);
    [$user, $token] = learner();
    $user->forceFill(['is_qa' => true])->save();
    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->post('/api/v1/qa/report', ['report' => json_encode(['note' => 'микрофон молчит'], JSON_THROW_ON_ERROR)])
        ->assertCreated();

    $jsons = glob($this->qaReportDir . '/*.json') ?: [];
    expect($jsons)->toHaveCount(1)
        ->and(glob($this->qaReportDir . '/*.png') ?: [])->toBe([]);

    $written = json_decode((string) file_get_contents($jsons[0]), true);
    expect($written['screenshot'])->toBeNull()->and($written['note'])->toBe('микрофон молчит');
});

// ПРАВИЛО: наряд Ч.0.4 — «/health отдаёт commit бэкенда».
// ЛОВИТ: строку версии, которая молчит про сервер. Весь смысл этой пары в том, чтобы за полсекунды
// закрыть вопрос «ту ли сборку мы смотрим»; половина ответа не закрывает его вовсе.
it('names the build that is answering, without a token', function () {
    config()->set('app.commit', 'def5678');

    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.commit', 'def5678');
});

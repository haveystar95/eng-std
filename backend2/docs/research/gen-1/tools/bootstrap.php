<?php

declare(strict_types=1);

/**
 * GEN-1 — загрузчик приложения для скриптов прогона (`run-plan.php`, `dump-plan.php`).
 *
 * Запускается ТОЛЬКО внутри контейнера `app` и ТОЛЬКО с переопределением окружения:
 *
 *   docker compose exec -T \
 *     -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync \
 *     -e SPEECH_ENABLED=false -e CACHE_STORE=array \
 *     app php docs/research/gen-1/tools/run-plan.php …
 *
 * Переменные окружения контейнера перебивают `.env` (Dotenv immutable), поэтому боевая база и
 * воркер Horizon не затронуты: всё, что делает скрипт, живёт в процессе этого вызова.
 *
 * Отказ без переопределения базы — намеренно: скрипт пишет пользователей, планы и платные строки
 * реестра, и делать это на `wordtrainer` наряд запрещает.
 */

use App\Modules\Generation\Application\Port\DispatchesExampleRepair;
use App\Modules\Generation\Application\Port\DispatchesImageAttachment;
use App\Modules\Generation\Application\Port\DispatchesLineSpeech;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Console\Kernel;

require '/app/vendor/autoload.php';

if (getenv('DB_DATABASE') !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "GEN-1: DB_DATABASE must be wordtrainer_e2e_test (got '" . (string) getenv('DB_DATABASE') . "'). Refusing.\n");
    exit(2);
}
if (getenv('QUEUE_CONNECTION') !== 'sync') {
    fwrite(STDERR, "GEN-1: QUEUE_CONNECTION must be sync so the day is written in this process. Refusing.\n");
    exit(2);
}

/** @var \Illuminate\Foundation\Application $app */
$app = require '/app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// ПОБОЧНЫЕ РАБОТЫ ДНЯ — ВЫКЛЮЧЕНЫ. Станок (MECH, gpt-4o-mini), картинки (Pexels) и озвучка не
// входят в предмет наряда и не входят в его кап: день уже пригоден до них. Заглушки, а не тумблеры,
// потому что у станка и картинок тумблера нет, а с `QUEUE_CONNECTION=sync` они выполнились бы прямо
// здесь и заплатили бы за то, что никто не просил.
$app->instance(DispatchesExampleRepair::class, new class implements DispatchesExampleRepair
{
    public function repairThenEnrich(CollectionId $collectionId, UserId $ownerId, string $generatorVersion): void {}
});
$app->instance(DispatchesImageAttachment::class, new class implements DispatchesImageAttachment
{
    public function dispatch(CollectionId $collectionId): void {}
});
$app->instance(DispatchesLineSpeech::class, new class implements DispatchesLineSpeech
{
    public function dispatch(CollectionId $collectionId): void {}
});

return $app;

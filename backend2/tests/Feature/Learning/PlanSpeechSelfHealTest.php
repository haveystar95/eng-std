<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Port\OrdersLineSpeech;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * «ГОТОВИМ ОЗВУЧКУ» — СОСТОЯНИЕ НА СЕКУНДЫ, А НЕ НАВСЕГДА (канон §7, доп. к наряду DAY-2-FIX).
 *
 * Живьём реплика роли висела в этом состоянии бессрочно. Причина структурная: озвучка заказывалась
 * РОВНО В ОДНОМ месте — при генерации дня, — поэтому день, написанный до того, как труба появилась,
 * не получал файлов никогда и никто их больше не просил. Экран честно ждал того, чего не заказывали.
 *
 * Теперь недостачу видит посадка: она ищет адреса файлов ровно перед тем, как человек эти реплики
 * услышит, и это единственное место, где дыра заметна раньше, чем её увидит человек.
 */
beforeEach(function (): void {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

/** Записывает заказы озвучки вместо того, чтобы ставить джобу. */
final class RecordingSpeechOrders implements OrdersLineSpeech
{
    /** @var list<string> */
    public array $ordered = [];

    public function order(array $collectionIds): void
    {
        foreach ($collectionIds as $collectionId) {
            $this->ordered[] = $collectionId->value;
        }
    }
}

it('заказывает озвучку реплик, у которых файла нет', function () {
    $orders = new RecordingSpeechOrders;
    app()->instance(OrdersLineSpeech::class, $orders);

    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    // Второй день несёт разговор сцены 1; файлов у неё нет ни у одной реплики — в тестах труба
    // выключена, и станок озвучки не ходил никуда.
    $session = planSession($this, $token, $planId);
    expect($session['dialogues'])->not->toBeEmpty()
        ->and($session['line_audio'])->toBe([]);

    // Коллекция ДНЯ СЦЕНЫ, а не сегодняшнего: молчит реплика первого дня, и покупать надо её.
    $sceneCollection = DB::table('learning_plan_days')
        ->where('plan_id', $planId)
        ->where('day_index', 1)
        ->value('collection_id');

    expect($orders->ordered)->toContain((string) $sceneCollection);
});

it('ничего не заказывает, когда все реплики разговора уже озвучены', function () {
    $orders = new RecordingSpeechOrders;
    app()->instance(OrdersLineSpeech::class, $orders);

    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);
    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    // Озвучить всё, что посадка станет играть файлом, ТЕМ ЖЕ голосом, которым её ищет индекс.
    $voice = app(\App\Modules\Shared\Domain\Service\VoiceCatalog::class)->forLanguage('en');
    expect($voice)->not->toBeNull();

    $lines = DB::table('learning_plan_days as d')
        ->join('collection_items as ci', 'ci.collection_id', '=', 'd.collection_id')
        ->join('terms as t', 't.id', '=', 'ci.term_id')
        ->where('d.plan_id', $planId)
        ->whereIn('t.shelf', ['hear', 'rescue'])
        ->whereNull('ci.deleted_at')
        ->pluck('t.id');

    foreach ($lines as $termId) {
        DB::table('term_audios')->insert([
            'id' => strtoupper(\Illuminate\Support\Str::ulid()->toBase32()),
            'term_id' => $termId,
            'voice' => $voice->key(),
            'variant' => $voice->variant(),
            'provider' => 'test',
            'format' => 'mp3',
            'path' => "line-audio/test/{$termId}.mp3",
            'bytes' => 1024,
            'created_at' => now(),
        ]);
    }

    $orders->ordered = [];
    $session = planSession($this, $token, $planId);

    expect($session['line_audio'])->not->toBeEmpty()
        ->and($orders->ordered)->toBe([]);
});

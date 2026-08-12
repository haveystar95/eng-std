<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\ValueObject\RejectedItem;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentGenerationRejectionJournal;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function openRequestRow(): string
{
    $userId = Ulid::generate();
    DB::table('users')->insert([
        'id' => $userId,
        'email' => 'rejection-' . $userId . '@example.test',
        'name' => 'Rejection Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $requestId = Ulid::generate();
    DB::table('generation_requests')->insert([
        'id' => $requestId,
        'user_id' => $userId,
        'prompt' => 'фразовые глаголы',
        'normalized_prompt' => 'фразовые глаголы',
        'source_lang' => 'ru',
        'target_lang' => 'en',
        'levels' => json_encode(['B1']),
        'size' => 12,
        'prompt_version' => 'v6',
        'status' => 'succeeded',
        'created_at' => now(),
    ]);

    return $requestId;
}

it('persists what the barrier refused, verbatim', function () {
    $requestId = openRequestRow();

    (new EloquentGenerationRejectionJournal())->record($requestId, [
        new RejectedItem('on the same page', 'translation', 'Поле translation должно быть на «ru» (і).', 2),
    ]);

    $row = DB::table('generation_rejections')->where('request_id', $requestId)->first();

    expect($row)->not->toBeNull()
        // The TEXT, not a term id: the whole point of a barrier is that no term was created.
        ->and($row?->text)->toBe('on the same page')
        ->and($row?->field)->toBe('translation')
        ->and((int) $row?->attempts)->toBe(2)
        ->and($row?->reason)->toContain('і');
});

it('writes nothing for a clean run', function () {
    $requestId = openRequestRow();

    (new EloquentGenerationRejectionJournal())->record($requestId, []);

    expect(DB::table('generation_rejections')->where('request_id', $requestId)->count())->toBe(0);
});

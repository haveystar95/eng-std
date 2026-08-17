<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('reports nothing when no verification checks are resolved', function () {
    Artisan::call('learning:verification-stats');

    expect(Artisan::output())->toContain('No "known" verification checks resolved yet.');
});

it('reports the three outcome shares and warns on a small sample', function () {
    [$user] = learner();
    $term = seedWordFor($user, 'apple', 'яблоко');

    foreach (['again', 'hard', 'good'] as $grade) {
        DB::table('reviews')->insert([
            'id' => Ulid::generate(), 'user_id' => $user->id, 'term_id' => $term,
            'grade' => $grade, 'exercise_mode' => 'typing',
            'is_correct' => $grade !== 'again', 'is_practice' => false, 'is_verification' => true,
            'answered_at' => now(), 'created_at' => now(),
        ]);
    }

    Artisan::call('learning:verification-stats');
    $output = Artisan::output();

    expect($output)
        ->toContain('again 1')
        ->toContain('hard 1')
        ->toContain('good+easy 1')     // three buckets, not two
        ->toContain('this is noise');  // absolute count below 200 → warn, don't tune
});

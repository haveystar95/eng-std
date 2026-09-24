<?php

/**
 * FIX-4 — ПРИЁМКА В: the ADM-1 check «звук ≠ текст» (`sound_text_mismatch`, DECISIONS п. 403) over EVERY plan of the live
 * base, by the deployed code — the same section the admin's plan page reads (`PlanInspection::section(…, 'issues')`), with
 * the check's own counts; and the plans whose served answers could not be read (`not_checked`), so a 0 is a 0 of what was
 * looked at. No model, no voice: the check reads the answers the phone gets and the voicing journal.
 *
 * Run (after the deploy, on the live base): docker exec -w /app wt_app php docs/research/fix-4/tools/sound-text.php
 */

declare(strict_types=1);

use App\Modules\Plan\Application\Inspection\PlanInspection;
use App\Modules\Plan\Domain\Inspection\Check\SoundTextMismatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Http::preventStrayRequests();

$inspection = app(PlanInspection::class);
$plans = DB::table('plans')->orderBy('created_at')->get(['id', 'status']);
$total = 0;
$looked = 0;
$unavailable = [];
foreach ($plans as $plan) {
    $issues = $inspection->section((string) $plan->id, 'issues', null);
    if ($issues === null) {
        continue;
    }
    $looked++;
    $count = 0;
    foreach ($issues['checks'] as $check) {
        if ($check['code'] === SoundTextMismatch::CODE) {
            $count = (int) $check['count'];
        }
    }
    $total += $count;
    $skipped = array_values(array_filter($issues['not_checked'], static fn (string $n): bool => str_starts_with($n, 'звук ≠ текст по ответу клиенту')));
    if ($skipped !== []) {
        $unavailable[] = substr((string) $plan->id, 4, 6).' ('.$plan->status.'): '.$skipped[0];
    }
    printf("%s · %-9s · звук ≠ текст: %d\n", substr((string) $plan->id, 4, 6), $plan->status, $count);
}
printf("\nпланов: %d · проверено: %d · находок «звук ≠ текст»: %d\n", count($plans), $looked, $total);
if ($unavailable !== []) {
    echo "не проверено по ответу клиенту:\n  ".implode("\n  ", $unavailable)."\n";
}

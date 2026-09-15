<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

/**
 * IS THIS A DATABASE VOICED ONLY BY NAME? (TTS-2, the architect's decision.) On a database listed in
 * `generation.speech.named_plans_only_databases` — the e2e stand, for good — a new day is not voiced on its own and
 * `plan:speak-backfill` buys nothing without `--plan`: the voice there is bought only for the plans named.
 */
final class VoiceDatabase
{
    public static function namedPlansOnly(): bool
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        return in_array($database, array_map('strval', (array) config('generation.speech.named_plans_only_databases', [])), true);
    }
}

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * «ВЕРНО С ПЕРВОГО РАЗА» И «САМОЕ ТРУДНОЕ» УХОДЯТ (DAY-UI-2). Их читал только старый кабинет дня —
     * подвал с процентами, который кадр 23-0c вычел; окно дня их не показывает, и считать их на каждом
     * ответе было бы работой без читателя. Оба числа выводятся из `day_cards` (append-only ответы),
     * поэтому потерянного нет.
     *
     * DROP COLUMN в Postgres — только метаданные, без переписывания таблицы. `down()` возвращает
     * колонки пустыми: значения выводимы из карточек, но пересчитывать их некому.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE plan_days DROP COLUMN IF EXISTS first_try_share, DROP COLUMN IF EXISTS hardest_unit_kind, DROP COLUMN IF EXISTS hardest_unit_ref, DROP COLUMN IF EXISTS hardest_unit_text');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE plan_days ADD COLUMN first_try_share numeric(4,2) NULL, ADD COLUMN hardest_unit_kind varchar(16) NULL, ADD COLUMN hardest_unit_ref varchar(16) NULL, ADD COLUMN hardest_unit_text text NULL');
    }
};

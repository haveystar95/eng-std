<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

final class EloquentTermProgressRepository implements TermProgressRepository
{
    private const TABLE = 'user_term_progress';

    public function __construct(private readonly TermProgressMapper $mapper) {}

    public function findForUpdate(UserId $userId, TermId $termId): ?TermProgress
    {
        $row = DB::table(self::TABLE)
            ->where('user_id', $userId->value)
            ->where('term_id', $termId->value)
            ->lockForUpdate()
            ->first();

        return $row !== null ? $this->mapper->toEntity((array) $row) : null;
    }

    public function save(TermProgress $progress): void
    {
        $now = now();
        $key = ['user_id' => $progress->userId()->value, 'term_id' => $progress->termId()->value];
        $values = [...$this->mapper->toColumns($progress), 'updated_at' => $now];

        $table = DB::table(self::TABLE)->where($key);
        if ($table->exists()) {
            $table->update($values);

            return;
        }

        DB::table(self::TABLE)->insert([...$key, ...$values, 'created_at' => $now]);
    }
}

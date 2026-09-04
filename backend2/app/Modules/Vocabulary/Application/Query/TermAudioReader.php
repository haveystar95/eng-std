<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Shared\Domain\ValueObject\LineVoice;
use App\Modules\Vocabulary\Application\Dto\TermAudioRow;

/**
 * КАКИЕ РЕПЛИКИ УЖЕ ЗВУЧАТ этим голосом.
 *
 * Три вопроса, три метода, и каждый задаёт свой читатель:
 *  - сборка посадки спрашивает {@see forTerms()} — «дай URL-ы, что есть» (пусто = «нет», не ошибка);
 *  - станок озвучки спрашивает {@see missingFor()} — «что ещё не куплено», и это то место, где
 *    держится идемпотентность: реплика, у которой строка есть, второй раз не покупается;
 *  - раздача файла спрашивает {@see byId()}.
 */
interface TermAudioReader
{
    /**
     * @param  list<string>  $termIds
     * @return array<string, TermAudioRow>  keyed by term id; terms with no audio are absent
     */
    public function forTerms(array $termIds, LineVoice $voice): array;

    /**
     * @param  list<string>  $termIds
     * @return list<string>  the ids that have no audio for this voice and variant yet
     */
    public function missingFor(array $termIds, LineVoice $voice): array;

    public function byId(string $audioId): ?TermAudioRow;
}

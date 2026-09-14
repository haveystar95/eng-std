<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Shared\Domain\ValueObject\LineVoice;
use InvalidArgumentException;

/**
 * «СКАЖИ ЭТИ СТРОКИ ОДНИМ ЗАХОДОМ» (DAY-UI-3) — разговор двумя голосами или пачка строк одним голосом.
 *
 * Сценарий, а не реплика, потому что вендор берёт за ЗАПРОС и режет по запросам в сутки: весь диалог
 * дня — один вызов, все фразы — один, все слова — один. Порядок строк — порядок звука; адаптер
 * возвращает звук каждой строки в том же порядке.
 */
final readonly class SpeechScript
{
    /**
     * @param  list<SpeechTurn>  $turns
     * @param  array<string, LineVoice>  $voices  speaker key → voice; one or two speakers
     */
    public function __construct(
        public string $lang,
        public array $turns,
        public array $voices,
    ) {
        if ($turns === []) {
            throw new InvalidArgumentException('a script needs at least one line');
        }
        if ($voices === [] || count($voices) > 2) {
            throw new InvalidArgumentException('a script is spoken by one or two voices');
        }
        foreach ($turns as $turn) {
            if (! isset($voices[$turn->speaker])) {
                throw new InvalidArgumentException("no voice for speaker «{$turn->speaker}»");
            }
            if (trim($turn->text) === '') {
                throw new InvalidArgumentException('a line of a script is empty');
            }
        }
    }

    /** One speaker — a batch read aloud line by line, not a conversation. */
    public function isBatch(): bool
    {
        return count($this->voices) === 1;
    }

    public function characters(): int
    {
        return array_sum(array_map(static fn (SpeechTurn $t): int => mb_strlen(trim($t->text)), $this->turns));
    }
}

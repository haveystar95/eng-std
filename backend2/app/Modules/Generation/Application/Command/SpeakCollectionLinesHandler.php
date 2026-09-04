<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Collections\Application\Query\GetCollectionTermSet;
use App\Modules\Collections\Application\Query\GetCollectionTermSetHandler;
use App\Modules\Generation\Application\Port\LineSpeechReporter;
use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\StoreTermAudio;
use App\Modules\Vocabulary\Application\Command\StoreTermAudioHandler;
use App\Modules\Vocabulary\Application\Query\SpeakableLineReader;
use App\Modules\Vocabulary\Application\Query\TermAudioReader;
use Throwable;

/**
 * СТАНОК ОЗВУЧКИ: реплики коллекции → файлы, через Application соседних модулей и никак иначе.
 *
 * Форма — та же, что у {@see AttachCollectionImagesHandler}, и это не совпадение: обе работы —
 * «дособрать материал, который дню уже не нужен, чтобы быть пригодным». Отсюда все свойства:
 *
 * - **Идемпотентность держат читатели, а не этот код.** `TermAudioReader::missingFor()` возвращает
 *   только те реплики, у которых файла ЭТОГО голоса и ЭТОГО темпа ещё нет; повтор джобы, второй
 *   план с той же фразой и общий каталог сходятся в одном месте — реплика не покупается дважды.
 * - **Невозвратный отказ вендора пропускает реплику**, а не валит проход: одна фраза, которую
 *   модель отказалась читать, не должна лишать голоса остальные четыре.
 * - **Возвратная ошибка выходит наружу** ({@see TransientSpeechError}) — её ловит джоба и повторяет
 *   с backoff; всё, что уже озвучено в этом проходе, записано и на повторе не покупается снова.
 *
 * Тумблер трубы стоит ВЫШЕ (диспетчер): выключенная труба не должна доходить даже до чтения полок.
 */
final readonly class SpeakCollectionLinesHandler
{
    public function __construct(
        private GetCollectionTermSetHandler $termSet,
        private SpeakableLineReader $lines,
        private TermAudioReader $audios,
        private StoreTermAudioHandler $store,
        private SpeechSynthesizerPort $speech,
        private VoiceCatalog $voices,
        private LineSpeechReporter $reporter,
        /** @var list<string> which shelves get server audio — `generation.speech.shelves` */
        private array $shelves = ['hear', 'rescue'],
    ) {}

    public function __invoke(SpeakCollectionLines $command): void
    {
        $set = ($this->termSet)(new GetCollectionTermSet($command->collectionId));
        if ($set === null) {
            return;
        }

        $lines = $this->lines->linesFor($set->termIds, $this->shelves);
        if ($lines === []) {
            return;
        }

        // Язык берём у самих реплик, а не у плана: карточка знает свой язык, а коллекция дня — не
        // обязательно (и на общем каталоге это уже разошлось бы). Голос — конфиг пакета этого языка.
        $byLang = [];
        foreach ($lines as $line) {
            $byLang[$line->lang][] = $line;
        }

        foreach ($byLang as $lang => $group) {
            $voice = $this->voices->forLanguage((string) $lang);
            if ($voice === null) {
                continue; // у языка нет голоса — реплики звучат системным синтезом, как раньше
            }

            $termIds = array_map(static fn ($l): string => $l->termId, $group);
            $missing = $this->audios->missingFor($termIds, $voice);
            if ($missing === []) {
                continue;
            }

            $wanted = array_flip($missing);
            $spoken = 0;

            foreach ($group as $line) {
                if (! isset($wanted[$line->termId])) {
                    continue;
                }

                try {
                    $audio = $this->speech->speak($line->text, (string) $lang, $voice);
                } catch (TransientSpeechError $e) {
                    // Наружу — джоба повторит. Всё, что успели, уже записано.
                    $this->reporter->pass(
                        $command->collectionId->value,
                        $voice->key(),
                        $spoken,
                        count($missing) - $spoken,
                    );
                    throw $e;
                } catch (Throwable $e) {
                    $this->reporter->refused($line->termId, $voice->key(), $e->getMessage());

                    continue;
                }

                ($this->store)(new StoreTermAudio(
                    termId: TermId::fromString($line->termId),
                    voice: $voice,
                    format: $audio->format,
                    bytes: $audio->bytes,
                    durationMs: $audio->durationMs,
                    costUsd: $audio->costUsd,
                ));
                $spoken++;
            }

            $this->reporter->pass(
                $command->collectionId->value,
                $voice->key(),
                $spoken,
                count($missing) - $spoken,
            );
        }
    }
}

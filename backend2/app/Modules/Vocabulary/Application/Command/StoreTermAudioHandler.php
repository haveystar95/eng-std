<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Port\TermAudioStore;
use App\Modules\Vocabulary\Application\Port\TermAudioWriter;

final readonly class StoreTermAudioHandler
{
    public function __construct(
        private TermAudioStore $store,
        private TermAudioWriter $writer,
    ) {}

    /** @return string|null the audio id, or null when this voice already had one for the term */
    public function __invoke(StoreTermAudio $command): ?string
    {
        if ($command->bytes === '') {
            return null; // a vendor answering with no audio is «нет озвучки», never a zero-byte file
        }

        // ПОРЯДОК ВАЖЕН: файл сначала, строка потом. Строка — это обещание, что файл есть, и
        // обещание, выданное раньше файла, читается сборкой посадки как готовая озвучка, которой
        // ещё нет на диске, — то самое «тишина вместо голоса», которое канон §7 запрещает.
        $id = Ulid::generate();
        $path = $this->store->put($id, $command->format, $command->bytes);

        $written = $this->writer->write(
            audioId: $id,
            termId: $command->termId->value,
            voice: $command->voice,
            format: $command->format,
            path: $path,
            bytes: strlen($command->bytes),
            durationMs: $command->durationMs,
            costUsd: $command->costUsd,
        );

        if (! $written) {
            // Кто-то успел раньше (второй воркер, повтор джобы). Строка чужая и правильная, а наш
            // файл — сирота: он не адресуем ничем, потому что адрес это id строки. Убираем.
            $this->store->delete($path);

            return null;
        }

        return $id;
    }
}

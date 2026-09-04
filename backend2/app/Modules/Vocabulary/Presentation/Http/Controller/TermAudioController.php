<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Presentation\Http\Controller;

use App\Modules\Vocabulary\Application\Port\TermAudioStore;
use App\Modules\Vocabulary\Application\Query\TermAudioReader;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * ФАЙЛ ОЗВУЧКИ по адресу строки. Один GET, отдаёт байты, кэшируется навсегда.
 *
 * `immutable` в Cache-Control — не оптимизация, а правда о ресурсе: id строки уникален для пары
 * (реплика, голос+темп), поэтому содержимое по этому адресу не может измениться. Смена голоса —
 * это ДРУГОЙ адрес, и в этом весь механизм инвалидации: ни клиенту, ни прокси не нужно спрашивать
 * «не устарело ли», потому что устареть нечему.
 */
final class TermAudioController
{
    public function __construct(
        private readonly TermAudioReader $audios,
        private readonly TermAudioStore $store,
    ) {}

    public function show(string $audioId): Response
    {
        $row = $this->audios->byId($audioId);
        if ($row === null) {
            throw new NotFoundHttpException('audio not found');
        }

        $bytes = $this->store->read($row->path);
        if ($bytes === null) {
            // Строка есть, файла нет — это дефект, а не 404 «такого не бывает». Но для клиента
            // исход тот же: озвучки нет, и он играет системным голосом.
            throw new NotFoundHttpException('audio file missing');
        }

        return new Response($bytes, 200, [
            'Content-Type' => match ($row->format) {
                'wav' => 'audio/wav',
                'aac' => 'audio/aac',
                default => 'audio/mpeg',
            },
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}

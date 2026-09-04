<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http;

/**
 * АДРЕС ФАЙЛА ОЗВУЧКИ на проводе (наряд TTS-1, Ч.1.3).
 *
 * Абсолютный и построенный ОТ ТЕКУЩЕГО ЗАПРОСА, а не от `APP_URL`. Телефон ходит через ngrok, а
 * `APP_URL` — это `localhost` контейнера: ссылка, собранная из конфига, была бы недостижима ровно
 * на том устройстве, ради которого труба строилась. `url()` внутри запроса берёт хост запроса,
 * то есть тот же ngrok, которым клиент только что пришёл.
 *
 * Живёт в Presentation, потому что HTTP-адрес — это Presentation: Application знает id строки
 * озвучки, и знать про схему и хост ему нечего.
 */
final class LineAudioUrl
{
    public static function for(?string $audioId): ?string
    {
        return $audioId === null || $audioId === '' ? null : url('/api/v1/audio/lines/' . $audioId);
    }
}

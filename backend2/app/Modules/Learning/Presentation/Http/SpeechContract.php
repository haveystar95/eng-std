<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http;

use App\Modules\Learning\Domain\Service\SpeechNormalization;
use App\Modules\Learning\Domain\ValueObject\SpeechGradingRules;

/**
 * ЧЕМ ТЕЛЕФОН СУДИТ РЕЧЬ — одним блоком на обоих пейлоадах сессии (наряд SPEECH-2, Ч.3.3, Ч.4.2).
 *
 * Здесь, в Presentation, а не на View, по той же причине, по которой `scene_run` едет на View: то
 * — числа ОДНОЙ посадки, собранные вместе с ней, а это — свойство сервера, одинаковое для любой
 * сессии любого пользователя. Пропускать его через Application значило бы протаскивать конфиг
 * грейдера через каждый билдер сессии ради строки, которая от них не зависит.
 *
 * Один вызов на два ресурса — учебную сессию и сессию плана, — чтобы «клиент судит по одной
 * таблице» было правдой по построению, а не по внимательности того, кто добавит третий пейлоад.
 */
final class SpeechContract
{
    /** @return array{thresholds: array<string, float|int>, normalization: array{version: string, entries: array<string, string>}} */
    public static function block(): array
    {
        return [
            'thresholds' => SpeechGradingRules::fromConfig((array) config('learning.plan.speech', []))->toArray(),
            'normalization' => SpeechNormalization::forWire(),
        ];
    }
}

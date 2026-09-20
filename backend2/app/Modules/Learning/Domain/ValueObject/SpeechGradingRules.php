<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * ПОРОГИ ЗАЧЁТА РЕЧИ — В КОНФИГЕ, НЕ В КОДЕ (наряд SPEECH-2, Ч.3.3).
 *
 * Это продуктовые суждения о том, сколько реплики человек обязан сказать, и первый раз, когда одно
 * из них окажется неверным, оно должно сдвинуться без выката приложения. Живут в
 * `config/learning.php → speech`, едут телефону в контракте сессии и читаются ОБЕИМИ
 * сторонами: экран и сервер судят одной функцией по одним числам (Ч.3.4).
 *
 *   $readAloud       фраза НА ЭКРАНЕ («Повтори вслух», чтение примера): доля слов цели, которую надо
 *                    произнести. Высокая (0.9) намеренно: текст перед глазами, и «прочитал половину»
 *                    — это не прочитал;
 *   $recallRest      фраза БЕЗ ТЕКСТА («Скажи сам», свой ход в разговоре): доля ОСТАЛЬНЫХ слов
 *                    реплики — тех, что не входят в ключ, — при обязательном ключе. Низкая (0.6),
 *                    потому что «сказал проще» остаётся законным: человек имеет право выразить ту же
 *                    мысль короче, но про ФРАЗУ, а не про одно слово;
 *   $wholeLine       реплика без ключа судится ЦЕЛИКОМ (фикс DAY-GATE-1) — тот же порог, что и
 *                    всегда ({@see SpokenLine});
 *   $almostFloor     ниже этой доли реплика уже не «почти», а «не то»: список пропущенных слов,
 *                    в котором лежит вся реплика, ничего не объясняет;
 *   $fillerAllowance сколько служебных слов (артикль уже не считается вовсе, здесь — предлоги,
 *                    связки) прощается сверх порога. Одно — ровно то, что съедает распознаватель.
 */
final readonly class SpeechGradingRules
{
    public function __construct(
        public float $readAloud = 0.9,
        public float $recallRest = 0.6,
        public float $wholeLine = 0.7,
        public float $almostFloor = 0.5,
        public int $fillerAllowance = 1,
    ) {}

    /** @param array<string, mixed> $config `config('learning.speech')` */
    public static function fromConfig(array $config): self
    {
        return new self(
            readAloud: (float) ($config['read_aloud_coverage'] ?? 0.9),
            recallRest: (float) ($config['recall_rest_coverage'] ?? 0.6),
            wholeLine: (float) ($config['whole_line_coverage'] ?? 0.7),
            almostFloor: (float) ($config['almost_floor'] ?? 0.5),
            fillerAllowance: (int) ($config['filler_allowance'] ?? 1),
        );
    }

    /** @return array{read_aloud_coverage: float, recall_rest_coverage: float, whole_line_coverage: float, almost_floor: float, filler_allowance: int} */
    public function toArray(): array
    {
        return [
            'read_aloud_coverage' => $this->readAloud,
            'recall_rest_coverage' => $this->recallRest,
            'whole_line_coverage' => $this->wholeLine,
            'almost_floor' => $this->almostFloor,
            'filler_allowance' => $this->fillerAllowance,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Shared\Domain\Service\LexicalNormalizer;

/**
 * АББРЕВИАТУРА, УСЛЫШАННАЯ ПО БУКВАМ, — ЭТО ТА ЖЕ АББРЕВИАТУРА (наряд SPEECH-2, Ч.4.2).
 *
 * `SFSpeechRecognizer` пишет «sequel» за SQL, «a p i» за API, «h r» за HR — не потому, что человек
 * ошибся, а потому, что так звучит буква. Без этой таблицы каждая реплика с аббревиатурой была
 * промахом на ровном месте, и промахом в append-only журнале.
 *
 * ОДНА ТАБЛИЦА НА ДВЕ СТОРОНЫ. Она лежит здесь, версионируется вместе с кодом и едет телефону в
 * контракте сессии — второго словаря на клиенте нет и не должно быть: две таблицы разъезжаются, и
 * первым признаком расхождения будет «телефон сказал „не то“, а сервер засчитал», то есть ровно та
 * поломка, которую чинил DAY-GATE-1.
 *
 * Что таблица НЕ делает: она не чинит произношение и ничего не подсказывает распознавателю — этим
 * занимаются `contextualStrings` (Ч.4.1), которые едут в сам запрос распознавания. Здесь только
 * чтение готового транскрипта.
 *
 * Ключи — КАНОНИЗИРОВАННЫЕ формы ({@see LexicalNormalizer::canonicalize()}): нижний регистр, без
 * знаков, слова через один пробел. Значение — тоже канонизированное, то есть ровно то, во что
 * превратится сама аббревиатура из текста карточки. Сравнивать канон с каноном — единственный
 * способ не думать о регистре и точках дважды.
 */
final readonly class SpeechNormalization
{
    /**
     * ВЕРСИЯ ТАБЛИЦЫ. Едет клиенту рядом с самой таблицей и попадает в дев-строку: когда телефон и
     * сервер разойдутся в вердикте, первым вопросом будет «одну ли таблицу они читали».
     */
    public const VERSION = '1';

    /**
     * Что распознаватель пишет → чем это на самом деле было.
     *
     * Первичный список собран из аббревиатур, реально встречающихся в фикстурных планах (SQL, API,
     * HR, IT, UI, CV, QA, AI, DB, URL, JSON, HTTP, REST, SDK, CI), плюс общий IT/HR-набор. Растёт
     * от живых транскриптов, а не от фантазии: строка, которой не было на устройстве, стоит здесь
     * ровно столько же, сколько её нет.
     *
     * @var array<string, string>
     */
    private const TABLE = [
        // — то, что встречается в планах —
        'sequel' => 'sql',
        's q l' => 'sql',
        'ess cue el' => 'sql',
        'a p i' => 'api',
        'ay pee eye' => 'api',
        'apy' => 'api',
        'h r' => 'hr',
        'aitch ar' => 'hr',
        'i t' => 'it',
        'eye tee' => 'it',
        'u i' => 'ui',
        'you eye' => 'ui',
        'u x' => 'ux',
        'you ex' => 'ux',
        'c v' => 'cv',
        'see vee' => 'cv',
        'q a' => 'qa',
        'cue ay' => 'qa',
        'a i' => 'ai',
        'd b' => 'db',
        'dee bee' => 'db',
        'u r l' => 'url',
        'earl' => 'url',
        'j son' => 'json',
        'jason' => 'json',
        'h t t p' => 'http',
        's d k' => 'sdk',
        'c i' => 'ci',
        'c i c d' => 'cicd',
        'rest full' => 'restful',
        'restful' => 'restful',

        // — общий IT/HR-набор —
        'c e o' => 'ceo',
        'c t o' => 'cto',
        'c f o' => 'cfo',
        'p m' => 'pm',
        'k p i' => 'kpi',
        'kay pee eye' => 'kpi',
        'o k r' => 'okr',
        'r o i' => 'roi',
        'c r m' => 'crm',
        'e r p' => 'erp',
        'b two b' => 'b2b',
        'b to b' => 'b2b',
        'b two c' => 'b2c',
        'b to c' => 'b2c',
        'sass' => 'saas',
        'sas' => 'saas',
        's a a s' => 'saas',
        'a w s' => 'aws',
        'i d e' => 'ide',
        'c s s' => 'css',
        'h t m l' => 'html',
        'x m l' => 'xml',
        'm l' => 'ml',
        'o s' => 'os',
        'v p n' => 'vpn',
        's s h' => 'ssh',
        'g d p r' => 'gdpr',
        'p h d' => 'phd',
        'm b a' => 'mba',
        'u s b' => 'usb',
        'p d f' => 'pdf',
        'f a q' => 'faq',
        'n d a' => 'nda',
        'p t o' => 'pto',
        's l a' => 'sla',
        'm v p' => 'mvp',
        'p o c' => 'poc',
        'a s a p' => 'asap',
        'f y i' => 'fyi',
        'e o d' => 'eod',
        't l' => 'tl',
        'one on one' => '1on1',
        'one to one' => '1on1',
    ];

    public function __construct(private LexicalNormalizer $normalizer = new LexicalNormalizer()) {}

    /**
     * Транскрипт, в котором распознанные формы заменены каноном. Вход — сырой текст, выход —
     * КАНОНИЗИРОВАННАЯ строка: и то и другое сравнивается по словам, и канонизировать дважды
     * значит расходиться в мелочах.
     *
     * Замена идёт от САМОЙ ДЛИННОЙ формы: «a p i» должно съесться целиком, а не оставить после
     * «a i» → `ai` кусок «p». Порядок здесь и есть корректность.
     */
    public function apply(string $transcript): string
    {
        $canonical = $this->normalizer->canonicalize($transcript);
        if ($canonical === '') {
            return '';
        }

        $words = explode(' ', $canonical);
        $out = [];
        for ($i = 0; $i < count($words);) {
            $matched = false;
            for ($span = min(self::MAX_SPAN, count($words) - $i); $span >= 1; $span--) {
                $phrase = implode(' ', array_slice($words, $i, $span));
                if (isset(self::TABLE[$phrase])) {
                    $out[] = self::TABLE[$phrase];
                    $i += $span;
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                $out[] = $words[$i];
                $i++;
            }
        }

        return implode(' ', $out);
    }

    /** Самая длинная форма таблицы, в словах — потолок окна замены. */
    private const MAX_SPAN = 4;

    /**
     * Таблица на провод — телефон читает ЭТУ, своей у него нет.
     *
     * @return array{version: string, entries: array<string, string>}
     */
    public static function forWire(): array
    {
        return ['version' => self::VERSION, 'entries' => self::TABLE];
    }
}

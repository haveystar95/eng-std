<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\SpeechGradingRules;
use App\Modules\Learning\Domain\ValueObject\SpokenCredit;
use App\Modules\Learning\Domain\ValueObject\SpokenVerdict;
use App\Modules\Shared\Domain\Service\LexicalNormalizer;

/**
 * ЗАЧЁТ ВСЕЙ ФРАЗЫ — одна функция, которой судят и экран, и сервер (наряд SPEECH-2, Ч.3).
 *
 * До этого наряда зачёт речи держался на КЛЮЧЕ: услышал ключ — верно, всё остальное неважно.
 * На устройстве это выглядело так: человек говорит длинную реплику, на первом узнанном слове
 * микрофон закрывается и ставит «верно». Фразу никто не дослушал и не оценил, а тренажёр, который
 * учит говорить фразами, засчитывал слово.
 *
 * Теперь у зачёта три формы, и выбирает между ними ОДИН вопрос — есть ли текст перед глазами:
 *
 *   read_aloud    фраза НА ЭКРАНЕ («Повтори вслух», чтение примера): покрытие слов цели
 *                 ≥ {@see SpeechGradingRules::$readAloud}. Ключ не нужен: задача — прочитать это;
 *   key_and_rest  текста НЕТ («Скажи сам», свой ход в разговоре) и у реплики есть ключ: ключ
 *                 ОБЯЗАТЕЛЕН и покрытие ОСТАЛЬНЫХ слов реплики ≥ {@see SpeechGradingRules::$recallRest}.
 *                 «Сказал проще» остаётся законным — но про фразу, а не про одно слово;
 *   whole_line    текста нет и ключа нет: реплика судится ЦЕЛИКОМ (фикс DAY-GATE-1 — телефон был
 *                 строже сервера ровно потому, что этой ветки у него не было).
 *
 * Артикли, порядок слов, знаки и регистр не считаются ни в одной из трёх. Порядок — потому что
 * распознаватель роняет и подменяет слова, но не переставляет их; артикли — потому что он их ест.
 *
 * Чего здесь нет и не будет: закрытия хода. Движок ({@see \App\Modules\Learning\Domain\Service} —
 * его половина на клиенте) закрывается тишиной, тапом, сторожем или ошибкой канала, и НИКОГДА
 * совпадением: ход, закрытый на узнанном ключе, — это фраза, которую не дослушали.
 */
final readonly class SpokenLine
{
    public function __construct(
        private SpokenCoverage $coverage = new SpokenCoverage(),
        private LexicalNormalizer $normalizer = new LexicalNormalizer(),
        private SpeechNormalization $abbreviations = new SpeechNormalization(),
    ) {}

    /**
     * @param  string  $transcript  что услышал распознаватель, сырым
     * @param  string  $line  вся реплика карточки, как она написана
     * @param  list<string>  $keys  ключ и его упрощённые формы; пусто — ключа нет
     * @param  bool  $printed  стоит ли текст реплики перед глазами
     */
    public function judge(
        string $transcript,
        string $line,
        array $keys,
        bool $printed,
        SpeechGradingRules $rules,
    ): SpokenVerdict {
        // АББРЕВИАТУРЫ — ДО ВСЕГО ОСТАЛЬНОГО: «sequel» это SQL, и считать его лишним словом значит
        // штрафовать человека за то, как звучит буква.
        $heard = $this->abbreviations->apply($transcript);
        $target = $this->stripArticles($this->normalizer->canonicalize($line));

        if ($printed || $keys === []) {
            $threshold = $printed ? $rules->readAloud : $rules->wholeLine;
            $name = $printed ? 'read_aloud' : 'whole_line';

            return $this->judgeAgainst(
                $heard,
                // БЕЗ АРТИКЛЕЙ И В СПИСКЕ «НЕ ХВАТИЛО» ТОЖЕ. Покрытие их не считает, и список,
                // который их называет, — это то же самое «Не то» на съеденном артикле, только
                // подробнее (Ч.3.5).
                $this->stripArticleWords($line),
                $target,
                $threshold,
                $name,
                $rules,
                forgiveFiller: $printed,
            );
        }

        // КЛЮЧ ОБЯЗАТЕЛЕН. Не найден — это не «почти»: человек сказал что-то другое, и список
        // «не хватило» из всей реплики ничего бы ему не объяснил.
        $matched = $this->matchedKey($heard, $keys, $rules);
        if ($matched === null) {
            return new SpokenVerdict(
                SpokenCredit::Wrong,
                coverage: $this->coverage->ratio($heard, $target),
                missing: $this->forReading($this->coverage->missing($heard, $this->stripArticleWords($line))),
                threshold: 'key_and_rest',
                normalized: $heard,
            );
        }

        // ДВА РАЗНЫХ РОДА КЛЮЧЕЙ, И ПУТАТЬ ИХ НЕЛЬЗЯ.
        //
        // `speaking_key` — КУСОК реплики: слово дня, вставленное в дырку рамки («…ищу _место в
        // аренду_ для долгого проживания»). Он всегда стоит в реплике сплошным куском, потому что
        // оттуда и взят. Такой ключ — половина ответа, и вторая половина спрашивается: покрытие
        // ОСТАЛЬНЫХ слов реплики. Это и есть Ч.3.2 — «сказал проще» про фразу, а не про слово.
        //
        // `speaking_keys` — ДРУГОЙ СПОСОБ СКАЗАТЬ ВСЮ реплику: «my back hurts» вместо «It hurts in
        // my lower back», «top skills?» вместо «What skills are most important for this role?»
        // (канон GEN-1, Y4). Он в реплике сплошным куском НЕ стоит — и не должен: это перефраз, а
        // не фрагмент. Спрашивать с него «остальные слова реплики» значит требовать сказать её
        // дважды, и живой прогон 08.09 показал ровно это: человек сказал реплику своими словами,
        // сервер засчитал, телефон напечатал «Не то».
        //
        // Различает их ОДИН вопрос: стоит ли ключ в реплике подряд. Перефраз при этом обязан быть
        // ФРАЗОЙ — на однословную «альтернативу» правило фрагмента возвращается, иначе зачёт снова
        // сполз бы до одного слова.
        if (! $this->isRunOf($matched, $target) && $this->wordCount($matched) >= 2) {
            return new SpokenVerdict(
                SpokenCredit::Correct,
                $this->coverage->ratio($heard, $matched),
                threshold: 'key_and_rest',
                normalized: $heard,
            );
        }

        // …и ОСТАЛЬНАЯ реплика: то, что осталось от неё без ключа. Пусто (реплика и есть ключ) —
        // зачёт, потому что мерить нечего, а ключ уже прозвучал.
        $rest = $this->without($target, $matched);
        if (trim($rest) === '') {
            return new SpokenVerdict(SpokenCredit::Correct, 1.0, threshold: 'key_and_rest', normalized: $heard);
        }

        return $this->judgeAgainst($heard, $rest, $rest, $rules->recallRest, 'key_and_rest', $rules, forgiveFiller: false);
    }

    /**
     * Один порог, посчитанный до конца: покрытие, список пропущенных, поблажка на связку и три
     * слова человеку.
     *
     * [$displayed] — цель в том виде, в каком её читают («не хватило: …»); [$target] — она же для
     * счёта. Они расходятся ровно в одном месте: у «остальной реплики» написанного вида нет,
     * и обе роли играет то, что от неё осталось.
     */
    private function judgeAgainst(
        string $heard,
        string $displayed,
        string $target,
        float $threshold,
        string $name,
        SpeechGradingRules $rules,
        bool $forgiveFiller,
    ): SpokenVerdict {
        $ratio = $this->coverage->ratio($heard, $target);
        $missing = $this->forReading($this->coverage->missing($heard, $displayed));

        // ОДНО ПРОПУЩЕННОЕ СЛОВО-СВЯЗКА ПРОЩАЕТСЯ (Ч.3.1). Артикли уже сняты с обеих сторон, а
        // предлог или союз распознаватель ест по той же причине — он безударный. Поблажка стоит
        // ТОЛЬКО там, где текст перед глазами: без текста пропущенный предлог — это уже другая мысль.
        $forgiven = $forgiveFiller
            && $missing !== []
            && count($missing) <= $rules->fillerAllowance
            && $this->allFiller($missing);

        if ($ratio >= $threshold || $forgiven) {
            return new SpokenVerdict(SpokenCredit::Correct, $ratio, threshold: $name, normalized: $heard);
        }

        return new SpokenVerdict(
            $ratio >= $rules->almostFloor ? SpokenCredit::Almost : SpokenCredit::Wrong,
            $ratio,
            missing: $missing,
            threshold: $name,
            normalized: $heard,
        );
    }

    /**
     * КАКОЙ ключ прозвучал — канонизированным, потому что дальше по нему считают. Покрытием, тем
     * же, что и всегда ({@see SpokenCoverage}); первый подошедший и есть ответ, порядок списка —
     * это порядок предпочтения (сначала сам `speaking_key`, потом упрощённые формы).
     *
     * @param  list<string>  $keys
     */
    private function matchedKey(string $heard, array $keys, SpeechGradingRules $rules): ?string
    {
        foreach ($keys as $key) {
            $canonical = $this->stripArticles($this->normalizer->canonicalize($key));
            if ($canonical === '') {
                continue;
            }
            if ($this->coverage->ratio($heard, $canonical) >= $rules->wholeLine) {
                return $canonical;
            }
        }

        return null;
    }

    /**
     * Стоит ли ключ в реплике СПЛОШНЫМ КУСКОМ — то есть является ли он фрагментом её, а не другим
     * способом её сказать. Единственный вопрос, которым эти два рода ключей вообще различаются
     * ({@see judge()}).
     *
     * Оба аргумента уже канонизированы и без артиклей.
     */
    private function isRunOf(string $key, string $target): bool
    {
        if ($key === '' || $target === '') {
            return false;
        }
        $needle = explode(' ', $key);
        $hay = explode(' ', $target);
        $span = count($needle);
        for ($i = 0; $i + $span <= count($hay); $i++) {
            if (array_slice($hay, $i, $span) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Реплика без слов ключа — «остальное», покрытие которого и есть вторая половина зачёта.
     *
     * Мультимножеством, а не вырезанием подстроки: сюда доезжает только ключ-ФРАГМЕНТ, но и он
     * может стоять в реплике не единственный раз, и «съесть по одному вхождению на слово» —
     * единственный счёт, который не отгрызает лишнего.
     */
    private function without(string $target, string $key): string
    {
        $words = $target === '' ? [] : explode(' ', $target);
        if ($key !== '') {
            foreach (explode(' ', $key) as $word) {
                $at = array_search($word, $words, strict: true);
                if ($at !== false) {
                    unset($words[$at]);
                    $words = array_values($words);
                }
            }
        }

        return implode(' ', $words);
    }

    private function wordCount(string $canonical): int
    {
        return $canonical === '' ? 0 : count(explode(' ', $canonical));
    }

    /**
     * Написанная реплика без артиклей — В НАПИСАННОМ ВИДЕ: слова остаются такими, какими человек их
     * читает («don't», «long-term»), уходят только a/an/the. Это то, из чего строится список
     * «не хватило», и он обязан говорить теми же словами, что стоят на карточке.
     */
    /**
     * Слово для чтения человеком: без хвостовой пунктуации предложения. Список печатается через
     * запятую, и точка внутри такого перечисления читается как конец строки. Апостроф и дефис
     * остаются — они внутри слова. Зеркало клиентского `_forReading`.
     *
     * @param  list<string>  $words
     * @return list<string>
     */
    private function forReading(array $words): array
    {
        return array_map(
            static fn (string $w): string => (string) preg_replace('/[.,!?;:»"\']+$/u', '', $w),
            $words,
        );
    }

    private function stripArticleWords(string $displayed): string
    {
        $words = preg_split('/\s+/u', trim($displayed)) ?: [];

        return implode(' ', array_filter(
            $words,
            fn (string $w): bool => ! in_array($this->normalizer->canonicalize($w), self::ARTICLES, true),
        ));
    }

    private function stripArticles(string $canonical): string
    {
        $words = $canonical === '' ? [] : explode(' ', $canonical);

        return implode(' ', array_filter($words, static fn (string $w): bool => ! in_array($w, self::ARTICLES, true)));
    }

    /** @param list<string> $words */
    private function allFiller(array $words): bool
    {
        foreach ($words as $word) {
            $canonical = $this->normalizer->canonicalize($word);
            if (! in_array($canonical, self::FILLERS, true)) {
                return false;
            }
        }

        return true;
    }

    private const ARTICLES = ['a', 'an', 'the'];

    /**
     * СЛОВА-СВЯЗКИ: безударные служебные слова, которые распознаватель роняет чаще всего. Артиклей
     * здесь нет — они сняты раньше и не считаются вовсе.
     */
    private const FILLERS = [
        'to', 'of', 'in', 'on', 'at', 'for', 'with', 'by', 'from', 'about', 'into', 'over',
        'and', 'or', 'but', 'so', 'that', 'as', 'is', 'am', 'are', 'was', 'were', 'be', 'been',
        'do', 'does', 'did', 'it', 'up', 'out',
    ];
}

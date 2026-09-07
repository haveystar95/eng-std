<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\SituationalCandidate;

/**
 * ИЗ ЧЕГО СОБИРАЮТСЯ ВАРИАНТЫ ТАКТА «ЧТО ТЕБЕ СКАЗАЛИ?» — наряд DAY-FIX-3, Ч.2.2.
 *
 * Живой прогон владельца: два варианта такта 1 были перефразами друг друга — «Anything else?» и
 * «Do you have any questions?». Обе реплики роли, обе приглашения, и «правильного» среди них нет
 * по смыслу: человек угадывает, какую из двух одинаковых записал сервер.
 *
 * ## Правило
 *
 *   1. дистрактор — реплика роли ДРУГОЙ ФУНКЦИИ. Функция реплики роли — приглашение (роль
 *      `ask`-пары) или умение сцены, которому она служит (`skill_ref`): вопрос про опыт, вопрос про
 *      проект, приглашение — три функции, и два приглашения — одна;
 *   2. кандидат, у которого ≥ половины слов общие с правильной репликой — перефраз, вон;
 *   3. кандидаты, у которых ≥ половины слов общие МЕЖДУ СОБОЙ, — один кандидат: остаётся первый;
 *   4. сначала своя сцена, потом — реплики роли из других сцен плана, по возрастанию дня;
 *   5. реплика, которая в этом разговоре УЖЕ прозвучала, — кандидат, но последний: её смысл
 *      человек только что слышал, и как «неправильный» он отбрасывается легко. Это не запрет,
 *      в отличие от вариантов ответа (DAY-FIX-2, Ч.1.5): у последнего такта сцены из одной
 *      сцены запрет оставил бы пул пустым, а карточка без вариантов — это ступень B, которая
 *      никогда не закрывается.
 *
 * Доля считается от слов КАНДИДАТА: «Do you have any other questions for me?» делит с «Do you have
 * an appointment?» три слова из восьми — это не перефраз; «What is the problem today?» против
 * «What seems to be the problem?» делит три из пяти — перефраз.
 *
 * Дальше список уходит в обычный сборщик карточки узнавания с его воротами — форма (вопрос среди
 * вопросов), длина, — потому что «из какой полки» и «похож ли на ответ» это два разных вопроса.
 */
final class PlanHearOptions
{
    /** The shelf the interlocutor's lines stand on. */
    public const SHELF_HEAR = 'hear';

    /** The function of a role line that invites a question — the role of an `ask` pair. */
    public const FUNCTION_INVITATION = 'ask';

    /** Share of a candidate's words that may coincide with another line before it is a paraphrase. */
    public const MAX_SHARED_WORDS = 0.5;

    /**
     * Кандидаты в варианты такта 1 для реплики роли [$target], своей сценой вперёд.
     *
     * @param  array<int, list<SituationalCandidate>>  $scenes  карточки плана по индексу дня
     * @param  int|null  $ownDay  день сцены, которой принадлежит сама реплика
     * @param  array<string, string>  $functions  term id → функция реплики роли: {@see FUNCTION_INVITATION}
     *                                            у роли `ask`-пары; у остальных функция — `skill_ref`
     * @param  list<string>  $alreadyHeard  term id реплик, уже прозвучавших в этом разговоре — уходят в хвост
     * @return list<string>  term id, в порядке предпочтения
     */
    public static function forLine(
        SituationalCandidate $target,
        array $scenes,
        ?int $ownDay,
        array $functions = [],
        array $alreadyHeard = [],
    ): array {
        $order = array_keys($scenes);
        sort($order);
        if ($ownDay !== null) {
            $order = [$ownDay, ...array_values(array_filter($order, static fn (int $d): bool => $d !== $ownDay))];
        }

        $heard = array_fill_keys($alreadyHeard, true);
        $targetFunction = self::functionOf($target, $functions);
        $targetWords = self::words($target->text);

        /** @var list<array{id: string, words: array<string, true>}> $kept */
        $kept = [];
        /** @var list<SituationalCandidate> $lines */
        $lines = [];
        foreach ($order as $day) {
            foreach ($scenes[$day] ?? [] as $candidate) {
                $lines[] = $candidate;
            }
        }
        // Already heard in this conversation → the tail of the list, not off it (rule 5).
        usort($lines, static fn (SituationalCandidate $a, SituationalCandidate $b): int => (int) isset($heard[$a->termId]) <=> (int) isset($heard[$b->termId]));

        foreach ($lines as $candidate) {
            if ($candidate->termId === $target->termId) {
                continue;
            }
            if ($candidate->shelf !== self::SHELF_HEAR) {
                continue;
            }
            // ДРУГАЯ ФУНКЦИЯ. A candidate whose function is unknown is not «a different one»:
            // unknown here is a coin toss, exactly as it is for the reply's options.
            $function = self::functionOf($candidate, $functions);
            if ($function === null || $targetFunction === null || $function === $targetFunction) {
                continue;
            }
            $words = self::words($candidate->text);
            if ($words === [] || self::shares($words, $targetWords)) {
                continue;
            }
            $paraphrase = false;
            foreach ($kept as $other) {
                if (self::shares($words, $other['words']) || self::shares($other['words'], $words)) {
                    $paraphrase = true;

                    break;
                }
            }
            if ($paraphrase) {
                continue;
            }
            $kept[] = ['id' => $candidate->termId, 'words' => $words];
        }

        return array_map(static fn (array $row): string => $row['id'], $kept);
    }

    /** @param array<string, string> $functions */
    private static function functionOf(SituationalCandidate $line, array $functions): ?string
    {
        $function = $functions[$line->termId] ?? $line->skillRef;

        return $function === null || trim($function) === '' ? null : trim($function);
    }

    /**
     * Does [$candidate] share at least {@see MAX_SHARED_WORDS} of its own words with [$against]?
     *
     * @param  array<string, true>  $candidate
     * @param  array<string, true>  $against
     */
    private static function shares(array $candidate, array $against): bool
    {
        if ($candidate === []) {
            return false;
        }
        $common = count(array_intersect_key($candidate, $against));

        return $common / count($candidate) >= self::MAX_SHARED_WORDS;
    }

    /**
     * The words of a line as a set — lower-cased, punctuation off.
     *
     * @return array<string, true>
     */
    private static function words(string $text): array
    {
        $folded = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', mb_strtolower(trim($text))) ?? '';
        $out = [];
        foreach (preg_split('/\s+/u', trim($folded)) ?: [] as $word) {
            if ($word !== '') {
                $out[$word] = true;
            }
        }

        return $out;
    }
}

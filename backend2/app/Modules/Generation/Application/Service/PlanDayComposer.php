<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Service;

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\PlanDayDraft;
use App\Modules\Generation\Application\Dto\PlanSpend;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Application\Port\PlanDefectReporter;
use App\Modules\Generation\Application\Port\PlanPromptSource;
use App\Modules\Generation\Application\Port\RecordsPlanSpend;
use App\Modules\Generation\Application\Port\RescueKitSource;
use App\Modules\Generation\Domain\Exception\PlanDayRefused;
use App\Modules\Generation\Domain\Service\PlanDayValidator;
use App\Modules\Generation\Domain\Service\PlanLanguageNotes;
use App\Modules\Generation\Domain\Service\PlanSkillRefNormalizer;
use App\Modules\Generation\Domain\ValueObject\PlanDayCandidate;
use App\Modules\Generation\Domain\ValueObject\PlanDayItem;
use App\Modules\Generation\Domain\ValueObject\PlanDialogueTurn;
use App\Modules\Generation\Domain\ValueObject\PlanShelf;
use App\Modules\Generation\Domain\ValueObject\PlanViolation;
use App\Modules\Generation\Domain\ValueObject\RescuePhrase;
use App\Modules\Learning\Application\Dto\PlanDayGenerationBrief;
use App\Modules\Shared\Domain\Service\LanguageName;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * P2 — the day-scene's material, asked for and judged.
 *
 * Everything about talking to the model lives here and nothing about writing to the database, so
 * the expensive half can be exercised on its own and the write half tested without a vendor.
 *
 * ## ONE RUN OF THE JOB IS ONE PAID CALL — unchanged, and it used to be false
 *
 * This class made its own second call inside `compose()` until v0.3, on top of the second RUN the
 * day's own counter allows: two by two, four paid calls, $0.197 on a budget written for two. The
 * re-run lives in exactly one place — the day row's `generation_attempts` — and the only second
 * call this class may make is a REPAIR ({@see PlanDayRepairer}), charged to its own column.
 *
 * ## v0.4: one scene in, six shelves out, and one gate instead of two
 *
 * The brief is a SCENE ({@see PlanDayGenerationBrief::sceneJson()}) and the answer is six shelves.
 * Two things went with the three arrays:
 *
 *   the three exact counts — a day is no longer «one card off», and every size is a counter;
 *   {@see \App\Modules\Generation\Domain\Service\PlanCoherenceValidator} — its three rules were
 *   about a day inside a plan, and v0.4 answers all three elsewhere: a term an earlier day taught
 *   is now `card.clone` (carded, so it is REPAIRABLE, which `plan.term_repeated` never was), a
 *   duplicated checkpoint is P1's own rule about promising an ability twice, and the entity
 *   agreement check lost its input the day P1 stopped answering with gender and number.
 *
 * ## GEN-1 (v0.7): two things the server now does to a PAIRED answer before the gate
 *
 *   a word or chunk that stands in no line of the scene is DROPPED ({@see pruneUnspoken()}) — the
 *   canon says the day's pieces come out of its lines, and the live run shipped 21 of 75 that came
 *   out of nowhere; a dropped card is a counter, never a refusal, and the addresses of the rest do
 *   not move;
 *   a pair whose half a REPAIR rewrote is judged again ({@see rejudgeRepaired()}) — P2R fixes a
 *   card at an address and knows nothing about the exchange it stands in, so «does the fixed reply
 *   still answer its question» was a question nobody asked.
 */
final readonly class PlanDayComposer
{
    public const PROMPT_VERSION = 'plan_day.v0.7';

    /** Counter: a word or chunk that stood in no line of the scene and was dropped (GEN-1, канон Y5). */
    public const WORD_OUTSIDE_LINES_DROPPED = 'plan_day_word_outside_lines_dropped';

    /** Counter: a misnumbered `skill_ref` brought back onto the scene's id ({@see PlanSkillRefNormalizer}). */
    public const SKILL_REF_REPAIRED = 'plan_day_skill_ref_repaired';

    /** The most alternative forms a spoken line keeps — «1–2 варианта ключа». */
    public const MAX_SPEAKING_KEYS = 2;

    /** The slot in a frame, and the one string {@see assemble()} replaces. */
    private const SLOT = PlanDayItem::SLOT;

    public function __construct(
        private ContentModelPort $model,
        private PlanPromptSource $prompts,
        private RecordsPlanSpend $ledger,
        /**
         * Where a DROPPED reading hint goes, and every counter of a written day. The one defect
         * that is repaired instead of refused has to be visible — see {@see PlanDefectReporter}.
         */
        private PlanDefectReporter $defects,
        /**
         * P2R — the SECOND call this class may make, and the only one it may make twice-per-run.
         * Null on a composer built without one, which is what a test that is not about repair
         * wants: no repairer, no second call.
         */
        private ?PlanDayRepairer $repairer = null,
        private PlanDayValidator $validator = new PlanDayValidator(),
        /**
         * THE FIVE PHRASES THE SERVER OWNS (канон §5). The model never writes them; it is handed
         * them as a forbidden list, and a day that teaches one again is a clone. Null means this
         * build has no language pack wired, and then the day is written without a kit rather than
         * refused — the same shape every other «this language has no rule yet» takes here.
         */
        private ?RescueKitSource $rescueKit = null,
        private PlanLanguageNotes $notes = new PlanLanguageNotes(),
        /**
         * СУД НАД ПАРАМИ (P2 v0.6, наряд DAY-FIX-2, Ч.1.2): судья на каждую пару, переписчик на
         * отбитую, выброс после двух правок. Null — суда нет, пары принимаются как написаны; это
         * то, что хочет тест, который не про суд, и это же — день, пришедший в старой форме
         * полками, где судить нечего.
         */
        private ?PlanPairCourt $court = null,
    ) {}

    /**
     * ONE DAY, ASKED FOR — and, when it came back nearly right, ONE REPAIR CALL on the cards that
     * failed.
     *
     * The order is what makes the repair safe. The day is judged whole; if the fatal verdict lands
     * on at most half its cards and every violation has a card behind it, P2R is asked for those
     * cards and nothing else; the answer is merged at the addresses that were asked about; the
     * pairs a merged card belongs to are judged again; and the MERGED day is judged whole again,
     * from scratch. A repaired card meets every rule the original had to meet.
     *
     * @param  array<string, string>  $known  term id → text, met on an earlier day of this plan
     *
     * @throws PlanDayRefused when the day failed the validator — with the verdict as addresses and
     *                        the number of REPAIR calls, so the day row can charge them beside its
     *                        day calls rather than inside them (Д-18)
     */
    public function compose(PlanDayGenerationBrief $brief, array $known): PlanDayDraft
    {
        $rescue = $this->rescueFor($brief);
        $answer = $this->ask($brief, $known, $rescue);
        // ПАРЫ → СУД → ПОЛКИ (v0.6). Ответ, написанный обменами, сначала проходит судью каждой
        // пары, и только устоявшие пары ложатся на полки `hear` / `say` / `ask` — вместе с
        // цепочкой, которая у пар одна: их порядок. Ответ старой формы (полками) проходит здесь
        // насквозь, и это то, что держит тесты на v0.4/v0.5 живыми.
        $expectsPairs = is_array($answer->payload['pairs'] ?? null);
        $payload = $this->shelved($brief, $answer->payload);
        $items = $this->items($payload);
        // THE ORDER THE SCENE IS SPOKEN IN, read once. It survives a repair untouched: P2R is asked
        // about CARDS and answers with cards, so the chain the day was written with is still the
        // chain of the day that comes out of the merge — the refs are addresses, and the addresses
        // are exactly what a repair may not move ({@see PlanDayRepairer::merge()}).
        $dialogue = $this->dialogue($payload);
        if ($expectsPairs) {
            $items = $this->normalizeSkillRefs($brief, $items);
            $items = $this->pruneUnspoken($brief, $items);
        }
        [$violations, $candidate] = $this->judge($brief, $known, $rescue, $items, $dialogue, $expectsPairs);
        $this->record($brief, $answer, $violations);
        $repairCalls = 0;

        if ($violations !== [] && $this->repairer !== null) {
            $repair = $this->repairer->repair($brief, $items, $violations);
            if ($repair !== null) {
                $repairCalls = 1;
                $this->reportWarnings($brief, $candidate, counted: false);

                [$items, $dialogue] = $this->rejudgeRepaired($brief, $items, $repair->items, $dialogue, $expectsPairs);
                if ($expectsPairs) {
                    // A REPAIRED card may be a new piece that stands in no line («out of 10» on
                    // the live doctor day), or re-point at a misnumbered skill: the merge is
                    // judged by the same rules the answer was.
                    $items = $this->normalizeSkillRefs($brief, $items);
                    $items = $this->pruneUnspoken($brief, $items);
                }
                [$violations, $candidate] = $this->judge($brief, $known, $rescue, $items, $dialogue, $expectsPairs);
                $violations = [...$violations, ...$repair->violations];
            }
        }

        $this->reportWarnings($brief, $candidate, counted: $violations === []);

        if ($violations !== []) {
            throw PlanDayRefused::invalid($violations, $repairCalls);
        }

        return $this->draft($brief, $known, $answer, $items, $repairCalls, $dialogue);
    }

    /**
     * The `dialogue` array as turns — parsed, never judged (the verdict is
     * {@see PlanDayValidator::checkDialogue()}).
     *
     * An entry that is not an object is skipped rather than kept as an empty turn: a turn with no
     * side and no ref would be reported as three defects about nothing, and the day is already
     * failing on the ones that name something.
     *
     * @param  array<string, mixed>  $payload
     * @return list<PlanDialogueTurn>
     */
    private function dialogue(array $payload): array
    {
        $out = [];
        $rows = is_array($payload['dialogue'] ?? null) ? $payload['dialogue'] : [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = PlanDialogueTurn::fromArray($row);
            }
        }

        return $out;
    }

    /**
     * ПАРЫ ОТВЕТА → ПОЛКИ ДНЯ, через суд (P2 v0.6, наряд DAY-FIX-2, Ч.1).
     *
     * Ответ старой формы — с `hear`/`say`/`ask` и, может быть, `dialogue` — возвращается как есть:
     * ни суда, ни разбора. Ответ с `pairs` сначала судится попарно ({@see PlanPairCourt}), затем
     * раскладывается: `role` каждой пары на `hear`, `you` — на `say` (`answer`) или `ask` (`ask`),
     * а цепочка — это сами пары по порядку. Полки и цепочка после этого выглядят ровно так, как их
     * писала v0.5, поэтому валидатор, починка и запись дня не знают, что форма ответа изменилась.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function shelved(PlanDayGenerationBrief $brief, array $payload): array
    {
        if (! is_array($payload['pairs'] ?? null)) {
            return $payload;
        }

        /** @var list<array<string, mixed>> $pairs */
        $pairs = array_values(array_filter($payload['pairs'], static fn (mixed $p): bool => is_array($p)));
        if ($this->court !== null) {
            $pairs = $this->court->hold($brief, $pairs, self::dayWordsOf($payload))['pairs'];
        }

        return [...$payload, ...self::explodePairs($pairs)];
    }

    /**
     * ПАРЫ КАК ПОЛКИ И ЦЕПОЧКА — одно правило, которое читают композер, фейк и фикстуры.
     *
     * Адрес реплики — её место на полке: роль пары i лежит на `hear[i]`, «ты» — на `say[j]` или
     * `ask[k]` по типу пары, индексы бегут по полке. Так адреса совпадают с теми, которые видят
     * P2R и валидатор, и переписать карточку по адресу можно, не зная о парах.
     *
     * @param  list<array<string, mixed>>  $pairs
     * @return array{hear: list<array<string, mixed>>, say: list<array<string, mixed>>, ask: list<array<string, mixed>>, dialogue: list<array{turn: string, ref: string, pair: string}>}
     */
    public static function explodePairs(array $pairs): array
    {
        $hear = [];
        $say = [];
        $ask = [];
        $dialogue = [];

        foreach ($pairs as $pair) {
            $kind = ($pair['kind'] ?? null) === PlanDialogueTurn::PAIR_ASK
                ? PlanDialogueTurn::PAIR_ASK
                : PlanDialogueTurn::PAIR_ANSWER;
            $role = is_array($pair['role'] ?? null) ? $pair['role'] : [];
            $you = is_array($pair['you'] ?? null) ? $pair['you'] : [];

            $hear[] = [...$role, 'kind' => PlanDayItem::KIND_LINE, 'speaker' => PlanDayItem::SPEAKER_ROLE];
            $dialogue[] = ['turn' => PlanDialogueTurn::ROLE, 'ref' => 'hear[' . (count($hear) - 1) . ']', 'pair' => $kind];

            $youItem = [...$you, 'kind' => PlanDayItem::KIND_LINE];
            if ($kind === PlanDialogueTurn::PAIR_ASK) {
                $ask[] = $youItem;
                $dialogue[] = ['turn' => PlanDialogueTurn::YOU, 'ref' => 'ask[' . (count($ask) - 1) . ']', 'pair' => $kind];
            } else {
                $say[] = $youItem;
                $dialogue[] = ['turn' => PlanDialogueTurn::YOU, 'ref' => 'say[' . (count($say) - 1) . ']', 'pair' => $kind];
            }
        }

        return ['hear' => $hear, 'say' => $say, 'ask' => $ask, 'dialogue' => $dialogue];
    }

    /**
     * Тексты слов и связок дня — то, из чего переписчику пары предлагают взять ключ.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private static function dayWordsOf(array $payload): array
    {
        $out = [];
        foreach ([PlanShelf::Words->value, PlanShelf::Chunks->value] as $shelf) {
            $cards = is_array($payload[$shelf] ?? null) ? $payload[$shelf] : [];
            foreach ($cards as $card) {
                $text = is_array($card) && is_scalar($card['text'] ?? null) ? trim((string) $card['text']) : '';
                if ($text !== '') {
                    $out[] = $text;
                }
            }
        }

        return $out;
    }

    /**
     * `skill_ref`, КОТОРЫЙ МОДЕЛЬ ПРОНУМЕРОВАЛА ПО-СВОЕМУ, ВОЗВРАЩАЕТСЯ НА ID СЦЕНЫ (вердикт
     * владельца по GEN-1, V14): «s2.0», «s1.4», «s3» при умениях `s2.1…s2.3` — по порядковому
     * номеру, если он читается однозначно ({@see PlanSkillRefNormalizer}). Что не читается,
     * остаётся как есть и отбивается валидатором БЕЗ адреса — день пишется заново целиком, а не
     * чинится по карточкам и не умирает на починке.
     *
     * @param  list<PlanDayItem>  $items
     * @return list<PlanDayItem>
     */
    private function normalizeSkillRefs(PlanDayGenerationBrief $brief, array $items): array
    {
        $ids = $brief->skillIds();
        if ($ids === []) {
            return $items;
        }

        $out = [];
        foreach ($items as $item) {
            $ref = trim((string) $item->skillRef);
            if (in_array($ref, $ids, true)) {
                $out[] = $item;

                continue;
            }

            $fixed = PlanSkillRefNormalizer::normalize($ref, $ids);
            if ($fixed === null || $fixed === $ref) {
                $out[] = $item;

                continue;
            }

            $this->defects->warned(
                $brief->planId,
                $brief->dayIndex,
                self::SKILL_REF_REPAIRED,
                "{$item->arrayName()}[{$item->index}]: skill_ref «{$ref}» → «{$fixed}»",
                counted: true,
            );
            $out[] = $item->withSkillRef($fixed);
        }

        return $out;
    }

    /**
     * СЛОВО, КОТОРОГО НЕТ НИ В ОДНОЙ РЕПЛИКЕ, В ДЕНЬ НЕ ПОПАДАЕТ (наряд GEN-1, канон Y5).
     *
     * «Слова и связки — из этих же реплик» (канон §2) было счётчиком
     * ({@see PlanDayValidator::SUBSTITUTION_OUTSIDE_FRAME}), и живой прогон отдал 21 карточку из
     * 75, которые ни в одной реплике не стоят — `lamp` в сцене, где никто не говорит о лампе. Такая
     * карточка не чинится (чинить нечего — реплики целы) и не стоит повтора дня; она просто не
     * карточка ЭТОЙ сцены, и день пишется без неё.
     *
     * Совпадение — по границам слов, регистр и пунктуация сняты, любая реплика сцены (роли или
     * ученика): то же правило, которым валидатор считал счётчик. Форма слова не угадывается —
     * «hurt» при реплике «It hurts» отбрасывается, и промпт v0.7 просит писать карточку в той
     * форме, в какой она стоит в реплике. Индексы остальных карточек не двигаются: адрес — позиция
     * в ответе, а не в списке.
     *
     * @param  list<PlanDayItem>  $items
     * @return list<PlanDayItem>
     */
    private function pruneUnspoken(PlanDayGenerationBrief $brief, array $items): array
    {
        $lines = '';
        foreach ($items as $item) {
            if ($item->kind === PlanDayItem::KIND_LINE) {
                $lines .= ' ' . self::fold($item->text) . ' ';
            }
        }

        $kept = [];
        foreach ($items as $item) {
            $shelf = PlanShelf::tryFromName($item->arrayName());
            if ($shelf !== PlanShelf::Words && $shelf !== PlanShelf::Chunks) {
                $kept[] = $item;

                continue;
            }

            $needle = self::fold($item->text);
            if ($needle === '' || str_contains($lines, ' ' . $needle . ' ')) {
                $kept[] = $item;

                continue;
            }

            $this->defects->warned(
                $brief->planId,
                $brief->dayIndex,
                self::WORD_OUTSIDE_LINES_DROPPED,
                "«{$item->text}» ({$item->arrayName()}[{$item->index}]) не стоит ни в одной реплике сцены — выброшено",
                counted: true,
            );
        }

        return $kept;
    }

    /**
     * ПАРА, У КОТОРОЙ ПОЧИНКА ПЕРЕПИСАЛА ПОЛОВИНУ, СУДИТСЯ ЗАНОВО (наряд GEN-1, Ч.4.2 V7).
     *
     * P2R чинит карточку ПО АДРЕСУ и не знает, что `say[2]` — ответ на `hear[2]`. До этого
     * починенная реплика возвращалась в цепочку без суда, и «отвечает ли она» проверял никто.
     * Теперь каждая пара, у которой изменилась любая половина (текст или перевод), идёт к судье;
     * «нет» — обе карточки пары и оба хода уходят из дня. Переписки здесь нет: второго вызова у
     * починки не бывает, а пара без ответа хуже отсутствующей. Сцена, потерявшая четвёртую пару,
     * отбивается валидатором (`day.pairs_too_few`) — честно.
     *
     * @param  list<PlanDayItem>  $before  the day as judged before the repair
     * @param  list<PlanDayItem>  $after   the merged day
     * @param  list<PlanDialogueTurn>  $dialogue
     * @return array{0: list<PlanDayItem>, 1: list<PlanDialogueTurn>}
     */
    private function rejudgeRepaired(PlanDayGenerationBrief $brief, array $before, array $after, array $dialogue, bool $expectsPairs): array
    {
        if (! $expectsPairs || $this->court === null || $dialogue === []) {
            return [$after, $dialogue];
        }

        $was = [];
        foreach ($before as $item) {
            $was[$item->arrayName() . '#' . $item->index] = $item;
        }
        $now = [];
        foreach ($after as $item) {
            $now[$item->arrayName() . '#' . $item->index] = $item;
        }
        $changed = static function (?PlanDayItem $a, ?PlanDayItem $b): bool {
            if ($a === null || $b === null) {
                return $a !== $b;
            }

            return $a->text !== $b->text || $a->translation !== $b->translation;
        };

        $dropAddresses = [];
        $keptTurns = [];
        for ($i = 0; $i + 1 < count($dialogue); $i += 2) {
            $roleTurn = $dialogue[$i];
            $youTurn = $dialogue[$i + 1];
            $roleAddress = $roleTurn->address();
            $youAddress = $youTurn->address();
            $role = $roleAddress === null ? null : ($now[$roleAddress] ?? null);
            $you = $youAddress === null ? null : ($now[$youAddress] ?? null);

            $touched = $changed($roleAddress === null ? null : ($was[$roleAddress] ?? null), $role)
                || $changed($youAddress === null ? null : ($was[$youAddress] ?? null), $you);

            if ($touched && $role !== null && $you !== null) {
                $verdict = $this->court->judgeOne(
                    $brief,
                    intdiv($i, 2),
                    $youTurn->pair ?? PlanDialogueTurn::PAIR_ANSWER,
                    $role->text,
                    $you->text,
                    $role->translation,
                    $you->translation,
                );
                if (! $verdict['fits']) {
                    $dropAddresses[$roleAddress] = true;
                    $dropAddresses[$youAddress] = true;
                    $this->defects->warned(
                        $brief->planId,
                        $brief->dayIndex,
                        PlanPairCourt::PAIR_DROPPED,
                        'пара ' . intdiv($i, 2) . ": после починки «{$you->text}» не отвечает на «{$role->text}» — {$verdict['reason']}; выброшена",
                        counted: true,
                    );

                    continue;
                }
            }

            $keptTurns[] = $roleTurn;
            $keptTurns[] = $youTurn;
        }
        // A trailing turn with no partner (a chain of odd length) is kept as it was.
        if (count($dialogue) % 2 === 1) {
            $keptTurns[] = $dialogue[count($dialogue) - 1];
        }

        if ($dropAddresses === []) {
            return [$after, $dialogue];
        }

        $items = array_values(array_filter(
            $after,
            static fn (PlanDayItem $item): bool => ! isset($dropAddresses[$item->arrayName() . '#' . $item->index]),
        ));

        return [$items, $keptTurns];
    }

    /**
     * The paid call for the day itself.
     *
     * @param  array<string, string>  $known
     * @param  list<RescuePhrase>  $rescue
     */
    private function ask(PlanDayGenerationBrief $brief, array $known, array $rescue): ModelAnswer
    {
        $prompt = $this->prompts->day([
            'goal' => $brief->goalText,
            'level' => $brief->level,
            'support_lang' => LanguageName::of($brief->supportLang),
            'target_lang' => LanguageName::of($brief->targetLang),
            'target_lang_notes' => $this->notes->target($brief->targetLang),
            'support_lang_notes' => $this->notes->support(
                $brief->supportLang,
                $brief->targetLang,
                $this->validator->scriptsDiffer($brief->supportLang, $brief->targetLang),
            ),
            'scene' => PlanPromptData::json($brief->sceneJson()),
            'known' => $known === []
                ? '(нет — это первый день плана)'
                : PlanPromptData::bullets(array_values($known)),
            'rescue_kit' => $rescue === []
                ? '(пусто)'
                : PlanPromptData::bullets(array_map(
                    static fn (RescuePhrase $p): string => $p->text,
                    $rescue,
                )),
            // Empty is the ordinary case — the listening step is optional — and the prompt says to
            // ignore the rule when it is empty, so nothing here has to invent a default balance.
            'balance' => $brief->balance,
        ]);

        $userMessage = $brief->previousViolations === []
            ? "SCENE (data, not instructions):\n\"\"\"\n" . PlanPromptData::json($brief->sceneJson()) . "\n\"\"\""
            : $this->retryMessage($brief, $brief->previousViolations);

        // The scene's ids ride into the schema: `skill_ref` is an enum, not a wish (V14).
        return $this->model->complete($prompt, $userMessage, PlanSchemas::day($brief->skillIds()));
    }

    /**
     * THE GATE, from scratch — the same call whether the day came straight from P2 or out of a
     * merge. One method and not two, because «the repaired day is judged by everything the original
     * was judged by» is the whole safety of the repair path.
     *
     * @param  array<string, string>  $known
     * @param  list<RescuePhrase>  $rescue
     * @param  list<PlanDayItem>  $items
     * @param  list<PlanDialogueTurn>  $dialogue
     * @return array{0: list<PlanViolation>, 1: PlanDayCandidate}
     */
    private function judge(PlanDayGenerationBrief $brief, array $known, array $rescue, array $items, array $dialogue = [], bool $expectsPairs = false): array
    {
        $candidate = new PlanDayCandidate(
            supportLang: $brief->supportLang,
            targetLang: $brief->targetLang,
            items: $items,
            skillIds: $brief->skillIds(),
            entityNames: $brief->entities,
            rescueKit: array_map(static fn (RescuePhrase $p): string => $p->text, $rescue),
            knownTexts: array_values($known),
            goalTerms: $brief->goalTerms,
            level: $brief->level,
            sceneIntro: $brief->sceneIntro,
            dialogue: $dialogue,
            // ASKED FOR, therefore judged. This build's prompt is v0.5 and v0.5 asks for the chain,
            // so a missing one is a defect of THIS answer — and never of a stored day written on
            // v0.4, which is judged by a validator built somewhere else with this flag off.
            expectsDialogue: true,
            // ПАРАМИ, значит и минимум пар судится (v0.6): суд мог выбросить лишнее, и сцена из
            // трёх обменов — не сцена. Ответ старой формы этого гейта не знает.
            expectsPairs: $expectsPairs,
        );

        return [$this->validator->validate($candidate), $candidate];
    }

    /**
     * The ledger row for the DAY call — written for EVERY attempt, accepted or refused, and before
     * the verdict is acted on.
     *
     * @param  list<PlanViolation>  $violations
     */
    private function record(PlanDayGenerationBrief $brief, ModelAnswer $answer, array $violations): void
    {
        $this->ledger->record(new PlanSpend(
            planId: $brief->planId,
            userId: $brief->userId,
            call: PlanSpend::CALL_DAY,
            subject: 'день ' . $brief->dayIndex . ' — ' . $brief->dayTitle,
            supportLang: $brief->supportLang,
            targetLang: $brief->targetLang,
            promptVersion: $this->prompts->dayVersion(),
            model: $answer->model,
            tokensIn: $answer->tokensIn,
            tokensOut: $answer->tokensOut,
            costUsd: $answer->costUsd,
            size: $brief->termBudget,
            succeeded: $violations === [],
            error: $violations === [] ? null : mb_substr(implode('; ', array_map(
                static fn (PlanViolation $v): string => (string) $v,
                $violations,
            )), 0, 500),
        ));
    }

    /**
     * WHAT THE ANSWER GOT AWAY WITH — reported for EVERY answer, counted only for the one that was
     * written. A refused answer is thrown away whole, so the log is the only place its shape is
     * ever recorded; the counters stay a measure of weak days SHIPPED.
     */
    private function reportWarnings(PlanDayGenerationBrief $brief, PlanDayCandidate $candidate, bool $counted): void
    {
        foreach ($this->validator->warnings($candidate) as $warning) {
            $this->defects->warned(
                $brief->planId,
                $brief->dayIndex,
                $warning->code,
                $warning->detail,
                counted: $counted,
            );
        }
    }

    /**
     * The day's material as it will be STORED — the reading hints normalised, the draft assembled.
     *
     * @param  array<string, string>  $known
     * @param  list<PlanDayItem>  $items
     * @param  list<PlanDialogueTurn>  $dialogue
     */
    private function draft(PlanDayGenerationBrief $brief, array $known, ModelAnswer $answer, array $items, int $repairCalls = 0, array $dialogue = []): PlanDayDraft
    {
        $mandatory = $this->validator->scriptsDiffer($brief->supportLang, $brief->targetLang);
        $normalized = [];
        foreach ($items as $item) {
            $hint = $this->validator->transliterationFor($brief->supportLang, $item->transliteration);
            if ($hint === null && $mandatory && $item->arrayName() !== PlanShelf::Numbers->value) {
                $this->defects->transliterationDropped(
                    $brief->planId,
                    $brief->dayIndex,
                    $item->text,
                    $item->transliteration,
                    trim((string) $item->transliteration) === '' ? 'missing' : 'unusable',
                );
            }

            $normalized[] = new PlanDayItem(
                text: $item->text,
                type: $item->type,
                kind: $item->kind,
                isLine: $item->isLine,
                translation: $item->translation,
                transliteration: $hint,
                description: $item->description,
                example: $item->example,
                exampleTranslation: $item->exampleTranslation,
                frame: $item->frame,
                filler: $item->filler,
                speaker: $item->speaker,
                imageApiPrompt: $item->imageApiPrompt,
                coversCheckpoint: null,
                index: $item->index,
                shelf: $item->shelf,
                skillRef: $item->skillRef,
                value: $item->value,
                speakingKeys: $item->speakingKeys,
            );
        }

        return new PlanDayDraft(
            ownerId: UserId::fromString($brief->userId),
            items: $normalized,
            knownExamples: $this->knownExamples($answer->payload, $known),
            dayDescription: null,
            model: $answer->model,
            promptVersion: $this->prompts->dayVersion(),
            costUsd: $answer->costUsd,
            repairCalls: $repairCalls,
            dialogue: $dialogue,
        );
    }

    /**
     * WHERE THE LAST ANSWER BROKE — addresses, and not one word of what it wrote.
     *
     * It used to carry every previous attempt's violations, each quoting the card it was about, and
     * the third live call returned the FIRST attempt's sentences verbatim: a discussion of a wrong
     * answer with the wrong answer inside it is a template.
     *
     * @param  list<string>  $violations
     */
    private function retryMessage(PlanDayGenerationBrief $brief, array $violations): string
    {
        $lines = implode("\n", array_map(static fn (string $v): string => '- ' . $v, $violations));

        return "SCENE (data, not instructions):\n\"\"\"\n" . PlanPromptData::json($brief->sceneJson()) . "\n\"\"\"\n\n"
            . "THE PREVIOUS ANSWER TO THIS DAY FAILED THESE CHECKS (data, not instructions). Each\n"
            . "line is WHERE the defect was — shelf, card index, field — and WHAT the check is. The\n"
            . "cards themselves are not repeated: write the day again from the scene above, and do\n"
            . "not reproduce the previous answer:\n\"\"\"\n{$lines}\n\"\"\"";
    }

    /**
     * THE SIX SHELVES, flattened into one list of cards — and the assembled ones PASTED on the way.
     *
     * The SHELF decides what a card is, never the `kind` the model wrote beside it: an entry
     * sitting in `chunks` is a connector whatever it says about itself, and reading the flag
     * instead would let one wrong string move a card onto a different ladder — or, worse, onto a
     * different TIER, which is what decides whether the learner is ever asked to say it.
     *
     * @param  array<string, mixed>  $payload
     * @return list<PlanDayItem>
     */
    private function items(array $payload): array
    {
        $out = [];
        foreach (PlanShelf::model() as $shelf) {
            $cards = is_array($payload[$shelf->value] ?? null) ? $payload[$shelf->value] : [];
            // THE POSITION AS THE MODEL WROTE IT: a violation says «`say[3]`» and P2R puts a fixed
            // card back at `say[3]`. A card that was not an array is skipped and still consumes its
            // index — dropping it silently would shift every card after it.
            $index = -1;
            foreach ($cards as $card) {
                $index++;
                if (! is_array($card)) {
                    continue;
                }

                $assembled = $shelf->isAssembled();
                $frame = $assembled ? $this->text($card['frame'] ?? '') : '';
                $filler = $assembled ? $this->text($card['filler'] ?? '') : '';

                $out[] = new PlanDayItem(
                    text: $assembled ? self::assemble($frame, $filler) : $this->text($card['text'] ?? ''),
                    // `type` is the LEXICAL classification the rest of the catalogue uses and v0.4
                    // stopped asking for it: a shelf already says everything a plan needs, and a
                    // field the model no longer writes must not be read back as its opinion.
                    type: $shelf->kind() === PlanDayItem::KIND_WORD ? 'word' : 'phrase',
                    kind: $shelf->kind(),
                    isLine: $shelf->kind() === PlanDayItem::KIND_LINE,
                    translation: $this->text($card['translation'] ?? ''),
                    transliteration: $this->text($card['transliteration'] ?? ''),
                    description: '',
                    example: $this->text($card['example'] ?? ''),
                    exampleTranslation: $this->text($card['example_translation'] ?? ''),
                    frame: $frame,
                    filler: $filler,
                    // WHOSE TURN IT IS, from the shelf and not from the answer. `hear` is the
                    // interlocutor's by definition; `say` and `ask` are the learner's; a word, a
                    // connector and a number are nobody's whole turn.
                    speaker: match (true) {
                        $shelf->isRole() => PlanDayItem::SPEAKER_ROLE,
                        $shelf->kind() === PlanDayItem::KIND_LINE => PlanDayItem::SPEAKER_LEARNER,
                        default => null,
                    },
                    imageApiPrompt: $this->text($card['image_api_prompt'] ?? ''),
                    coversCheckpoint: null,
                    index: $index,
                    shelf: $shelf->value,
                    skillRef: $this->text($card['skill_ref'] ?? '') ?: null,
                    value: $this->text($card['value'] ?? '') ?: null,
                    // Only a spoken line of the learner's carries keys; a role line that wrote
                    // some (the schema does not let it, but a fixture might) keeps none.
                    speakingKeys: $shelf->isAssembled() && ! $shelf->isRole() && $shelf !== PlanShelf::Numbers
                        ? self::speakingKeysOf($card['speaking_keys'] ?? null)
                        : [],
                );
            }
        }

        return $out;
    }

    /**
     * `speaking_keys` as the model wrote them — trimmed, non-empty, unique, at most
     * {@see MAX_SPEAKING_KEYS}. Anything that is not a list of strings reads as no keys.
     *
     * PUBLIC for the same reason {@see assemble()} is: the repairer and the fixtures read a key
     * list the same way the day does.
     *
     * @return list<string>
     */
    public static function speakingKeysOf(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $value) {
            $key = is_scalar($value) ? trim((string) $value) : '';
            if ($key !== '' && ! in_array($key, $out, true)) {
                $out[] = $key;
            }
            if (count($out) === self::MAX_SPEAKING_KEYS) {
                break;
            }
        }

        return $out;
    }

    /**
     * THE LINE THE LEARNER WILL SEE — `frame` with `filler` pasted into its one slot.
     *
     * One substitution, and only the first: a frame with two slots is a defect the validator names
     * ({@see PlanDayValidator::GAP_MISSING}), and pasting into both would hide it behind a sentence
     * that reads fine. A frame with no slot IS the line — that is what a formula is.
     *
     * ONE thing is done to the seam, and nothing to the content (наряд GEN-1, Ч.4.2 V4): a space
     * between the slot and the punctuation right after it is closed — «… for ___ .» pastes as
     * «… for lower back pain.», never «… pain .». Five live lines shipped with that gap; it is a
     * property of the paste, not of the sentence the model was told it was writing, and the
     * validator judges the pasted line. No other spacing repair, no capitalisation, no full stop.
     *
     * PUBLIC because this is the formula, and the formula belongs to one place: the fixtures and
     * the tests build their days through it rather than re-implementing the paste beside it.
     */
    public static function assemble(string $frame, string $filler): string
    {
        $at = mb_strpos($frame, self::SLOT);
        if ($at === false) {
            return $frame;
        }

        $tail = mb_substr($frame, $at + mb_strlen(self::SLOT));
        $tail = (string) preg_replace('/^\s+(?=[.,!?;:])/u', '', $tail);

        return mb_substr($frame, 0, $at) . $filler . $tail;
    }

    /**
     * The plan's rescue kit for this pair — five phrases, or none when the pack has no such pair.
     *
     * @return list<RescuePhrase>
     */
    private function rescueFor(PlanDayGenerationBrief $brief): array
    {
        return $this->rescueKit?->forPair($brief->targetLang, $brief->supportLang) ?? [];
    }

    /**
     * Fresh examples for the terms an earlier day of this plan already taught.
     *
     * DORMANT SINCE v0.4, and deliberately still here. The v0.3 prompt asked for a `known` shelf
     * beside the six; the v0.4 canon names the known units as input only, so the schema no longer
     * permits that key and this reader finds nothing to file ({@see PlanSchemas::day()}). It costs
     * one array lookup per day and it is the whole feature, ready for the day the canon asks for
     * the shelf back — deleting it would make restoring a paragraph of prompt into a code change.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $known  term id → text
     * @return list<array{term_id: string, example: string, example_translation: string}>
     */
    private function knownExamples(array $payload, array $known): array
    {
        if ($known === []) {
            return [];
        }

        $byText = [];
        foreach ($known as $termId => $text) {
            $byText[mb_strtolower(trim($text))] = $termId;
        }

        $out = [];
        $rows = is_array($payload['known'] ?? null) ? $payload['known'] : [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $termId = $byText[mb_strtolower($this->text($row['text'] ?? ''))] ?? null;
            if ($termId === null) {
                // A term the model invented for the KNOWN block. Dropped rather than matched
                // loosely: an example written for a term we did not ask about would be filed
                // against whichever term the fuzzy match happened to pick.
                continue;
            }

            $examples = is_array($row['examples'] ?? null) ? $row['examples'] : [];
            foreach (array_slice($examples, 0, 2) as $example) {
                if (! is_array($example)) {
                    continue;
                }
                $sentence = $this->text($example['example'] ?? '');
                if ($sentence === '') {
                    continue;
                }
                $out[] = [
                    'term_id' => $termId,
                    'example' => $sentence,
                    'example_translation' => $this->text($example['example_translation'] ?? ''),
                ];
            }
        }

        return $out;
    }

    /** Case and punctuation off, spaces collapsed — the shape a line and a piece are compared in. */
    private static function fold(string $text): string
    {
        $folded = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', mb_strtolower(trim($text))) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $folded));
    }

    private function text(mixed $raw): string
    {
        return is_scalar($raw) ? trim((string) $raw) : '';
    }
}

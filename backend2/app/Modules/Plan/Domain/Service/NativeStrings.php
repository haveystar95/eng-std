<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\ValueObject\DayType;
use DateTimeImmutable;

/**
 * EVERY DYNAMIC STRING THAT INFLECTS comes from the server, ready to print (`docs/plan-v2.md` §10):
 * the countdown, the route summary, the slot of a day. The client formats dates and numbers; it
 * never conjugates. Three languages are written out; anything else falls back to English rather
 * than to a wrong ending.
 */
final class NativeStrings
{
    /** @var array<string, array<string, array{0: string, 1: string, 2: string}>> word → [one, few, many] */
    private const FORMS = [
        'ru' => [
            'day' => ['день', 'дня', 'дней'],
            'scene' => ['ситуация', 'ситуации', 'ситуаций'],
            'review' => ['повторение', 'повторения', 'повторений'],
            'line' => ['реплика', 'реплики', 'реплик'],
            'line_of' => ['реплику', 'реплики', 'реплик'],
            'phrase_of' => ['фразу', 'фразы', 'фраз'],
            'question_of' => ['вопроса', 'вопросов', 'вопросов'],
            'time' => ['раз', 'раза', 'раз'],
        ],
        'uk' => [
            'day' => ['день', 'дні', 'днів'],
            'scene' => ['ситуація', 'ситуації', 'ситуацій'],
            'review' => ['повторення', 'повторення', 'повторень'],
            'line' => ['репліка', 'репліки', 'реплік'],
            'line_of' => ['репліку', 'репліки', 'реплік'],
            'phrase_of' => ['фразу', 'фрази', 'фраз'],
            'question_of' => ['питання', 'питань', 'питань'],
            'time' => ['раз', 'рази', 'разів'],
        ],
        'en' => [
            'day' => ['day', 'days', 'days'],
            'scene' => ['situation', 'situations', 'situations'],
            'review' => ['review', 'reviews', 'reviews'],
            'line' => ['line', 'lines', 'lines'],
            'line_of' => ['line', 'lines', 'lines'],
            'phrase_of' => ['phrase', 'phrases', 'phrases'],
            'question_of' => ['question', 'questions', 'questions'],
            'time' => ['time', 'times', 'times'],
        ],
    ];

    /** @var array<string, array<string, string>> */
    private const WORDS = [
        'ru' => ['rehearsal' => 'репетиция', 'today' => 'сегодня', 'tomorrow' => 'завтра'],
        'uk' => ['rehearsal' => 'репетиція', 'today' => 'сьогодні', 'tomorrow' => 'завтра'],
        'en' => ['rehearsal' => 'rehearsal', 'today' => 'today', 'tomorrow' => 'tomorrow'],
    ];

    /**
     * «ЧТО БЫЛО ХОРОШО» (кадр 37-13) — the lines of the day's summary, assembled by the server and
     * printed by the client. Placeholders: `{n}` the number reached, `{of}` the number possible,
     * `{noun}` the noun agreed with `{n}` ({@see count()}).
     *
     * @var array<string, array<string, string>>
     */
    private const HIGHLIGHTS = [
        'ru' => [
            'said_self' => 'Сказал сам {n} {noun} из {of}',
            'phrases_used' => 'В разговоре использовал {n} {noun} из {of}',
            'understood_all' => 'Понял все вопросы',
            'understood_except' => 'Понял вопросы, кроме {n} {noun}',
        ],
        'uk' => [
            'said_self' => 'Сказав сам {n} {noun} із {of}',
            'phrases_used' => 'У розмові використав {n} {noun} із {of}',
            'understood_all' => 'Зрозумів усі питання',
            'understood_except' => 'Зрозумів питання, крім {n} {noun}',
        ],
        'en' => [
            'said_self' => 'Said {n} {noun} of {of} on your own',
            'phrases_used' => 'Used {n} {noun} of {of} in the conversation',
            'understood_all' => 'Understood every question',
            'understood_except' => 'Understood the questions but {n} {noun}',
        ],
    ];

    /** @var array<string, list<string>> the month a date is written with, January first — genitive where the language inflects it */
    private const MONTHS = [
        'ru' => ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'],
        'uk' => ['січня', 'лютого', 'березня', 'квітня', 'травня', 'червня', 'липня', 'серпня', 'вересня', 'жовтня', 'листопада', 'грудня'],
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    ];

    /** @var array<string, array{dated: string, undated: string}> the promise closing the plan summary */
    private const PROMISE = [
        'ru' => ['dated' => '{to} {day} {month} скажешь всё это сам', 'undated' => 'Скажешь всё это сам'],
        'uk' => ['dated' => 'До {day} {month} скажеш усе це сам', 'undated' => 'Скажеш усе це сам'],
        'en' => ['dated' => 'By {month} {day} you will say all of this yourself', 'undated' => 'You will say all of this yourself'],
    ];

    /**
     * THE TALK'S TITLE (кадр 37-5, наряд CONV-2, п. 12): «Поговори с врачом». `{with}` is the preposition, `{role}` the role
     * in the instrumental ({@see InstrumentalRole}); `anyone` is the title when the role cannot be inflected with
     * certainty — plain, and never a wrong ending.
     *
     * @var array<string, array{with: string, anyone: string}>
     */
    private const TALK_TITLE = [
        'ru' => ['with' => 'Поговори {with} {role}', 'anyone' => 'Поговори с собеседником'],
        'uk' => ['with' => 'Поговори {with} {role}', 'anyone' => 'Поговори зі співрозмовником'],
        'en' => ['with' => 'Talk to the {role}', 'anyone' => 'Talk to your partner'],
    ];

    /**
     * WHAT THE JUDGE SAYS WHEN THE CODE RULES «NO» (наряд CONV-2, п. 7): `nothing` — nothing was heard at all; `main` —
     * the frame's own words and nothing of the answer: «That works for me» to «which days?» (the owner's gym day,
     * 21.09). `{hint}` is the window's hint as the lesson wrote it («в какие дни это подходит»), so the line names what
     * is missing by its meaning, never «слово в пропуске» — the model's words for it, which the learner cannot act on.
     *
     * @var array<string, array{nothing: string, main: string}>
     */
    private const JUDGE = [
        'ru' => ['nothing' => 'Не расслышал — скажи ещё раз', 'main' => 'Не сказал главного — {hint}'],
        'uk' => ['nothing' => 'Не розчув — скажи ще раз', 'main' => 'Не сказав головного — {hint}'],
        'en' => ['nothing' => "Didn't catch that — say it again", 'main' => 'The main part is missing — {hint}'],
    ];

    /** How many scene titles the plan summary names. */
    public const SUMMARY_SCENES = 3;

    public function __construct(private readonly string $lang) {}

    /**
     * «Регистрация на рейс, заселение в отель, ресторан. К 17 сентября скажешь всё это сам» — the
     * first scene titles of the route, lowercased and joined, the first letter raised, then the
     * promise, dated when the plan has a date. Null when there is no title to name.
     *
     * @param  list<string>  $sceneTitles  the scene days' titles in route order
     */
    public function planSummary(array $sceneTitles, ?DateTimeImmutable $eventDate): ?string
    {
        $titles = [];
        foreach ($sceneTitles as $title) {
            $clean = trim($title);
            if ($clean !== '') {
                $titles[] = mb_strtolower($clean);
            }
            if (count($titles) === self::SUMMARY_SCENES) {
                break;
            }
        }
        if ($titles === []) {
            return null;
        }
        $joined = implode(', ', $titles);
        $joined = mb_strtoupper(mb_substr($joined, 0, 1)).mb_substr($joined, 1);

        $promise = self::PROMISE[$this->table()];
        $tail = $eventDate === null
            ? $promise['undated']
            : strtr($promise['dated'], [
                '{to}' => self::toBefore((int) $eventDate->format('j')),
                '{day}' => (string) (int) $eventDate->format('j'),
                '{month}' => self::MONTHS[$this->table()][(int) $eventDate->format('n') - 1],
            ]);

        return "{$joined}. {$tail}";
    }

    /**
     * Russian «к» / «ко» before the day of the month: «ко» only before 2 («ко 2 сентября» — «ко
     * второму»), «к» before everything else, 12 and 22 included («к 12», «к 22») — доработка PLAN-UI-3.
     */
    private static function toBefore(int $day): string
    {
        return $day === 2 ? 'Ко' : 'К';
    }

    /** «До приёма · 5 дней» — the prompt's `until_phrase_native` with the count the server knows. */
    public function untilPhrase(string $untilNative, int $daysLeft): string
    {
        return "{$untilNative} · ".$this->count($daysLeft, 'day');
    }

    /**
     * «5 дней · 3 ситуации, 1 повторение, репетиция».
     *
     * @param  list<DayType>  $layout
     */
    public function routeSummary(array $layout): string
    {
        $scenes = count(array_filter($layout, static fn (DayType $t): bool => $t === DayType::Scene));
        $reviews = count(array_filter($layout, static fn (DayType $t): bool => $t === DayType::Review));
        $rehearsal = in_array(DayType::Rehearsal, $layout, true);

        $parts = [$this->count($scenes, 'scene')];
        if ($reviews > 0) {
            $parts[] = $this->count($reviews, 'review');
        }
        if ($rehearsal) {
            $parts[] = $this->word('rehearsal');
        }

        return $this->count(count($layout), 'day').' · '.implode(', ', $parts);
    }

    public function today(): string
    {
        return $this->word('today');
    }

    public function tomorrow(): string
    {
        return $this->word('tomorrow');
    }

    /** «3 ситуации» — number and noun agreed. */
    public function count(int $n, string $noun): string
    {
        $forms = self::FORMS[$this->table()][$noun] ?? self::FORMS['en'][$noun];

        return $n.' '.$forms[$this->pluralIndex($n)];
    }

    /**
     * One line of «Что было хорошо» (кадр 37-13), ready to print: the number, the noun agreed with
     * it, and — where the line says «из M» — what it is out of.
     */
    public function highlight(string $key, int $n, string $noun, ?int $of = null): string
    {
        $template = self::HIGHLIGHTS[$this->table()][$key] ?? self::HIGHLIGHTS['en'][$key] ?? '';
        $forms = self::FORMS[$this->table()][$noun] ?? self::FORMS['en'][$noun] ?? ['', '', ''];

        return strtr($template, [
            '{n}' => (string) $n,
            '{noun}' => $forms[$this->pluralIndex($n)],
            '{of}' => (string) ($of ?? $n),
        ]);
    }

    /**
     * «Поговори с врачом» — the entry title of the talk, from the role of the scene it opens with, in the learner's
     * language. A role that cannot be inflected with certainty — or none — gives «Поговори с собеседником».
     */
    public function talkTitle(?string $roleNative): string
    {
        $titles = self::TALK_TITLE[$this->table()];
        $role = trim((string) $roleNative);
        if ($role === '') {
            return $titles['anyone'];
        }
        if ($this->table() === 'en') {
            $first = mb_substr($role, 0, 1);
            $second = mb_substr($role, 1, 1);
            $lowered = $second !== '' && mb_strtolower($second) === $second ? mb_strtolower($first).mb_substr($role, 1) : $role;

            return strtr($titles['with'], ['{role}' => $lowered]);
        }
        $instrumental = InstrumentalRole::of($this->table(), $role);

        return $instrumental === null
            ? $titles['anyone']
            : strtr($titles['with'], ['{with}' => InstrumentalRole::with($this->table(), $instrumental), '{role}' => $instrumental]);
    }

    /**
     * The judge's own «no» ({@see JUDGE}): `nothing`, or `main` with the window's hint.
     *
     * @param  'nothing'|'main'  $key
     */
    public function judgeReason(string $key, string $hint = ''): string
    {
        return strtr(self::JUDGE[$this->table()][$key], ['{hint}' => trim($hint)]);
    }

    private function word(string $key): string
    {
        return self::WORDS[$this->table()][$key] ?? self::WORDS['en'][$key];
    }

    private function table(): string
    {
        return isset(self::FORMS[$this->lang]) ? $this->lang : 'en';
    }

    /** 0 = one, 1 = few (2–4), 2 = many — the Slavic rule; English uses one / many. */
    private function pluralIndex(int $n): int
    {
        if ($this->table() === 'en') {
            return $n === 1 ? 0 : 2;
        }
        $mod10 = $n % 10;
        $mod100 = $n % 100;
        if ($mod10 === 1 && $mod100 !== 11) {
            return 0;
        }
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return 1;
        }

        return 2;
    }
}

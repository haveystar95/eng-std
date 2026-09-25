<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
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
     * THE TALK'S TITLE (кадр 37-5, наряд CONV-2, п. 12; several scenes — наряд FIX-4c §4): «Поговори с врачом», «Поговори с
     * регистратором и врачом». `{with}` is the preposition, said once before the first role, `{roles}` the roles in the
     * instrumental ({@see InstrumentalRole}) joined by `and`; `anyone` / `anyones` is the title when a role cannot be
     * inflected with certainty — plain, and never a wrong ending.
     *
     * Only the three languages whose roles the code can put in the right case, or needs in none, are here. Every other
     * native names the talk by its pack's `talk_title_template` — a neutral «Rozmowa: recepcjonistka i lekarz» that puts
     * no role in any case ({@see talkTitle()}, наряд LANG-1 §6).
     *
     * @var array<string, array{with: string, and: string, anyone: string, anyones: string}>
     */
    private const TALK_TITLE = [
        'ru' => ['with' => 'Поговори {with} {roles}', 'and' => 'и', 'anyone' => 'Поговори с собеседником', 'anyones' => 'Поговори с собеседниками'],
        'uk' => ['with' => 'Поговори {with} {roles}', 'and' => 'і', 'anyone' => 'Поговори зі співрозмовником', 'anyones' => 'Поговори зі співрозмовниками'],
        'en' => ['with' => 'Talk to {roles}', 'and' => 'and', 'anyone' => 'Talk to your partner', 'anyones' => 'Talk to your partners'],
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

    /**
     * THE SAME LINES SAID OF A LEARNER WHO IS A WOMAN (наряд FIX-3 §1): a line about the learner ends as the learner's
     * gender asks — «Сказала сама», «скажешь всё это сама» — by the profile ({@see $learner}); a profile that says
     * nothing reads the masculine, the way the lesson writes an unavoidable past form. By the key of the table the line
     * comes from: `highlight.*`, `promise.*`, `judge.*`. English needs none.
     *
     * @var array<string, array<string, string>>
     */
    private const FEMININE = [
        'ru' => [
            'highlight.said_self' => 'Сказала сама {n} {noun} из {of}',
            'highlight.phrases_used' => 'В разговоре использовала {n} {noun} из {of}',
            'highlight.understood_all' => 'Поняла все вопросы',
            'highlight.understood_except' => 'Поняла вопросы, кроме {n} {noun}',
            'promise.dated' => '{to} {day} {month} скажешь всё это сама',
            'promise.undated' => 'Скажешь всё это сама',
            'judge.main' => 'Не сказала главного — {hint}',
        ],
        'uk' => [
            'highlight.said_self' => 'Сказала сама {n} {noun} із {of}',
            'highlight.phrases_used' => 'У розмові використала {n} {noun} із {of}',
            'highlight.understood_all' => 'Зрозуміла усі питання',
            'highlight.understood_except' => 'Зрозуміла питання, крім {n} {noun}',
            'promise.dated' => 'До {day} {month} скажеш усе це сама',
            'promise.undated' => 'Скажеш усе це сама',
            'judge.main' => 'Не сказала головного — {hint}',
        ],
    ];

    /** How many scene titles the plan summary names. */
    public const SUMMARY_SCENES = 3;

    /** @param  VoiceGender|null  $learner  the learner's gender by their profile — null: not said, the masculine is read */
    public function __construct(private readonly string $lang, private readonly ?VoiceGender $learner = null) {}

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
            ? $this->gendered('promise.undated', $promise['undated'])
            : strtr($this->gendered('promise.dated', $promise['dated']), [
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
        $template = $this->gendered("highlight.{$key}", self::HIGHLIGHTS[$this->table()][$key] ?? self::HIGHLIGHTS['en'][$key] ?? '');
        $forms = self::FORMS[$this->table()][$noun] ?? self::FORMS['en'][$noun] ?? ['', '', ''];

        return strtr($template, [
            '{n}' => (string) $n,
            '{noun}' => $forms[$this->pluralIndex($n)],
            '{of}' => (string) ($of ?? $n),
        ]);
    }

    /**
     * «Поговори с врачом» — the entry title of the talk, from the roles of the scenes it walks, in the order it walks
     * them, in the learner's language (наряд FIX-4c §4): one scene — «Поговори с врачом»; two — «Поговори с регистратором и
     * врачом»; three or more — «Поговори с регистратором, врачом и медсестрой». The preposition is said once, by the
     * first role («со стоматологом и врачом»); a role two scenes share is said once. A role that cannot be inflected with
     * certainty — or none at all — gives «Поговори с собеседником», and «Поговори с собеседниками» when the talk has
     * several people: never a wrong ending.
     *
     * ANY OTHER NATIVE (наряд LANG-1 §6; дополняет пп. 375, 417): the English fallback printed the learner's own role
     * nouns inside English words — «Talk to the recepcjonistka and the lekarz». A native whose pack writes
     * `talk_title_template` gets a title that inflects nothing ({@see neutralTalkTitle()}); ru, uk and en keep theirs
     * whatever the pack says, and a native whose pack has no template keeps the English fallback, as before.
     *
     * @param  list<string>  $rolesNative
     * @param  LanguagePack|null  $native  the pack of this native — null, or a pack without the template: the fallback
     */
    public function talkTitle(array $rolesNative, ?LanguagePack $native = null): string
    {
        $template = isset(self::TALK_TITLE[$this->lang]) ? null : self::talkTemplate($native);
        if ($template !== null) {
            return self::neutralTalkTitle($rolesNative, $template);
        }

        $titles = self::TALK_TITLE[$this->table()];
        $forms = [];
        $people = [];
        $unsure = false;
        foreach ($rolesNative as $roleNative) {
            $role = trim((string) preg_replace('/\s+/u', ' ', $roleNative));
            if ($role === '') {
                continue;
            }
            $people[mb_strtolower($role)] = true;
            $form = $this->table() === 'en' ? 'the '.self::lowerFirst($role) : InstrumentalRole::of($this->table(), $role);
            if ($form === null) {
                $unsure = true;

                continue;
            }
            $forms[mb_strtolower($form)] ??= $form;
        }
        if ($people === [] || $unsure) {
            return count($people) > 1 ? $titles['anyones'] : $titles['anyone'];
        }
        $forms = array_values($forms);
        $last = (string) array_pop($forms);
        $roles = $forms === [] ? $last : implode(', ', $forms).' '.$titles['and'].' '.$last;

        return strtr($titles['with'], [
            '{with}' => $this->table() === 'en' ? '' : InstrumentalRole::with($this->table(), $forms[0] ?? $last),
            '{roles}' => $roles,
        ]);
    }

    /**
     * THE PACK'S TITLE OF A TALK (наряд LANG-1 §6) — `talk_title_template` as {@see talkTitle()} reads it:
     *
     * - `title` — «Rozmowa: {roles}», the roles put in `{roles}` as they are written, in no case;
     * - `and` — «i», said once, before the last role: «a, b i c»;
     * - `anyone` — «Rozmowa», the whole title when the talk has no role to name;
     * - `lower_first` — true: «Lekarz» → «lekarz» (an acronym keeps its capitals); false where a noun keeps its capital
     *   in the middle of a sentence (de «Gespräch: Rezeptionistin und Arzt»); absent reads true;
     * - `and_before` — optional, pattern → the word said instead of `and` before a last role the pattern matches, as it
     *   is printed: es «médico e internista», «y» still before «hie-» (`['/^h?[ií](?![aeouáéóú])/iu' => 'e']`); absent or
     *   `[]` — `and` everywhere.
     *
     * A template without its three strings is no template — the English fallback, never a title with a hole in it; nor
     * is a `title` with no «{roles}» in it, which would name nobody in a talk that has people — the same line
     * {@see LanguagePack::talkTitleTemplate()} draws (there it throws, for the pack-shape test; here the title is only
     * printed, so it falls back instead).
     *
     * @return array{title: string, and: string, anyone: string, lower_first: bool, and_before: array<string, string>}|null
     */
    private static function talkTemplate(?LanguagePack $native): ?array
    {
        if ($native === null || ! $native->has('talk_title_template')) {
            return null;
        }
        $template = $native->map('talk_title_template');
        $title = $template['title'] ?? null;
        $and = $template['and'] ?? null;
        $anyone = $template['anyone'] ?? null;
        if (! is_string($title) || ! str_contains($title, '{roles}') || ! is_string($and) || trim($and) === '' || ! is_string($anyone) || trim($anyone) === '') {
            return null;
        }
        $before = [];
        $patterns = $template['and_before'] ?? [];
        foreach (is_array($patterns) ? $patterns : [] as $pattern => $word) {
            if (is_string($pattern) && $pattern !== '' && is_string($word) && trim($word) !== '') {
                $before[$pattern] = trim($word);
            }
        }

        return [
            'title' => $title,
            'and' => trim($and),
            'anyone' => $anyone,
            'lower_first' => ($template['lower_first'] ?? true) !== false,
            'and_before' => $before,
        ];
    }

    /**
     * «Rozmowa: recepcjonistka i lekarz» — the roles in the order the talk walks them, each said once (a role two scenes
     * share, whatever its capitals), joined «a, b i c», put into the template's title; no role at all — its `anyone`. Nothing
     * is inflected, so nothing can be a wrong ending: the title names the roles, it does not speak to them.
     *
     * @param  list<string>  $rolesNative
     * @param  array{title: string, and: string, anyone: string, lower_first: bool, and_before: array<string, string>}  $template
     */
    private static function neutralTalkTitle(array $rolesNative, array $template): string
    {
        $roles = [];
        foreach ($rolesNative as $roleNative) {
            $role = trim((string) preg_replace('/\s+/u', ' ', $roleNative));
            if ($role === '') {
                continue;
            }
            $roles[mb_strtolower($role)] ??= $template['lower_first'] ? self::lowerUnlessAcronym($role) : $role;
        }
        if ($roles === []) {
            return $template['anyone'];
        }
        $roles = array_values($roles);
        $last = (string) array_pop($roles);
        $and = $template['and'];
        foreach ($template['and_before'] as $pattern => $word) {
            if (preg_match($pattern, $last) === 1) {
                $and = $word;

                break;
            }
        }
        $joined = $roles === [] ? $last : implode(', ', $roles).' '.$and.' '.$last;

        return strtr($template['title'], ['{roles}' => $joined]);
    }

    /**
     * «Lekarz» → «lekarz», «Рэгістратар» → «рэгістратар», but an acronym keeps its capitals: a first word with two
     * capitals or more («HR-menedżer», «IT-specjalista», «DJ») is left as written. Only the first word is asked, so
     * «Specjalista IT» still reads «specjalista IT».
     */
    private static function lowerUnlessAcronym(string $role): string
    {
        $first = explode(' ', $role, 2)[0];
        if (preg_match_all('/\p{Lu}/u', $first) >= 2) {
            return $role;
        }

        return mb_strtolower(mb_substr($role, 0, 1)).mb_substr($role, 1);
    }

    /** «Doctor» → «doctor», but «HR manager» keeps its capitals: a first letter goes lower only before a lower one. */
    private static function lowerFirst(string $role): string
    {
        $first = mb_substr($role, 0, 1);
        $second = mb_substr($role, 1, 1);

        return $second !== '' && mb_strtolower($second) === $second ? mb_strtolower($first).mb_substr($role, 1) : $role;
    }

    /**
     * The judge's own «no» ({@see JUDGE}): `nothing`, or `main` with the window's hint.
     *
     * @param  'nothing'|'main'  $key
     */
    public function judgeReason(string $key, string $hint = ''): string
    {
        return strtr($this->gendered("judge.{$key}", self::JUDGE[$this->table()][$key]), ['{hint}' => trim($hint)]);
    }

    /** The line as said of this learner: the feminine one when the profile says so and the language has it. */
    private function gendered(string $key, string $line): string
    {
        return $this->learner === VoiceGender::Female ? (self::FEMININE[$this->table()][$key] ?? $line) : $line;
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

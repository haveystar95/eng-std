# Тренажёры v3 — спека

**Статус:** архитектурная спека, код не менялся. Разведка read-only по `backend2/` + `mobile/`
на ветке `feat/mobile-backend2-cutover` (HEAD `c3e8952`).

**Что делаем:** три новых тренажёра — `sentence_scramble`, `dictation`, `which_sentence_correct` —
**на существующем механизме**: `enabled_modes` + гейты по данным + веерная лестница, ровно так же,
как в своё время вставали `listening` и `cloze`.

**Что НЕ делаем (отложено, см. «Часть 2»):** перестройку планировщика — learning-политику в БД,
динамику встреч/успехов дня-0, FSRS-кандидат вместо `Sm2Scheduler`. `Sm2Scheduler` и текущая
лестница в этой работе не трогаются.

**Параллельно идёт** сессия офлайн-сборщика практики (фаза 2, `mobile/lib/data/practice/`) —
спека учитывает её и ничего в `app/`/`mobile/` не правит.

---

## Решено (спека принята)

Все семь открытых вопросов закрыты пользователем. Раздел «Открытые вопросы» в конце оставлен как
история решения — **источник правды здесь**.

| # | Вопрос | Решение |
|---|---|---|
| 1 | Пороги гейта `scramble` | **4…12 токенов** (96.1% примеров текущей базы) |
| 2 | `dictation` целиком/фрагмент | **целиком, константой** в v1; `dictation_scope` — поле политики (Часть 2) |
| 3 | Верхняя граница `dictation` | **4…10 токенов** — своя, жёстче чем у scramble: набор с клавиатуры дороже сборки из чипов |
| 4 | Эталон = предложение | **`TermAnswerKeyView` расширяем полем `example`**; уточнение формулировки инварианта в `.claude/skills/learning-srs` идёт **в одном коммите с кодом scramble**, не отдельно |
| 5 | `varchar(16)` | **имена режимов короткие: `scramble` / `dictation` / `pick_correct`**; колонка `varchar(16)` остаётся, миграции ширины нет |
| 6 | Опций у `pick_correct` | **3 (1 верное + 2 дистрактора)** → гейт `distractorCount >= 2` |
| 7 | Бэкфилл дистракторов | **только после eval** на 5 вычитанных вручную коллекциях; массовый прогон — отдельным решением по результату |

**Что из этого меняет тело спеки:**

- везде ниже читать имена режимов как `scramble`, `dictation`, `pick_correct` (в тексте они
  встречаются в длинной форме `sentence_scramble` / `which_sentence_correct` — это тот же режим);
- находка **Б1 снята**: `sentence_scramble` (17) и `which_sentence_correct` (22) не влезали в
  `varchar(16)`, короткие имена (`scramble` = 8, `dictation` = 9, `pick_correct` = 12) влезают.
  Миграция теперь только добавляет значения в `reviews_exercise_mode_check`;
- в коммите №2 (scramble) появляется правка скилла `learning-srs` — она часть коммита, а не хвост;
- гейт `dictation` — собственные константы `MIN_DICTATION_TOKENS = 4` / `MAX_DICTATION_TOKENS = 10`,
  а не переиспользование scramble-порогов;
- `OPTION_COUNT` для `pick_correct` = 3, в отличие от 4 у `multiple_choice`;
- шаг «бэкфилл 822 примеров» уходит из коммита №4 — там остаётся генерация на 5 коллекций + eval.

---

## 1. Где живёт механизм сегодня

### 1.1 Состав режимов — единственный переключатель

| Что | Где |
|---|---|
| Список включённых режимов | [`backend2/config/learning.php:10`](../../backend2/config/learning.php) — `enabled_modes` |
| Синглтон `EnabledModes` из конфига | [`LearningServiceProvider.php:76-82`](../../backend2/app/Modules/Learning/Infrastructure/Provider/LearningServiceProvider.php) (внимание: **второй** хардкод-список в fallback-дефолте `config(...)`) |
| Enum значений + потолок грейда | [`Domain/ValueObject/ExerciseMode.php`](../../backend2/app/Modules/Learning/Domain/ValueObject/ExerciseMode.php) — `maxGrade()`, `isProduction()` |
| Набор + `only()`/`first()` | [`Domain/ValueObject/EnabledModes.php`](../../backend2/app/Modules/Learning/Domain/ValueObject/EnabledModes.php) |

### 1.2 Лестница

Вся в [`Domain/Service/ExerciseSelector.php`](../../backend2/app/Modules/Learning/Domain/Service/ExerciseSelector.php), два входа:

- **SRS** — `select()` (строки 50–89): `known` → typing; reps 0 → multiple_choice;
  learning/relearning reps ≥ 1 → `[base, listening, cloze?]` со сдвигом `reps-1`; review →
  `[typing, listening, cloze?]` со сдвигом `reps`. Ротация по счётчику повторов, не `rand()`.
- **Free practice** — `selectForPractice()` + `applicableModes()` (строки 102–135): веер по **всем**
  применимым режимам, round-robin по `cardIndex + crc32(termId)`.

Деградация всегда только внутри `EnabledModes` — невключённый режим выдать невозможно.

### 1.3 Сборка карточки и провод

`StudyCardAssembler` ([`Application/Service/StudyCardAssembler.php`](../../backend2/app/Modules/Learning/Application/Service/StudyCardAssembler.php))
считает признаки термина (`$clozeable` строки 56–59, `$wordCount` строка 60), зовёт селектор и
докладывает mode-specific extras: `options` для multiple_choice, `chips` для word_bank.
Дальше `SessionCardView` → `SessionResource` → клиент.

### 1.4 Полный список мест, где режимы перечислены (что придётся тронуть)

Это и есть ответ на «как встают три режима без новых хардкод-списков»: **новых списков заводить не
надо — надо расширить существующие десять и не добавить одиннадцатый.**

**Сервер**

1. `config/learning.php:10` — `enabled_modes` ← *единственная точка правды о составе*
2. `LearningServiceProvider.php:78` — fallback-дефолт `config()` (дублирующий список; **кандидат на
   удаление** — пусть падает громко, а не тихо на трёх режимах)
3. `ExerciseMode.php` — enum-кейсы + `maxGrade()` match (exhaustive → PHPStan поймает)
4. `ExerciseSelector.php:71-81` — массивы лестниц; `:124-128` — match применимости
5. `StudyCardAssembler.php:70-76` — ветки extras
6. `SubmitReviewsRequest.php:49` — `in:multiple_choice,word_bank,typing,listening,cloze`
7. Миграция `2026_07_30_150000_add_grading_columns_to_reviews.php:24,30` —
   **`varchar(16)`** + `CHECK (exercise_mode IN (...))`
8. `openapi/openapi.yaml:718` и `:1282` — два enum-списка

**Клиент**

9. `mobile/lib/data/models.dart:139-154` — enum `ExerciseMode` (wire) + `fromWire` (fallback `typing`) + `isTyped`
10. `mobile/lib/data/practice/practice_mode_selector.dart:16-22` — `PracticeModes.serverDefault`
    (**порядок = контракт** round-robin) + `applicableModes` switch (`:71-75`)
11. `local_session_builder.dart:83-93` — ветки extras
12. `session/session_exercise.dart` — рендер (`_promptCard:333`, `_instructionFor:342`) и аффордансы
13. `session/session_grading.dart:phaseFor` — exhaustive switch (компилятор поймает)

**Контракт между ними**

14. `backend2/tests/Fixtures/practice-mode-contract.json`, генерится
    `tests/Unit/Learning/ExerciseSelectorTest.php:230-273` (`EXPORT_PRACTICE_FIXTURE=1`),
    сверяется `mobile/test/data/practice/practice_mode_selector_contract_test.dart`

### 1.5 Две находки, которые блокируют работу до её начала

> **Б1. `reviews.exercise_mode` — `varchar(16)`.** `sentence_scramble` = 17 символов,
> `which_sentence_correct` = 22. Не влезают. Либо расширить колонку (рекомендация: `varchar(32)`,
> заодно снять скрытый лимит на будущие режимы), либо укоротить имена (`scramble`, `pick_correct`).
> Плюс пересоздать `reviews_exercise_mode_check`. → открытый вопрос №5.

> **Б2. Выбор примера недетерминирован.** `EloquentTermContentReader.php:28-30` берёт первый
> попавшийся `term_examples` без `ORDER BY` (`$examples[$term_id] ??= $row`). В текущей базе
> **50 терминов из 766 имеют 2–3 примера** — для них карточка может показать разный пример между
> запросами, а клиент замиррорил один-единственный. Для scramble/dictation это «разные задания на
> одно слово», для `which_sentence_correct` — прямая поломка (дистракторы привязаны к `example_id`).
> **Фикс обязателен и идёт первым коммитом:** `ORDER BY created_at, id` + отдавать `example_id`.

---

## 2. Общие правила для трёх режимов

### 2.1 Гейты по данным — в одном месте (аналог `clozeable`)

Сегодня «что можно дать этому термину» размазано по четырём точкам: ассемблер считает признаки,
`select()` их принимает параметрами и втыкает тернарниками в лестницы, `applicableModes()` повторяет
их отдельным match, клиент дублирует все три. Три новых режима добавляют ещё два предиката —
без единого места это 5 предикатов × 4 точки.

**Вводим `Learning/Domain/ValueObject/TermPlayability`** (чистый VO, без Laravel):

```php
final readonly class TermPlayability
{
    public function __construct(
        public int  $answerWordCount,        // word_bank ≥ 2
        public bool $clozeable,              // cloze
        public int  $exampleTokenCount,      // sentence_scramble: MIN..MAX
        public bool $hasExampleTranslation,  // sentence_scramble: промпт задания
        public bool $hasExample,             // dictation
        public int  $distractorCount,        // which_sentence_correct ≥ 2
    ) {}

    /** ЕДИНСТВЕННОЕ место, где живут правила применимости. */
    public function supports(ExerciseMode $mode): bool { /* один match */ }
}
```

Следствия:

- `ExerciseSelector::select()` / `selectForPractice()` принимают `TermPlayability` вместо
  `(int $answerWordCount, bool $clozeable)`;
- `applicableModes()` = `array_filter($enabled->modes, $playability->supports(...))`;
- **из лестниц уходят тернарники** — `$clozeable ? [a,b,c] : [a,b]` превращается в упорядоченный
  список, отфильтрованный тем же `supports()`. Лестница становится **данными**, а не веткой —
  именно это условие делает переезд в политику (Часть 2) механическим;
- пороги (`MIN_SCRAMBLE_TOKENS`, `MAX_SCRAMBLE_TOKENS`, `MIN_DISTRACTORS`, `2` для word_bank) —
  именованные константы VO, будущие поля политики;
- клиентское зеркало `TermPlayability` в `practice_mode_selector.dart`, пиннится той же фикстурой
  (в кейсы добавляются `example_token_count`, `has_example_translation`, `distractor_count`).

Это **отдельный первый коммит без изменения поведения**: фикстура после рефактора обязана
перегенериться байт-в-байт одинаковой.

### 2.2 Куда режимы встают в лестнице

Гейты уже отфильтруют неподходящее, поэтому вставка — это добавление элемента в список:

| Ветка | Сейчас | Становится |
|---|---|---|
| `known` (верификация) | `typing` | без изменений |
| reps 0 | `multiple_choice` | без изменений — узнавание, тут не место продукции |
| learning/relearning reps ≥ 1 | `[base, listening, cloze]` | `[base, listening, cloze, sentence_scramble, which_sentence_correct]` |
| review | `[typing, listening, cloze]` | `[typing, listening, cloze, dictation, sentence_scramble, which_sentence_correct]` |

Логика распределения: `dictation` — производство целого предложения на слух, самое дорогое, только
в `review`. `sentence_scramble` и `which_sentence_correct` — узнавание/сборка, годятся уже с
learning. Порядок внутри списка = порядок ротации; новое встаёт **в конец**, чтобы не переставить
ротацию уже учащимся словам.

`selectForPractice` менять не нужно вообще — веер сам подхватит новые режимы из `EnabledModes`.

### 2.3 Грейдинг: что не меняется

- **Сервер — единственный грейдер.** Клиент шлёт сырой ответ (`reviews[].response`, text), сервер
  грейдит в `AnswerGrader`. Никаких новых форматов ответа (никаких массивов индексов чипов).
- **Клиентская проверка никогда не строже серверной** — `SessionGrader.check` остаётся как есть,
  он уже режимо-независим (нормализация + 1-символьный тайпо).
- **Потолки грейда** (`ExerciseMode::maxGrade()`):
  - `sentence_scramble` → `Good` (все токены даны — узнавание/сборка, как word_bank);
  - `which_sentence_correct` → `Good` (выбор из вариантов);
  - `dictation` → `Easy` (свободный ввод из памяти/на слух — продукция, как typing/listening).
  Инвариант «recognition modes never emit easy» соблюдён.
- **Латентность** (`LatencyBaseline` per (user, mode)) — новые режимы стартуют с абсолютных
  дефолтов. Для всех трёх эталон — **предложение**, поэтому `ExpectedAnswer::isPhrase` должен быть
  `true`, иначе сработают `WORD_SLOW_MS = 8000` и любая сборка из 8 чипов уедет в `hard`.

### 2.4 Развилка, которую надо решить: эталон = предложение

`SubmitReviewsHandler` берёт эталон только из `TermAnswerKeyReader` — а тот по инварианту отдаёт
**собственные формы термина**. Для всех трёх новых режимов проверяется **предложение-пример**.

Предложение: расширить `TermAnswerKeyView` полями `example` + `exampleId` (nullable) и выбирать
эталон **по режиму** в `AnswerGrader`/handler: scramble/dictation/which_sentence → `example`,
остальные → `accepted` формы.

Это не обход инварианта, а его уточнение: правило «ключ — формы термина, никаких
`term_translations`» защищает от подсовывания перевода в ответ. Здесь спрашивают не термин, а
предложение — и эталон это ровно то самое предложение из `term_examples`, перевод в ключ по-прежнему
не попадает. **Но формулировку инварианта в `.claude/skills/learning-srs` придётся обновить явно,
иначе `invariant-reviewer` справедливо зарубит диф.** → открытый вопрос №4.

Соответственно `SessionCardView.answer` для этих режимов = предложение (поле по своему определению —
«the target answer, for grading feedback», так что это чтение контракта, а не хак). Единственное
клиентское следствие: `_FeedbackBlock` не должен дублировать `example` под ответом-предложением —
одна ветка по флагу «эталон = предложение».

---

## 3. (a) `sentence_scramble`

Собрать предложение-пример из чипов-слов. По сути word_bank, поднятый с уровня слова на уровень
предложения — и это главное, что позволяет почти не писать нового кода.

### 3.1 Токенизация: сервер или клиент — оба, по одному правилу

Сервер токенизирует и кладёт перемешанные токены в **существующее** поле `chips`
(`SessionCardView.chips`) — словарь карточки не расширяется. Но офлайн-практика собирается на
устройстве, где сервера нет, поэтому клиент токенизирует сам. Значит правило одно, реализаций две —
ровно как сегодня с `ChipShuffler` ↔ `_chips()` и `crc32`, и пиннится той же контракт-фикстурой.

**Правила (фиксируем; проценты — по 822 примерам текущей базы):**

| Правило | Почему |
|---|---|
| split по `\s+` (unicode) | как `ChipShuffler::words()` |
| внутрисловный апостроф **остаётся** в токене (`don't`, `teacher's`) | 10.8% примеров содержат его; `don` + `'t` — каша |
| финальная `.!?` **снимается** с последнего токена и чипом не показывается | 99.3% примеров её имеют → чип-точка был бы бесполезной константой в каждом задании |
| внутренняя пунктуация **остаётся приклеенной** к своему токену (`morning,`) | 14.8% примеров с запятой; отдельный чип-запятая не несёт смысла, но добавляет позицию |
| регистр **не понижается**, первый токен остаётся с заглавной | грейдер регистр игнорирует (`LexicalNormalizer`), так что это не ужесточает задание; зато `example` остаётся источником порядка байт-в-байт |
| перемешка **гарантированно ≠ оригинал** | уже реализовано в `ChipShuffler::chips()` (retry ×10) |
| декой-чипов **нет** в v1 | у word_bank они есть только для phrasal verb; на предложении лишний чип резко поднимает сложность — отложить |

### 3.2 Source of truth порядка

`term_examples.sentence` — та же строка, что уже едет в карточке как `example` и в `/sync`.
Новых колонок не нужно. Предварительное условие — фикс Б2 (детерминированный выбор примера).

### 3.3 Контракт ответа и грейдинг

Клиент шлёт в `response` **собранную строку**, токены через один пробел — ровно как word_bank
сегодня (`_assembled = _placed.map(...).join(' ')`, `session_exercise.dart:261`). Индексы чипов не
шлём: `response` — это text, и сервер грейдит текст.

Грейдинг — существующий `AnswerGrader` без новых стадий:
нормализация (регистр, пунктуация → пробел, схлоп пробелов, контракции) приводит `She goes to work.`
и `she goes to work` к одному виду; тайпо-леница (levenshtein = 1) на собранном из чипов
предложении практически недостижима — перестановка слов даёт большую дистанцию, пропуск короткого
токена (`to`) даёт 3 → `again`. Это правильное поведение.

### 3.4 Гейт по данным

`MIN_SCRAMBLE_TOKENS ≤ exampleTokenCount ≤ MAX_SCRAMBLE_TOKENS` **и** `hasExampleTranslation`
(перевод предложения — это промпт задания «собери это по-английски»; в текущей базе он есть у
100% примеров, но колонка nullable, поэтому гейт нужен).

Распределение длин по 822 примерам (`term_examples`, prod-база юзера):

| токенов | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 | 11 | 12 | 13 | 14 | 15 | 16 |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| примеров | 4 | 19 | 35 | 81 | 138 | 147 | 151 | 116 | 63 | 40 | 17 | 3 | 6 | 2 |

Покрытие: `≥4` → 99.5%, `≥5` → 97.2%, `≥6` → 92.9%; `≤10` → 84%, `≤12` → 98.7%.
**Рекомендация: 4…12 → 790/822 = 96.1%** (ниже 4 собирать нечего — 3 чипа это 6 вариантов;
выше 12 экран заполняется чипами и цена одной ошибки становится непропорциональной).
→ открытый вопрос №1.

### 3.5 Клиент

Переиспользуется почти целиком: `_chipTray` + `_AssemblyLine` + кнопка «Проверить» (F12) + `_giveUp`
(«Не помню»). Отличия: промпт карточки = `example_translation` вместо перевода термина; перенос
строк в сборочной линии; новая l10n-строка инструкции (RU-источник + EN).

---

## 4. (b) `dictation`

TTS проговаривает предложение-пример → пользователь набирает его.

### 4.1 Что переиспользуем — и что переиспользовать нечего

**Из listening (без единой правки в `Pronouncer`):**

- вся аудиосессия F20-r/F20-r2: `.playback` + `mixWithOthers`, `autoStopSharedSession(false)`,
  один `warmUp()` на сессию, один `release()` на выходе, `_wakeRoute()` перед речью
  (`mobile/lib/data/pronouncer.dart:56-124`);
- автоплей при появлении карточки — тот же `_afterTransition(..., delay: 100ms)` с гардом
  `isCurrent()` (`session_exercise.dart:154-164`). В `initState` условие `if (_isListening)`
  становится предикатом `if (_playsOnAppear)` — **расширение условия, не копия ветки**;
- «замедленно» — `onSpeak(text, slow: true)` (`_rateSlow = 0.30`), тот же `QuietButton`;
- большой `_PlayCircle` для повтора.

> **TTS-кэша не существует.** `Pronouncer` всегда синтезирует системным TTS; `audioUrl` не
> проигрывается вовсе (`TODO(audio-override)`, `pronouncer.dart:96-101`), и ни один термин его не
> несёт. Кэшируются только *параметры движка* (`_lastLocale`/`_lastRate`) — это переиспользуется
> автоматически. Практический вывод: **dictation не добавляет сетевого аудио и работает офлайн**,
> как listening. Никакого «кэша озвучек» проектировать не надо.

**Из typing (один код, не копия):**

- `_inputField()`, `_submitTyped()`, нормализация — как есть;
- **«Не помню»** — `_giveUp()` → `_commit('', usedHint: _usedHint)`. Кнопка рисуется по условию
  `_mode.isTyped` (`session_exercise.dart:306`), поэтому достаточно добавить `dictation` в
  `ExerciseMode.isTyped` (`models.dart:154`) — одна строка, ветка не дублируется;
- «Первая буква» (`_useFirstLetter`) работает как есть — берёт первый символ `answer`, который
  здесь предложение;
- клавиатура: как listening, **не** автофокусим (сначала дать услышать).

### 4.2 Целиком vs фрагмент

**v1 — целиком, константой** (`DICTATION_SCOPE = full`): озвучка целого предложения — то, что TTS
делает естественно, а резка на фрагменты требует правил границ и осмысленной озвучки обрывка.

Отмечено как **будущая крутилка**: `dictation_scope: full | clause | window(N)` — поле
**политики** (Часть 2), а не вторая константа в конфиге, чтобы не заводить второй переключатель
рядом с `enabled_modes`. → открытый вопрос №2.

### 4.3 Гейт по данным

`hasExample` + верхняя граница длины (набирать 16 слов с клавиатуры — наказание, а не тренировка).
Нижняя граница здесь не нужна: короткое предложение диктуется прекрасно.
→ открытый вопрос №3 (та же верхняя граница, что у scramble, или своя, более жёсткая).

---

## 5. (c) `which_sentence_correct`

Одно верное предложение + 2–3 дистрактора с типичной ошибкой; выбрать верное.

### 5.1 Схема (владелец — Vocabulary, там же где `term_examples`)

```sql
CREATE TABLE example_distractors (
    id                char(26)    PRIMARY KEY,
    example_id        char(26)    NOT NULL REFERENCES term_examples(id) ON DELETE CASCADE,
    sentence          text        NOT NULL,
    error_type        varchar(24) NOT NULL,   -- article|preposition|tense|word_order|false_friend|agreement
    error_span        text        NULL,       -- подстрока sentence с ошибкой   → find_the_mistake
    correction        text        NULL,       -- чем её заменить                → find_the_mistake
    generator_version varchar(16) NOT NULL,   -- 'v1' — версия промпта, как prompt_version
    created_at        timestamptz NOT NULL,
    updated_at        timestamptz NOT NULL    -- нужен для окна (since, upper] в /sync
);
CREATE INDEX example_distractors_example_idx  ON example_distractors (example_id);
CREATE INDEX example_distractors_updated_idx  ON example_distractors (updated_at);
CREATE UNIQUE INDEX example_distractors_uidx  ON example_distractors (example_id, sentence);
ALTER TABLE example_distractors ADD CONSTRAINT example_distractors_error_type_check
    CHECK (error_type IN ('article','preposition','tense','word_order','false_friend','agreement'));
```

Заметки:

- **`error_span`/`correction` заполняются сразу**, хотя `which_sentence_correct` их не читает — это
  закладка под `find_the_mistake` (режим **не реализуем**). Валидация на импорте обязательна
  (`error_span` — подстрока `sentence`), иначе будущий режим получит мусор, который сейчас никто не
  заметит.
- `generator_version` — по образцу `prompt_version` у генерации коллекций: позволяет пере-генерить
  всё сделанное старым промптом и сравнить eval'ы.
- **`ON DELETE CASCADE` не декоративен:** `EloquentTermExampleWriter::replace` (кнопка «Новый
  пример», `RegenerateExampleHandler`) **удаляет** строку `term_examples` и вставляет новую с новым
  id. Дистракторы уедут вместе со старым примером, термин перестанет проходить гейт и карточка
  честно деградирует в другой режим. Правильное поведение — **при условии**, что после
  `ReplaceTermExample` в очередь ставится пере-генерация; иначе «Новый пример» тихо выключает режим
  для термина.
- `UNIQUE (example_id, sentence)` — защита от повторного прогона джобы.

### 5.2 Джоба генерации (по образцу `AttachImagesJob`)

```
Generation/Infrastructure/Job/GenerateExampleDistractorsJob
    tries = 3, timeout = 120, backoff = [10, 30, 60]
    failed() → Log::warning; режим просто не предлагается (best-effort, ровно как картинки)
```

Обвязка — как у enrichment/картинок, всё кросс-модульно через Application:

- `Generation/Application/Command/GenerateExampleDistractors(+Handler)`;
- порт `ExampleDistractorGeneratorPort` + `OpenAiExampleDistractorGenerator` (Structured Outputs,
  strict JSON-schema — как `OpenAiExampleRegenerator`) + `FakeExampleDistractorGenerator` для тестов;
- версионируемый промпт `Infrastructure/Prompt/example_distractors.v1.md`;
- запись — через новую Vocabulary Application-команду `ImportExampleDistractors` (Generation не
  трогает таблицы Vocabulary — паттерн `ImportTerm` / `ReplaceTermExample`);
- Vocabulary Query `ExampleDistractorReader` (для карточки) + `DistractorlessExampleReader`
  (батч: какие примеры ещё без дистракторов) — аналог `PendingTermImageReader`/`EnrichableTermReader`;
  он же **гейт идемпотентности**: повтор джобы не пере-тратит модель;
- спенд — таблица `example_distractor_generations` по образцу `example_regenerations`
  (model/tokens/cost), иначе учёт смешается с enrichment;
- **диспатч в трёх точках:** (1) хвостом успешной генерации коллекции, как `AttachImagesJob`;
  (2) после `ReplaceTermExample`; (3) артизан-команда бэкфилла для существующих 822 примеров.

### 5.3 Промпт: анти-паттерн как главное требование

Основная ловушка LLM здесь — выдать вместо ошибки **другой грамматически допустимый вариант**
(«in the morning» → «in the mornings»). Тогда оба варианта верны и задание нечестное. Поэтому:

**В промпт (жёстко):**

- **ровно одна ошибка** на предложение; всё остальное — символ-в-символ исходник (это делает
  дистрактор проверяемым автоматически);
- ошибка **из фиксированного списка типичных ошибок русскоязычных**:
  - `article` — пропуск/лишний артикль, `a` ↔ `the`;
  - `preposition` — `in`/`on`/`at`/`to`/`for` не на месте;
  - `tense` — Past Simple вместо Present Perfect, Present Simple вместо Continuous;
  - `word_order` — место наречия, порядок в вопросе, прямой порядок вместо инверсии;
  - `false_friend` — `actual`/`current`, `sympathetic`/`nice`, `magazine`/`shop`, `accurate`/`neat`;
  - `agreement` — `he do`, `there is` при множественном;
- **запрещено:** менять смысл; подменять слово синонимом; добавлять/убирать клаузы; менять регистр
  или пунктуацию; выдавать вариант, который носитель признает допустимым.

**Валидация на импорте (детерминированная, до всякого eval):**

1. `sentence != term_examples.sentence`;
2. расстояние правки от исходника ограничено «одной точечной правкой» — пословный levenshtein ≤ 2
   (2 допускает перестановку для `word_order`);
3. `error_span` — подстрока `sentence`;
4. `correction` встречается в исходном предложении;
5. `error_type` ∈ CHECK-списке.

Не прошло — **выбрасывается строка, не вся пачка** (как `DraftValidator` тримит items).

### 5.4 Eval-подмножество (по образцу `generation:eval`)

- фикстура `tests/Fixtures/distractor-examples.json` — ~30 реальных примеров из базы, разложенных по
  длине и типу термина;
- команда `distractors:eval` в `Generation/Presentation/Console` (по образцу
  `EvalGenerationCommand`), с `--fake` для no-spend smoke;
- метрики: доля отбракованных валидатором; распределение `error_type` (не должно схлопнуться в одни
  артикли); **доля «дистрактор на самом деле допустим»** — ручная разметка подмножества ~30, это и
  есть измерение анти-паттерна; доля дублей; tokens/cost;
- baseline → `docs/distractor-eval-v1.json`; версию промпта флипаем только после сравнения, как
  делали v3 → v4.

### 5.5 Карточка

Новых полей контракта **ноль**: `options` уже есть — туда кладём `[верное, ...дистракторы]`,
перемешанные тем же `Randomizer`. Клиентский `_options()` уже рендерит список и помечает верный
через `SessionGrader.check(o, _card.answer)`, где `answer` = верное предложение → работает как есть.
Единственное — `_SessionOption` рассчитан на короткий текст; нужен визуальный проход на перенос
строк. Гейт: `distractorCount >= 2`.

---

## 6. `/sync` — аддитивная дельта

В духе A3 (`image_url`), ничего ломающего:

- в `changes.terms[]` добавить **`example_id`** (клиенту он нужен как ключ дистракторов; сегодня
  едет только текст примера — `SyncController::term()`);
- новый поток **`changes.example_distractors[]`**:
  `{ id, example_id, op: "upsert", updated_at, sentence, error_type }`.
  `error_span`/`correction` клиенту **не шлём**, пока `find_the_mistake` не реализован — добавятся
  аддитивно позже;
- в `GetSyncDeltaHandler` новый ordered stream **добавляется в конец** конкатенации `$all` — курсор
  кодирует `(upper, offset)` по склеенному потоку, и вставка в середину сместила бы офсеты у
  клиентов с сохранённым курсором;
- скоуп — дистракторы примеров тех терминов, что в живых коллекциях пользователя
  (`CollectionSyncReader::liveTermIds`, как `TermChangeReader::changedTermIds`);
- **тонстоуны не нужны:** CASCADE при замене примера следа не оставляет, но мы и так шлём term с
  новым `example_id` — клиент при смене `example_id` удаляет локальные дистракторы старого примера.
  `op` всегда `upsert`, как у `triages`;
- клиентская дрифт-схема **v9**: новая таблица `ExampleDistractors(id, exampleId, sentence,
  errorType)` + колонка `Terms.exampleId`, шаг `if (from < 9)` в `onUpgrade`
  (`app_database.dart:227-253`);
- OpenAPI: два аддитивных блока (`/sync` terms + новый массив).

---

## 7. Офлайн: что нужно клиентскому сборщику практики

Сборщик (`LocalPracticeSessionBuilder`, фаза 2 — **в работе в параллельной сессии**) уже устроен
правильно: режимы приходят параметром `PracticeModes enabled = PracticeModes.serverDefault`.
Сигнатуру менять не нужно. Что потребуется:

1. **`PracticeModes.serverDefault`** — добавить новые значения **в том же порядке**, что в
   `config/learning.php` (порядок = контракт round-robin).
2. **`PracticeModeSelector.applicableModes`** → заменить switch на клиентский `TermPlayability`
   (зеркало серверного).
3. **Данные из локального мирора:** `Term.example` ✅ и `Term.exampleTranslation` ✅ уже есть;
   `exampleTokenCount` считается локальным токенизатором (тем же, что чипы);
   `distractorCount` ❌ — появляется только с дрифт-таблицей из п.6.
4. **`_card()`** — две ветки: `sentence_scramble` → `chips = _sentenceChips(example)` (новый
   приватный метод рядом с `_chips`, тот же `Random`); `which_sentence_correct` →
   `options = [correct, ...distractors]..shuffle(random)`.
5. **Чистоту билдера сохранить:** он принимает `List<Term>` и не знает про БД. Дистракторы
   передавать готовой картой `Map<String /*exampleId*/, List<String>>`, загруженной одним запросом
   на входе в экран, — не тянуть `AppDatabase` внутрь билдера.
6. **`answer` = предложение** для scramble/dictation и в локальной карточке тоже — то же правило,
   что на сервере, иначе офлайн-грейдинг разойдётся с онлайновым.
7. **Контракт-фикстуру расширить** полями `example_token_count`, `has_example_translation`,
   `distractor_count`, перегенерить `EXPORT_PRACTICE_FIXTURE=1`, Dart-тест подхватит.
8. `dictation` офлайн работает без оговорок (системный TTS); `which_sentence_correct` офлайн
   работает ровно тогда, когда дистракторы засинканы — гейт `distractorCount >= 2` это и
   обеспечивает автоматически.

---

## 8. План коммитов

Каждый — с зелёными гейтами (`composer check` через commit-hook + `flutter analyze`/`flutter test`),
`invariant-reviewer` перед `/close-task`, строка в ROADMAP.

| # | Коммит | Содержание |
|---|---|---|
| 0 | `fix(vocabulary): детерминированный выбор примера` | Б2: `ORDER BY id` во всех чтениях `term_examples`. Только закрепление примера — `example_id` в контракте едет коммитом №5 вместе с остальной дельтой. Предусловие для всего остального |
| 1 | `refactor(learning): TermPlayability — гейты по данным в одном месте` | VO + `supports()`; селектор принимает его; `applicableModes` = фильтр; тернарники уходят из лестниц; клиентское зеркало. **Поведение не меняется — фикстура перегенерится байт-в-байт** |
| 2 | `feat(learning): scramble` | миграция (только новое значение в CHECK — ширина колонки не меняется), enum + `maxGrade`, лестницы, ассемблер (chips из example), `example` в `TermAnswerKeyView` **+ уточнение инварианта в скилле `learning-srs` этим же коммитом**, валидация запроса, OpenAPI, клиент (рендер + `isTyped`/`phaseFor` + l10n RU/EN), фикстура. Гейт 4…12 |
| 3 | `feat(learning): dictation` | enum + лестница + предикат автоплея + `isTyped` + ветка фидбека + l10n. `Pronouncer` не трогаем. Гейт 4…10 (свои константы) |
| 4 | `feat(generation): example_distractors + джоба + eval` | схема (Vocabulary), `ImportExampleDistractors`, порт/адаптер/фейк, промпт v1 + анти-паттерн, валидатор импорта, джоба + 3 точки диспатча, `distractors:eval` + baseline **на 5 вычитанных вручную коллекциях**. Массового бэкфилла в коммите нет — решение по нему после eval. **Режим ещё не включён** |
| 5 | `feat(sync): example_distractors в дельте` | аддитивные `example_id` + новый поток, дрифт v9, чистка при смене `example_id`, OpenAPI |
| 6 | `feat(learning): which_sentence_correct` | enum + лестница + ассемблер (`options` из `ExampleDistractorReader`) + гейт ≥2 + клиент + l10n + фикстура |
| 7 | `feat(mobile): офлайн-сборка новых режимов` | п.7 целиком — **после** приземления фазы 2 сборщика |

Порядок соответствует просьбе (scramble → dictation → enrichment-джоба → which_sentence);
sync-коммит вставлен между джобой и режимом, потому что раньше синкать нечего, а позже режим не
заработает офлайн.

---

## Часть 2 (отложено) — планировщик и политика

Здесь только скетч. **Ничего из этого в текущей работе не строим и не подготавливаем кодом** —
единственное обязательство сейчас: не завести хардкод, который потом помешает.

### Схема `learning_policies` (скетч)

```sql
CREATE TABLE learning_policies (
    id         char(26)    PRIMARY KEY,
    user_id    char(26)    NULL,                    -- NULL = дефолт продукта; строка юзера переопределяет
    name       varchar(32) NOT NULL,
    algorithm  varchar(16) NOT NULL DEFAULT 'sm2',  -- sm2 | fsrs
    config     jsonb       NOT NULL,
    version    int         NOT NULL,
    created_at timestamptz NOT NULL,
    updated_at timestamptz NOT NULL
);
```

### Что туда переедет

- `enabled_modes` **и их порядок** (сейчас `config/learning.php`);
- лестницы: `known` / reps-0 / learning-relearning / review (сейчас массивы в `ExerciseSelector`);
- пороги гейтов: word_bank ≥ 2, scramble MIN/MAX, which_sentence ≥ 2 (константы `TermPlayability`);
- `dictation_scope: full | clause | window(N)`;
- дневная квота новых слов, размер сессии, `VERIFICATION_PASS_DAYS = 90`;
- **динамика встреч/успехов дня-0** — то, ради чего всё затевается;
- пороги латентности `AnswerGrader` (`SLOW_FACTOR`/`FAST_FACTOR` + абсолютные `WORD_*`/`PHRASE_*`).

### Условия, при которых переезд останется механическим (соблюдать уже сейчас)

1. состав и порядок режимов читаются на рантайме ровно из одного места (сегодня — синглтон
   `EnabledModes` из конфига; **удалить дублирующий fallback-список** в `LearningServiceProvider`);
2. лестница — **данные** (упорядоченный список), а не `if`/тернарник по признакам термина; гейты
   отдельно, фильтром (`TermPlayability`);
3. любой новый порог — именованная константа в Domain, не литерал в ветке;
4. клиент получает свой набор режимов из одного значения (`PracticeModes`) — уже так.

### Scheduler: FSRS-кандидат (скетч)

- Планировщик = **сменная стратегия политики** (`algorithm: sm2 | fsrs`). Порт `Scheduler` уже есть;
  меняется только биндинг (сегодня жёстко `Sm2Scheduler` в `LearningServiceProvider`).
- Для `fsrs` конфиг хранит **`desired_retention` (дефолт 0.9)** вместо хардкод-интервалов; параметры
  модели лежат там же и версионируются вместе с политикой.
- Миграция состояния — **реплей review-лога**: он append-only и уже несёт `grade`, `answered_at`,
  `client_seq`, `is_practice`, то есть достаточен, чтобы пересобрать стабильность/сложность без
  отдельного бэкфилла.
- Разделение слоёв: **планировщик решает «когда», лестница режимов — «каким тренажёром»**. Они
  ортогональны; при смене алгоритма лестница не меняется.
- В текущей реализации под это **ничего не меняем**. Одно ограничение: не привязывать выбор режима к
  `ease_factor`/`interval_days` — только к `reps`/`state`, как сейчас; иначе ортогональность
  сломается ровно в момент перехода на FSRS.

---

## Открытые вопросы (нужны решения)

1. **Пороги гейта `sentence_scramble`.** Рекомендация **4…12 токенов** (96.1% из 822 примеров).
   Альтернативы: 5…12 (93.8%), 4…10 (83.6%). Какой?
2. **`dictation`: целиком или фрагмент.** Рекомендация — **целиком**, константой в v1, а «фрагмент»
   отложить в поле политики `dictation_scope`. Подтверждаешь?
3. **Верхняя граница длины для `dictation`** — та же, что у scramble (12), или жёстче (набор с
   клавиатуры дороже сборки из чипов, напрашивается ~10)?
4. **Эталон = предложение** (п. 2.4). Расширяем `TermAnswerKeyView` полем `example` и уточняем
   формулировку инварианта «ключ — собственные формы термина» в `.claude/skills/learning-srs`?
   Без этого `invariant-reviewer` зарубит коммит №2 — и будет прав.
5. **`reviews.exercise_mode varchar(16)`** (Б1). Расширяем до `varchar(32)` (имена читаемые в
   аналитике) или укорачиваем режимы до `scramble` / `pick_correct`?
6. **Сколько опций у `which_sentence_correct`** — 3 (2 дистрактора) или 4, как `OPTION_COUNT` у
   multiple_choice? От этого зависит и порог гейта, и цена генерации.
7. **Бэкфилл дистракторов на 822 существующих примера** — одним прогоном артизан-команды сразу после
   коммита №4, или сначала eval на подмножестве и решение по качеству?

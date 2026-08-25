# Аудит: дистракторы-«расстяжки контракций» и соседние классы брака

- **Дата:** 2026-08-25 14:05 EEST
- **HEAD:** `de7dcf495d6535905673692572fe4e39fee9c7a7`
- **Режим сессии:** только чтение. Кода, миграций, промптов, тестов не тронуто; в базу ни одной записи;
  внешних LLM-вызовов — 0. Единственный созданный файл — этот.

Повод: на телефоне выпала карточка `pick_correct` по термину **Piece of cake** (idiom, B1, en),
в которой два из трёх вариантов грамматически верны.

```
эталон  : Don't worry about the test — it'll be a piece of cake.
перевод : Не переживай из-за теста — это будет проще простого.

дистрактор 1 (БРАК): Don't worry about the test — it will be a piece of cake.
                     span=[it will]  correction=[it'll]  error_type=modal_to  gv=mech-v13.1
дистрактор 2 (ОК)  : Don't worry about test — it'll be a piece of cake.
                     span=[about test] correction=[about the test] error_type=article gv=mech-v13.1
```

Оба ряда висят на одном закреплённом примере
`term_examples.id = 01M0D7PBE333DJV73B6NR5DV96`, `term_id = 01M0D7PBE1WPHK7BG99SBWFZKG`:

- ряд-брак — `example_distractors.id = 01M0QZM5HM5FC0WA0N78RHR9YM`;
- законный ряд — `example_distractors.id = 01M0QZM5HMR3P14VH420WEXQYQ`.

---

## Часть 1. История и фактическое состояние гейта контракций

### 1.1. Что именно нормализуется перед сравнением

Одна цепочка на всё сравнение — `App\Modules\Shared\Domain\Service\LexicalNormalizer`
(`app/Modules/Shared/Domain/Service/LexicalNormalizer.php`).

`canonicalize()` (строки 36–50), по порядку:

1. `TextNormalizer::fold()` — юникод-фолд (диакритика, ß→ss и т.п.);
2. `mb_strtolower(trim(...))`;
3. `expandContractions()` (строки 63–84) — **до** снятия пунктуации, иначе апостроф режется;
4. `preg_replace('/[^\p{L}\p{N}\s]+/u', ' ')` — вся пунктуация в пробел;
5. схлопывание пробелов.

`normalize()` = `canonicalize()` + `stripArticle()` (строки 52–56): снимает **ведущий** `the|a|an`.

`expandContractions()` раскрывает **ровно этот закрытый список** (строки 67–77):

| группа | что есть | чего НЕТ |
|---|---|---|
| `'d` | `i'd` | `you'd`? — есть; `he'd`, `she'd`, `it'd`, `that'd`, `who'd` — **нет** |
| `'ll` | `i'll`, `you'll`, `we'll`, `they'll` | **`it'll`, `he'll`, `she'll`, `that'll`, `there'll` — нет** |
| `'m` | `i'm` | — |
| `'re` | `you're`, `we're`, `they're` | `there're`, `who're` — нет |
| `'ve` | `i've`, `you've`, `we've`, `they've` | `there've`, `who've`, `should've`, `would've`, `could've`, `might've` — нет |
| `'s` | `it's`, `that's`, `there's`, `let's` | любое другое `'s` (`how's`, `here's`, `he's`, `she's`, `what's`, `who's`) — нет |
| `n't` | `don't doesn't didn't isn't aren't wasn't weren't can't won't wouldn't couldn't shouldn't haven't hasn't hadn't` | `mustn't`, `needn't`, `shan't`, `ain't`, `daren't` — нет |

Плюс `expandPerfectAuxiliary()` (строки 102–107) — единственное правило вне списка:
`<что угодно>'s been → <…> has been` и `<что угодно>'d been → <…> had been`.

Замена идёт через `preg_replace_callback("/\b[a-z]+'[a-z]+\b/", ...)` — то есть форма, которой нет
в карте, **остаётся как есть**, а потом шаг 4 режет апостроф и оставляет висящий токен.
Живой результат:

```
normalize("…it'll be a piece of cake.")  = "do not worry about the test it ll be a piece of cake"
normalize("…it will be a piece of cake.") = "do not worry about the test it will be a piece of cake"
```

Сверх этого — `EnrichmentValidator::sentenceEquals()`
(`app/Modules/Generation/Domain/Service/EnrichmentValidator.php:717–736`) и его помощник
`contractionReadings()` (строки 745–755). Он пробует **оба прочтения только для голого `'s`**:
`/\b([a-zA-Z]+)'s\b/` → `$1 is` и `$1 has`. Ни `'ll`, ни `'re`, ни `'ve`, ни `'d`, ни `n't`, ни `'m`
там не участвуют.

### 1.2. Существует ли сейчас проверка на «отличается только раскрытием контракции»?

**Нет — в общем виде не существует.** Есть узкая: `EnrichmentValidator::sentenceEquals()`
(строки 717–736), покрывающая только `'s`, плюс тот закрытый список местоимений в
`LexicalNormalizer::expandContractions()`. Форма `it'll` не покрыта ни там, ни там.

Вызывается `sentenceEquals` из двух мест:
`EnrichmentValidator::validDistractors()` (строка 459, гейт `DistractorGate::EqualsExample`) и
`AuditDistractorsHandler` (`app/Modules/Generation/Application/Command/AuditDistractorsHandler.php:104`).

Воспроизведение решения валидатора на живых строках (tinker, только чтение):

```
norm(эталон)             = [do not worry about the test it ll be a piece of cake]
norm(дистрактор 1)       = [do not worry about the test it will be a piece of cake]
sentenceEquals(d1, ex)   = false      ← гейт EqualsExample НЕ сработал
repairsTo(d1,'it will',"it'll",ex) = true    ← круговая проверка ПРОШЛА
canonicalize('it will') === canonicalize("it'll") → false  ← гейт NoOpCorrection НЕ сработал
```

Итого ряд прошёл **все** проверки `validDistractors()`. Механика провала подробно:

- `EqualsExample` промахнулся, потому что `it'll` не в карте → `it ll` ≠ `it will`;
- `NoOpCorrection` (строка 469) сравнивает `canonicalize(span)` с `canonicalize(correction)` —
  `it will` против `it ll`, тоже не равны;
- `RepairDoesNotMatchExample` (строка 486) — единственная проверка, которая тут **обязана** пройти:
  подстановка `correction` на место `span` даёт ровно эталон. Она подтверждает, что ряд «честный»
  по своим трём полям, и ничего не говорит о грамматичности;
- `ErrorType::tryFromWire('modal_to')` — валидный тип, ярлык не проверяется по существу (известное
  свойство: ярлыкам не верить).

То есть три поля ряда согласованы между собой идеально — брак именно в том, что «ошибка» ошибкой
не является, а на это смотрит **только** `sentenceEquals`.

### 1.3. Git-археология

Проверка появилась **один раз и сразу узкой**:

```
146cd4e  2026-08-13  fix(generation): дыра валидатора E.2 — equality пробует оба раскрытия 's
```

Diff по существу: добавлены `EnrichmentValidator::sentenceEquals()` и `contractionReadings()`
(+60 строк в валидаторе), `AuditDistractorsHandler` переведён на новый публичный метод, добавлены
2 юнит-теста и 1 фича-тест. Сообщение коммита прямо ограничивает область:
«equality пробует **оба раскрытия `'s`**», «Правка — `sentenceEquals()`: пробует ОБА прочтения
(`'s→is` и `'s→has`)».

Поисковые проверки истории:

- `git log -S "contractionReadings" -- app` → ровно один коммит, `146cd4e`. Не менялся с тех пор.
- `git log -S "sentenceEquals" -- app` → `146cd4e` (появление) и `854384e` (2026-08-20,
  переиспользование при замене примера). Сужения не было.
- `git log -S "expandContractions" -- app` → `dd8fd56` (2026-07-30, грейдер), `745f648` (2026-08-07),
  `146cd4e`. Карта местоимений с тех пор не сужалась.
- **`git log --all -S "it'll"` по всему репозиторию → пусто.** Строки `it'll` в коде не было
  никогда — ни в карте `LexicalNormalizer`, ни в тестах.
- `LexicalNormalizer.php` трогали три коммита: `745f648`, `1f78336`, `d0879d8` (юникод-фолд).
  Ни один не расширял и не сужал набор контракций.

Пересадка генерации v2 (`GENERATION_STACK`, механика v12/v13/v13.1) `sentenceEquals` не касалась.

### 1.4. Тесты

Есть ровно те, что описывают узкий случай:

- `tests/Unit/Generation/EnrichmentValidatorTest.php:79` — «scraps a contraction-only rewrite…»:
  `I'd` → `I would`. Проходит **потому, что `i'd` есть в карте**, а не потому, что есть общий гейт.
- `tests/Unit/Generation/EnrichmentValidatorTest.php:193` — `He's been` → `He has been`
  (правило `expandPerfectAuxiliary`).
- `tests/Unit/Generation/EnrichmentValidatorTest.php:206–223` — живые кейсы E.2: `How's` → `How is`,
  `Here's` → `Here is` (правило `contractionReadings`, голое `'s`).
- `tests/Feature/Generation/AuditDistractorsTest.php:80` — тот же `Here's` в ретро-аудите.

Теста на `'ll` / `'re` / `'ve` / `n't` / `'d` вне карты — **нет**. Ни один тест не утверждает, что
класс закрыт целиком; каждый утверждает конкретную строку.

### Вывод части 1

> **Гейта на класс «расстяжка контракции» в широком виде не существовало — коммитом `146cd4e`
> (2026-08-13) был закрыт только голый `'s`, а всё остальное держится на закрытом списке
> местоимений в `LexicalNormalizer::expandContractions()`, куда `it'll` никогда не входило.
> Это не регрессия и не следствие пересадки v2: дыра в этой форме была с самого начала.**

Отдельно: промпт станка **прямо запрещает** этот класс —
`app/Modules/Generation/Infrastructure/Prompt/v13.1/61-distractors.md:60`:
«A re-spelled contraction is not an error… the grader folds them together, so such a "distractor"
IS the right answer». Обещание «грейдер их сворачивает» для `it'll` **неверно** — это и есть
корень: инструкция модели опирается на свойство нормализатора, которым тот не обладает,
а детерминированный backstop покрывает другой, более узкий случай.

---

## Часть 2. Масштаб по витрине

Подсчёт сделан разовым читающим скриптом в `artisan tinker` (SELECT-only). Нормализация контракций
реализована **в скрипте, независимо от кода валидатора**:

- глифы `’ ‘ \` ´` → `'`, нижний регистр, тире `— – −` → пробел;
- детерминированные раскрытия в обе стороны: `can't→can not`, `won't→will not`,
  `shan't→shall not`, `cannot→can not`, `<X>n't→<X> not`, `<X>'ll→<X> will`, `<X>'re→<X> are`,
  `<X>'ve→<X> have`, `let's→let us`, `<X>'m→<X> am`;
- неоднозначные `'s` и `'d` — **оба** прочтения (`is`/`has`, `would`/`had`), перебором комбинаций
  (при >5 вхождениях — два однородных прочтения вместо перебора);
- затем пунктуация → пробел, схлопывание пробелов.

Считались две строгости: **strict** — совпадение только по детерминированным раскрытиям;
**loose** — плюс неоднозначные `'s`/`'d`.

### 2.1–2.2. Числа

| показатель | значение |
|---|---|
| всего дистракторов в `example_distractors` | **1273** |
| подозрительных по 2.1 (**strict**) | **1** |
| подозрительных по 2.1 (**loose**, с `'s`/`'d`) | **1** (тот же ряд) |
| из них с маркером `mech-v13.1` | **1** |
| из них со старыми маркерами | **0** |
| затронуто уникальных терминов | **1** |
| затронуто коллекций | **2** |

Разбивка витрины по маркерам станка (для контекста):
`mech-v13.1` 491, `enrich-v1-topup3` 395, `enrich-v1` 168, `mech-v12.1` 66, `mech-v14` 49,
`mech-v12` 43, `mech-v14.1` 34, `mech-v14.2` 27.

Комментарий: единственная находка — ровно тот ряд, который вылез на телефоне. Дыра в валидаторе
широкая по форме, но на живых данных сработала один раз. Это согласуется с записью в `146cd4e`
о том, что сухой прогон по 996 дистракторам тогда не нашёл ничего сверх двух известных рядов.
**Оговорка:** скрипт сравнивает дистрактор с **его собственным** `term_examples.sentence`
(через `example_id`), так что «подозрительность» здесь = «это тот же самый закреплённый пример».
Дистракторы, грамматичные по другой причине (не совпадающие с эталоном текстуально),
этим счётом не ловятся — см. п. 6.

### 2.3. Более грубый признак: `error_span` == `correction` после той же нормализации

**1 ряд из 1273** (`mech-v13.1`, 1 термин, 1 пример, 2 коллекции) — тот же самый ряд
`it will` / `it'll`. Других «исправлений, которые ничего не исправляют», по этому признаку нет.

Это ожидаемо: в коде уже есть гейт `NoOpCorrection`
(`EnrichmentValidator.php:469`), сравнивающий `canonicalize(span)` с `canonicalize(correction)` —
он ловит всё, кроме тех же непокрытых контракций.

### 2.4. Полные записи подозрительных (все, что есть — 1 из 10 запрошенных)

```
1)  did       = 01M0QZM5HM5FC0WA0N78RHR9YM
    eid       = 01M0D7PBE333DJV73B6NR5DV96
    term      = Piece of cake   (idiom, B1, en)   tid=01M0D7PBE1WPHK7BG99SBWFZKG
    эталон    = Don't worry about the test — it'll be a piece of cake.
    ex.tr     = Не переживай из-за теста — это будет проще простого.
    sentence  = Don't worry about the test — it will be a piece of cake.
    span      = [it will]
    correction= [it'll]
    error_type= modal_to
    маркер    = mech-v13.1
```

Больше подозрительных по критерию 2.1/2.3 в витрине нет — оставшиеся девять строк отчёта пусты
не по недосмотру, а потому что таких рядов один.

---

## Часть 3. Порог `pick_correct`: считает штуки или годность

### 3.1. Где решается и что именно считается

Решение — `TermPlayability::supports()`
(`app/Modules/Learning/Domain/ValueObject/TermPlayability.php:101–103`):

```php
ExerciseMode::PickCorrect => ! $this->exampleIsAnswer
    && $this->hasExampleTranslation
    && $this->distractorCount >= self::MIN_PICK_CORRECT_DISTRACTORS,   // = 2
```

`distractorCount` приходит из `PlayabilityAssessor::assess()`
(`app/Modules/Learning/Domain/Service/PlayabilityAssessor.php:34,50`), а формируется
`ModeContentRequirements::assess()` (`.../Service/ModeContentRequirements.php:70–71`) как
`count(DistractorSpanFilter::usableIndexes($distractorSpans))`.

`DistractorSpanFilter::usableIndexes()`
(`app/Modules/Learning/Domain/Service/DistractorSpanFilter.php:82–96`) — это **вся** «годность»:

```php
$folded = mb_strtolower(trim($span));
if ($folded === '' || isset($seen[$folded])) continue;   // пустой или повтор span — мимо
```

**Ответ: порог считает ШТУКИ — количество записей `example_distractors`, схлопнутых по
уникальному `error_span` (trim + lowercase).** Никакой проверки годности сверх «span непустой и
не повторяется» на этом пути нет. Проверка грамматичности живёт **только** на записи
(`EnrichmentValidator`), при чтении карточки её не повторяет никто.

### 3.2. Почему карточка «Piece of cake» всё-таки собралась — путь по коду

1. `EloquentAdminContentHealthReader::pinnedExamples()` / карточный ридер берут **закреплённый**
   пример — минимальный `id` среди `term_examples` термина. Здесь он один:
   `01M0D7PBE333DJV73B6NR5DV96`.
2. На нём два ряда `example_distractors`, spans: `it will` и `about test`.
3. `DistractorSpanFilter::usableIndexes(['it will','about test'])` → `[0, 1]` — оба span непустые
   и различны → **2**.
4. `PlayabilityAssessor` → `TermPlayability(distractorCount: 2, hasExampleTranslation: true,
   exampleIsAnswer: false)`.
5. `supports(PickCorrect)` → `true`. Термин допущен.
6. `StudyCardAssembler` (`app/Modules/Learning/Application/Service/StudyCardAssembler.php:205–227`)
   берёт `array_slice($usableDistractors, 0, PICK_CORRECT_WRONG_OPTIONS)` = оба ряда, добавляет
   эталон и перемешивает.

Грамматичность ряда №1 нигде на этом пути не проверяется — она была отвергнута один раз, на записи,
и там гейт промахнулся (п. 1.2). Никакого второго рубежа нет by design: «валидатор — граница между
моделью и базой», всё, что в базе, считается годным.

### 3.3. Что показывает `ModeContentRequirements` и раздел «Контент» в `wt_admin`

Вызов на реальных данных термина (только чтение):

```
usable indexes        = 0,1
pick_correct status   = ok
объяснение            = «годных дистракторов 2 — хватает на эталон + 2 неверных.»
```

`EloquentAdminContentHealthReader::spansByExample()` (строки 234–247) отдаёт **тот же** список
`error_span`, и отчёт прогоняет его через **тот же** `DistractorSpanFilter`.
`PassportDistractorRow::usable` помечает оба ряда годными по тому же признаку.

**Расхождение витрины с реальностью зафиксировано:** админка показывает термин здоровым по
`pick_correct` («годных дистракторов 2»), тогда как реально годен один. Слово «годных» в отчёте
означает «span непустой и уникальный», а читается как «грамматически ошибочный и проверенный».
Это одна и та же деривация с карточкой — то есть отчёт не врёт относительно карточки, он врёт
относительно смысла слова «годный».

### 3.4. Сколько терминов держится на браке

| показатель | значение |
|---|---|
| терминов с закреплённым примером | 710 |
| терминов, проходящих порог `pick_correct` сейчас (перевод примера + ≥2 span-distinct) | **388** |
| из них **потеряют** `pick_correct`, если убрать подозрительный ряд из 2.1 | **1** |
| из них потеряют `pick_correct`, если убрать ряды с кириллицей (соседний класс, ниже) | **3** |

То есть чистка по классу «расстяжка контракции» стоит ровно одну карточку — **Piece of cake**.

---

## Соседний класс, найденный по ходу (руками не тронут)

Пользователь прислал вторую карточку: термин **start a conversation** (phrase, A2), где дистрактор —

```
sentence  = He always knows how to начать a conversation.
span      = [начать]   correction = [start]   error_type = false_friend
маркер    = enrich-v1-topup3
```

Русское слово внутри английского предложения. Ряд старый (`enrich-v1-topup3`), но **класс не
истреблён и текущим станком**: скан всей витрины даёт **3 ряда с кириллицей в `sentence`
дистрактора**, по одному от `enrich-v1`, `enrich-v1-topup3` и **`mech-v13.1`**:

```
1)  term = Salary expectations …  gv=enrich-v1
    эталон   : Let's discuss your salary expectations for this position.
    sentence : Let's discuss your salary expectations по this position.
    span=[по] corr=[for] type=preposition

2)  term = start a conversation (phrase, A2, en)  tid=01KZET5MWCDZP45NJQBGKPKHEG  gv=enrich-v1-topup3
    эталон   : He always knows how to start a conversation.
    sentence : He always knows how to начать a conversation.
    span=[начать] corr=[start] type=false_friend

3)  term = Rozmowa (word, A1, pl)  tid=01M0MHDT83M27S2SCZ496YV031  gv=mech-v13.1
    эталон   : Nasza rozmowa trwała do późna.
    sentence : Nasza rozmowa trwała к поздna.
    span=[к поздna] corr=[do późna] type=preposition
```

Почему проходит: в `EnrichmentValidator::validDistractors()` (строки 370–500) **нет ни одного
обращения к `LanguagePurity`**. Перечень гейтов (`DistractorGate`, 15 кейсов) языковой чистоты не
содержит вовсе. `LanguagePurity` применяется только в `languageFindings()` (строки 573–607) и
только к `translation`, `example_translation` и `exampleSentence` — и там это **finding**
(запись в отчёт), а не отказ в записи. Дистракторные предложения не смотрит никто.
Теста, утверждающего чистоту языка дистрактора, тоже нет (`LanguagePurityTest` тестирует сам
детектор, но он никуда в этот путь не подключён).

Промпт при этом запрет содержит: `v13.1/00-role.md:16` — «A {{source_lang}} letter inside a
{{target_lang}} field is not a typo, it is a broken card». Ряд `mech-v13.1` показывает, что одной
инструкции недостаточно.

Побочно: такая карточка ещё и выдаёт ответ — подсказка «должно быть: start» печатается под
вариантами, а вариант с русским словом опознаётся не по грамматике, а по алфавиту.

---

## Что чинится строкой (детерминированно, без LLM) — перечисление, не реализация

1. **Расширить раскрытие контракций** до полного набора субъект+клитика (`it'll`, `he'll`,
   `she'll`, `that'll`, `there'll`, `he's`, `she's`, `what's`, `who's`, `here's`, `how's`,
   `he'd`, `she'd`, `it'd`, `should've`, `would've`, `could've`, `mustn't`, `needn't`, `ain't`…)
   — либо через карту, либо через общее правило по клитике (`'ll→will`, `'re→are`, `'ve→have`,
   `'m→am`, `n't→not`) с перебором прочтений для неоднозначных `'s`/`'d`.
2. **Симметрично расширить `sentenceEquals()`** — сейчас он пробует прочтения только для `'s`.
3. **Гейт языковой чистоты на дистракторе**: прогнать `sentence` (и `correction`) дистрактора через
   `LanguagePurity::isClean(<язык термина>, …)` внутри `validDistractors()`, добавив кейс в
   `DistractorGate`. Полностью детерминированно.
4. **Ретро-аудит `AuditDistractorsHandler`** дополняется теми же двумя проверками — тогда
   существующая команда найдёт старые ряды без обращения к модели.
5. **Тесты класса, а не строки**: параметризованный набор по всем клитикам, чтобы «закрыт `'s`» не
   читалось как «закрыт класс».
6. **Терминология витрины**: `ContentAssessment`/`ModeContentRequirements` говорят «годных
   дистракторов N», имея в виду «span-distinct N». Либо переименовать, либо посчитать годность
   по-настоящему.

**Что строкой не берётся и требует судью грамматичности:**

- Дистрактор, который «сломан» так, что получился другой корректный английский — смена времени,
  замена детерминатива (`the`↔`my`), изменение смысла (`before`↔`after`). Промпт это запрещает
  (`61-distractors.md:46–57`), но детерминированно неотличимо от настоящей ошибки.
- Верность ярлыка `error_type` по существу (в разобранном ряду стоит `modal_to` при полном
  отсутствии модального с `to`).
- Правдоподобие ошибки для носителя `source_lang` — то, ради чего дистрактор вообще пишется.
- «Ложный друг», который на деле не ложный друг, а просто другое слово.

---

## Что осталось неясным

1. **Почему станок написал этот ряд, если промпт его запрещает** — не установлено: логов
   конкретного прогона `mech-v13.1` по этому термину не смотрел (сессия не запускала генерацию),
   `DistractorGateLog` фиксирует ветку гейта, а не текст запроса. Установимо чтением журнала
   прогона, если он сохранён.
2. **Числа «до» этой правки** — сухой прогон `enrich:audit-distractors` не запускался (запрещено),
   так что расхождение между моим скриптом и штатным аудитом не проверено. Ожидаемо штатный
   аудит нашёл бы **0** (он ходит через тот же `sentenceEquals`).
3. **Класс «грамматичный дистрактор, текстуально не равный эталону»** — по построению не
   измерим этим скриптом. Сколько таких в 1273 рядах — **не установлено**, требует судьи.
4. **Изоморфность переводов (QA-17/QA-22)** — вне скоупа по условию наряда, не трогал.
5. **Полнота списка контракций для будущей правки** — набор в п. «что чинится строкой» составлен
   по разбору, а не по замеру частот на живом корпусе; какие формы реально встречаются в 731
   примере — не считал.

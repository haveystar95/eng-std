# LANG-1 · Ключи языкового пакета — спецификация для исполнителей языков

Наряд LANG-1 §1 (фундамент). Семь целевых языков плана — `en pl ro es it de fr`, девять родных — `ru uk be pl ro es it de fr`
(`LanguageRoles::planTargets()` / `planNatives()`, DECISIONS п. 145). Пакет языка — `config/lesson/lang/<code>.php`, один
файл на язык; его читают валидатор урока, судья каркасов разговора, сравнение речи, сборка карточек, страж перевода
роли и строки ученика. Здесь — **каждый ключ, который читает код**: кто читает (файл:строка на момент написания, ветка
`lang-1`), сторона, точная форма, смысл, no-op, примеры en/ru, грабли. Исполнитель языка пишет пакет по этому документу и
проверяет его разделом «Как проверить пакет» (§7).

**Сверено с кодом ветки 26.09, после пакетов** (коммиты «LANG-1 §4 — союз после десятков — свой ключ пакета…», «LANG-1
по разведке — строчная после цифры не „форма“…», «LANG-1 §4 — языковые пакеты…»): `LanguagePack::normal()` теперь
сворачивает, а `speech()` сам приводит списки к форме текста (§1.3, §8 п. 1 закрыт); соединитель после десятков — свой
ключ `number_tens_joiners` (§3.9, §3.9а, §4.5); апостроф ʼ — не чужая буква, латинские двойники в кириллическом чтении
чинит разбор урока (§3.2); «строчная первая буква» `options.form_mismatch` — по первому символу и против верного варианта
(§6, §8 п. 4). Номера строк «файл:строка» — на коммит спецификации (51a7c3f4), дальше файлы сдвинулись: ищите по имени
метода. Решения — DECISIONS пп. 430 (пакеты), 431 (числа), 433 (страж), 434 (заголовок), 437 (механические).

Содержание: §1 общие правила · §2 сводная таблица · §3 ключи по одному · §4 шесть форм LANG-1 · §5 что даёт
`lang.pack_missing` · §6 фатальные коды и их ключи · §7 как проверить пакет · §8 известные пределы.

---

## 1. Общие правила

### 1.1. Стороны

- **target** — язык, который учат: что ученик говорит и слышит (реплики, каркасы `frame_target`, наполнения, проверки,
  разговор). Пакет читается как `$packs->for($plan->targetLang())`.
- **native** — родной язык ученика: чтения (`pronunciation_native`), native-каркасы, вопросы и варианты «Слушаю»,
  перевод роли, заголовки. `$packs->for($plan->nativeLang())`.
- **both** — ключ читают обе стороны.

Кто какой стороной бывает: `en` — только target; `ru uk be` — только native; `pl ro es it de fr` — **обе**, их пакет
пишет ключи обеих сторон. Сверх того каждый пакет — «сосед» для стража перевода (`script_letters` + `common_words`,
§4.1), поэтому эти два ключа нужны и `en`.

### 1.2. null, отсутствие и no-op

- `LanguagePack::has($key)` — это `isset()`: **null и отсутствие ключа — одно и то же**, «ключ не написан».
- Проверка валидатора сначала спрашивает `LessonValidationContext::reads($code, $side, ...$keys)`
  (`app/Modules/Plan/Domain/Check/LessonValidationContext.php:40`); не написан хоть один ключ — проверка не бежит и
  пишется пропуск `lang.pack_missing` (§5). Это не находка, но это **дыра**: живой день этой пары её не проверит.
- Правило, которое к языку не относится, пишется **явным no-op, никогда null** (решение наряда). No-op — значение,
  которое читатель принимает (не бросает `LanguagePackKeyMissing`, не ломает регулярку) и по которому ничего не находит.
  Все no-op этого документа прогнаны тестом `tests/Unit/Plan/LessonValidatorTest.php` → «reads a pack of the spec's
  no-ops…» (`lvNoOpPack()`): ни пропуска, ни исключения, ни находки.
- Ключи вне `reads()` (судья, речь, сборка) null тоже терпят (молча «нет правила»), но правило то же: пишите no-op, чтобы
  отсутствие было решением, а не забытым ключом.
- Универсальный no-op для **регулярки**: `'/(?!)/u'` (никогда не совпадает). `pattern()` требует строку: `[]` или `''`
  на месте регулярки — исключение или «совпало всё».
- No-op для **списка** — `[]`; для **карты** — `[]` или карта с пустыми полями (где поля обязательны — см. ключ).

### 1.3. Как писать слова — зависит от того, КАК читатель режет текст

Это главная ловушка пакета. Один и тот же список может сравниваться с текстом, прочитанным по-разному:

| Семейство | Кто | Как читается текст | Как писать слова пакета |
|---|---|---|---|
| **Ⓐ валидатор** | `LanguageWords`, `SpeakingKey`, `SlotJudge`, `WordCards` — через `Words::tokens()/spans()` + `LanguagePack::normal()` | нижний регистр; все знаки вон, **кроме апострофа и дефиса внутри слова** («l'hôpital», «n-am», «est-ce», «c'est» — одно слово каждое; `’` читается как `'`); **свёрнуто** (`normal()` = `TextNormalizer::fold` + нижний регистр + `’`→`'`: ß→ss, œ→oe, ş→ș, ţ→ț) — и слово текста, и каждый список пакета (списки строятся тем же `normal()`) | стандартная орфография языка («heißen» и «heissen» — одно слово), нижний регистр, прямой апостроф `'`; слова с апострофом/дефисом — целиком |
| **Ⓑ судья каркасов** | `FrameJudge`, `FrameWords` | **свёрнуто** (`TextNormalizer::fold`: NFC, ß→ss, œ→oe, ş→ș, ţ→ț), нижний регистр, `contractions` раскрыты (и элизии, §4.4), затем апостроф удалён, дефис/слэш/прочие знаки — пробел | списки пакета судья пропускает через то же чтение, поэтому годится любое написание; **пишите свёрнуто**. Исключения — `partitive` и `contractions_before` (сравниваются как написаны): свёрнуто, нижний регистр, прямой апостроф |
| **Ⓒ сравнение речи** | `SpeechMatch` (+ телефон, зеркало в Dart), `LineShare`, `WordBases` | сокращения → буквы; `LexicalNormalizer::canonicalize`: **свёрнуто**, нижний регистр, английские сокращения раскрыты (только английский список!), **апостроф удалён** («l'hôpital» → «lhôpital»), все прочие знаки и дефис — пробел; числа → цифры (`SpokenNumbers`) | стандартная орфография языка: **`speech()` сам проводит** каждое слово `unstressed_words`, `articles`, `number_joiners`, `number_tens_joiners` и каждую запись `number_words` через тот же `canonicalize` («dreißig» → `dreissig`, «quatre-vingt-dix» → `quatre vingt dix`, ro «şi» с седилью → `și`); свернувшееся в пустое выброшено, две записи, свернувшиеся в одну, — одна (первая написанная). Нельзя одно: элизия — апостроф удаляется, а не режет, «dell'» станет `dell`, а текст «dell'ospedale» — одним словом `dellospedale` |
| **Ⓓ страж перевода** | `ReplyNative` | серии букв (с комбинирующими знаками), режется по всему остальному — апостроф и дефис тоже; `normal()` — свёрнуто, как Ⓐ | одиночные серии букв, стандартная орфография, нижний регистр |
| **Ⓔ регулярки** | см. ключ | каждая применяется к своей строке (§3) | всегда `/u`; где свёртка меняет букву — обе формы: `(?:ß\|ss)`, `(?:œ\|oe)`, `[șş]`, `[țţ]` |
| **Ⓕ как в тексте** | `abbreviations` | ищется в сыром тексте, регистр не важен, пробел внутри = любая серия пробелов | так, как пишется в тексте, с точками |

Итог для ß/œ/ş (de, fr, ro): с пакетами LANG-1 свёртка стоит с обеих сторон всех списков — в Ⓐ, Ⓓ (`normal()`), Ⓒ
(`speech()`) и Ⓑ годится стандартная орфография языка («heißen», «sœur», «și» или «şi»), свёрнутая тоже. Текст модели с
седилью «ş» встречает «ș» пакета. Исключения: `partitive` и `contractions_before` (Ⓑ, как написаны — пишите свёрнуто) и
**регулярки Ⓔ**: регулярка Ⓐ получает уже свёрнутое слово, поэтому буква, которую свёртка меняет, пишется обеими формами
(`(?:ß|ss)`, `[șş]`) — регулярка с одной «ß» больше не совпадёт (докблок `LanguagePack::normal()`).

### 1.4. Регулярки

Флаг `/u` обязателен (PHP с `/u` включает UCP: `\s` ловит и неразрывный пробел U+00A0/U+202F, `\p{L}` — буквы любого
алфавита). Синтаксическая ошибка регулярки — это `false` + warning, в тестах Laravel — исключение: прогоните §7. Регулярка
про одно слово якорится `^…$`.

### 1.5. Ключи с формой, которую проверяет код

`mapString`/`mapInt` бросают `LanguagePackKeyMissing` на отсутствующее или не того типа поле: у `word_forms` (3 int),
`article_sound` (6 строк), `agreement` (`min_letters`, `after_slot_words` — int) все поля обязательны. `mapWords` поле
терпит (нет — `[]`). `sentence_ends` пустым не бывает (§3.3).

---

## 2. Сводная таблица

`reads` — ключ спрашивается через `reads()` (иначе пропуск `lang.pack_missing`). no-op «—» — у языка стороны ключа всегда
есть настоящее значение: пишите его.

| Ключ | Сторона | Форма | Сем. | reads | no-op |
|---|---|---|---|---|---|
| `script` | native | regex (строка чтения целиком; строгий алфавит языка) | Ⓔ | да | — |
| `script_letters` | native + сосед | regex (одна буква): ровно `'/^[\p{Latin}]$/u'` или `'/^[\p{Cyrillic}]$/u'` | Ⓔ | да | — (строка-эталон письменности) |
| `sentence_ends` | both | map знак → вид | — | да | — |
| `abbreviations` | both | list, как в тексте | Ⓕ | нет | `[]` |
| `question_word_order` | target | map {auxiliaries, subjects} | Ⓐ | нет | `['auxiliaries' => [], 'subjects' => []]` |
| `function_words` | both | list | Ⓐ | да | — |
| `unstressed_words` | target | list | Ⓒ | нет | `[]` |
| `number_words` | target | map запись (слово или несколько) → цифры-строка | Ⓒ | нет | `[]` |
| `number_joiners` | target | list — соединитель **после масштаба** | Ⓒ | нет | `[]` |
| `number_tens_joiners` | target | list — соединитель **после десятков** (§3.9а) | Ⓒ | нет | `[]` |
| `word_forms` | both | map {stem_min, stem_tail, content_min_letters} int | Ⓐ | да | — |
| `lemma_forms` (**наряд GEN-4**) | target | map словарное слово → list его форм | Ⓐ | нет | нет ключа — формы только по буквам (§3.10а) |
| `number_pattern` | both | regex (слово) | Ⓔ | да | — |
| `time_pattern` | native (target — по желанию) | regex (слово) | Ⓔ | да (native) | target: `'/(?!)/u'` |
| `amount_pattern` | native (target — по желанию) | regex (слово) | Ⓔ | нет | `'/(?!)/u'` |
| `amount_prefix` | native (target — по желанию) | regex (слово) | Ⓔ | нет | `'/(?!)/u'` |
| `everyday_words` | target | list | Ⓐ | да | — |
| `ordinary_heads` | target | list | Ⓐ | да | — |
| `closers` | target | list фраз | Ⓐ | да | — |
| `saying_verbs` | target | list | Ⓐ | да | — |
| `alternative_words` | target | list | Ⓐ | нет (с GEN-4c — `targetReading()` правил да/нет) | — |
| `yes_no` (**наряд GEN-4c**) | target | list: «да» первым, «нет» вторым, дальше прочие | Ⓐ | нет (`targetReading()`) | — (обязателен у семи целей) |
| `question_words` (**наряд GEN-4c**) | target | list слов и оборотов (несколько слов подряд) | Ⓐ | нет (`targetReading()`) | — (обязателен у семи целей) |
| `second_question_pattern` | target | regex (сырой текст) | Ⓔ | да | `'/(?!)/u'` |
| `articles` | target | list | Ⓐ Ⓑ Ⓒ | да | `[]` |
| `dangling_words` | target | list | Ⓑ | нет | `[]` |
| `seam_repeatable_words` | target | list | Ⓐ | да | `[]` |
| `article_sound` | target | map, 6 строк | Ⓐ Ⓔ | да | §3.24 |
| `clause` | target | map, 5 списков | Ⓐ | да | 5 пустых списков |
| `unresolved_pronouns` | target | map, 7 списков | Ⓐ | да | 7 пустых списков |
| `gendered_past_pattern` | native | regex с группой 1 | Ⓔ | да | `'/(?!)/u'` |
| `agreement` | native | map {words, short_forms, suffixes_before_slot, min_letters, after_slot_words} | Ⓐ | да | §3.28 |
| `rescue_line` | target | string | — | нет | — |
| `rescue` | target (**наряд LANG-1b §2**) | list `{target, native: {ru, uk, be, pl, ro, es, it, de, fr}}` × 6 | — | нет | `[]` у ru, uk, be |
| `neutral_reply` | both | string | — | нет | — |
| `irregular_forms` | target | map форма → основа | Ⓒ Ⓑ | нет | `[]` |
| `inflection_rules` | target | list [regex, замена] | Ⓒ Ⓑ | нет | `[]` |
| `person_swap` | target | map слово → слово | Ⓒ | нет | `[]` |
| `contractions` | target | map (+ элизии, §4.4) | Ⓑ | нет | `[]` |
| `contractions_before` | target | map след. слово → {хвост → слово} | Ⓑ | нет | `[]` |
| `intro_words` | target | list (фразы можно) | Ⓑ | нет | `[]` |
| `clause_starters` | target | list | Ⓑ | нет | `[]` |
| `negation` | target | map {words, after, do_support} (§4.3) | Ⓑ | нет | `[]` |
| `partitive` | target | map {word, determiners} | Ⓑ (сырое) | нет | `[]` |
| `common_words` | native + сосед (всем) | list частых И отличительных (35–86 слов) | Ⓓ | нет | `[]` (соседи не различаются) |
| `talk_title_template` | native, кроме ru/uk | map (§4.2) | — | нет | `[]` у ru, uk, en (заголовок строит код) |

---

## 3. Ключи по одному

Формат: **сторона · читатели** · форма · смысл · no-op · пример en / ru · грабли.

### 3.1. `script`
- **native** · `LanguageWords::readsInScript()` `LanguageWords.php:412`; `reads`: `StructureRules.php:105` (`pronunciation.script`, предупреждение).
- regex, применяется к **строке чтения целиком** (`pronunciation_native`): совпало — чтение в письменности ученика.
- Цифры, знаки, пробелы, знак ударения U+0301 должны проходить. **Пишите строгий алфавит языка**, а не письменность —
  так написаны все пакеты LANG-1: pl `'/^[aąbcćdeęfghijklłmnńoóprsśtuwyzźżAĄ…Ż\p{N}\p{P}\s\x{0301}]*$/u'`, de
  `[a-zA-ZäöüÄÖÜß\x{1E9E}…]`, ro — с «ș ț» и с седилью «ş ţ». Это предупреждение ловит «чужую букву своей письменности»
  (польское «ł» в чтении испанцу), которую фатальная `foreign_script` намеренно пропускает (§3.2): строгий алфавит здесь
  стоит предупреждения, а не дня.
- no-op — нет (у каждого родного есть письменность).
- en: `'/^[A-Za-z\p{N}\p{P}\s\x{0301}]*$/u'` (en не родной — написан для полноты). ru: `'/^[а-яА-ЯёЁ\p{N}\p{P}\s\x{0301}]*$/u'`;
  uk и be — свои буквы (і ї є ґ; і ў) и **апостроф ʼ `\x{02BC}`**.
- Грабли: `\p{P}` не включает `\p{S}` (символы вроде «+»); прямой апостроф `'` — `\p{P}`, а ʼ (U+02BC) — буква-модификатор
  `\p{Lm}`, её вносят явно.

### 3.2. `script_letters`
- **native + сосед** · `LanguageWords::foreignLetters()` `LanguageWords.php:425`; `reads`: `StructureRules.php:106` (`pronunciation.foreign_script` — **ФАТАЛЬНЫЙ**); `ReplyNative::missing()` `ReplyNative.php:62,70`; `LanguagePack::asNeighbour()` `LanguagePack.php:126` → соседство `ReplyNative.php:92`.
- regex на **одну букву** (к каждой букве чтения по отдельности; цифры, знаки — не буквы и не проверяются).
- Смысл: буква не из этой письменности в чтении — фатально (карточка покажет «ֆоутoуз»); перевод роли, где меньше половины
  букв свои, — не перевод. Два языка — соседи **ровно тогда, когда строки `script_letters` совпадают посимвольно**.
- **Пишите ровно эталон:** латиница — `'/^[\p{Latin}]$/u'` (en, pl, ro, es, it, de, fr), кириллица —
  `'/^[\p{Cyrillic}]$/u'` (ru, uk, be — как у ru). Любое отклонение строки (лишний пробел, другой порядок) выключает
  сравнение соседей.
- no-op — нет. en: `'/^[\p{Latin}]$/u'` (как сосед латиницы; с пакетами LANG-1 написан). ru, uk, be: `'/^[\p{Cyrillic}]$/u'`.
  Держит `tests/Unit/Plan/LanguagePacksTest.php` («writes the one letters pattern of its script, exactly…»).
- Грабли: `\p{Latin}` — это и ð, и ș, и ł: строгость письменности, не алфавита языка (алфавит — `script`, §3.1); греческая
  θ из МФА — чужая буква (фатально), и это правильно.
- **Буква без своей письменности ничьей чужой не бывает** (`LanguageWords::foreignLetters()`, после спецификации):
  Unicode Common и Inherited пропускаются. Апостроф ʼ (U+02BC) украинского и белорусского — буква `\p{L}`, но письменности
  у неё нет, и «пʼять», «інтэрвʼю» больше не фатальны.
- **Латинские двойники в кириллическом слове чтения находкой не становятся**: `LessonParser` при разборе урока (и
  починённой карточки) меняет в каждой серии букв, где есть кириллица, латинские a e o c p x y k A E O C P X Y B H K M T
  на кириллические и á é ó ý — на гласную с U+0301 («телефoн» → «телефон», «лекáжа» → «лека́жа»). Серия без кириллицы
  («SMS-ку» — своё «SMS») и буквы других письменностей (ֆ, θ, პ) остаются находками `foreign_script`. Это были 7 из 7
  находок `foreign_script` разведки LANG-1 и 4 из 11 — на переигранных днях ru→en (`baseline.md`; DECISIONS п. 437).

### 3.3. `sentence_ends`
- **both** · `SentenceEnds::__construct` `SentenceEnds.php:63`; `LanguagePack::sentenceEnds()` `LanguagePack.php:191` →
  `FrameWords::sentences` `FrameWords.php:59`, `FrameText::fill` (точка сокращения), `CardObjects`, `PhraseCards`,
  `SpokenLines`, `SceneMaterial.php:174`, озвучка (`SceneVoiceQueue`, `VoiceTable`), `BuildLessonHandler`,
  `ReviseLessonHandler`, `ConversationMaterial`, `DayWindowViews`; `reads`: `FrameRules.php:51–54`, `FillerRules.php:43`,
  `PartnerRules.php:31–32`, `CheckRules.php:43`, `LineRules.php:40`, `StructureRules.php:45` (`exchange.second_question` — **ФАТАЛЬНЫЙ**).
- map `знак => вид`; виды — **ровно** `'statement'`, `'question'`, `'exclamation'`, `'ellipsis'`: `frame.native_punct`
  сравнивает вид конца target- и native-каркаса, `isQuestion` ищет `'question'`.
- Смысл: где кончается предложение; знак конца стоит перед пробелом, закрывающей кавычкой/скобкой или концом текста
  (кавычки: `» « " ' “ ” ‘ ’ › ‹ ) ]` — немецкие „…“ и ‚…‘, французские « … » с пробелами внутри, польские/румынские „…”,
  немецкие »…«; наряд LANG-1 §1).
- no-op — нет: у каждого языка `. ? ! …`. Пустой `[]` не ломает класс (с LANG-1 — «концов нет»), но тогда каждый каркас
  «без знака», а второй вопрос не ловится — не пишите.
- en = ru: `['.' => 'statement', '?' => 'question', '!' => 'exclamation', '…' => 'ellipsis']`.
- Грабли: испанские `¿ ¡` — **не** концы, не вносите их (если внесёте — код их выкинет). Французский пробел перед `? ! : ;`
  не мешает: знак ищется сам по себе.

### 3.4. `abbreviations`
- **both** · `SentenceEnds::__construct` `SentenceEnds.php:71` (через `words()`), `LanguagePack::speech()` `LanguagePack.php:165`
  (как написаны, только в форме хранения текста — `TextNormalizer::canonical`: составные буквы, ș ț с запятой; с точками
  и регистром → `SpeechMatch::foldAbbreviations`, и на телефон).
- list, **как пишется в тексте** (Ⓕ), с точками; регистр не важен; пробел внутри — любая серия пробелов («z. B.», «т. е.»).
- Смысл: точка сокращения не кончает предложение внутри текста («3 p.m. and 5:30 p.m.» — одно), а в самом конце текста
  закрывает его; в речи сокращение сворачивается в буквы («p.m.» → «pm»). Перед сокращением может стоять пробел,
  открывающая кавычка/скобка или `¿ ¡`.
- no-op `[]` (каждая точка — конец).
- en: `['a.m.', 'p.m.', 'e.g.', 'i.e.', 'etc.', 'vs.', 'Mr.', 'Mrs.', 'Ms.', 'Dr.', 'St.']`; ru: `['т. е.', 'т. д.', 'т. п.', 'г.', 'ул.']`.
- Грабли: **не вносите слово, которое бывает обычным словом с точкой** («No.» — это и «нет.»). Польские сокращения на
  согласную пишутся без точки («dr», «mgr») — им тут не место.

### 3.5. `question_word_order`
- **target** · `LanguageWords::isQuestion()` `LanguageWords.php:192` (читается только при `has()`), от него
  `exchange.second_question` (**ФАТАЛЬНЫЙ**), `learner.restates_partner`.
- map `['auxiliaries' => list, 'subjects' => list]` (Ⓐ): последнее предложение, начинающееся «вспомогательный +
  подлежащее», — вопрос и без «?».
- no-op `['auxiliaries' => [], 'subjects' => []]` — вопрос только по знаку. **Для языков без инверсии-вопроса (pl, ro, es,
  it) — только no-op**; fr — no-op (инверсия пишется через дефис: «avez-vous» — одно слово Ⓐ, правило его не видит); de —
  можно (Haben/Können/Kann + Sie/ich/du/wir…), но каждая пара «глагол + местоимение» в начале утвердительной фразы
  станет фатальным вопросом — взвесьте.
- en: auxiliaries `can could may … have has had`, subjects `i you he she it we they there`; ru: `null` (не target).

### 3.6. `function_words`
- **both** · `LanguageWords::isFunction()` `LanguageWords.php:52` (от него `content()`, `notIn()`, `shared()`, `valueKind()`,
  `names()`, `unresolvedPronoun()`), `SpeakingKey.php:38,61`, `SlotJudge.php:150`, `ListeningExchange.php:24`;
  `reads`: `FrameRules.php:55`, `KindRules.php:42`, `CheckRules.php:43–45`, `VocabularyRules.php:37`, `ListeningRules.php:44–46`.
- list (Ⓐ): слова без собственного содержания — артикли, предлоги, союзы, местоимения, вспомогательные и модальные,
  частицы, «да/нет/пожалуйста», сокращения-формы целиком («don't», «c'est», «l'ho» — если они бывают словами Ⓐ).
- Смысл: «контентные слова» считаются мимо них (копия пары слов в проверке, ключ «говорения», общие слова вопроса
  «Слушаю» и обмена, «окно сказало только служебное»).
- no-op — нет (пустой список = «всё — содержание»: проверки станут строже, пропуска не будет, но смысл ломается).
- en: `a an the to of in on at … okay ok well oh sorry thanks thank sure let`; ru: `и в во на с со у к … можно нужно надо`.

### 3.7. `unstressed_words`
- **target** · `LanguagePack::speech()` `LanguagePack.php:163` → `SpeechMatch` (режим «повтор»), `LineShare.php:34`; на телефон.
- list (Ⓒ): слова, которые распознаватель глотает — артикли, предлоги, вспомогательные/модальные; выкидываются с обеих
  сторон, когда ученик повторяет увиденную строку. Уже `function_words`: «no», «what», «one», «please» и **отрицание** сюда
  не входят.
- no-op `[]`.
- en: `a an the to of in … must`; ru: `в во на с … будут`.
- Грабли: пишите в орфографии языка — `speech()` сам сворачивает («weiß» станет `weiss`); но апостроф в Ⓒ удаляется:
  «dell'», «l'» здесь не встретятся никогда (элизия склеится со словом: «j'ai» — `jai`).

### 3.8. `number_words`
- **target** · `LanguagePack::speech()` `LanguagePack.php:156` → `SpokenNumbers::fold` (сравнение речи, судья окна,
  телефон).
- map `запись => 'цифры'` (Ⓒ). **Значение — строка** (`'20'`, не `20`: не-строка молча выброшена). Запись — одно слово или
  **несколько через один пробел** (§4.5): «soixante dix» => '70', «quatre vingt dix neuf» => '99'. Писать можно как пишет
  язык — `speech()` проводит каждую запись через `canonicalize`: «soixante-dix» станет `soixante dix`, «dreißig» —
  `dreissig`; две записи, свернувшиеся в одну, — одна (первая написанная). Масштаб (100, 1000, 10⁶…) и его формы
  множественного числа — отдельные записи того же значения (ro «sute», pl «tysiące»).
- no-op `[]` (числа словами ≠ цифрам).
- en: `zero one … hundred thousand million`; ru: `ноль один одна два две … миллионов` (только именительные формы).
- Грабли: de пишет 21–99 **одним словом** («einundzwanzig») — `SpokenNumbers` не режет слова, нужны записи на каждое
  нужное число (пакет de строит их циклом).

### 3.9. `number_joiners`
- **target** · `LanguagePack::speech()` → `SpeechPack::$numberJoiners` → `SpokenNumbers::fold` (и телефон).
- list (Ⓒ): слово, соединяющее **масштаб** (100, 1000…) с числом меньше ста после него: en «one hundred **and** twenty» →
  120, «two thousand and five» → 2005; de «tausend **und** eins» → 1001. **Только после масштаба** (с коммита «LANG-1 §4 —
  союз после десятков — свой ключ пакета»): соединитель после десятков — свой ключ, §3.9а. Одним списком на оба места
  английский читал «between twenty and one hundred dollars» одним числом «2100»; с двумя ключами каждое чтение en и ru —
  прежнее, слово в слово (прогон 40 млн en и 260 млн ru последовательностей, докблок `SpokenNumbers`).
- no-op `[]` (ru, uk, be, pl, ro, es, it, fr — у fr «cent vingt» подряд, «entre cent et deux cents» — два числа).
- en: `['and']`; de: `['und']`.

### 3.9а. `number_tens_joiners`
- **target** · `LanguagePack::speech()` → `SpeechPack::$numberTensJoiners` → `SpokenNumbers::fold` (и зеркало в Dart). На
  телефон ключ уходит **только непустым** (`SpeechPack::toArray()`): блок `speech` языка без него — байт в байт прежний, в
  OpenAPI `PlanSpeechRules.number_tens_joiners` — необязательный.
- list (Ⓒ): слово между ДЕСЯТКАМИ (≥ 20, не масштаб), которые сами пришли не через соединитель, и единицей после них:
  es «treinta **y** uno» → 31, «ciento treinta y uno» → 131, ro «douăzeci **și** unu» → 21; масштаб после единицы умножает
  соединённое, как всякий масштаб. Что не помещается после десятков, не соединяется («vingt et trente» — 20 и 30), и
  десятки, сами пришедшие через соединитель, второго не берут (докблок `SpokenNumbers`).
- no-op `[]` (en, ru, uk, be, pl, it, de — у de единица стоит внутри слова: «zwischen zwanzig und dreißig» — два числа).
- es `['y']`, ro `['și']`, fr `['et']` (у fr «X et un» ещё и записи `number_words`: «un» своим числом пакет не пишет).

### 3.10. `word_forms`
- **both** · `LanguageWords::content()` `:60` (`content_min_letters`), `sameStem()` `:74` (`stem_min`, `stem_tail`); `reads`:
  `KindRules.php:42`, `CheckRules.php:43–45`, `VocabularyRules.php:37`, `ListeningRules.php:44–46`.
- map трёх **int** (все обязательны — `mapInt` бросает): две формы одного слова, если у более короткого совпадает всё, кроме
  последних `stem_tail` букв, и не меньше `stem_min`; слово короче `content_min_letters` — не контентное.
- no-op — нет.
- en: `['stem_min' => 3, 'stem_tail' => 3, 'content_min_letters' => 1]`; ru: `['stem_min' => 4, 'stem_tail' => 2, 'content_min_letters' => 2]`.
- Грабли: флективным языкам (pl, ro, it, es, de, fr) — ближе к ru (4/2/2), иначе «un»/«una», «dzień»/«dnia» расходятся.

### 3.10а. `lemma_forms` (наряд GEN-4)
- **target** · `LanguageWords::irregular()`, читает `TermForms::form()` — правила скелета `vocab.not_found` (ФАТАЛЬНО) и
  `vocab.used_in_wrong`. Необязательный: без ключа находятся только формы «по буквам» (`sameStem`, общее начало, приставка).
- map: словарное слово — как знаменательное слово термина словаря, в `LanguagePack::normal()` (ro — глагол без «a», de — ß
  как ss) → list форм, которых не достают правила букв: «vouloir» → «veux», «putea» → «pot», «go» → «going», «ząb» → «zęby».
- Пишут семь целей (en, pl, ro, es, it, de, fr); ru, uk, be — не цели.
- Грабли: не `irregular_forms` разговора (форма → основа, в каноне речи без апострофа, у fr/pl/es/ro/uk/be намеренно пуст) —
  другой читатель и другое направление. Прогон ворот GEN-4 уронил день ro→fr на «vouloir», сказанном «Je veux».

### 3.11. `number_pattern`
- **both** · `LanguageWords::isNumber()` `:141` (к `normal(токен Ⓐ)`), `NumberValues::of()` `NumberValues.php:52` (target —
  какая реплика «говорит число», `ListenCards.php:222`; native — значение и варианты «Поймай число», `:223`); `reads`:
  `CheckRules.php:43` (target), `ListeningRules.php:46` (native).
- regex на **одно слово** (Ⓐ: дефис/апостроф внутри остаются — en пишет «(?:-(…))*» для «thirty-nine»): цифра где угодно,
  числительные, порядковые, «раз/половина».
- no-op — нет.
- en: `'/\d|^(?:zero|one|…|once|twice)(?:-(?:…))*$/u'`; ru: `'/^(?:\d[\p{L}\d:.,]*|один|одн[аоуиы]\w*|…|раз|раза)$/u'`.
- Грабли: ß/œ — обе формы (`drei(?:ß|ss)ig`); элизия — одно слово Ⓐ («l'un»).

### 3.12. `time_pattern`
- **native** (target — по желанию) · `LanguageWords::isTime()` `:146`, `NumberValues::of()` `:53`; `reads`: `ListeningRules.php:46` (native).
- regex на одно слово: единицы времени, части суток, дни, месяцы, «вчера/через/назад».
- no-op: для target-only языка (en) — `null` как сейчас или `'/(?!)/u'`; **у родного — настоящее значение** (иначе
  пропуск `listening.distractor_not_filler`).
- en: `null`; ru: `'/^(?:назад|спустя|…|выходн\w*)$/u'`.
- Грабли: у target, если написан, меняет выбор реплики «Поймай число» (реплика с одним «tomorrow» станет кандидатом).

### 3.13. `amount_pattern`
- **native** (target — по желанию) · `NumberValues::of()` `:54`.
- regex на одно слово: единицы, в которых **считают** (минуты, дни, недели, таблетки, градусы) — вариант «Поймай число»
  есть количество, а не дата.
- no-op `'/(?!)/u'` (количеством будет только числительное).
- en: `null`; ru: `'/^(?:секунд\w*|минут\w*|…|капл[ьия]\w*)$/u'`.

### 3.14. `amount_prefix`
- **native** (target — по желанию) · `NumberValues` `:55`, `:187`.
- regex на одно слово: предлоги/определители, стоящие **слева** от количества и входящие в вариант («на этой неделе»,
  «через неделю»).
- no-op `'/(?!)/u'`.
- en: `null`; ru: `'/^(?:на|в|во|за|через|…|кажд\w*)$/u'`.

### 3.15. `everyday_words`
- **target** · `LanguageWords::isEveryday()` `:246`; `reads`: `VocabularyRules.php:37–38` (`vocab.everyday_word`, `vocab.free_combination`).
- list (Ⓐ): STOP-LIST промта (числа, семья, время, цвета, be/have/go) и слова, которые ученик знает на любом уровне — не
  «слово дня».
- no-op — нет. en: `one two three … problem question answer`; ru: `null` (не target).

### 3.16. `ordinary_heads`
- **target** · `isOrdinaryHead()` `:251`; `reads`: `VocabularyRules.php:37`.
- list (Ⓐ): обычные прилагательные/кванторы — голова свободного сочетания («heavy things»), не чанк.
- no-op — нет. en: `big small little good bad nice great heavy many much lot few some other different important real whole`.

### 3.17. `closers`
- **target** · `isCloser()` `:265` — весь текст реплики (токены Ⓐ через пробел) равен записи; `reads`: `PartnerRules.php:33`.
- list **фраз** (Ⓐ, нижний регистр, апострофы как в тексте: «you're welcome», «d'accord», «c'est parfait»).
- no-op — нет. en: `anything else`, `sounds good`, `thank you`, `have a nice day`, `that's great`…

### 3.18. `saying_verbs`
- **target** · `isSaying()` `:256`; `reads`: `CheckRules.php:44` (`check.about_learner`).
- list (Ⓐ): глаголы «сказать/ответить/упомянуть/хотеть» во всех формах, какими их пишет вопрос проверки.
- no-op — нет. en: `say says said tell tells told answer … want wants wanted`.

### 3.19. `alternative_words`
- **target** · `isAlternative()` `:261`; `reads`: `CheckRules.php:45` — оба сняты с валидатором урока (GEN-4). С **GEN-4c** —
  `LanguageWords::alternative()` ← `AskedFor` (`partner.yes_no_missing`, `partner.yes_no_extra`): слово списка **между двумя
  словами** вопроса ученика (окно `___` — тоже слово; не первое и не последнее) — вопрос-выбор («Is ___ gross or net?»), его не
  проверяет ни одно из двух правил; «…, oder?» в конце — да/нет.
- list (Ⓐ): слово, которым собеседник перечисляет варианты («or»).
- no-op — нет. en: `['or']` (es «o», «u»; fr «ou»; it «o», «oppure»; de «oder»; pl «albo», «lub», «bądź»; ro «sau»). Грабли
  GEN-4c: pl «czy» в списке нет — вопрос-выбор «brutto czy netto?» читается да/нет; «jeść lub pić» внутри да/нет-вопроса
  читается выбором (спорные, отчёт GEN-4c).

### 3.20. `second_question_pattern`
- **target** · `asksTwice()` `:271` — к **сырому тексту**; `reads`: `PartnerRules.php:31`.
- regex: одно предложение, которое после запятой с «and/or» спрашивает снова.
- no-op `'/(?!)/u'` (тогда «два вопроса» — только два знака «?»). Флаги `/iu`.
- en: `'/,\s*(?:and|or)\s+(?:do|does|…|should)\b[^?]*\?/iu'`.

### 3.21. `articles`
- **target** · `FrameWords::packReading()` `FrameWords.php:159` (Ⓑ: выкидываются из сравнения каркаса), `LanguagePack::speech()`
  `:164` (Ⓒ: выкидываются в режимах речи; артикль перед масштабом = 1: «a hundred», ro «o sută»), `LanguageWords::isArticle()`
  `:280` (Ⓐ: «артикль после артикля» на шве — часть `filler.ungrammatical`, **ФАТАЛЬНОГО**), `WordCards.php:248,252`;
  `reads`: `FillerRules.php:43`.
- list: определённые и неопределённые артикли в формах, какими они стоят отдельным словом.
- no-op `[]` (pl — артиклей нет).
- en: `['a', 'an', 'the']`; ru: `null` → `[]` не нужно (ru не target).
- Грабли: элидированный артикль («l'», «un'») отдельным словом не бывает — во Ⓑ его раскрывают `contractions` (§4.4), в Ⓐ
  «l'hôpital» — одно слово; вносить «l'» в `articles` бессмысленно. es/fr/it «un/una/un» перед «millón/million/milione»
  станут числом 1 — так и нужно.

### 3.22. `dangling_words`
- **target** · `FrameJudge::breaksOff()` `FrameJudge.php:152` (Ⓑ, читается через `FrameWords`).
- list: слова, на которых фраза оборваться не может («Yes my», «I have a») — ход, оборванный на таком слове, не «не понял».
- no-op `[]`.
- en: `['a', 'an', 'the', 'my', 'your', 'our', 'their']`.

### 3.23. `seam_repeatable_words`
- **target** · `isSeamRepeatable()` `:286` (Ⓐ); `reads`: `FillerRules.php:43` (`filler.ungrammatical`, **ФАТАЛЬНЫЙ**).
- list: слово, которое шов каркаса и наполнения может сказать дважды и остаться языком («move in in June», «that that»).
- no-op `[]` (каждое удвоение на шве — фатально).
- en: `in on out off up down over back away through around by that had`.

### 3.24. `article_sound`
- **target** · `isSoundArticle()` `:290`, `articleMismatch()` `:302`; `reads`: `FillerRules.php:43,45` (`filler.ungrammatical`
  **ФАТАЛЬНЫЙ**, `filler.article_seam`).
- map **шести строк** (все обязательны — `mapString`): `before_vowel`, `before_consonant` (артикли), `vowel`, `consonant`,
  `exception` (regex к `normal(слово)`), `spelled` (regex к слову как написано — аббревиатура читается по буквам).
- no-op (язык без артикля, меняющегося по звуку — все, кроме en):
  `['before_vowel' => '', 'before_consonant' => '', 'vowel' => '/(?!)/u', 'consonant' => '/(?!)/u', 'spelled' => '/(?!)/u', 'exception' => '/(?!)/u']`
  (пустая строка никогда не равна слову).
- en: `an` / `a`, `'/^[aeio]/u'`, `'/^[bcdfgjklmnpqrstvwyz]/u'`, `'/^(?:[A-Z]{2,}|[A-Z]-)/u'`, `'/^one/u'`.
- Грабли: it «uno/un», «lo/il», fr «ce/cet», es «el agua» — правило двухартиклевое и звуковое, как в en; если пишете
  настоящее — проверьте на живом дне: промах здесь фатален.

### 3.25. `clause`
- **target** · `LanguageWords::clause()` `:331`; `reads`: `FillerRules.php:43,44` (`filler.ungrammatical` **ФАТАЛЬНЫЙ** — «целое
  предложение там, где у каркаса уже есть глагол»; `filler.is_clause` — предупреждение).
- map пяти списков (Ⓐ; `mapWords` терпит отсутствие поля): `subjects`, `finite` — подлежащее + финитный глагол в начале →
  SENTENCE; `contractions` — токен «подлежащее+глагол» одним словом («i'm», fr «c'est», «j'ai», it «c'è»); `subordinators`
  — союз в начале → CLAUSE; `subordinators_before_subject` — союз только перед подлежащим («after he eats», не «after
  meals»).
- no-op: `['subjects' => [], 'finite' => [], 'contractions' => [], 'subordinators' => [], 'subordinators_before_subject' => []]`.
- en: subjects `i we he she they it you`, finite `am is are … can will`, …
- Грабли: у языков с выпадающим подлежащим (es, it, pl, ro) «tengo fiebre» не узнаётся — это нормально; а вот широкий
  `finite` (es «es», «está») + `subjects` («él», «ella») даст фатальную находку на честном наполнении — держите списки узкими.

### 3.26. `unresolved_pronouns`
- **target** · `unresolvedPronoun()` `:365`; `reads`: `FrameRules.php:55` (`frame.unresolved_pronoun`, предупреждение).
- map семи списков (Ⓐ): `words` (местоимения, на которые каркас может опираться), `frame_initial_subject`, `existential`,
  `determiner_or_number`, `partitive`, `be_forms`, `determiners`.
- no-op: все семь `[]` (достаточно `words => []`).
- en: `words: it that one there`, …

### 3.27. `gendered_past_pattern`
- **native** · `genderedPast()` `:443` — к `mb_strtolower(текст)`, берётся **группа 1**; `reads`: `NativeRules.php:25`
  (`native.gendered_past`, только пока пол ученика неизвестен).
- regex с одной захватывающей группой — форма прошедшего времени с родом после «я».
- no-op `'/(?!)/u'` (es, de, ro — прошедшее без рода у «я»; fr/it — «je suis allé(e)», «sono andato/a» — по желанию; pl —
  «byłem/byłam» — нужно; uk, be — нужно).
- en: `null`; ru: `'/(?<![\p{L}])я\s+(?:(?:не|уже|…)\s+)?(\p{Cyrillic}{2,}[аяеиыуоё]л(?:а|ся|ась)?)(?![\p{L}])/u'`.

### 3.28. `agreement`
- **native** · `agreeingWithSlot()` `:456`; `reads`: `FrameRules.php:56` (`frame.native_agreement`, предупреждение).
- map: `words`, `short_forms`, `suffixes_before_slot` — списки (Ⓐ); `min_letters`, `after_slot_words` — **int, обязательны**.
  Слово прямо перед `___` согласуется, если оно в списках или прилагательное по окончанию (≥ `min_letters` букв); одно из
  `after_slot_words` слов после `___` — если в списках.
- no-op: `['words' => [], 'short_forms' => [], 'suffixes_before_slot' => [], 'min_letters' => 99, 'after_slot_words' => 0]`.
- en: `null`; ru: притяжательные/указательные/«какой» во всех падежах, краткие формы, `['ый','ий',…]`, `4`, `2`.

### 3.29. `rescue_line`
- **target** · `LanguagePack::rescueLine()` `LanguagePack.php:200` → `TakeConversationTurnHandler.php:91` (что говорит
  ход-«спасение» в пузыре ученика).
- string, короткая реплика «простите, не расслышал». no-op — нет (без неё ход без слов).
- en: `'Sorry?'`; ru: `'Простите?'` (uk `'Перепрошую?'`, ro `'Poftim?'`).

### 3.29а. `rescue` (наряд LANG-1b §2)
- **target** · `LanguagePack::rescue()` → `RescueKits::of()` (набор плана на проводе, `rescue_kit`) и `RescueKits::owed()`
  (звук набора, `VoiceRescueKitHandler`).
- list из **шести** строк `['target' => <реплика на цели>, 'native' => ['ru' => …, 'uk' => …, 'be' => …, 'pl' => …, 'ro' =>
  …, 'es' => …, 'it' => …, 'de' => …, 'fr' => …]]`: «Sorry?» (= `rescue_line`), «Could you say that more slowly, please?»,
  «I don't understand.», «One moment.», «Can you write it down?», «Thank you.» — в языке цели, с переводом на все девять
  родных. no-op — `[]` у языков, которые бывают только родными (ru, uk, be): набор — ЦЕЛИ.
- Пишет одна матрица на все пакеты — `docs/research/lang-1b/tools/rescue-kit.py` (6 смыслов × 10 языков; колонка — и
  строка цели, и перевод для ученика этого языка; fr — с U+00A0 перед «?», es — с «¿»). Строка набора с пустым текстом или
  без перевода — ошибка пакета (`LanguagePackKeyMissing`), не пропуск.
- Тест: `LanguagePacksTest` (шесть строк у каждой цели, девять родных у каждой, первая — `rescue_line`, один перевод смысла
  на все цели, `[]` у ru/uk/be), `RescueKitTest` (пара ru→de — немецкий набор с русскими переводами, be→en — английский с
  белорусскими).

### 3.30. `neutral_reply`
- **both** · `LanguagePack::neutralReply()` `:212` → `ConversationMoves.php:437` (target — сказанное, native — его перевод).
- string: одна нейтральная реплика роли, **одинаковая по смыслу во всех пакетах**. no-op — нет.
- en: `'I see. Please go on.'`; ru: `'Понятно. Продолжайте, пожалуйста.'`.

### 3.31. `irregular_forms`
- **target** · `WordBases::of()` `WordBases.php:20,23` (из `LineShare` — Ⓒ, из `FrameJudge::doSupport` — Ⓑ).
- map `форма => основа` (свёрнуто, нижний регистр, без апострофов).
- Смысл: две формы — одно слово, если их основы встречаются («has» = «have», «me» = «i»).
- no-op `[]`. **Ловушка:** без этого ключа `WordBases` не читает и `inflection_rules` — язык с одними правилами пишет
  `'irregular_forms' => []`.
- en: `am/is/are/was… => be`, `has/had => have`, …; ru: не пишется.

### 3.32. `inflection_rules`
- **target** · `WordBases::of()` `:25`.
- list пар `[regex, замена]` — каждое совпавшее правило даёт основу (регулярка к свёрнутому слову без апострофа). Неверная
  пара молча пропускается.
- no-op `[]`. en: `['/^(.{2,})ies$/u', '$1y']`, …, `['/^(.{2,})s$/u', '$1']`.

### 3.33. `person_swap`
- **target** · `LineShare::share()` `LineShare.php:35–36` (к словам Ⓒ).
- map `слово => слово`: первое и второе лицо, переставленные для поиска «эха» роли («My son» → «your son»).
- no-op `[]`. en: `i => you, me => you, my => your, … are => am`.

### 3.34. `contractions` · 3.35 `contractions_before`
- **target** · `FrameWords::contractions()` `FrameWords.php:182`, `spelt()` `:124` (Ⓑ; прочитаны раз на пакет —
  `packReading()` `:159`).
- `contractions`: map `слово => 'слова через пробел'` — целое слово; **ключ, кончающийся апострофом, — префиксная элизия**
  (§4.4). `contractions_before`: map `следующее слово => [хвост => слово]` («'s» перед «been» — «has»).
- no-op `[]` оба.
- en: `"i'm" => 'i am'`, `"don't" => 'do not'`, `'cannot' => 'can not'`…; `['been' => ["'s" => 'has', "'d" => 'had']]`.
- Грабли: ключи — нижний регистр, апостроф любой (`'` или `’` — читаются одинаково); **сначала целое слово, потом
  элизия**: «s'il» => 'si il' спасает «s'il» от элизии «s'» => 'se'. `clause.contractions` — другой ключ (Ⓐ).

### 3.36. `intro_words` · 3.37 `clause_starters`
- **target** · `FrameJudge::starts()` `FrameJudge.php:422`, `clauseStarts()` `:454` (Ⓑ).
- list; запись `intro_words` может быть фразой («thank you»). Каркас сказан в начале хода или сразу после серии
  вводных слов; и сразу после союза, начинающего клаузу, — где угодно (наряд FIX-4b §1).
- no-op `[]` оба.
- en: `hello hi hey yes no okay ok oh well so sure great nice thanks 'thank you' please and um uh`; `['and', 'but', 'so', 'then', 'or']`.

### 3.38. `negation` — §4.3. · 3.39 `partitive`
- **target** · `FrameJudge::afters()` `FrameJudge.php:284`.
- map `['word' => string, 'determiners' => list]`: окно из одного детерминатора может опустить `word` после себя («I have no
  experience» = «I have ___ of experience»). Сравнивается **как написано** — свёрнуто, нижний регистр, одно слово.
- no-op `[]`. en: `['word' => 'of', 'determiners' => ['no', 'any', 'some', 'much', 'little', 'enough', 'more', 'less']]`.

### 3.40. `common_words` — §4.1. · 3.41 `talk_title_template` — §4.2.

### 3.42. `yes_no` (наряд GEN-4c)
- **target** · `LanguageWords::yesNoOpening()`, `yesNoWords()` ← `partner.yes_no_missing` / `partner.yes_no_extra`
  (`SkeletonContext::targetReading('yes_no', 'question_words', 'alternative_words')` — без любого из трёх правила не бегут).
- list (Ⓐ): слова, которыми ответ на вопрос да/нет **начинается** — «да» **первым**, «нет» **вторым** (причина находки называет
  эти два с заглавной: «start with Da. or Nu.»), дальше прочие (de «doch», fr «si» — «да» на отрицательный вопрос). Сравнивается
  **первое слово** реплики (`Words::tokens`, `normal()`: регистр, знаки, «¡ ¿» не в счёт) — «No test is needed now.»
  начинается с «no», «Nu este nevoie…» — с «nu».
- no-op — нет; ru, uk, be (не цели) — не пишут.
- ro `['da', 'nu']`, en `['yes', 'no']`, fr `['oui', 'non', 'si']`, es `['sí', 'no']`, it `['sì', 'no']`, de `['ja', 'nein', 'doch']`,
  pl `['tak', 'nie']`.
- Грабли: акцент обязателен там, где он отличает слово — es «Si le interesa…» (если) и it «Si può…» (местоимение) не «да»;
  fr «Si vous…» (если) читается как «да» — предел списка. Отрицание, совпадающее с «нет» (ro «nu», es «no», pl «nie»), в
  начале реплики читается как ответ «нет».

### 3.43. `question_words` (наряд GEN-4c)
- **target** · `LanguageWords::questionWord()` ← `AskedFor` (те же два правила).
- list (Ⓐ): слова и **обороты** вопроса о факте (что, какой, кто, где, когда, как, сколько, почему); оборот — слова подряд
  («how many», «cât de», «à quelle heure», «wie viel», «o której», «por qué»). Ищется **где угодно** в каркасе (fr «Je commence
  à quelle heure ?», «C'est combien ?» — слово вопроса в конце): слово текста — целиком и по частям при апострофе и дефисе
  («What's» даёт «what», «Dov'è» — «dov», «qu'est-ce que» — одно слово «qu'est-ce» и «que»). Находится самый длинный оборот.
- Нет ни одного и нет выбора (`alternative_words`) — вопрос да/нет. **Не писать** то, что само спрашивает да/нет: fr «que»
  («Est-ce que ___ compte ?»), pl «czy».
- Семь целей: ro `ce care cine cui unde încotro când cum cât câtă câți câte`, «de ce», «cât de», «de când», «până când», «cât
  timp», «la ce oră», «ce fel de»; en `what which who whom whose where when why how`, «how many», «how much», «how long», «how
  often», «what time», «what kind of»; de — w-слова и «wie viel(e)», «wieviel», «wie lange», «wie oft», «wie spät», «um wie viel
  uhr», «was für», wo-наречия; fr — `qui quoi quel(s) quelle(s) lequel… où quand comment combien pourquoi`, «qu'est-ce que/qui», «à
  quelle heure», «combien de temps», «depuis quand», «jusqu'à quand»; es — с акцентами (`qué cuál quién dónde cuándo cómo cuánto…`),
  «por qué», «a qué hora», «qué tan»; it — `che cosa chi quale qual dove quando come perché quanto…` и усечённые `dov com cos
  quant`; pl — `co kto gdzie kiedy jak dlaczego ile jaki… który…`, «po co», «o której», «od kiedy», «jak długo».
- Грабли: слово списка в придаточном ничего не спрашивает — it «Posso lavorare come ___?» (как), pl «co miesiąc» (каждый), de
  «so schnell wie möglich» — такой каркас читается вопросом о факте (сторона, где «да/нет» не требуется). Прогон GEN-4c: 136
  каркасов записанных ответов — ни одного ложного «да/нет» или «факт» (отчёт GEN-4c).

---

## 4. Шесть форм LANG-1 (решены главной сессией — одинаково для всех исполнителей)

### 4.1. `common_words`
- **native + сосед (пишет каждый пакет, en тоже)** · `LanguagePack::commonWords()` `LanguagePack.php:80`, `asNeighbour()` `:126`
  → `ReplyNative::inNeighboursWords()` `ReplyNative.php:92–96`.
- `list<string>` — частые слова языка, нижний регистр как даёт `LanguagePack::normal()` (Ⓓ): **одиночные серии
  букв** — без апострофов, пробелов, дефисов (строка режется на них: «c'est» → «c», «est»; пишите «est»).
- Смысл: перевод роли (`reply_native`) — не перевод, если в нём < 2 слов, частотных только у родного, и ≥ 2 слов, частотных
  только у соседа с той же `script_letters` (любого языка развёртки, не только цели). Общие у двух списков слова сами
  выпадают из сравнения.
- **Частые И отличительные** (так написаны все десять пакетов; наряд говорил «30 самых частотных»): в список не входит
  слово, которое в том же написании — обычное слово соседа той же письменности, даже если сосед его не внёс. Иначе оно
  считается «чужим» в честной строке соседа, и два таких слова — отказ честному переводу (проба отказала ru-строке «Для
  записи к врачу приходите до двенадцати», пока список uk держал «для» и «до»). Так en не пишет «a i in to on do so no me
  my we…», pl — «nie i a o do to na ja mi ale…». Длина — чтобы в обычной строке языка их было два и с чужим именем внутри:
  ru 86, pl 52, it 51, fr 48, be 47, ro 45, de 44, uk 44, en 41, es 35. Держат `tests/Unit/Plan/Lang/CrossPackTest.php`
  («lists no word a neighbour in the same letters says as an ordinary word»; «keeps every learner's line of the scouting
  run a translation» — 1 322 родные строки разведки, 0 ложных отказов) и `LanguagePacksTest.php`.
- no-op `[]` (соседи не различаются, ничего не отклоняется).
- Грабли: орфография любая — `normal()` сворачивает и строку, и список (ß, œ, ş); только слова, **которые реально
  встречаются в коротких репликах** (служебные: предлоги, местоимения, «быть/иметь»; en — ещё «today», «tomorrow» из реплик
  роли), не темы.

### 4.2. `talk_title_template`
- **native, кроме ru и uk** (en — не родной; be — пишет) · `NativeStrings::talkTemplate()` `NativeStrings.php:348–375`,
  `LanguagePack::talkTitleTemplate()` `LanguagePack.php:96` (строгий: бросает на `title` без `{roles}` и не-bool `lower_first`).
- map: `'title' => 'Rozmowa: {roles}'`, `'and' => 'i'`, `'anyone' => 'Rozmowa'`, `'lower_first' => true`;
  необязательно `'and_before' => [regex => слово]` — слово вместо `and` перед последней ролью, чья запись совпала с
  регуляркой (es: `['/^h?[ií](?![aeouáéóú])/iu' => 'e']` — «médico e internista», перед «hie-» остаётся «y»; it:
  `['/^e/iu' => 'ed']`); нет или `[]` — `and` везде. `and_before` читает только заголовок (`NativeStrings`),
  `LanguagePack::talkTitleTemplate()` его не отдаёт и не проверяет.
- Смысл: заголовок разговора без склонения: роли по порядку сцен, каждая один раз (без учёта регистра), через «, » и `and`
  перед последней; нет ролей — `anyone`. `lower_first` понижает первую букву роли, кроме аббревиатуры — **в ПЕРВОМ слове
  роли ≥ 2 заглавных** («HR-menedżer», «IT-specjalista», «DJ» остаются; «Specjalista IT» → «specjalista IT»). **de —
  `'lower_first' => false`** (существительные с заглавной: «Gespräch: Rezeptionistin und Arzt»); fr — `title`
  «Conversation\u{00A0}: {roles}» (неразрывный пробел перед двоеточием).
- no-op `[]` — у ru, uk, en (склонение ru/uk и английский — в коде `NativeStrings::TALK_TITLE`; для этих трёх код пакет не
  спрашивает вовсе). Родной без шаблона (или с шаблоном без трёх строк) — английский запасной заголовок, как до наряда.
- Грабли: пишите все четыре поля, `lower_first` — bool явно (`talkTitleTemplate()` бросает на поле, которого нет или не
  того типа; `LanguagePacksTest` держит каждый пакет развёртки).

### 4.3. `negation`
- **target** · `FrameJudge::negation()` `FrameJudge.php:379–409` (с `oneWordEach()`), `freeNot()` `:348`, `doSupport()` `:363`.
- map `['words' => list, 'after' => list|null, 'do_support' => list]`. Слово из `words`, **вставленное** в ход, ничего не
  стоит: сразу после слова из `after`, если `after` — список (en: формы be, модальные, have); **где угодно, включая первое
  слово**, если `after` = null или не написан (pl «nie», ro «nu», «n» («n-am» — дефис = пробел), es «no», it «non», fr
  «ne», «pas», de «nicht», «kein», «keine», «keinen», «keinem», «keiner», «keines»). `do_support` — английское do/does/did +
  отрицание + глагол по основе.
- Отрицание прощается только **добавленным**: слово отрицания, которое есть в каркасе и выпало из хода, — обычное отличие
  (позитивный ход против негативного каркаса — «почти»). Отрицание в окне — значение ученика («Das passt mir am Montag nicht»
  → окно «Montag nicht»).
- Старая en-форма `'word' => 'not'` читается как `words: ['not']`. `'after' => []` (пустой список) — «ни после чего», т. е.
  никогда; не путайте с null.
- no-op `[]`. en: `['word' => 'not', 'do_support' => ['do','does','did'], 'after' => ['am','is',…,'had']]` (или новая форма).
- Каждая запись читается как слово хода (Ⓑ): «N'», «Nicht» тоже сработают; запись, дающая больше одного слова, выбрасывается.

### 4.4. `contractions` — префиксные элизии
- Запись, **ключ которой кончается апострофом**, — префикс: слово, начинающееся этим префиксом и имеющее **букву** после
  него, читается как значение записи + остаток (остаток читается тем же правилом): fr `"j'" => 'je'` («j'ai» → «je ai»),
  `"n'" => 'ne'`, `"l'" => 'le'`, `"d'" => 'de'`, `"qu'" => 'que'`, `"s'" => 'se'`, `"jusqu'" => 'jusque'`; it `"l'" => 'lo'`,
  `"un'" => 'una'`, `"dell'" => 'dello'`, `"all'" => 'allo'`. Применяется **до** удаления апострофа; `'` и `’` равны (в тексте и
  в ключе).
- Так «Je n'ai pas de fièvre» против «J'ai ___» — сказано (ne/pas бесплатны по `negation`).
- Слово без буквы после префикса («d'1») — не элизия; элизия отдельным словом («l' hôpital») — целая запись → «le».
- Порядок: `contractions_before` → целое слово → элизия → разрезание по знакам.

### 4.5. `number_words` из нескольких слов; соединители — два ключа
- Запись `number_words` может держать **несколько слов через один пробел**; в каждом месте читается **самая длинная**
  совпавшая запись — одним значением («soixante dix» → 70, «quatre vingt dix neuf» → 99, одиночное «quatre» → 4).
- Соединитель — **два ключа по месту** (первая версия спецификации держала оба места в одном `number_joiners`, и английский
  поехал: «twenty and five» стало 25, «between twenty and one hundred dollars» — «between 2100 dollars»):
  `number_joiners` — только **после масштаба** (en «one hundred and twenty» → 120, de «tausend und eins» → 1001, §3.9);
  `number_tens_joiners` — только **после десятков** (≥ 20, не масштаб), пришедших не через соединитель (es «treinta y uno»
  → 31, ro «douăzeci și unu» → 21, «ciento treinta y uno» → 131, §3.9а). Слово может стоять в обоих списках. Что не
  помещается после десятков, не соединяется: fr 71 «soixante et onze» — **только отдельной записью**
  `'soixante et onze' => '71'` (соединитель внутри записи допустим). en и ru читают каждую последовательность так же, как
  до наряда (DECISIONS п. 431). Подробно — докблок `app/Modules/Shared/Domain/Service/SpokenNumbers.php`; зеркало телефона
  — `mobile/lib/data/plan/session/spoken_numbers.dart`, одна таблица примеров с сервером.

### 4.6. No-op вместо null
- Правило, которое к языку не относится, пишется no-op из §2/§3 — **никогда null**: null в ключе из `reads()` — это пропуск
  `lang.pack_missing` на каждом дне этой пары. Проверено тестом `lvNoOpPack()`.

---

## 5. Что даёт `lang.pack_missing` (вызовы `reads()`)

Пропуск пишется, когда у пакета стороны нет хоть одного ключа; ключи ниже — **все**, что спрашивает `reads()`.

**Target** (`$context->target`):

| Код | Ключи |
|---|---|
| `frame.no_end_punct`, `frame.native_punct` | `sentence_ends` |
| `frame.unresolved_pronoun` | `unresolved_pronouns`, `function_words` |
| `filler.ungrammatical` (**фатальный**) | `sentence_ends`, `articles`, `article_sound`, `clause`, `seam_repeatable_words` |
| `filler.is_clause` | `clause` |
| `filler.article_seam` | `article_sound` |
| `exchange.second_question` (**фатальный**) | `sentence_ends` |
| `partner.two_questions` | `sentence_ends`, `second_question_pattern` |
| `partner.too_long` | `sentence_ends` |
| `partner.closer` | `closers` |
| `rescue.new_fact` | `function_words`, `word_forms` |
| `check.verbatim` | `function_words`, `word_forms`, `number_pattern`, `sentence_ends` |
| `check.about_learner` | `saying_verbs`, `function_words`, `word_forms` |
| `check.listed_alternative_as_wrong` | `alternative_words`, `function_words`, `word_forms` |
| `vocab.free_combination` | `ordinary_heads`, `everyday_words`, `function_words`, `word_forms` |
| `vocab.everyday_word` | `everyday_words` |
| `learner.restates_partner` | `sentence_ends` (и `question_word_order`, если написан — вне `reads()`) |

Итого target: `sentence_ends function_words word_forms number_pattern unresolved_pronouns articles article_sound clause
seam_repeatable_words second_question_pattern closers saying_verbs alternative_words ordinary_heads everyday_words`.

**Native** (`$context->native`):

| Код | Ключи |
|---|---|
| `pronunciation.script` | `script` |
| `pronunciation.foreign_script` (**фатальный**) | `script_letters` |
| `frame.no_end_punct`, `frame.native_punct` | `sentence_ends` |
| `frame.native_agreement` | `agreement` |
| `listening.same_exchange`, `listening.no_learner_value` | `function_words`, `word_forms` |
| `listening.distractor_not_filler` | `function_words`, `word_forms`, `number_pattern`, `time_pattern` |
| `native.gendered_past` (только при неизвестном поле) | `gendered_past_pattern` |
| `options.form_mismatch` (**фатальный**) | — (ключей не читает) |

Итого native: `script script_letters sentence_ends agreement function_words word_forms number_pattern time_pattern
gendered_past_pattern`.

Вне `reads()` пропуск не пишется, но **пакет без ключа молча беднеет**: без `sentence_ends` нет `FrameText::fill` по
сокращениям и режутся предложения голой регуляркой; без `number_pattern`/`time_pattern` нет карточки «Поймай число»; без
`common_words` соседи не различаются; без `rescue_line` ход-спасение без слов.

---

## 6. Фатальные коды (`LessonGate::FATAL`) и от каких ключей они зависят

День с фатальной находкой не выдаётся до починки P2R (не больше двух карточек), иначе `failed`. Поэтому ключи этой
таблицы пишутся **осторожнее всего**: лишнее слово в списке — ложная фатальная находка на честном уроке; дыра — пропуск.

| Код | Сторона | Ключи | Как ключ делает находку |
|---|---|---|---|
| `line.ne_frame` | — | ни одного (`FrameText::line`: каркас + наполнение посимвольно; клей, регистр первой буквы — за `¿ ¡` тоже — и знак конца прощены) | — |
| `exchange.repeats` | — | ни одного (тот же `FrameText::line`) | — |
| `filler.ungrammatical` | target | `sentence_ends` + `abbreviations` (наполнение «несёт своё предложение»), `articles` (артикль после артикля), `article_sound` (a/an), `clause` (целое предложение при глаголе каркаса), `seam_repeatable_words` (удвоение на шве) | без любого из пяти ключей — только языконезависимая часть (, ; : в конце, слот, удвоение) |
| `exchange.second_question` | target | `sentence_ends` (вид `question`), `question_word_order` (если написан — вопрос без «?») | широкий `question_word_order` = ложные фатальные |
| `pronunciation.foreign_script` | native | `script_letters` | узкая буквенная регулярка = ложные фатальные (латиница: `\p{Latin}`, не алфавит языка). Не находки: буква без письменности (ʼ U+02BC) и латинские двойники в кириллическом слове — их чинит разбор урока (§3.2) |
| `options.form_mismatch` | native | ни одного (длина 0.5–2× правильного, **строчная первая буква**, кусок реплики собеседника) | «строчная» — по **первому символу**, не по первой букве, и только **против верного варианта** (коммит «LANG-1 по разведке — строчная после цифры не „форма“…»): вариант с цифры, кавычки, «¿ ¡» регистра не имеет (fr «9 h du matin», ru «1050 евро» больше не фатальны); строчный вариант — находка, только когда верный с заглавной (или наоборот); все со строчной — одна форма. de «am Montag» против «Am Freitag» — по-прежнему находка; ключом не лечится. Длина и «кусок реплики» — как у FIX-3 (правило решает архитектор) |
| `check.shape`, `listening.shape`, `exchange.shape` | — | ни одного | — |
| `vocab.known_repeat`, `frame.known_repeat` | — | ни одного (`FrameText::identity`) | — |

---

## 7. Как проверить пакет

Условия: пакет лежит в `config/lesson/lang/<code>.php`; PHP — только в сайдкаре, своя БД (Unit-тесты БД не трогают).
Ожидание: **пропусков нет** на каждой стороне, которой язык бывает; ни один читатель не бросает.

### 7.1. Tinker — пропуски валидатора для пары (как читает бой: `config('lesson.lang')`)

Замените `pl`/`en` на свою пару: язык как target — `$native = "ru"; $target = "<code>"`, как native — `$native = "<code>";
$target = "en"`. Выводит JSON пропусков; должно быть `[]` (у ru/en-стороны пары пропусков нет и сейчас).

```sh
docker exec -w /wt -e DB_DATABASE=<YOUR_DB> wt_lang1 php artisan tinker --execute='$native = "pl"; $target = "en"; $packs = app(App\Modules\Plan\Domain\Check\Language\LanguagePacks::class); $request = new App\Modules\Plan\Application\Dto\LessonRequest("x", "x", "English", "Russian", App\Modules\Plan\Domain\ValueObject\PlanLevel::Beginner, null, 8, 8, App\Modules\Plan\Infrastructure\Model\FakePlanModel::roles(), new App\Modules\Plan\Domain\Lesson\EarlierDays); $lesson = (new App\Modules\Plan\Domain\Lesson\LessonParser)->parse(App\Modules\Plan\Infrastructure\Model\FakePlanModel::lessonPayload($request)); $context = new App\Modules\Plan\Domain\Check\LessonValidationContext(8, 8, $packs->for($native), $packs->for($target)); (new App\Modules\Plan\Domain\Check\LessonValidator)->run($lesson, $context); echo json_encode(array_map(fn ($s) => $s->toArray(), $context->skips->all()), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;'
```

Урок фейковый (англо-русский) — находки в нём бессмысленны для чужой пары, смотрите **только пропуски**: какие проверки
пара пропускает, от текста урока не зависит. Пол ученика не передан — `native.gendered_past` спрашивается тоже.

### 7.2. Pest — пропуски обеих сторон и каждый читатель вне валидатора

В свой тестовый файл (`uses(RefreshDatabase::class)` не нужен — это Unit; имя функции-хелпера не заводится, всё в
замыкании). `lessonPacks()` читает **все** файлы `config/lesson/lang/*.php` (`tests/Pest.php`). Прогон:
`docker exec -w /wt -e DB_DATABASE=<YOUR_DB> wt_lang1 vendor/bin/pest <ваш файл>`.

```php
<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\NumberValues;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\Language\PackSkip;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\FrameJudge;
use App\Modules\Plan\Domain\Service\LineShare;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\Service\WordBases;
use App\Modules\Plan\Domain\ValueObject\ConversationPhrase;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\Service\LanguageRoles;

it('gives every reader of the pack what it reads, in the shape it reads it', function (string $code) {
    $pack = lessonPacks()->for($code);
    $words = new LanguageWords($pack);
    $gaps = static function (string $side) use ($code): array {
        $context = $side === 'target' ? lessonContext('ru', $code) : lessonContext($code, 'en');
        $request = new LessonRequest('x', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays);
        (new LessonValidator)->run((new LessonParser)->parse(FakePlanModel::lessonPayload($request)), $context);

        return array_values(array_filter(
            array_map(static fn (PackSkip $skip): array => $skip->toArray(), $context->skips->all()),
            static fn (array $skip): bool => $skip['language'] === $code,
        ));
    };

    if (in_array($code, LanguageRoles::planTargets(), true)) {
        expect($gaps('target'))->toBe([]);
        foreach (['abbreviations', 'question_word_order', 'unstressed_words', 'number_words', 'number_joiners', 'number_tens_joiners', 'irregular_forms', 'inflection_rules', 'person_swap', 'contractions', 'contractions_before', 'intro_words', 'clause_starters', 'negation', 'partitive', 'dangling_words', 'rescue_line', 'neutral_reply', 'script_letters', 'common_words'] as $key) {
            expect($pack->has($key))->toBeTrue("the target side reads `{$key}`");
        }
        expect(NumberValues::of($pack))->not->toBeNull();
        // Each call throws on a key written in the wrong shape.
        (new FrameJudge)->move('a b c', [new ConversationPhrase('x', 'p1', 'A ___.', '', null, null)], $pack);
        (new FrameJudge)->breaksOff('a b', [], $pack);
        (new LineShare)->share('a b', 'b a', $pack, swapPersons: true);
        WordBases::of('abc', $pack);
        $words->isQuestion('a b');
        $words->asksTwice('a, b?');
        $words->isCloser('a');
        $words->clause('a b c');
        $words->articleMismatch('a', 'b');
        $words->unresolvedPronoun('a b ___.');
        $words->valueKind('a 2');
    }
    if (in_array($code, LanguageRoles::planNatives(), true)) {
        expect($gaps('native'))->toBe([]);
        foreach (['abbreviations', 'amount_pattern', 'amount_prefix', 'neutral_reply', 'common_words'] as $key) {
            expect($pack->has($key))->toBeTrue("the native side reads `{$key}`");
        }
        if (! in_array($code, ['ru', 'uk'], true)) {
            expect($pack->talkTitleTemplate())->not->toBeNull()
                ->and((new NativeStrings($code))->talkTitle(['Recepcjonistka', 'MRI'], $pack))->toContain('MRI');
        }
        expect(NumberValues::of($pack))->not->toBeNull();
        $words->agreeingWithSlot('a b ___ c d.');
        $words->genderedPast('a b');
        $words->foreignLetters('ab');
        $words->readsInScript('ab');
        $words->valueKind('a 2');
    }
})->with(['pl']);
```

Сниппет написан до пакетов и прогнан тогда для `en` и `ru` (зелёное, кроме `has()` ключей LANG-1, которых у них ещё не
было); `number_tens_joiners` в список ключей цели добавлен после. С пакетами все десять пакетов держат тесты ветки:
`tests/Unit/Plan/Lang/CrossPackTest.php` → «leaves no check of a plan pair without its key» (пропусков валидатора нет у
каждой цели при родном ru и у каждого родного при цели en — каждая сторона каждого языка), `tests/Unit/Plan/LanguagePacksTest.php` (ни один ключ развёрнутого пакета не
null, эталоны `script_letters`, форма `common_words` и `talk_title_template`), `tests/Unit/Plan/Lang/<Code>PackTest.php` —
по языку; прежний `LessonValidatorTest` → «leaves no check without its key for the English target and the Russian
learner» (`lvPackGaps()`) остался.

### 7.3. Смысл, а не только форма

Пропусков нет — это форма. Смысл проверяют строки своего языка: каркас с отрицанием и элизией судьёй
(`tests/Unit/Plan/FrameJudgeTest.php` — синтетические pl/ro/es/it/fr/de пакеты этого наряда показывают, какие ходы должны
выходить «сказано»), числа словами (`SpokenNumbers`), конец предложения с кавычками языка
(`tests/Unit/Plan/SentenceEndsTest.php`), и живой день пары (`docs/research/lang-1/tools/live.php`).

---

## 8. Известные пределы и открытые вопросы (для отчёта)

1. ~~**`LanguagePack::normal()` не сворачивает**~~ — **закрыто** (коммит «LANG-1 §4 — союз после десятков — свой ключ
   пакета; списки речи в форме текста…»): `normal()` = `TextNormalizer::fold` + нижний регистр + `’`→`'` (ß→ss, œ→oe,
   ş→ș, ţ→ț) — и для слова текста, и для списков пакета (Ⓐ, Ⓓ); `speech()` проводит слова списков и записи `number_words`
   через `LexicalNormalizer::canonicalize` — в Ⓒ они в форме текста. Стандартная орфография пакета верна для всех
   семейств; en и ru отдают те же списки, что до наряда. Цена: регулярки Ⓐ получают свёрнутое слово — буква, которую
   свёртка меняет, пишется обеими формами (§1.3).
2. **de: каждое существительное с заглавной** — `LanguageWords::names()` читает его как имя, и `check.verbatim` освобождает
   пару с существительным (под-срабатывание, не ложная находка). Ключом не лечится.
3. **fr: пробел перед `? ! : ;`** теряется в `FrameText::fill()` и `withEndMarkClosed()` (для en/ru это была типографская
   грязь) — native-карточка fr покажет «fièvre?»; сравнение строк не страдает.
4. **`options.form_mismatch` «строчная первая буква»** — механическая часть **закрыта** (DECISIONS п. 437): регистр
   читается по первому символу (вариант с цифры, кавычки, «¿ ¡» — без регистра: «9 h du matin», «1050 евро» больше не
   фатальны) и только против верного варианта. Осталось: немецкий предлог со строчной («am Montag») против верного с
   заглавной — находка; и само правило роняет дни на любом языке — ложные «куски реплики» (время, адрес, цена, имя:
   «Завтра в одиннадцать», «King Street 14», «Двести леев») и граница длины в 1–3 буквы. Замер и предложение — отчёт
   наряда, вопрос архитектору (правило FIX-3 §5 наряд не менял).
5. `partitive` сравнивается несвёрнутым (в отличие от остальных списков судьи) — пишите свёрнуто.
6. `question_word_order` не видит инверсию через дефис (fr «avez-vous» — одно слово Ⓐ).
7. `SpokenNumbers` не режет немецкие числительные-композиты («einundzwanzig») — только записями.

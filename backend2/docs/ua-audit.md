# Аудит: украинский текст в русских полях

Стадия 1 (диагностика) по двум баг-репортам:

1. карточка free practice показала пользователю украинский перевод идиомы `on the same page` —
   «на одній хвилі, розуміти одне одного»;
2. в свежесгенерированной коллекции `01KZV8ZDTN750KK03BWQ0XS4BX` «Essential American Phrasal
   Verbs» «много украинских слов».

Скан детерминированный (буквы `і ї є ґ` + сверка объявленного языка строки), выполнен
`2026-08-12` по живой базе `wordtrainer` и по сид-файлу `database/seeders/data/store_content.json`.
**Ничего не правится этим документом** — он только фиксирует, где заражение и почему станок его
пропустил.

---

## 1. Механизм: как украинский доехал до экрана

Заражение — **не одно, а три разных дефекта**, и они лечатся по-разному.

### Класс A — перевод в чужом языке, честно так и помеченный

```
term_translations
  term_id = 01KZBYGSW4MMRGJKM5CTY377PY   (on the same page)
  lang=ru  «быть на одной волне»                    is_primary = true
  lang=ru  «согласны друг с другом, в согласии»     is_primary = true
  lang=uk  «на одній хвилі, розуміти одне одного»   is_primary = true
```

Строка **не соврала о своём языке** — она `lang='uk'`. Ошибка в том, что её выбрал ридер:

- `EloquentTermContentReader::byIds()` (`app/Modules/Vocabulary/Infrastructure/Eloquent/EloquentTermContentReader.php:23`)
  берёт перевод как `whereIn(term_id) -> orderByDesc('is_primary')` и **никак не фильтрует по языку**.
  У термина три `is_primary = true`, поэтому «первый» определяется физическим порядком строк —
  и им оказалась украинская.
- Сигнатура `TermContentReader::byIds(array $termIds)` вообще не принимает язык, хотя
  `profiles.native_language` в базе есть. Пять вызывающих (Learning ×4, Collections ×1) язык не передают.
- Тот же дефект в `EloquentTermCurator::setPrimaryTranslation()`
  (`.../EloquentTermCurator.php:144`): правка перевода из админки тоже ищет строку
  `orderByDesc('is_primary')` без языка, то есть может починить не ту строку.

Ни один детектор здесь не мог сработать по построению: **строка не является испорченным русским,
она является корректным украинским**. Детектор «украинские буквы в русском поле» её не видит,
потому что поле не заявлено русским.

### Класс B — русское по контракту поле, заполненное украинским

```
term_examples.sentence_translation   -- колонки lang НЕТ, поле неявно = source_lang коллекции
  «Важливо, щоб команда розуміла одне одного щодо цілей проєкту.»
```

Вот это уже настоящая утечка: поле обязано быть русским, в нём украинский. Здесь детектор
работает и обязан ловить.

### Класс C — заражение переезжает в НОВЫЕ коллекции через дедупликацию

Самый неприятный из трёх, потому что выглядит как свежий баг генерации, а генерация тут ни при чём.

Коллекция `01KZV8ZDTN750KK03BWQ0XS4BX` «Essential American Phrasal Verbs» сгенерирована
**сегодня, 12.08 15:20 UTC, промптом v5** — и показывает украинский на **9 карточках из 22**.
При этом модель отработала чисто: каждый термин, созданный этим прогоном (ULID `01KZV8ZE…`),
имеет безупречный русский перевод и русский перевод примера. Испорчены ровно те термины,
которые прогон **не создавал**:

```
break down       01KZCAVGJSX2JDRET2C5G40RES   создан 06.08
come across      01KZCAVGNNFMQ616ZK2646EJ77   создан 06.08
give up          01KZCAVGJZMS5H1DXP2JAV1WPZ   создан 06.08
look after       01KZCAVGKBKES8E3T236XP3AGB   создан 06.08
look forward to  01KZCAVGP9H4VY2TXPSE9H3HHG   создан 06.08
put off          01KZCAVGKT3D5BK24KSRX5A9BS   создан 06.08
take off         01KZCAVGKJ13X0ZA6P7FMY6ZBN   создан 06.08
turn down        01KZCAVGN647QTTW66GXEGRJ7Y   создан 06.08
turn up          01KZCAVGM3GRGG9VW8FF054769   создан 06.08
```

Термины **глобально дедуплицированы**. `ImportTerm` → `FindOrCreateTerm` нашёл существующий
термин по `(lang, normalized_text, pos)` и **домержил** к нему свежий русский перевод и свежий
пример — рядом со старыми украинскими, не вместо них. Дальше срабатывают два правила чтения:

- **перевод**: `EloquentTermContentReader` берёт первую строку по `orderByDesc('is_primary')`
  без фильтра по языку (класс A);
- **пример**: он же пиннит пример через `orderBy('id')` — то есть **самый первый по ULID**,
  а ULID монотонен по времени. Старый пример от 06.08 всегда старше свежего, поэтому
  на карточке закрепляется именно украинский перевод, хотя рядом в таблице лежат один-два
  чистых русских.

```
come across, term_examples ORDER BY id:
  01KZCAVGNPD5HKS6RD1N1PGCHK  «Я натрапив на стару нашу фотографію вчора.»   <- пиннится
  01KZDZXGDY5BA4DAR14GH5P9ZC  «Я наткнулся на интересную статью вчера.»
  01KZV8ZDZ997KPKHZ6F8RT3MHH  «Я наткнулся на интересную информацию…»
```

Правило `orderBy('id')` само по себе корректно и намеренно (закрепить один пример навсегда,
чтобы сервер и клиент не расходились) — но в паре с грязной историей оно гарантирует, что
показывается **худший** из имеющихся вариантов. Пока грязь в базе, каждая новая коллекция,
которая переиспользует эти термины, будет заражена заново. Барьер на записи это НЕ лечит:
запись новых данных была чистой.

---

## 2. Молчал ли станок

**Нет, по этому термину станок не молчал.** `enrichment_findings` содержит ровно ту находку:

| id | kind | field | generator_version | detail |
|---|---|---|---|---|
| `01KZV73GJHWJ8DPCJB467ZZERF` | `ua_leakage` | `translation` | `enrich-v1` | Украинские буквы в русском поле (і): «на одній хвилі, розуміти одне одного». |
| `01KZV73GJH9H9BPS0PX4W0H9G9` | `ua_leakage` | `example_translation` | `enrich-v1` | Украинские буквы в русском поле (і є): «Важливо, щоб команда розуміла одне одного щодо цілей проєкту.». |
| `01KZSK0X2YFV3VQ10G27KD5T3C` | `ua_leakage` | `example_translation` | `enrich-v1` | Украинские буквы в русском поле (і є): «Я добре лажу зі своєю командою, що робить співпрацю ефективною.». |

Станок нашёл и записал. Пользователь всё равно увидел украинский. Три причины, все системные:

1. **Находка — не барьер.** `EnrichmentValidator` пишет `EnrichmentFinding` в таблицу для человека.
   Она ничего не блокирует и ничего не чинит; данные уже лежали в базе к моменту находки.
2. **Станок идёт ПОСЛЕ записи.** `ProcessGenerationHandler::chainEnrichment()` ставится в очередь
   после того, как `materialize()` уже записал термины. Обогащение по построению не может быть
   воротами на записи.
3. **Покрытие станка — меньшинство базы.** `term_enrichment_versions`: 154 термина `enrich-v1` +
   99 `enrich-v1-topup` при 865 терминах всего. Из 89 терминов с украинским
   `sentence_translation` станок вообще смотрел на **2**. Остальные 87 никто не читал.

### Скоуп детектора (гипотеза из задачи — не подтвердилась)

Гипотеза была «переводы примеров проверяются, `translation` самого термина — нет».
Проверено по `EnrichmentValidator::languageFindings()`
(`app/Modules/Generation/Domain/Service/EnrichmentValidator.php:321`) — **проверяются оба**:

```php
foreach (['translation' => $candidate->translation,
          'example_translation' => $candidate->exampleTranslation] as $field => $value) {
```

Плюс `example` (английское поле) — на любую не-латиницу. Не проверяются: `term_accepted_variants.text/note`,
дистракторы, `collections.title/description/topic`. По ним скан ниже дал 2 и 0 строк соответственно.

Кандидат собирается `EloquentEnrichmentTargetReader` — он тоже берёт перевод
`orderByDesc('is_primary')` **без фильтра по языку**, из-за чего в поле `translation` кандидата
попала украинская строка. Именно поэтому находка по `translation` вообще появилась: детектор
увидел «русское поле» с украинскими буквами. Тот же lang-слепой ридер, что и на клиенте.

---

## 3. Полный скан базы

Каждое поле, которое показывается пользователю по-русски.

| Поле | Правило | Строк | В т.ч. достижимо из коллекции |
|---|---|---:|---:|
| `term_translations.text`, `lang='ru'` | буквы `і ї є ґ` | **0** | 0 |
| `term_translations.text`, `lang <> 'ru'` | язык ≠ `collections.source_lang` | **142** (uk 97, de 45) | 24 строки / 22 термина |
| `term_examples.sentence_translation` | буквы `і ї є ґ` | **89** | 22 (21 термин) |
| `term_accepted_variants.text` / `.note` | буквы `і ї є ґ` | 2 | 2 (те же термины) |
| `example_distractors.sentence/correction/error_span` | буквы `і ї є ґ` | 0 | 0 |
| `collections.title/description/topic` | буквы `і ї є ґ` | 0 | 0 |

Итого затронуто **140 терминов**, из них **22 лежат хотя бы в одной коллекции** и, значит,
могут доехать до устройства. Остальные 118 — сироты (в базе 285 терминов вообще не входят
ни в одну коллекцию); они не видны никому и в перегенерацию не берутся.

Заражённых коллекций — 8 из 32:

| коллекция | заражённых терминов |
|---|---:|
| `01KZDZXG73678Q24PWC86AKNS4` Most Common Phrasal Verbs | 13 |
| `01KZV8ZDTN750KK03BWQ0XS4BX` Essential American Phrasal Verbs | 9 |
| `01KZDSFFPMS8FRSHVZD75DNH3R` Job Interview Essentials | 4 |
| `01KZEQ53H4CDK9SQWNVWAZK5J4` Собеседование в IT | 1 |
| `01KZEQ6ME3B9GV0K8J5VSYYZYJ` Собеседование в IT: продвинутый уровень | 1 |
| `01KZEQ7A8Z2DW75KDAF0Z984VC` Деловые созвоны и переписка | 1 |
| `01KZET6TZTJMD1SV4JNRKDV6JR` Покупки в супермаркете | 1 |
| `01KZV71W27470H2B0AYCHYQT9E` Essential IT Phrasal Verbs… | 1 |

Ни одной строки, где русское поле испорчено украинскими буквами при `lang='ru'`, нет:
всё заражение — либо чужой язык с честной меткой (класс A), либо поле без метки языка (класс B).

### 3.1. Достижимые: чужой язык перевода (класс A)

| term_id | термин | lang | значение | коллекции |
|---|---|---|---|---|
| `01KZBYGSW4MMRGJKM5CTY377PY` | on the same page | uk | на одній хвилі, розуміти одне одного | Essential IT Phrasal Verbs…, Деловые созвоны и переписка |
| `01KZBYGSWDAMKVF3AZ5M4CZ628` | Could you give an example? | uk | Чи не могли б ви навести приклад? | Job Interview Essentials |
| `01KZBYGSTQKK974HTDWVBQWEMC` | team player | uk | командний гравець | Job Interview Essentials |
| `01KZBYGSTF210P8A637DX326SA` | What are your strengths? | uk | Які ваші сильні сторони? | Job Interview Essentials |
| `01KZBYGSV85X09V3VP8FH4271P` | What are your weaknesses? | uk | Які ваші слабкі сторони? | Job Interview Essentials |
| `01KZCAVGJSX2JDRET2C5G40RES` | break down | uk | зламатися | Most Common Phrasal Verbs, Essential American Phrasal Verbs |
| `01KZCAVGNZ901FZ89A3SPVB001` | bring up | uk | піднімати (тему) | Most Common Phrasal Verbs |
| `01KZCAVGNNFMQ616ZK2646EJ77` | come across | uk | натрапити | Most Common Phrasal Verbs |
| `01KZCAVGK5MC9W6HNTRYVH9KJY` | find out | uk | з'ясувати | Most Common Phrasal Verbs |
| `01KZCAVGJZMS5H1DXP2JAV1WPZ` | give up | uk | здаватися | Most Common Phrasal Verbs |
| `01KZCAVGKBKES8E3T236XP3AGB` | look after | uk | доглядати | Most Common Phrasal Verbs |
| `01KZCAVGP9H4VY2TXPSE9H3HHG` | look forward to | uk | з нетерпінням чекати | Most Common Phrasal Verbs |
| `01KZCAVGNDNHN3V63QNJDWCA29` | pick up | uk | піднімати, забирати | Most Common Phrasal Verbs |
| `01KZCAVGKT3D5BK24KSRX5A9BS` | put off | uk | відкладати | Most Common Phrasal Verbs |
| `01KZCAVGMBCSDP035TKRENBBZ7` | run out of | uk | закінчитися | Most Common Phrasal Verbs |
| `01KZCAVGMX825VQGZZV85Y1TMZ` | set up | uk | влаштовувати | Most Common Phrasal Verbs |
| `01KZCAVGKJ13X0ZA6P7FMY6ZBN` | take off | uk | взлітати, знімати | Most Common Phrasal Verbs |
| `01KZCAVGN647QTTW66GXEGRJ7Y` | turn down | uk | відхилити | Most Common Phrasal Verbs |
| `01KZCAVGM3GRGG9VW8FF054769` | turn up | uk | з'явитися | Most Common Phrasal Verbs |
| `01KZB67VNNTWTPHA2VJXHQ9SH1` | Can I pay by card? | de | Kann ich mit Karte bezahlen? | Покупки в супермаркете |
| `01KZBYGSVM9WP1F2G0VFSKMBFG` | get along with | uk | ладити з **и** ладнати (2 строки) | Собеседование в IT |
| `01KZBYGSXBFDJNKPNT3BXKF9QP` | What motivates you? | uk | Що вас мотивує? | Собеседование в IT: продвинутый уровень |

**У всех 22 уже есть корректная строка `lang='ru'`.** Перегенерация переводов терминов не нужна —
нужен снос чужой строки. Стоимость: 0 токенов.

### 3.2. Достижимые: украинский перевод примера (класс B)

| example_id | term_id | термин | перевод примера |
|---|---|---|---|
| `01KZBYGSW68QNGXP0VTT9F8D3W` | `01KZBYGSW4MMRGJKM5CTY377PY` | on the same page | Важливо, щоб команда розуміла одне одного щодо цілей проєкту. |
| `01KZBYGSWE0ZTYK66GN7EGXN89` | `01KZBYGSWDAMKVF3AZ5M4CZ628` | Could you give an example? | Чи не могли б ви навести приклад успішного ІТ проєкту, який ви очолювали? |
| `01KZBYGSTR3M6CV6Y11CY5XE7P` | `01KZBYGSTQKK974HTDWVBQWEMC` | team player | Я вважаю себе командним гравцем, який завжди співпрацює з іншими. |
| `01KZBYGSTH779HCBFNY383KTFH` | `01KZBYGSTF210P8A637DX326SA` | What are your strengths? | Які ваші сильні сторони, що стосуються цієї ІТ позиції? |
| `01KZBYGSV9D0QKNZ3XCH2QRJZY` | `01KZBYGSV85X09V3VP8FH4271P` | What are your weaknesses? | Чи не могли б ви розповісти про одну зі своїх слабких сторін? |
| `01KZCAVGJVEA1SHHD1W30QGJPE` | `01KZCAVGJSX2JDRET2C5G40RES` | break down | Моя машина зламалася по дорозі на роботу. |
| `01KZCAVGP1D6EGTY9H60NQJ29Q` | `01KZCAVGNZ901FZ89A3SPVB001` | bring up | Вона завжди піднімає тему політики за вечерею. |
| `01KZCAVGNPD5HKS6RD1N1PGCHK` | `01KZCAVGNNFMQ616ZK2646EJ77` | come across | Я натрапив на стару нашу фотографію вчора. |
| `01KZCAVGK6KECRECMRXNR5FP0F` | `01KZCAVGK5MC9W6HNTRYVH9KJY` | find out | Вона з'ясувала правду про ситуацію. |
| `01KZCAVGK009EM2VTCVB6NFPQ6` | `01KZCAVGJZMS5H1DXP2JAV1WPZ` | give up | Я не здамся, поки не досягну своїх цілей. |
| `01KZCAVGKDDWDF34H5BGQGV9B5` | `01KZCAVGKBKES8E3T236XP3AGB` | look after | Можеш доглянути за моїм котом, поки мене немає? |
| `01KZCAVGPBX217F781V6BY7TTR` | `01KZCAVGP9H4VY2TXPSE9H3HHG` | look forward to | Я з нетерпінням чекаю на відпустку наступного місяця. |
| `01KZCAVGNEGQGDF533H0CJSGBW` | `01KZCAVGNDNHN3V63QNJDWCA29` | pick up | Я заберу тебе в аеропорту о 3 годині дня. |
| `01KZCAVGKWCQW1B4NMNN3612EZ` | `01KZCAVGKT3D5BK24KSRX5A9BS` | put off | Він був втомлений, тож він відклав зустріч на завтра. |
| `01KZCAVGMD2A9R9DPNHM0NMD0F` | `01KZCAVGMBCSDP035TKRENBBZ7` | run out of | У нас закінчилося молоко, можеш купити ще? |
| `01KZCAVGMZPQT5ZVVQAF1YZE6J` | `01KZCAVGMX825VQGZZV85Y1TMZ` | set up | Вони влаштували конференц-зал для зустрічі. |
| `01KZCAVGKMFFV0DM4Q5E8RXT7B` | `01KZCAVGKJ13X0ZA6P7FMY6ZBN` | take off | Літак взлетів вчасно. |
| `01KZCAVGN779PY3J5096T17B90` | `01KZCAVGN647QTTW66GXEGRJ7Y` | turn down | Вона відхилила пропозицію роботи, тому що зарплата була занизькою. |
| `01KZCAVGM4EXWPRJTWSDPJDQNE` | `01KZCAVGM3GRGG9VW8FF054769` | turn up | Вона несподівано з'явилася на вечірці. |
| `01KZCAVGMP114XC3C6AXQ0A4HZ` | `01KZBYGSVM9WP1F2G0VFSKMBFG` | get along with | Я добре ладнаю з усіма колегами. |
| `01KZBYGSXD3SKKDT0FQ1AGVBWT` | `01KZBYGSXBFDJNKPNT3BXKF9QP` | What motivates you? | Що вас мотивує обрати кар'єру в ІТ? |

Английские предложения этих примеров **корректны** — испорчен только русский перевод. На двух
из них висят дистракторы, на двух терминах — принятые варианты; значит чинить надо перевод,
а само предложение не трогать, иначе дистракторы становятся дистракторами несуществующего текста.

У 9 из этих терминов рядом уже лежит **чистый русский пример** от более позднего прогона —
он просто проигрывает пиннингу по `orderBy('id')` (класс C). Их всё равно надо чинить на месте,
а не удалять: удаление примера сдвинет пиннинг и переставит карточку, которую пользователь
уже видит.

---

## 4. Откуда это взялось (и почему вернётся, если не почистить сид)

Затронутые термины созданы **06.08.2026**, до первого `generation_requests` (07.08). Это не
продукция текущего пайплайна — это сид витрины `database/seeders/data/store_content.json`,
экспорт более раннего прогона. В самом файле:

- 5 строк перевода с языком ≠ `source_lang` коллекции (4 из них `uk`),
- 4 перевода примера с украинскими буквами,
- 4 термина, 4 коллекции: «Деловые созвоны и переписка», «Покупки в супермаркете»,
  «Собеседование в IT», «Собеседование в IT: продвинутый уровень».

`migrate:fresh --seed` заново заражает базу. Чистка данных без чистки сида — не чистка.

Промпт, которым порождались коллекции 07.08 и позже — `v4`, затем `v5`. У `v5` раздел
«Language purity» с прямым запретом украинского уже есть
(`app/Modules/Generation/Infrastructure/Prompt/generate_collection.v5.md:71-87`); чего в нём нет —
пункта самопроверки и жёсткой привязки «перевод строго в языке, взятом из настроек коллекции».

---

## 5. Выводы для стадий 2–4

1. Детектор символов нужен, но он по построению видит только класс B. Класс A ловится
   сравнением объявленного языка строки с языком коллекции — это отдельная проверка,
   и она обязана быть в том же барьере.
2. Барьер должен стоять **на записи** в пайплайне генерации, а не в станке: станок идёт после
   записи и покрывает меньшинство базы.
3. Барьер не лечит класс C: там запись была чистой, а грязь пришла из истории термина через
   дедупликацию. Класс C лечится только зачисткой данных — и до неё каждая новая коллекция
   на этих терминах заражается заново.
4. Чинить надо три места: базу (22 термина), сид-файл (4 термина) и промпт (v6).
5. ~~**Не входит в этот наряд, но обязано попасть в ROADMAP:** `TermContentReader::byIds()`
   не принимает язык и выбирает перевод без фильтра. То же — `EloquentTermCurator::setPrimaryTranslation()`
   и `EloquentEnrichmentTargetReader`.~~ — **ЗАКРЫТО.** На HEAD `TermContentReader::byIds(array $termIds, string $lang)`
   принимает язык; `EloquentEnrichmentTargetReader::byIds()` тоже, и выбирает перевод через
   `TranslationPick::forTerms($ids, $lang)`, честно сообщая в брифе, если пришлось откатиться на
   другой язык; `EloquentTermCurator::setPrimaryTranslation()` принимает и язык термина, и язык
   перевода. Проверено 24.08 (HYG-1).

# Часть 0 — аудит фактического потока данных генерации

Снято по коду и по живой базе (483 термина / 493 перевода / 505 примеров) на HEAD `312da34`,
2026-08-20. Ничего не менялось: аудит только читает.

## 1. Что боевой v9-запрос возвращает, поле за полем

Схема — `OpenAiCollectionGenerator::schema()`, строки 162–200. На каждый item **ровно восемь**
полей, все `required`:

| Поле | Есть | Куда идёт |
|---|---|---|
| `text` | да | `terms.text` (+ `normalized_text`) |
| `type` | да | `terms.type` |
| `transcription` | да | `terms.ipa` |
| `translation` | да | `term_translations.text` (один ряд, `is_primary=true`) |
| `example` | да | `term_examples.sentence` |
| `example_translation` | да | `term_examples.sentence_translation` |
| `cefr` | да | `terms.cefr` |
| `image_api_prompt` | да (v4+) | `terms.image_api_prompt` → потом `AttachImagesJob` |
| **опции / дистракторы** | **НЕТ** | — |
| **формы слова / accepted variants** | **НЕТ** | — |
| **несколько примеров** | **НЕТ** — схема допускает ровно один | — |

На уровне коллекции: `title`, `description`, `collection_image_prompt`.

`{{size}}` в промпте получает НЕ запрошенный размер, а **перезаказ ×1.3**
(`GenerationPipeline::assemble`, строка 53), поэтому «EXACTLY {{size}}» в промпте и «размер
приблизителен» в валидаторе друг другу не противоречат — проверено, это не аномалия.

## 2. Что из этого пишется в базу

`ProcessGenerationHandler::materialize()` → `ImportTerm` → `FindOrCreateTerm` → `EloquentTermRepository::save()`.

- `terms` — один ряд (дедуп по `lang + normalized_text + pos`);
- `term_translations` — один ряд;
- `term_examples` — один ряд, **и только если пример непустой** (`ImportTermHandler` пропускает
  пустую строку; `DraftValidator` до этого может обнулить пример — см. §4).

Слияние при дедупе аддитивно: `addTranslation` / `addExample` игнорируют дубли, `ensureIpa` /
`ensureCefr` / `ensureImageApiPrompt` заполняют только пустое. **Существующий контент не
переписывается никогда.**

## 3. Что делает боевое обогащение

`BuildTermEnrichmentsHandler` → `EnrichmentPackerPort` (`enrich_pack.v2.md`) → `EnrichmentValidator`
→ `ImportTermEnrichment` → `EloquentTermEnrichmentWriter::append()`.

**Читает:** термин, его текущие accepted forms, первичный перевод, закреплённый пример и его
перевод, уже записанные дистракторы (`EnrichmentTargetView`).

**Дополняет (append-only, `insertOrIgnore`):**
- `term_accepted_variants` — дополнительные верные ответы;
- `example_distractors` — испорченные версии примера.

**Перезаписывает: ничего.** Единственный UPDATE — `terms.updated_at`, и только если что-то
действительно вставилось (иначе холостой перепрогон разослал бы коллекцию всем устройствам).

**Двойная оплата в боевой связке — НЕТ:** ни одно поле, порождённое коллекцией, обогащение не
порождает заново. Но платится другое:

- `back_translation` и `language_notes` из `enrich_pack.v2` — это **диагностика для человека**
  (пишется в `enrichment_findings`), а не контент. Их просят у модели на КАЖДОМ термине навсегда,
  то есть QA-проход оплачивается на каждой карточке, а не разово по витрине.
- Двойная оплата появляется, как только «полное обогащение» применяется к термину, у которого
  ядро уже есть, — что и подтвердил A/B части 2: конфигурация К1 платит за ядро дважды
  ($0.1505 против $0.0236 у «ядро + механика»).

## 4. Откуда берутся термины без примера

**Коллекция пример ВЫДАЁТ всегда** (поле `required` в схеме). Теряет его валидатор:

`DraftValidator::teachingExample()` (строка ~118) обнуляет пример, если он посимвольно совпадает
с термином (QA-7, «Where can I find dog food?» учили фразой «Where can I find dog food?»).
Перевод примера уходит вместе с ним. Термин записывается **без примера**, а следом
`RepairEchoExamplesHandler` на отдельной джобе генерирует настоящий пример и кладёт его через
`ReplaceTermExample`.

Окно существует, но закрывается асинхронно: **на сегодня терминов без примера в базе 0**.

Три дефекта, которые при этом остаются:

1. **`EloquentTermExampleWriter::replace()` УДАЛЯЕТ все примеры термина**, а `example_distractors`
   висят на `example_id` с `cascadeOnDelete` → **дистракторы уничтожаются вместе со старым
   примером**. Обогащение за них заплатило.
2. **Порядок цепочки хоронит дистракторы навсегда.** `ProcessGenerationHandler` диспатчит
   `chainEnrichment` ДО `repairExamples`. Обогащение приходит к эхо-термину, когда примера ещё
   нет, дистракторы построить не из чего, и термин помечается done на версии `enrich-v1`.
   Починенный позже пример дистракторов уже не получит — до бампа версии.
   На данных: **5 примеров-ремонтов, 4 из них на терминах, уже помеченных done, дистракторов
   у них 0.**
3. **Провенанс теряется у двух AI-писателей.** `TermExampleWriter::replace()` жёстко пишет
   `source='user'` (хотя пример написала модель) и не ставит `prompt_version`/`generation_model`;
   `EnrichTermHandler` («Учить это слово») тоже зовёт `ImportTerm` без штампа. Оба — ровно тот
   случай, который сентинел NULL и должен ловить.

## 5. Кто читает поля

| Поле | Рождает | Хранит | Читает | Перезаписывает |
|---|---|---|---|---|
| `text` | коллекция | `terms` | тренажёры (`SessionCardView.answer`), грейдер, админка | никто |
| `type` | коллекция | `terms` | `StudyCardAssembler` (однотипные опции), триаж | никто |
| `transcription`/`ipa` | коллекция | `terms` | карточка, TTS | `ensureIpa` только если пусто |
| `translation` | коллекция | `term_translations` | карточка (`prompt`), опции, экспорт | только ручная вычитка (`ApplyEnrichmentReview`) |
| `example` | коллекция | `term_examples` | карточка, ступень 3, дистракторы | **`replace()` — удаляет и вставляет** |
| `example_translation` | коллекция | `term_examples` | карточка | вместе с примером |
| `cefr` | коллекция | `terms` | `TriageTermsHandler` (риск верификации), `verification:stats` | `ensureCefr` только если пусто |
| `image_api_prompt` | коллекция | `terms` | `PendingTermImageReader` → `AttachImagesJob` (разово) | `ensure…` только если пусто |
| `accepted_variants.text` | обогащение | `term_accepted_variants` | грейдер, `TermContentReader` (зеркалится на устройство) | ручная вычитка |
| `accepted_variants.note` | обогащение | `term_accepted_variants` | **только экспорт вычитки и админка** | ручная вычитка |
| `example_distractors.*` | обогащение | `example_distractors` | `pick_correct` / `find_the_mistake` | каскадом при `replace()` |
| `back_translation` | обогащение | не хранится | `EnrichmentValidator` → findings | — |
| `language_notes` | обогащение | не хранится | `EnrichmentValidator` → findings | — |

**Платим за склад:**

- `accepted_variants.note` — тренажёрам не нужен, живёт ради человеческой вычитки. Оправдано, но
  это плата на каждом варианте.
- Колонки `terms`, которые **никто никогда не заполнял**: `pos` (0 из 483), `audio_url` (0),
  `frequency_rank` (0), `embedding` (0). Последняя особенно: под ней висит **HNSW-индекс
  `terms_embedding_hnsw`** на полностью пустой vector-колонке — расходы на запись без единого
  читателя.

## 6. Аномалии — списком

| # | Аномалия | Данные | Тяжесть |
|---|---|---|---|
| A1 | `replace()` каскадом сносит дистракторы вместе со старым примером | FK `cascadeOnDelete` | высокая |
| A2 | Обогащение бежит ДО починки примера → эхо-термин навсегда без дистракторов | 4 из 5 ремонтов | высокая |
| A3 | AI-примеры помечаются `source='user'` и без штампа промпта/модели | `TermExampleWriter::replace()` | средняя |
| A4 | «Учить это слово» пишет перевод/пример без штампа | `EnrichTermHandler` | средняя |
| A5 | `back_translation` + `language_notes` — QA на каждом термине навсегда | `enrich_pack.v2` §3–4 | средняя |
| A6 | Мёртвые колонки `pos`/`audio_url`/`frequency_rank`/`embedding` + HNSW-индекс на пустом | 0 из 483 | низкая |
| A7 | 9 терминов с ДВУМЯ `is_primary` переводами | найдено в прошлой сессии | средняя |
| A8 | Только 166 примеров из 505 имеют ≥2 дистрактора → `pick_correct` доступен трети карточек | запрос | средняя |

Ни одна не чинилась: боевой enrich и `OpenAiCollectionGenerator` по наряду не трогаются.

<!-- snapshot: 2026-08-20T10:24:51+00:00 · head: 9ded2e7 -->

# Bake-off провайдеров генерации — v10

Снимок: **2026-08-20T10:24:51+00:00** · HEAD: `9ded2e7` · run 01M0FA8SBB9PEKA9HJRR5SA3WT,01M0FA94D2SY419YRFEX4K8BE3,01M0FAW35ZP2XN1ZNHHWGTKSFQ,01M0FAR7ZVHTVSKF4XC3C4CXBP.

Промпт **v10**, языки **en ← ru**, run id `01M0FA8SBB9PEKA9HJRR5SA3WT,01M0FA94D2SY419YRFEX4K8BE3,01M0FAW35ZP2XN1ZNHHWGTKSFQ,01M0FAR7ZVHTVSKF4XC3C4CXBP`.

> Живой контент не изменён: прогон только читает термины и пишет в песочницу (`bakeoff_runs` / `bakeoff_calls` / `bakeoff_candidates`).

## Задание

| Тема | Почему она |
|---|---|
| вызываю сантехника: течёт кран и засорилась раковина | бытовая, в базе отсутствует — 0 из 458 терминов по сантехнике, коллекции с такой темой нет |

Одно и то же задание, слово в слово, каждому провайдеру: промпт одной версии и одной формы, схема одна, проверки одни. Различаются только модели.

## Откуда цифры

Отчёт собран из нескольких прогонов: каждая пара «трек + провайдер» взята из того прогона, где она ответила лучше всего — механическое правило «больше успешных вызовов», а не выбор понравившихся чисел. Сравнение остаётся честным: задание, промпт, схема и проверки у всех одни и те же, разбиение на прогоны — техническое.

| Трек | Прогон | Успешных вызовов (все провайдеры) |
|---|---|---|
| a/openai | `01M0FA8SBB9PEKA9HJRR5SA3WT` | 1 из 1 |
| a/anthropic | `01M0FA94D2SY419YRFEX4K8BE3` | 1 из 1 |
| a/xai | `01M0FAW35ZP2XN1ZNHHWGTKSFQ` | 1 из 1 |
| a/gemini | `01M0FAR7ZVHTVSKF4XC3C4CXBP` | 1 из 1 |

## Провайдеры

| Провайдер | Модель | Участвовал | Почему нет |
|---|---|---|---|
| OpenAI | `gpt-5.4` | да | — |
| Anthropic | `claude-sonnet-5` | да | — |
| xAI (Grok) | `grok-4.6` | да | — |
| Google (Gemini) | `gemini-3.7-flash` | да | — |

## Автопроверки

| Код | Что проверяет |
|---|---|
| `fields` | полнота полей |
| `lang_source` | язык перевода |
| `lang_target` | язык термина |
| `unique_text` | дубли терминов |
| `unique_translation` | дубли переводов |
| `example` | пример |
| `isomorphism` | изоморфность |
| `options` | опции |
| `verbatim` | термин дословно |
| `size` | размер списка |

Проверки ловят ИЗВЕСТНЫЕ классы брака, а не «качество». Набор, прошедший всё, по-прежнему может быть скучным или буквальным — для этого ниже примеры бок-о-бок.

## Трек А — генерация коллекций

| Провайдер | вызовов | ошибок вызова | items | чистых | fields | lang_source | lang_target | unique_text | unique_translation | example | isomorphism | размер | латентность med | токены in/out | $ |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| OpenAI | 1 | 0 | 12 | **12** (100%) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | ок | 10679 мс | 4262/1025 | $0.0260 |
| Anthropic | 1 | 0 | 12 | **12** (100%) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | ок | 36625 мс | 6251/4248 | $0.0550 |
| xAI (Grok) | 1 | 0 | 12 | **12** (100%) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | ок | 302820 мс | 4534/1455 | $0.0178 |
| Google (Gemini) | 1 | 0 | 12 | **12** (100%) | 0 | 0 | 0 | 0 | 0 | 0 | 0 | ок | 80113 мс | 4187/1487 | $0.0087 |

> **Автопроверки на этом задании никого не различили: у всех 100% чистых.** Это не значит «все одинаково хороши» — значит, что известные классы брака (чужой язык, дубли, пустые поля, потерянный адресат) здесь не сработали ни у кого, и выбор решается ЧТЕНИЕМ полных списков ниже плюс ценой и латентностью.

### Примеры бок-о-бок

**1. Тема: вызываю сантехника: течёт кран и засорилась раковина**

_OpenAI_ — все 12:

| # | Термин | Перевод | Пример | Брак |
|---|---|---|---|---|
| 1 | I need a plumber | Мне нужен сантехник | I need a plumber because the kitchen sink is blocked. | — |
| 2 | The tap is leaking | Кран течёт | The tap is leaking, and the water won't stop. | — |
| 3 | The sink is blocked | Раковина засорилась | The sink is blocked, so the water drains very slowly. | — |
| 4 | Could you come today? | Вы не могли бы прийти сегодня? | Could you come today, or is tomorrow better for you? | — |
| 5 | What time can you come? | Во сколько вы можете прийти? | What time can you come if the problem gets worse? | — |
| 6 | There is water under the sink | Под раковиной вода | There is water under the sink, so I put a bucket there. | — |
| 7 | Can you take a look? | Вы можете посмотреть? | Can you take a look at the tap first? | — |
| 8 | drain | слив | The drain smells bad when the sink is blocked. | — |
| 9 | pipe | труба | I think the pipe under the sink is leaking too. | — |
| 10 | faucet | смеситель | The faucet in the bathroom started leaking this morning. | — |
| 11 | fix | починить | Can you fix this today, or do you need to order a part? | — |
| 12 | clogged up | забилось | The sink got clogged up after dinner last night. | — |

_Anthropic_ — все 12:

| # | Термин | Перевод | Пример | Брак |
|---|---|---|---|---|
| 1 | call a plumber | вызвать сантехника | I need to call a plumber because the faucet won't stop dripping. | — |
| 2 | The faucet is leaking | Кран течёт. | The faucet is leaking again, right under the handle. | — |
| 3 | The sink is clogged | Раковина засорилась. | The sink is clogged and the water won't drain at all. | — |
| 4 | fix the leak | устранить течь | The plumber came to fix the leak under the kitchen sink. | — |
| 5 | clog up | засоряться | The drain clogs up every few months because of hair. | — |
| 6 | leak | течь (утечка воды) | There's a small leak under the bathroom sink. | — |
| 7 | plumber | сантехник | The plumber arrived within an hour of my call. | — |
| 8 | drain | слив | Hair and grease often build up in the kitchen drain. | — |
| 9 | Could you come take a look at my sink? | Не могли бы вы прийти и посмотреть мою раковину? | Could you come take a look at my sink? It's been leaking since yesterday. | — |
| 10 | How much do you charge for a call-out? | Сколько вы берёте за выезд? | How much do you charge for a call-out on weekends? | — |
| 11 | drip | капать | Water keeps dripping from the tap even when it's closed. | — |
| 12 | under the sink | под раковиной | There's a puddle of water under the sink every morning. | — |

_xAI (Grok)_ — все 12:

| # | Термин | Перевод | Пример | Брак |
|---|---|---|---|---|
| 1 | plumber | сантехник | We called a plumber to fix the kitchen tap. | — |
| 2 | The tap is leaking | Кран течёт | The tap is leaking even when I close it tightly. | — |
| 3 | The sink is clogged | Раковина засорена | The sink is clogged with leftover food. | — |
| 4 | turn off the water | перекрыть воду | Please turn off the water under the sink first. | — |
| 5 | There's a leak under the sink | Под раковиной есть протечка | There's a leak under the sink and the cupboard is wet. | — |
| 6 | Can you come today? | Вы можете приехать сегодня? | Can you come today? The kitchen floor is already wet. | — |
| 7 | The water won't go down | Вода не уходит | The water won't go down after I wash the dishes. | — |
| 8 | How much will it cost? | Сколько это будет стоить? | How much will it cost to replace the tap? | — |
| 9 | as soon as possible | как можно скорее | Please send a plumber as soon as possible — we have no water. | — |
| 10 | leak | протечка | I found a leak behind the washing machine. | — |
| 11 | drain | слив | Hair has blocked the drain again. | — |
| 12 | pipe | труба | Cold water is coming from a broken pipe. | — |

_Google (Gemini)_ — все 12:

| # | Термин | Перевод | Пример | Брак |
|---|---|---|---|---|
| 1 | plumber | сантехник | I need to call a plumber because the pipe is leaking. | — |
| 2 | leaking faucet | протекающий кран | The leaking faucet in the bathroom keeps dripping all night. | — |
| 3 | clogged sink | засорившаяся раковина | Water is not draining from the clogged sink. | — |
| 4 | pipe under the sink | труба под раковиной | Water is dripping from the pipe under the sink. | — |
| 5 | turn off the main valve | перекрыть главный кран | Before you replace the pipe, turn off the main valve. | — |
| 6 | plunger | вантуз | I tried to use a plunger to clear the drain. | — |
| 7 | drain | сливное отверстие | Hair and soap often block the drain. | — |
| 8 | unclog | прочистить от засора | The specialist used chemicals to unclog the kitchen pipe. | — |
| 9 | fix a leak | устранить утечку | How much will it cost to fix a leak in my kitchen? | — |
| 10 | water pressure | давление воды | The water pressure in our shower is very low today. | — |
| 11 | back up | подниматься обратно из-за засора | Dirty water started to back up into the bathtub. | — |
| 12 | drip | капать | The kitchen tap started to drip yesterday morning. | — |

## Как это читать

- Столбец «чистых» — items, не сработавшие НИ ПО ОДНОЙ проверке. Это самый строгий показатель и самый честный из автоматических.
- Столбцы проверок — сколько items сработало по каждой. Один item может сработать по нескольким, поэтому сумма столбцов больше числа грязных items.
- Победитель может быть РАЗНЫМ в треках А и Б: выбирать список слов и писать ключ к выданному термину — разные задачи, и пайплайны независимы.
- Провайдер, помеченный «нет» в таблице провайдеров, не участвовал вообще; его отсутствие — не результат.

### Чего цифры НЕ знают

- **`isomorphism` — грубый детектор, и он завышает.** Он ищет отдельное слово-соответствие и не видит лица, выраженного формой глагола: «Can you tell me…» → «Можете рассказать мне…» помечено как «потеряно: you», хотя «Можете» — это и есть второе лицо. Такой хит — КАНДИДАТ на человеческий взгляд, а не приговор; правило таким и задумано (см. `AddresseeIsomorphism`). Сравнивать провайдеров по этому столбцу можно — они меряются одной линейкой; читать его как «столько-то сломанных карточек» нельзя.
- **`lang_source` видит только половину класса.** Украинские буквы (і/ї/є/ґ) и чужой скрипт — да; украинское слово, написанное общими буквами («закрити рахунок»), — нет. Ноль в этом столбце не означает «украинского нет», означает «буквенного нет».
- **Совпадение опции по смыслу не проверяется.** Дубль по строке ловится, синоним верного ответа — нет: это решается чтением, а эвристика дала бы цифру, которую нельзя проверить.
- Ничего из перечисленного не решается добавлением проверки «на глазок»: цифры честны ровно настолько, насколько названы их границы.


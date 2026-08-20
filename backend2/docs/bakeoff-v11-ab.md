<!-- snapshot: 2026-08-20T12:08:23+00:00 · head: 312da34 -->

# Bake-off провайдеров генерации — v11

Снимок: **2026-08-20T12:08:23+00:00** · HEAD: `312da34` · run 01M0FGB86GANYCP74VD3Q3AZJT,01M0FGBHMC0CFKAFT1SWVB0ZEF,01M0FGCSSCJB892C23008GEMDZ,01M0FGEDX6QA2MR4EZHMJQH74S,01M0FGG87GPQA63J684MWMC2RV,01M0FGHNE7RK0Q7A87CX05NW42,01M0FGN50112N1YBYQGN0N9470,01M0FGN9C8MKWAZVTKSXEGACX1,01M0FGNQNQB3Z7FYPW1VQEFDZW,01M0FGP49XPNMNGP1PRY0CBXQ6.

Промпт **v11**, языки **en ← ru**, run id `01M0FGB86GANYCP74VD3Q3AZJT,01M0FGBHMC0CFKAFT1SWVB0ZEF,01M0FGCSSCJB892C23008GEMDZ,01M0FGEDX6QA2MR4EZHMJQH74S,01M0FGG87GPQA63J684MWMC2RV,01M0FGHNE7RK0Q7A87CX05NW42,01M0FGN50112N1YBYQGN0N9470,01M0FGN9C8MKWAZVTKSXEGACX1,01M0FGNQNQB3Z7FYPW1VQEFDZW,01M0FGP49XPNMNGP1PRY0CBXQ6`.

> Живой контент не изменён: прогон только читает термины и пишет в песочницу (`bakeoff_runs` / `bakeoff_calls` / `bakeoff_candidates`).

## Задание

| Тема | Почему она |
|---|---|
| собираюсь в поход с ночёвкой: палатка, спальник, костёр | бытовая новая — 0 совпадений в базе по палатке/костру/рюкзаку |

Одно и то же задание, слово в слово, каждому провайдеру: промпт одной версии и одной формы, схема одна, проверки одни. Различаются только модели.

## Откуда цифры

Отчёт собран из нескольких прогонов: каждая пара «трек + провайдер» взята из того прогона, где она ответила лучше всего — механическое правило «больше успешных вызовов», а не выбор понравившихся чисел. Сравнение остаётся честным: задание, промпт, схема и проверки у всех одни и те же, разбиение на прогоны — техническое.

| Трек | Прогон | Успешных вызовов (все провайдеры) |
|---|---|---|
| a|openai|gpt-5.4|собираюсь в поход с ночёвкой: палатка, спальник, костёр | `01M0FGB86GANYCP74VD3Q3AZJT` | 1 из 1 |
| a|openai|gpt-5.4|разговорные идиомы про деньги: сидеть на мели, экономить, транжирить | `01M0FGBHMC0CFKAFT1SWVB0ZEF` | 1 из 1 |
| b|openai|gpt-4o|set up the tent | `01M0FGCSSCJB892C23008GEMDZ` | 1 из 1 |
| b|openai|gpt-4o|sleeping bag | `01M0FGCSSCJB892C23008GEMDZ` | 1 из 1 |
| b|openai|gpt-4o|build a campfire | `01M0FGCSSCJB892C23008GEMDZ` | 1 из 1 |
| b|openai|gpt-4o|pitch a tent | `01M0FGCSSCJB892C23008GEMDZ` | 1 из 1 |
| b|openai|gpt-4o|torch | `01M0FGCSSCJB892C23008GEMDZ` | 1 из 1 |
| b|openai|gpt-4o|Is there firewood nearby? | `01M0FGCSSCJB892C23008GEMDZ` | 1 из 1 |
| b|openai|gpt-4o|roll out the sleeping mat | `01M0FGCSSCJB892C23008GEMDZ` | 1 из 1 |
| b|openai|gpt-4o|keep the fire going | `01M0FGCSSCJB892C23008GEMDZ` | 1 из 1 |
| b|openai|gpt-4o|packed | `01M0FGCSSCJB892C23008GEMDZ` | 1 из 1 |
| b|openai|gpt-4o|put out the fire | `01M0FGCSSCJB892C23008GEMDZ` | 1 из 1 |
| b|openai|gpt-5.4|set up the tent | `01M0FGEDX6QA2MR4EZHMJQH74S` | 1 из 1 |
| b|openai|gpt-5.4|sleeping bag | `01M0FGEDX6QA2MR4EZHMJQH74S` | 1 из 1 |
| b|openai|gpt-5.4|build a campfire | `01M0FGEDX6QA2MR4EZHMJQH74S` | 1 из 1 |
| b|openai|gpt-5.4|pitch a tent | `01M0FGEDX6QA2MR4EZHMJQH74S` | 1 из 1 |
| b|openai|gpt-5.4|torch | `01M0FGEDX6QA2MR4EZHMJQH74S` | 1 из 1 |
| b|openai|gpt-5.4|Is there firewood nearby? | `01M0FGEDX6QA2MR4EZHMJQH74S` | 1 из 1 |
| b|openai|gpt-5.4|roll out the sleeping mat | `01M0FGEDX6QA2MR4EZHMJQH74S` | 1 из 1 |
| b|openai|gpt-5.4|keep the fire going | `01M0FGEDX6QA2MR4EZHMJQH74S` | 1 из 1 |
| b|openai|gpt-5.4|packed | `01M0FGEDX6QA2MR4EZHMJQH74S` | 1 из 1 |
| b|openai|gpt-5.4|put out the fire | `01M0FGEDX6QA2MR4EZHMJQH74S` | 1 из 1 |
| b|openai|gpt-4o|be broke | `01M0FGG87GPQA63J684MWMC2RV` | 1 из 1 |
| b|openai|gpt-4o|be strapped for cash | `01M0FGG87GPQA63J684MWMC2RV` | 1 из 1 |
| b|openai|gpt-4o|live from paycheck to paycheck | `01M0FGG87GPQA63J684MWMC2RV` | 1 из 1 |
| b|openai|gpt-4o|tighten your belt | `01M0FGG87GPQA63J684MWMC2RV` | 1 из 1 |
| b|openai|gpt-4o|cut back on | `01M0FGG87GPQA63J684MWMC2RV` | 1 из 1 |
| b|openai|gpt-4o|save up | `01M0FGG87GPQA63J684MWMC2RV` | 1 из 1 |
| b|openai|gpt-4o|watch your spending | `01M0FGG87GPQA63J684MWMC2RV` | 1 из 1 |
| b|openai|gpt-4o|splash out | `01M0FGG87GPQA63J684MWMC2RV` | 1 из 1 |
| b|openai|gpt-4o|waste money on | `01M0FGG87GPQA63J684MWMC2RV` | 1 из 1 |
| b|openai|gpt-4o|money burns a hole in your pocket | `01M0FGG87GPQA63J684MWMC2RV` | 1 из 1 |
| b|openai|gpt-5.4|be broke | `01M0FGHNE7RK0Q7A87CX05NW42` | 1 из 1 |
| b|openai|gpt-5.4|be strapped for cash | `01M0FGHNE7RK0Q7A87CX05NW42` | 1 из 1 |
| b|openai|gpt-5.4|live from paycheck to paycheck | `01M0FGHNE7RK0Q7A87CX05NW42` | 1 из 1 |
| b|openai|gpt-5.4|tighten your belt | `01M0FGHNE7RK0Q7A87CX05NW42` | 1 из 1 |
| b|openai|gpt-5.4|cut back on | `01M0FGHNE7RK0Q7A87CX05NW42` | 1 из 1 |
| b|openai|gpt-5.4|save up | `01M0FGHNE7RK0Q7A87CX05NW42` | 1 из 1 |
| b|openai|gpt-5.4|watch your spending | `01M0FGHNE7RK0Q7A87CX05NW42` | 1 из 1 |
| b|openai|gpt-5.4|splash out | `01M0FGHNE7RK0Q7A87CX05NW42` | 1 из 1 |
| b|openai|gpt-5.4|waste money on | `01M0FGHNE7RK0Q7A87CX05NW42` | 1 из 1 |
| b|openai|gpt-5.4|money burns a hole in your pocket | `01M0FGHNE7RK0Q7A87CX05NW42` | 1 из 1 |
| m|openai|gpt-4o-mini|механика ×10 из 01M0FGN50112N1YBYQGN0N9470 | `01M0FGN50112N1YBYQGN0N9470` | 1 из 1 |
| m|openai|gpt-4o-mini|механика ×10 из 01M0FGN9C8MKWAZVTKSXEGACX1 | `01M0FGN9C8MKWAZVTKSXEGACX1` | 1 из 1 |
| c|openai|gpt-5.4|собираюсь в поход с ночёвкой: палатка, спальник, костёр | `01M0FGNQNQB3Z7FYPW1VQEFDZW` | 1 из 1 |
| c|openai|gpt-5.4|разговорные идиомы про деньги: сидеть на мели, экономить, транжирить | `01M0FGP49XPNMNGP1PRY0CBXQ6` | 1 из 1 |

## Провайдеры

| Провайдер | Модель | Участвовал | Почему нет |
|---|---|---|---|
| OpenAI | `gpt-5.4` | да | — |
| Anthropic | `claude-sonnet-5` | **нет** | не было заданий |
| xAI (Grok) | `grok-4.6` | **нет** | не было заданий |
| Google (Gemini) | `gemini-3.7-flash` | **нет** | не было заданий |

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
| `definition` | перевод-определение |
| `options` | опции |
| `verbatim` | термин дословно |
| `forms` | формы слова |
| `size` | размер списка |

Проверки ловят ИЗВЕСТНЫЕ классы брака, а не «качество». Набор, прошедший всё, по-прежнему может быть скучным или буквальным — для этого ниже примеры бок-о-бок.

## Трек А — генерация коллекций

| Провайдер | вызовов | ошибок вызова | items | чистых | fields | lang_source | lang_target | unique_text | unique_translation | example | isomorphism | definition | размер | латентность med | токены in/out | $ |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| OpenAI | 2 | 0 | 20 | **16** (80%) | 0 | 0 | 0 | 0 | 0 | 0 | 4 | 0 | ок | 10421 мс | 7934/1797 | $0.0468 |

 
### Примеры бок-о-бок

**1. Тема: собираюсь в поход с ночёвкой: палатка, спальник, костёр**

_OpenAI_ — все 10:

| # | Термин | Перевод | Пример | Брак |
|---|---|---|---|---|
| 1 | set up the tent | поставить палатку | Let's set up the tent before it gets dark. | — |
| 2 | sleeping bag | спальник | My sleeping bag is warm enough for a cold night. | — |
| 3 | build a campfire | развести костёр | We can build a campfire after dinner. | — |
| 4 | pitch a tent | разбить палатку | They pitched a tent near the lake. | — |
| 5 | torch | фонарик | Don't forget your torch for the walk back to camp. | isomorphism: пример — потеряно: your (you/your) |
| 6 | Is there firewood nearby? | Здесь рядом есть дрова? | Before we stop here, is there firewood nearby? | — |
| 7 | roll out the sleeping mat | расстелить коврик | I rolled out the sleeping mat inside the tent. | — |
| 8 | keep the fire going | поддерживать огонь | Can you keep the fire going while I cook? | — |
| 9 | packed | собранный | I'm packed and ready to leave early tomorrow. | — |
| 10 | put out the fire | потушить костёр | We need to put out the fire before we go to sleep. | — |

**2. Тема: разговорные идиомы про деньги: сидеть на мели, экономить, транжирить**

_OpenAI_ — все 10:

| # | Термин | Перевод | Пример | Брак |
|---|---|---|---|---|
| 1 | be broke | быть без денег | I'd love to come out tonight, but I'm broke till Friday. | — |
| 2 | be strapped for cash | испытывать нехватку денег | We're a bit strapped for cash this month, so let's eat at home. | — |
| 3 | live from paycheck to paycheck | жить от зарплаты до зарплаты | When I was a student, I lived from paycheck to paycheck. | — |
| 4 | tighten your belt | затянуть пояс | We need to tighten our belt until I find a new job. | isomorphism: термин — потеряно: your (you/your) |
| 5 | cut back on | сократить расходы на | I'm trying to cut back on takeout and save more money. | — |
| 6 | save up | копить | She's saving up for a new laptop. | — |
| 7 | watch your spending | следить за расходами | If you want to go on holiday, you need to watch your spending now. | isomorphism: термин — потеряно: your (you/your) |
| 8 | splash out | потратиться | They splashed out on a fancy hotel for their anniversary. | — |
| 9 | waste money on | тратить деньги на ветер на | Don't waste money on things you never use. | — |
| 10 | money burns a hole in your pocket | деньги жгут карман | Give him cash and he'll spend it right away—money burns a hole in his pocket. | isomorphism: термин — потеряно: your (you/your) |

## Трек Б — обогащение существующих терминов

| Провайдер | вызовов | ошибок вызова | items | чистых | fields | lang_source | lang_target | unique_text | unique_translation | example | isomorphism | definition | options | verbatim | размер | латентность med | токены in/out | $ |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| OpenAI | 40 | 0 | 40 | **33** (83%) | 0 | 0 | 1 | 0 | 0 | 0 | 6 | 0 | 0 | 0 | ок | 2155 мс | 185594/4685 | $0.5228 |

 
### Примеры бок-о-бок

**1. `set up the tent`**

| Провайдер | Перевод | Пример | Опции | Брак |
|---|---|---|---|---|
| OpenAI | поставить палатку | We arrived at the campsite and began to set up the tent before it got dark. | разжечь костёр / помыть посуду / найти дрова | — |
| OpenAI | поставить палатку | We need to set up the tent before it gets dark. | собрать палатку / сложить палатку / починить палатку | — |

**2. `sleeping bag`**

| Провайдер | Перевод | Пример | Опции | Брак |
|---|---|---|---|---|
| OpenAI | спальный мешок | I forgot my sleeping bag on the camping trip. | оливковое масло / сапоги для похода / лагерь на природе | — |
| OpenAI | спальный мешок | I packed a warm sleeping bag for the camping trip. | палатка / надувной матрас / походный рюкзак | — |

**3. `build a campfire`**

| Провайдер | Перевод | Пример | Опции | Брак |
|---|---|---|---|---|
| OpenAI | разжечь костёр | We need to build a campfire before it gets dark. | установить палатку / построить домик на дереве / сделать костер в походе | — |
| OpenAI | развести костёр | We built a campfire before it got dark. | поставить палатку / собрать хворост / потушить костёр | — |

**4. `pitch a tent`**

| Провайдер | Перевод | Пример | Опции | Брак |
|---|---|---|---|---|
| OpenAI | поставить палатку | We decided to pitch a tent near the lake. | распалить костёр / собрать рюкзак / купить палатку | — |
| OpenAI | ставить палатку | We pitched a tent by the lake before sunset. | разжечь костёр / собрать рюкзак / арендовать домик | — |

**5. `torch`**

| Провайдер | Перевод | Пример | Опции | Брак |
|---|---|---|---|---|
| OpenAI | факел | He used a torch to light the way through the dark cave. | лампа / фонарь / свеча | — |
| OpenAI | фонарик | I keep a torch in the car for emergencies. | зажигалка / свеча / лампочка | — |

**6. `Is there firewood nearby?`**

| Провайдер | Перевод | Пример | Опции | Брак |
|---|---|---|---|---|
| OpenAI | Есть ли поблизости дрова? | Excuse me, is there firewood nearby? We're planning to set up a campfire. | Есть ли поблизости вода? / Есть ли поблизости палатка? / Есть ли поблизости лес? | isomorphism: пример — потеряно: me (us/me) |
| OpenAI | Есть ли поблизости дрова? | We want to make a campfire tonight. Is there firewood nearby? | Есть ли поблизости вода? / Есть ли поблизости палатка? / Есть ли поблизости угли? | — |

## Трек В — one-shot (эксперимент)

| Провайдер | вызовов | ошибок вызова | items | чистых | fields | lang_source | lang_target | unique_text | unique_translation | example | isomorphism | definition | options | размер | латентность med | токены in/out | $ |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| OpenAI | 2 | 0 | 20 | **18** (90%) | 0 | 0 | 0 | 0 | 0 | 0 | 2 | 0 | 0 | ок | 13738 мс | 8930/2085 | $0.0536 |

 
### Деградация по позиции в списке

Доля items с браком в первой половине ответа против второй. Гипотеза: «хвост длинного ответа халтурит». Разница в пределах пары процентов на выборке этого размера — шум.

| Провайдер | 1-я половина | 2-я половина | Δ |
|---|---|---|---|
| OpenAI | 10% (1/10) | 10% (1/10) | +0 п.п. |

### Примеры бок-о-бок

**1. Тема: собираюсь в поход с ночёвкой: палатка, спальник, костёр**

_OpenAI_ — все 10:

| # | Термин | Перевод | Пример | Брак |
|---|---|---|---|---|
| 1 | pitch a tent | поставить палатку | We should pitch a tent before it gets dark. | — |
| 2 | sleeping bag | спальный мешок | My sleeping bag is warm enough for a cold night. | — |
| 3 | start a fire | разжечь костёр | Let's start a fire and make some tea. | — |
| 4 | set up camp | разбить лагерь | We set up camp near the lake. | — |
| 5 | headlamp | налобный фонарь | Take your headlamp in case you need to walk at night. | — |
| 6 | gather firewood | собрать дрова | The kids gathered firewood while we cooked dinner. | — |
| 7 | campfire | костёр | We sat by the campfire and talked for hours. | — |
| 8 | roll out a sleeping mat | расстелить туристический коврик | I rolled out a sleeping mat inside the tent. | — |
| 9 | boil water | вскипятить воду | We need to boil water for noodles. | — |
| 10 | pack light | не брать много вещей | Try to pack light if we're hiking all day. | — |

**2. Тема: разговорные идиомы про деньги: сидеть на мели, экономить, транжирить**

_OpenAI_ — все 10:

| # | Термин | Перевод | Пример | Брак |
|---|---|---|---|---|
| 1 | be broke | быть без денег | I can't go out tonight—I'm broke until payday. | — |
| 2 | be skint | сидеть без гроша | We ate at home because we were skint after the trip. | — |
| 3 | save up | копить | I'm saving up for a new phone. | — |
| 4 | cut back on | сократить траты на | We're trying to cut back on takeout this month. | — |
| 5 | watch your spending | следить за расходами | You need to watch your spending if you want to move out. | isomorphism: термин — потеряно: your (you/your) |
| 6 | live from paycheck to paycheck | жить от зарплаты до зарплаты | A lot of people live from paycheck to paycheck these days. | — |
| 7 | splash out on | раскошелиться на | He splashed out on a fancy watch last weekend. | — |
| 8 | blow money on | спустить деньги на | She blew money on clothes she never wears. | — |
| 9 | tighten your belt | затянуть пояс | We need to tighten our belts until business gets better. | isomorphism: термин — потеряно: your (you/your) |
| 10 | wasteful | расточительный | Buying food and throwing it away is wasteful. | — |

## Трек М — механика поверх готового ядра

| Провайдер | вызовов | ошибок вызова | items | чистых | fields | lang_source | lang_target | unique_text | unique_translation | options | forms | verbatim | размер | латентность med | токены in/out | $ |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| OpenAI | 2 | 0 | 21 | **20** (95%) | 1 | 0 | 0 | 0 | 0 | 1 | 0 | 0 | 2 из 2 | 4227 мс | 5501/620 | $0.0012 |

 
### Примеры бок-о-бок

**1. Тема: механика ×10 из 01M0FGN50112N1YBYQGN0N9470**

_OpenAI_ — все 11:

| # | Термин | Перевод | Пример | Брак |
|---|---|---|---|---|
| 1 | set up the tent | — | **нет** | — |
| 2 | sleeping bag | — | **нет** | — |
| 3 | build a campfire | — | **нет** | — |
| 4 | pitch a tent | — | **нет** | — |
| 5 | torch | — | **нет** | — |
| 6 | Is there firewood nearby? | — | **нет** | — |
| 7 | roll out the sleeping mat | — | **нет** | — |
| 8 | keep the fire going | — | **нет** | — |
| 9 | packed | — | **нет** | — |
| 10 | put out the fire | — | **нет** | — |
| 11 |  | — | **нет** | fields: пусто: text · options: опций 0, ожидалось 3 |

**2. Тема: механика ×10 из 01M0FGN9C8MKWAZVTKSXEGACX1**

_OpenAI_ — все 10:

| # | Термин | Перевод | Пример | Брак |
|---|---|---|---|---|
| 1 | be broke | — | **нет** | — |
| 2 | be strapped for cash | — | **нет** | — |
| 3 | live from paycheck to paycheck | — | **нет** | — |
| 4 | tighten your belt | — | **нет** | — |
| 5 | cut back on | — | **нет** | — |
| 6 | save up | — | **нет** | — |
| 7 | watch your spending | — | **нет** | — |
| 8 | splash out | — | **нет** | — |
| 9 | waste money on | — | **нет** | — |
| 10 | money burns a hole in your pocket | — | **нет** | — |

## A/B: конфигурации пайплайна

Строка — одна готовая коллекция: цепочка вызовов от темы до карточек с механикой. Стоимость и латентность СУММИРУЮТСЯ по стадиям (ученик ждёт обе), чистота — по стадиям раздельно, потому что дефект ядра и дефект механики чинят разные люди.

**Где «ядро чистых» показывает вдвое больше items, чем в коллекции — это и есть двойная оплата.** Конфигурация, в которой вторая стадия заново порождает ядро, платит за ядро дважды, и в этой колонке видно оба раза.

| Конфигурация | Тема | Ядро чистых | Механика чистых | $ на коллекцию | Латентность | Токены in/out |
|---|---|---|---|---|---|---|
| К1 ядро gpt-5.4 + полное обогащение gpt-4o | собираюсь в поход с ночёвкой: палатка, спальник, костёр | 18/20 (90%) | 9/10 (90%) | **$0.1505** | 29.2 с | 50369/2023 |
| К1* ядро gpt-5.4 + полное обогащение gpt-5.4 | собираюсь в поход с ночёвкой: палатка, спальник, костёр | 18/20 (90%) | 9/10 (90%) | **$0.1570** | 33.9 с | 50359/2071 |
| К2 ядро gpt-5.4 + механика gpt-4o-mini | собираюсь в поход с ночёвкой: палатка, спальник, костёр | 9/10 (90%) | 10/11 (91%) | **$0.0236** | 12.9 с | 6681/1195 |
| К3 one-shot gpt-5.4 | собираюсь в поход с ночёвкой: палатка, спальник, костёр | 10/10 (100%) | 10/10 (100%) | **$0.0267** | 12.4 с | 4464/1039 |
| К1 ядро gpt-5.4 + полное обогащение gpt-4o | разговорные идиомы про деньги: сидеть на мели, экономить, транжирить | 14/20 (70%) | 7/10 (70%) | **$0.1513** | 29.0 с | 50372/2072 |
| К1* ядро gpt-5.4 + полное обогащение gpt-5.4 | разговорные идиомы про деньги: сидеть на мели, экономить, транжирить | 15/20 (75%) | 8/10 (80%) | **$0.1576** | 32.9 с | 50362/2113 |
| К2 ядро gpt-5.4 + механика gpt-4o-mini | разговорные идиомы про деньги: сидеть на мели, экономить, транжирить | 7/10 (70%) | 10/10 (100%) | **$0.0244** | 14.6 с | 6754/1222 |
| К3 one-shot gpt-5.4 | разговорные идиомы про деньги: сидеть на мели, экономить, транжирить | 8/10 (80%) | 8/10 (80%) | **$0.0269** | 13.7 с | 4466/1046 |

_Стоимость — по фактическим токенам вызовов, а не по оценке._

### Кеш промпта

Цены выше считают КАЖДЫЙ входной токен по полной ставке. Вендор часть промпта отдал из своего кеша дешевле — значит суммы в таблице это верхняя граница, и сильнее всего завышена та строка, где один и тот же системный промпт уходил много раз подряд.

| Модель | Вызовов | Входных токенов | Из кеша | Доля |
|---|---|---|---|---|
| `gpt-5.4-2026-03-05` | 24 | 109651 | 76800 | 70% |
| `gpt-4o-mini-2024-07-18` | 6 | 16432 | 12672 | 77.1% |
| `gpt-4o-2024-08-06` | 20 | 92807 | 80640 | 86.9% |
| **всего** | | **218890** | **170112** | **77.7%** |

## Два этапа (А + Б) против one-shot (В)

Стоимость готовой коллекции целиком. Двухэтапная схема — это вызов трека А плюс по одному вызову обогащения на каждый термин; one-shot — один вызов. Цифры ниже — фактическая стоимость прогона, пересчитанная на коллекцию из 10 терминов.

| Провайдер | Схема | $ на коллекцию | Латентность | Чистых items |
|---|---|---|---|---|
| OpenAI | А (список) | $0.0234 | 10421 мс | 80% |
| OpenAI | Б (обогащение, ×10) | $0.1307 | 2155 мс × 10 терминов | 83% |
| OpenAI | **В (one-shot, один вызов)** | $0.0268 | 13738 мс | 90% |
| OpenAI | **А + Б — текущая схема, итого** | **$0.1541** | — | — |

Строка «А + Б» — это то, во что обходится готовая коллекция СЕЙЧАС; строка В — альтернатива ей одним вызовом. Трек В — **эксперимент**: он не заменяет А и Б, решение по схеме пайплайна принимает архитектор.

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


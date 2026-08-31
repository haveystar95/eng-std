# Живой прогон после PLAN-SESSION-FIX и пакета по «Ivanov» — 01.09.2026

План `01M1CQHCGR04FNVF19J4WP5TN5` «Отдых в Италии по-английски», собран владельцем на телефоне
(`vitalnost.meditation@gmail.com`), `ru→en, basic, 20 мин, событие 04.09`. Промпты
`plan_outline.v0.2` / `plan_day.v0.3` / `plan_day_repair.v0.2`, модель `gpt-5.4`.

## Цель прогона сместилась, и вот почему

Наряд просил день 2. К моменту запуска **день 2 уже был собран** — план короткий, планировщик пишет
дни сам, и день 2 ушёл в работу ещё 31.08 в 20:29, то есть на СТАРЫХ гейтах. Единственный
ненаписанный день — третий; на нём и проверялись новые гейты. День 2 разобран отдельно, бесплатно,
по тому, что лежит в базе.

## День 3: `ready` с первого вызова, без починки

**$0.047763 из капа $0.10.** Один платный вызов, P2R не понадобился.

| вызов | версия | $ | итог |
|---|---|---|---|
| P2 день 3 «Спросить дорогу в городе» | `plan_day.v0.3` | 0.047763 | **принят сразу** |

Для сравнения — вся история этого плана: каркас со второй попытки ($0.0325 + $0.0305), день 1 отбит
и починен P2R на **6 карточках** ($0.0454 + $0.0210), день 2 отбит и починен на **2 карточках**
($0.0567 + $0.0109). То есть P2R v0.2 отработал вживую ещё дважды, оба раза до `ready`. Итого по
плану **$0.244336**.

Коллекция `01M1CT7W98YZY112N1KAR5ZA76`, 14 карточек, **картинки 14 из 14**.

## Выгрузка дня 3

| термин (собранный `text`) | kind | frame | filler | speaker | перевод | чтение | пример | картинка |
|---|---|---|---|---|---|---|---|---|
| Hi, can I help you? | line | — (формула) | — | **role** | Здравствуйте, вы можете мне помочь? | хай кэн ай хелп ю | Hi, can I help you? I'm looking for the station. | да |
| I'm looking for the station | line | I'm looking for ___ | the station | learner | Я ищу вокзал. | айм лукинг фо зэ стейшн | I'm looking for the station, not the bus stop. | да |
| How do I get there? | line | — (формула) | — | learner | Как мне туда дойти? | хау ду ай гет зэр | How do I get there if I go on foot? | да |
| Go straight to the square | line | Go straight to ___ | the square | learner | Идите прямо до площади. | го стрейт ту зэ сквэр | Go straight to the square, then turn left. | да |
| Then turn left at the fountain? | line | Then turn left at ___? | the fountain | learner | Потом повернуть налево у фонтана? | зэн тёрн лефт эт зэ фаунтин | Then turn left at the fountain, right? | да |
| Sorry, I didn't catch that. | line | — (формула) | — | learner | Извините, я не расслышал. | сори ай диднт кэч зэт | Sorry, I didn't catch that — was it left or right? | да |
| Is it near the bridge? | line | Is it near ___? | the bridge | learner | Это рядом с мостом? | из ит нир зэ бридж | Is it near the bridge, or farther away? | да |
| Okay, thanks. | line | — (формула) | — | learner | Понятно, спасибо. | окей сэнкс | Okay, thanks. I'll go that way now. | да |
| the station | word | — | — | — | вокзал | зэ стейшн | Is it near the station? | да |
| the square | word | — | — | — | площадь | зэ сквэр | I'm looking for the square. | да |
| the fountain | word | — | — | — | фонтан | зэ фаунтин | Is it near the fountain? | да |
| bridge | word | — | — | — | мост | зэ бридж | I'm looking for the bridge. | да |
| turn left | chunk | — | — | — | повернуть налево | тёрн лефт | Turn left at the bridge? | да |
| go straight | chunk | — | — | — | идти прямо | гоу стрейт | Go straight to the station. | да |

Ни одного имени собственного карточкой, ни одного ключа-транслитерации: новые гейты на этом дне не
сработали, потому что срабатывать было не на чем. **Собеседник говорит** — «Hi, can I help you?»,
`speaker: role`, 1 реплика из 8.

Одно наблюдение к записи: у карточки `bridge` текст без артикля, а чтение — «зэ бридж», то есть
чтение написано для наполнителя `the bridge`, а не для термина. Гейт чтения этого не ловит (он
проверяет алфавит и пунктуацию, а не соответствие тексту). Не чинил.

## KNOWN — слова прошлых дней приходят примерами, а не карточками

Это то, ради чего блок заведён, и он работает в обе стороны.

**День 3** (знает дни 1–2, 28 терминов): в контексте дня 3 написаны **3 предложения на 3 термина**,
пересечение карточек с днями 1–2 — **0**, клонов с их примерами — **0**.

```
«Sorry, could you repeat that?» → Sorry, could you repeat that? I'm looking for the station.
«Can you write it down?»        → Can you write it down? I need the way to the station.
«a holiday»                     → I'm in Italy for a holiday, and I'm looking for the station.
```

**День 2** (знает день 1, разобран бесплатно): **14 предложений на 14 терминов** — то есть модель
дала новый пример КАЖДОМУ слову дня 1, все в ситуации кафе, пересечение карточек **0**, клонов
**0**.

```
«passport»   → I keep my passport in my bag while I eat at the cafe.
«three nights» → We're staying in Italy for three nights, so we want to try local food.
«sounds right» → The pasta and still water sounds right for lunch.
```

Разница 14 против 3 — не дефект: сколько известных слов взять, решает модель, и на дне 3 она взяла
только те, что вписываются в разговор про дорогу. Правило «известное не переучивается карточкой»
выполнено в обоих днях строго.

## Что это доказало и чего не проверило

Доказано: день собирается с первого вызова на новых гейтах; KNOWN отдаёт примеры, а не карточки;
P2R v0.2 дважды довёл отбитые дни до `ready` (6 и 2 карточки).

НЕ проверено живьём: гейты `day.term_is_a_name` и транслитерационное равенство ключа — на этом дне
модель не дала повода. Единственное живое подтверждение — офлайн-прогон валидатора по дню 1 того же
плана, где «Ivanov» отбивается, а «passport» (перевод «паспорт», он же собственное чтение) — нет.

---
description: Concise per-item completion report (Russian) after closing a Session-A item.
argument-hint: "(no args)"
disable-model-invocation: true
---

Write a **short completion report for the item you just closed** — the «/report per closed item»
step of the Session-A cadence. It is a checkpoint, not a handoff: what landed, where, and what
still needs proving.

## Hard rules

- **Язык отчёта — строго русский.** Заголовки, прозу, подписи — всё по-русски. Исключение только для
  идентификаторов кода/путей/команд (имена файлов, классов, `flutter test`, названия кадров).
- Ground every claim in what actually happened this session. Don't imply device-verification that
  didn't run — this project has repeatedly seen the device disprove correct-looking code.
- Reference files as clickable links (`path` or `path:line`), PRs/issues as full URLs.

## Структура

1. **Заголовок** — какой пункт закрыт (например «A3.6 — экран прогресса + пустые состояния»).
2. **Ворота** — одной строкой: `flutter analyze`, `flutter test` (сколько зелёных), guards
   (hex + cyrillic). Если что-то красное — пункт НЕ закрыт.
3. **Что сделано** — маркированный список по под-пунктам, каждый со ссылкой на файл(ы).
4. **Решения без согласования** — развилки, которые ты решила сама по токен-листу/инвариантам.
   Каждое: что выбрано и почему (одна строка). Если развилок не было — «нет».
5. **Проверка на устройстве** (см. ниже) — **обязательна, когда есть device-unverified работа**.

## Блок «Проверка на устройстве» (обязателен при device-unverified работе)

Almost every client item is code-verified but **not run on the device** (the device run is a single
batch). Whenever that's true for this item, the report MUST end with this block, in Russian:

- Заголовок **## Проверка на устройстве**.
- Команда сборки в отдельном ```bash-блоке, ровно:
  `cd mobile && flutter run --release -d 00008110-000A7CCC3492801E`
  (или актуальная из `mobile/CLAUDE.md`, если она изменилась).
- **Нумерованный чек-лист** конкретных действий на телефоне: что сделать и что должно быть видно —
  по одному пункту на проверяемый сценарий (маппится на код, который сейчас только под тестами).
- Также добавь эти пункты в общий накопительный чек-лист в
  `backend2/docs/session-handoff.md` (раздел «Device-batch checklist»), чтобы они не потерялись к
  batch-прогонy после A3.8.

Если весь пункт реально проверен на устройстве (редко) — скажи это явно и блок можно опустить.

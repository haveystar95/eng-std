# Карта экранов ↔ кадров — плановый контур

**Постоянный файл. Наряд, который правит экран планового контура, обязан обновить его строку здесь.**

Переписан нарядом PLAN-UI (2026-09-11) под новый план (PLAN-GEN, `docs/plan-v2.md`, `docs/plan-api.md`).
Строки старых серий — Вход v4, День v1, Диалог v1, Главная v2 — удалены вместе с их экранами: в
клиенте не осталось ни одного экрана, класса, строки или ассета старого плана (список — в отчёте
наряда PLAN-UI).

Источник кадров: экспорт канвасов Claude Design в этой папке — `plan.dc.html` (канвас «План»:
секции таб 21-x / вход 22-x / день 23-x), `tokens.dc.html` (токен-лист, разделы 4к–4о),
`base.dc.html` («База», только действующие кадры по оглавлению). Кадр — блок с `id` («21-2»,
«22-4b»); значения читаются из стилей блока; подписи под кадрами — спеки решений и анимаций;
таблица текстов — в конце канваса «План». Приоритет при расхождении: токен-лист (в т. ч. 4о)
старше подписи под кадром; подпись старше картинки.

Статусы: **совпадает** — экран сделан по кадру; **расходится** — есть кадр, экран другой, причина
названа; **не перенесён** — кадра нет в коде вовсе; **нет кадра** — экран есть, кадра для него не
рисовали.

## Навигация (токен-лист 4к-1)

| экран | код | кадры | статус |
|---|---|---|---|
| Таб-бар: Сегодня · План · Коллекции | [home/home_screen.dart](../../../mobile/lib/features/home/home_screen.dart), [ui/floating_tab_bar.dart](../../../mobile/lib/ui/floating_tab_bar.dart) | 21-x (пилюля), 4к-1 | совпадает: три таба, «План» центральный, латунного кольца у него больше нет |
| Профиль — кружок-аватар 30 в шапке | [profile/profile_avatar.dart](../../../mobile/lib/features/profile/profile_avatar.dart), [profile/profile_screen.dart](../../../mobile/lib/features/profile/profile_screen.dart) | 21-x (шапка), 11a | совпадает; профиль толкается поверх таба; внизу профиля — строка версии (клиент · сервер) всегда; выключатель «Звуки» (4к-3) |
| Прогресс — плита статистики на «Сегодня» | [training/training_home_screen.dart](../../../mobile/lib/features/training/training_home_screen.dart) → [progress/progress_screen.dart](../../../mobile/lib/features/progress/progress_screen.dart) | 19-1, 8a | совпадает: таба нет, тап по плите толкает экран прогресса |

## Таб «План» — кадры 21-x

| экран / состояние | код | кадры | статус |
|---|---|---|---|
| Плана нет | [plan/plan_tab_screen.dart](../../../mobile/lib/features/plan/plan_tab_screen.dart) `_EmptyState` + `PlanFinishedList` | 21-1 | совпадает |
| План идёт, день не начат / брошен | `PlanTabBody` + [plan/plan_day_plate_view.dart](../../../mobile/lib/features/plan/plan_day_plate_view.dart) → [ui/day_plate.dart](../../../mobile/lib/ui/day_plate.dart) (4н, свёрнутый размер) | 21-2, 21-2b, 21-3 | **расходится по контракту**: на плите нет «≈ N минут» и «N с подсказкой» (`docs/plan-api.md` не отдаёт оценку минут и подсказки по этапам) — вопрос архитектору |
| Подсказки первого раза | [plan/plan_tab_parts.dart](../../../mobile/lib/features/plan/plan_tab_parts.dart) `PlanHintLine`, флаги — [data/plan/plan_store.dart](../../../mobile/lib/data/plan/plan_store.dart) | 21-2c, 21-4c | совпадает: три на табе, одна при первом закрытии, без крестика, до первого действия |
| День закрыт | `PlanDayPlateView` (closed) | 21-4 | совпадает: светлая карточка, галка 30, «Вернутся в день N · K карточки» из `program[].state = failed` |
| Маршрут целиком, масштаб 10 дней | [plan/plan_route.dart](../../../mobile/lib/features/plan/plan_route.dart) | 21-5, 21-6 | совпадает: узлы 22, строки 64, фото 48, слот с сервера (галка / сегодня / завтра / пусто), ink до узла «сегодня» |
| План пройден | `PlanDoneCard` | 21-7 | **расходится по контракту**: «{p} фраз и {w} слов в работе» не отдаётся — строка «7 дней» без второй половины |
| Лист «Как устроен план» | [plan/plan_sheets.dart](../../../mobile/lib/features/plan/plan_sheets.dart) `showPlanHowSheet` | 21-8 | совпадает; один раз после первого плана (флаг на клиенте) |
| Меню плана | `PlanGoalRow` → `showFloatingContextMenu` (4в) | 21-9 | совпадает: дата / новый план / коллекция / удалить (терракота последним); «коллекция» — только когда `collection_id` есть |
| Лист даты | `showPlanDateSheet` | 21-10 | **расходится по контракту**: блока «Маршрут · было 7 → станет 5» нет (нет пробного `PATCH /schedule`); заголовок «Когда приём?» собран из `event_native`; добавлен вариант «Без даты» |
| Лист нового плана | `showPlanNewSheet` | 21-11 | совпадает; имя коллекции — `title_native` плана |
| Алерт удаления | `showPlanDeleteAlert` (5f) | 21-12 | совпадает |
| Пропущены дни | — | 21-13 | **не перенесён**: контракт не отдаёт ни факта пропуска, ни строки «маршрут пересобран» — вопрос архитектору |
| Событие прошло | `PlanOverdueCard` | 21-14 | совпадает по составу; «фраз и слов в работе» — как в 21-7 |
| День 1 собирается / готов / не собрался | `PlanDayPlateView` (building) · `PlanDayFailedCard` | 22-5a, 22-5b, 22-5c | совпадает; опрос `GET /plans/current` каждые 3 с, пока `lesson_status = building` |
| Уведомление «План готов» | [data/plan/plan_ready_notification.dart](../../../mobile/lib/data/plan/plan_ready_notification.dart), [plan/plan_ready_notification_host.dart](../../../mobile/lib/features/plan/plan_ready_notification_host.dart) | 22-6 | совпадает: только если приложение было свёрнуто во время сборки дня 1; тап → таб «План» |
| Кабинет дня (заглушка до DAY-UI) | [plan/plan_day_stub_screen.dart](../../../mobile/lib/features/plan/plan_day_stub_screen.dart) | — | **нет кадра, временно**: версия контракта из `Plan.versions`; удаляется нарядом DAY-UI |
| Завершённый план из списка | `PlanFinishedScreen` (тот же `PlanTabBody`, режим чтения) | 21-7 (режим чтения) | совпадает: «Открыть коллекцию» вместо «Собрать новый план» |

## Вход в план — кадры 22-x

| экран / состояние | код | кадры | статус |
|---|---|---|---|
| Каркас шагов: шапка «Отмена · Новый план · Далее», точки, лента «Изм.» | [plan/entry/entry_scaffold.dart](../../../mobile/lib/features/plan/entry/entry_scaffold.dart), [entry_tape.dart](../../../mobile/lib/features/plan/entry/entry_tape.dart), [plan_entry_screen.dart](../../../mobile/lib/features/plan/entry/plan_entry_screen.dart) | 22-1…22-4 | совпадает |
| Цель | [entry_goal_step.dart](../../../mobile/lib/features/plan/entry/entry_goal_step.dart) | 22-1a, 22-1b, 22-1c | совпадает; заготовки для чипов «Аренда / Собеседование / Поездка» добавлены нарядом по образцу «Врач» (в таблице канваса есть только «Врач») |
| Язык и уровень | [entry_language_step.dart](../../../mobile/lib/features/plan/entry/entry_language_step.dart) | 22-2 | совпадает: языки — `studyLanguagesFor` (английский, немецкий + язык аккаунта); «Средний» предвыбран при B1+ |
| Дни | [entry_days_step.dart](../../../mobile/lib/features/plan/entry/entry_days_step.dart) | 22-3a, 22-3b | **расходится по контракту**: строки расчёта «5 дней · 3 ситуации, 1 повторение, репетиция» под чипами нет — сервер отдаёт `route_summary` только с готовым планом |
| Превью: скелет → маршрут → не собрался → цель непонятна | [entry_preview_step.dart](../../../mobile/lib/features/plan/entry/entry_preview_step.dart) | 22-4a, 22-4b, 22-4c, 22-4d | совпадает; «около 10 секунд» — одна строка (другой для 7–9 сцен в контракте нет); свайп сцены → `DELETE /scenes/{id}` |

## Экраны без кадра (законно)

`plan_day_stub_screen.dart` — заглушка до DAY-UI (см. выше). Состояние «сервер не ответил, кэша
нет» на табе (`PlanLoadFailedCard`) — карточка с «Повторить» по §6 наряда, кадра нет.

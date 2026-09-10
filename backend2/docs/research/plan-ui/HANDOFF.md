# HANDOFF — наряд PLAN-UI (WIP, 2026-09-11 ~02:50)

Состояние: код нового таба «План» и входа в план написан, старый план снесён, отчёт
(`README.md` рядом) заполнен по разделам 1–2 и 2а. Остановлено по указанию владельца на этапе
прогона на симуляторе. Коммит — **WIP, ворота не гонялись**. База — `b816951b` (PLAN-GEN).

## 1. Что сделано по разделам наряда

| раздел | статус | где |
|---|---|---|
| 1 Разведка и чистка | **сделано** — таблица удалённого в `README.md` §1; grep по старым именам пуст (проверялось до последних правок) | `README.md` §1 |
| 2 Навигация | **сделано** — три таба Сегодня · План · Коллекции, аватар в шапке → профиль (толкается), плита статистики → прогресс; табов «Прогресс»/«Профиль» нет | `mobile/lib/features/home/home_screen.dart`, `training_home_screen.dart`, `collections_screen.dart`, `profile/profile_avatar.dart` |
| 3 Таб «План» | **сделано в коде** — все состояния из ответа сервера (`PlanTabState`), плита 4н (`ui/day_plate.dart`), маршрут, набор фраз, подсказки первого раза, лист «Как устроен план», меню 4в, листы даты / нового плана, алерт удаления, офлайн из кэша, «день не собрался» с retry, заглушка кабинета | `mobile/lib/features/plan/*.dart`, `mobile/lib/data/plan/*.dart` |
| 4 Вход | **сделано в коде** — 4 шага с лентой «Изм.», POST /plans → опрос build, 22-4a–d, свайп сцены, «Начать» → таб, уведомление «План готов» | `mobile/lib/features/plan/entry/*.dart`, `data/plan/plan_ready_notification.dart`, `features/plan/plan_ready_notification_host.dart` |
| 5 Строки | **сделано** — 136 ключей `plan*`/`planEntry*` из таблицы канваса в `app_ru.arb`/`app_en.arb`, ICU plural, словарь + гарды (каждый ключ в словаре, каждый читается из кода, слова 4к-4 запрещены) | `mobile/lib/l10n/`, `docs/plan-ui-glossary.md` (+`.py`), `mobile/test/l10n/*` |
| 6 Контракт и ошибки | **сделано в коде** — все вызовы по `plan-api.md` в секции «The plan» `api_client.dart`; без алертов/тостов; офлайн — кэш `sync_meta` + «нет сети»; на входе без сети — 22-4c с текстом | `mobile/lib/data/api_client.dart`, `plan_store.dart` |
| 7 Звук и хаптика | **сделано** — выключатель «Звуки» в профиле (`soundsEnabled`, по умолчанию вкл) → `AppFeedback.soundsEnabled`; звуков на табе/входе нет | `app_settings.dart`, `theme/feedback.dart`, `profile_screen.dart` |
| Тесты по канону | **сделано** — форматирование дат/сокращение/короткая цель (`plan_format_test`), сопоставление состояний и слотов (`plan_tab_state_test`), формы числа (`plan_plurals_test`) | `mobile/test/features/plan/`, `mobile/test/l10n/` |
| `design-map.md`, `mobile/CLAUDE.md` | **переписаны** под новый план | `backend2/docs/design/design-map.md`, `mobile/CLAUDE.md` |
| 8 Ворота | **не пройдены** — см. §3 | — |
| 9 Отчёт | §1, §2, §2а заполнены; §3–§6 — пусто | `README.md` |

Последний зелёный прогон: `flutter analyze` 0, `flutter test` 1252 passed — **до** четырёх правок,
сделанных во время прогона на симуляторе (см. §4). После них analyze/тесты не запускались.

## 2. Файлы

**Созданы** (все новые):
`mobile/lib/data/plan/{plan_models,plan_store,plan_ready_notification}.dart`;
`mobile/lib/features/plan/{plan_tab_screen,plan_tab_parts,plan_route,plan_day_plate_view,plan_sheets,plan_providers,plan_format,plan_day_stub_screen,plan_ready_notification_host}.dart`;
`mobile/lib/features/plan/entry/{plan_entry_screen,entry_state,entry_scaffold,entry_tape,entry_goal_step,entry_language_step,entry_days_step,entry_preview_step}.dart`;
`mobile/lib/ui/{day_plate,choice_card}.dart`; `mobile/lib/features/profile/profile_avatar.dart`;
`mobile/test/features/plan/{plan_format_test,plan_tab_state_test}.dart`, `mobile/test/l10n/plan_plurals_test.dart`;
`docs/plan-ui-glossary.py`; `backend2/docs/design/{plan,tokens,base}.dc.html` (экспорт канвасов), `backend2/docs/design/design-map.md` (переписан);
`backend2/docs/research/plan-ui/{README,HANDOFF}.md`, `shots/*.png`, `tools/*` (скрипты стенда, см. §5).

**Удалены**: 20 файлов `mobile/lib/features/plan/**` старого плана (таб, экраны дня, диалог, репетиция, вход v4 и др.),
`mobile/lib/data/{plan_models,plan_notifications,plan_sitting_store}.dart`, 17 тестов `mobile/test/features/plan/`,
`mobile/test/data/plan_sitting_store_test.dart`, 8 харнессов `mobile/tool/` (`entry_preview*`, `plan_*_preview`, `voice_preview`, `speech_preview`),
старые экспорты дизайна в `backend2/docs/design/` (папка `design/`, `phase*.html`, `tokens.html`, `standalone-offline.html`, `support.js`, `README.md`).
Полная таблица с судьбой каждого — `README.md` §1.

**Перемещены**: `mobile/lib/features/plan/build_stamp.dart → features/profile/`, тест — в `test/features/profile/`.

**Изменены (общие)**: `api_client.dart`, `providers.dart`, `models.dart`, `session_screen.dart` (3041 → ~1385 строк, плановые ветки сняты),
`session_exercise.dart`, `sitting_queue.dart`, `language_mode_support.dart`, `home_screen.dart`, `training_home_screen.dart`,
`collections_screen.dart`, `profile_screen.dart`, `progress_screen.dart`, `app_settings.dart`, `theme/{colors,feedback,shadows}.dart`,
`ui/{chip,floating_tab_bar,ui}.dart`, ARB + сгенерированные `app_localizations*.dart`, тесты `home_plan_blocks_test`, `session_size_label_test`,
`sitting_queue_test`, `no_internal_words_in_plan_test`, `plan_glossary_test`.

## 3. Что НЕ сделано

1. **Ворота §8 целиком**: analyze/тесты после последних правок; версия сборки на профиле (нет коммита →
   `BUILD_SHA` не проставлен, скриншота профиля с двумя хешами нет); самопроверка diff как ревьюером
   (§9.4) не делалась.
2. **Таблица «кадр → скриншот → расхождения» (§9.3)** — не собрана. Есть 12 скриншотов (см. §5),
   нет: 21-3, 21-4/4c, 21-5, 21-6 (Лиссабон 10 дней), 21-7 (аренда 3 дня, пройден), 21-8 (лист
   «Как устроен план» — не показался, см. §4.3), 21-10, 21-11, 21-12, 21-13, 21-14 (сдвиг календаря
   `plan:shift-day`), 22-4c (сеть), 22-4d («подтянуть английский»), 22-5b/5c, 22-6, профиль.
3. **Новая директива владельца (02:45)**: скриншоты состояний снимать **golden-тестами**
   (фикстура ответа сервера на каждое состояние → виджет экрана → PNG в `test/goldens/`), они же —
   тесты «состояние сервера → экран» по канону и остаются в проекте; симулятор — только один
   сквозной прогон живьём (вход → превью → таб → кабинет). Не начато.
4. Разделы §9.4–§9.6 отчёта; memory-заметка о стенде не обновлена (см. §6 ниже — что в неё положить).
5. Прогоны трёх остальных целей из песочницы (аренда, английский, Лиссабон).

## 4. Правки, сделанные во время прогона (не покрыты analyze/тестами после них)

1. `entry/plan_entry_screen.dart` `_loadPlan`: фото сцен и обложка приходят через секунду-две
   **после** `ready` — план перечитывается до 4 раз с шагом 4 с, пока есть `null`-картинки.
   Проверено живьём (22-4b с фото).
2. `plan_route.dart` (+ скелет в `entry_preview_step.dart`): колонка узла получила
   `height: double.infinity` — иначе линия между узлами не рисовалась (Stack сжимался до 22 px).
   Проверено живьём.
3. `plan_tab_screen.dart` `_openEntry`: лист «Как устроен план» не показывался после первого
   «Начать» — `ref.read(planHintsProvider).value` был `null`, потому что при пустом табе провайдер
   ещё никто не инициализировал. Теперь `await ref.read(planHintsProvider.future)`. **Живьём не
   проверено** (hot reload прошёл, повторного «Начать» не было).
4. `entry_days_step.dart` / `entry_state.dart`: `requestedDays` — чип переезжает на эффективные дни, а
   строка «До события 3 дня — план сократится до 3» сравнивает с выбранным. Проверено (22-3b).

Также замечено, не исправлено: системный запрос разрешения на уведомления всплывает сразу после
«Начать» поверх таба (в `_start` вызывается `requestPermission`) — в кадрах 22-5a/22-6 такого
момента нет; решить, где просить разрешение (вопрос в отчёт). Календарь даты — стандартный
Material `showDatePicker` (кадр 22-3b его не рисует).

## 5. Где остановилась и стенд

- Симулятор **iPhone 17 Pro `6633B08F-35EA-47D8-99AE-B96791B84058`** (iOS 26.5), приложение
  запущено было через `flutter run --debug -d 6633B08F… --dart-define=API_BASE_URL=http://localhost:8001
  --dart-define=DEV_LOGIN_EMAIL=qa-planui@wt.test` (процесс остановлен при передаче).
- В приложении оказался аккаунт **`qa@wt.test`** (`01M0YWXCGHFPA16AHW79MD20AH`), не `qa-planui`: в
  Keychain этого симулятора лежал токен с прошлых сессий, и dev-login не понадобился. Активный план
  врача **`01M26S6T90GV28WCR6ZE0YQTHR`** (5 дней, приём 16.09, день 1 `ready`) — у `qa@wt.test`.
  У `qa-planui@wt.test` — три плана врача в `ready` (не стартованы), их можно снести.
- Экран в момент остановки: таб 21-2 с открытым меню 21-9; тап по «Изменить дату» через maestro
  вернул exit 1 — не разбиралось.
- Скрипты стенда — `tools/`: `plan_drive.py` (login/current/list/create/start/wait-lesson/answer/
  close/delete/finish/room против `localhost:8001`, e-mail в константе `EMAIL`, токен в файле
  `qa_token.txt` — путь внутри скрипта указывает в scratchpad сессии, поправить), `tap.sh "50%,93%"`
  (maestro point-tap, `--no-reinstall-driver`), `shot.sh NAME` (фронтит окно симулятора и снимает
  `simctl io screenshot` в `shots/`), `entry_goal.yaml` (ввод цели врача + переход к дате),
  `swipe_up/down.yaml`, `dc_extract.py`/`dc_compact.py` (разбор `.dc.html` канвасов по `id` кадра).
  Maestro: `JAVA_HOME=/opt/homebrew/opt/openjdk@21`, `~/.maestro/bin/maestro --device <udid> test …`.
- Снятые скриншоты (`shots/`): 21-1, 21-2b (сгиб: маршрут + набор), 21-2c (плита + подсказки первого
  раза), 21-9 (меню), 22-1a, 22-1b (чип «Врач»), 22-2, 22-3a, 22-3b (сокращение до 3), 22-4a
  (скелет), 22-4b (маршрут с фото и линией), 22-5a (таб через ~0,5 с после «Начать» — под системным
  алертом уведомлений). Часть снята на iPhone 17 до переезда — размер тот же, 1206×2622.

## 6. Что не получилось и почему (симулятор)

1. **Параллельная сессия DAY-UI** (worktree `/Users/yalantisdenys/eng-std-day-ui`, устройства
   «DayUI iPhone 17» `F97AD291…` и **тот же iPhone 17 `139A714A…`**, на котором шёл этот прогон)
   дважды переустановила на iPhone 17 свою сборку (старый пятитабовый UI, `b816951b`): мой
   `flutter run` терял связь («Lost connection to device»), на экране появлялся чужой билд. Ушёл на
   отдельный iPhone 17 Pro. Симулятор для стенда надо брать **свой**, не «первый загруженный».
2. **MCP iOS Simulator** (`claude-ios-sim`): `screenshot` падает (crash-логи в DiagnosticReports),
   `tap` отвечал «tapped», но касание не доходило. Не использовать; `detach` сделан.
3. **Maestro, проценты**: я считал `y%` от уменьшенного превью скриншота (2000 px) вместо полной
   высоты (2622) — все тапы уходили выше цели (таб-бар — это `93%`, не `71%`; «Начать» на превью —
   `92%`). Пока не понял — успел «ответить» на слово-вызов на «Сегодня» (серия у `qa@wt.test`
   сброшена). Правильная проверка — `maestro hierarchy` (bounds в points 402×874) и сравнение с
   кадром. Каждый прогон maestro 30–120 с из-за переустановки xctest-драйвера
   (`--no-reinstall-driver` помогает); при одновременных запусках двух сессий папки логов
   `~/.maestro/tests/<время>` смешиваются.
4. **Замерзание экрана**: когда окно симулятора не на переднем плане (другая сессия фронтит своё),
   Impeller пишет «Waited 1 s for a drawable, giving up», и `simctl io screenshot` отдаёт старый
   кадр (часы на снимке отстают). Лечится `open -a Simulator --args -CurrentDeviceUDID <udid>`
   перед снимком — зашито в `shot.sh`.
5. Итог: ручной прогон каждого состояния через тапы — дорого и хрупко; по решению владельца
   состояния снимаются golden-тестами, симулятор — один сквозной прогон.

## 7. Следующие шаги (по директиве владельца)

1. Golden-тесты: фикстуры JSON ответов `GET /plans/current` + `GET /plans/{id}/days/{n}` на каждое
   состояние 21-x, `PlanBuild`/`Plan` на 22-x → `PlanTabBody` / `EntryPreviewStep` и др. → PNG в
   `mobile/test/goldens/`; таблица §9.3 отчёта ссылается на них.
2. Один сквозной прогон живьём: вход → превью → «Начать» → таб → заглушка кабинета (на своём
   симуляторе, тапы по `maestro hierarchy`).
3. `flutter analyze` + `flutter test`; самопроверка diff; коммит с `BUILD_SHA`; профиль с двумя хешами.
4. Дописать `README.md` §3–§6; обновить memory-заметку стенда пунктами из §6.

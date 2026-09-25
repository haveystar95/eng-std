# ACC-1 — аккаунт и доступ: удаление аккаунта, «один план, день 1 бесплатно», снос рубильника разговора, изоляция тестов, роль v3.4

Наряд ACC-1 (25.09.2026), карточка Notion SLV-150. Ветка `acc-1`, worktree `../backend2-acc1`, сайдкар `wt_acc1` (база
`wordtrainer_acc1_test`). Наряд назначен на Opus; исполнен сессией на Opus 5.5.

**Выкачено 25.09.2026, 16:30–16:34 UTC**: бэкапы боя и e2e → две новые таблицы на бою и e2e кодом ветки → fast-forward
`main` → `.env` боя → `restart horizon scheduler` → снос колонки (10 дней получили пропуск шестого этапа) → e2e и
`wordtrainer_test` догнаны → `access:grant lifetime` всем 24 пользователям боя → админка пересобрана → `stamp-build`.
Сервер отвечает сборкой **`d4707480`** (штамп обновлён коммитом этого отчёта). **Рубильник пейволла на бою выключен**
(`ACCESS_PAYWALL_ENABLED=false`). Сверка «до/после» глазами телефона при тех же замороженных часах: вне новых полей
`lock_reason` и `access` разницы нет — сборка (21) читает то же, что читала. Живая репетиция v3.4 на e2e: прощание на
ходе 18 принимает — «Yes, please…»; **$0.013001 из $0.10**.

Решения — DECISIONS пп. **420–426** и четыре записи в «Отменено»; контракт — `docs/plan-api.md` (разделы «Доступ и
пейволл», «С ACC-1»), `openapi/openapi.yaml`, `openapi/openapi-admin.yaml`; канон — `docs/plan-v2.md` §11; README модулей
Identity, Plan, Admin, Observability; ROADMAP — раздел ACC-1.

## §1 Удаление аккаунта и выход

**Что было.** `DELETE /auth/me` уже существовал (B3): стирал коллекции, пул, генерации, планы строками и отзывал токены. Не
стирал **файлы** (звук сцен и разговоров, копии фото на диске — они оставались навсегда), оставлял в журнале запросов
**тела** строк пользователя, **цель аудита админки** (`admin_audit_log.target_user_id`) и — строкой самого запроса удаления —
**id удалённого** (журнал пишет её после ответа, находка B3). Повтор удаления молча отвечал 204.

**Как теперь** (`CrossModuleAccountEraser`, одна транзакция, начатая блокировкой строки пользователя):

| что | куда |
|---|---|
| пользователь, профиль, пуш-адреса, визиты, права доступа (`entitlements`), переопределения тренажёров | удаляются (строка пользователя, остальное — каскадом FK) |
| планы — и удалённые тоже — с днями, карточками, ответами, разговорами и ходами, прохождениями этапов, возвратами, журналом плана и письмами | удаляются (`PlanAccountEraser`, FK-каскады) |
| файлы: `plan-audio/<сцена>/`, `plan-audio/conversations/<разговор>/`, `plan-images/<сцена>/` | удаляются **после коммита** (`DB::afterCommit`): откат транзакции файлы оставляет |
| коллекции и пул слов (прогресс, повторения, триажи, показы, сессии, статистика дня), подписки на стор | удаляются (модули Collections, Learning) |
| генерации, практика и её стенограммы, перегенерации примеров | удаляются (Generation) |
| все токены | отозваны |
| глобальные термины | остаются, `created_by` → null |
| журнал запросов `api_request_logs` | строки остаются (путь, метод, статус, длительность, размеры), `user_id` → null **и тела с заголовками строк этого пользователя → null** (ниже) |
| аудит админки | строки остаются, `target_user_id` → null (порт Admin `AdminAuditAnonymizer`) |
| журнал вызовов модели `model_calls` | не тронут — пользователя в нём нет вовсе |
| `account_deletions` | одна строка: HMAC-SHA256 id под ключом приложения (`user_hash`), `deleted_at`, `plans_count` |

**Отступление от буквы §1 по факту данных.** Наряд называет журнал запросов «обезличенным: в нём нет личных данных». В телах
строк самого пользователя они есть: речь ученика (`heard` ходов разговора), цель плана (`goal_text`), имя и email ответа
`GET /auth/me`. Поэтому у его строк вместе со связью снимаются и тела с заголовками; сами строки остаются для статистики.
Исходящие строки (вызовы вендоров: в теле — цель и реплики ученика) пользователя не называют вовсе — их при удалении не
найти по связи; они в ROADMAP хвостом ротации журнала.

**«Идемпотентно: повтор — 404».** Повтор тем же токеном до ручки не доходит: токен отозван вместе с аккаунтом — **401** на
входе, как у любого мёртвого токена. **404 `account_not_found`** получает повтор, прошедший проверку токена ДО коммита
первого удаления (гонка двух запросов): он ждёт блокировку строки пользователя и находит пустоту. Клиенту в обоих случаях —
«аккаунта нет». Другого способа ответить 404 на запрос с отозванным токеном нет, не храня отозванные токены.

**`POST /auth/logout`** был — гасит текущий токен; тест: второе устройство остаётся в сети.

**Канон** (`tests/Feature/Identity/AccountErasureCanonTest.php`): ученик с полным разбросом — два плана (один удалён), день
в работе, разговор с голосом, звук сцены, копия фото, журнал плана и письмо, пул, коллекция, генерация, практика со
стенограммой, поиск, пуш-адрес, визит, переопределение тренажёра, смена тарифа админом, право доступа, журнал всех вызовов.
После `DELETE /auth/me` — **перебор ВСЕХ текстовых колонок схемы** (char, varchar, text, json, jsonb) на id и на email: ни
одного совпадения; файлов нет, папок разговоров нет; токен мёртв (401); `model_calls` на месте; `account_deletions` — одна
строка с HMAC и `plans_count = 2`; второй ученик цел. Плюс: гонка — 404; сбой модуля на полпути — аккаунт и файлы целы;
logout. Мутанты: 8 из 8 пойманы (в том числе «файлы до коммита» и «без блокировки строки»).

## §2 Доступ и «один план, день 1 бесплатно»

**Таблица `entitlements`** (Identity): `user_id`, `source` admin | promo | apple | google, `product` month | year |
lifetime, `status` active | expired | grace, `starts_at`, `expires_at` (null — без конца), `updated_at` (+ `id`,
`created_at`); одна строка на (ученик, источник), каскад с пользователем. Ручек покупки нет — это PAY-1.

**Правило подписки** (`Identity\Domain\Service\AccessRule`): право в силе — `status` active или grace и `expires_at` в
будущем или null; доступ — `premium`, пока в силе хоть одно право, «до когда» и источник — у самого долгого.

| вход | ответ |
|---|---|
| `GET /auth/me` | `access {plan: free \| premium, expires_at, source}` |
| `php artisan access:grant {user} {product} {--until=}` | ученик по id или email; источник `admin`; `--until=2026-12-31` — «по этот день включительно, UTC»; `lifetime` с `--until` — отказ |
| `php artisan access:revoke {user}` | все права в силе → `expired`, конец — сейчас; строки остаются |
| `GET /admin/api/users/{id}/access` | `access`, все права (`active` у каждого), `paywall_enabled`; только чтение — для ADM-2 |

**Пейволл** (`Plan`: `Paywall`, `PlanAllowance`, `Application/Service/Paywalls`; порт `LearnerAccess` → Identity
`GetAccess`) — за рубильником **`access.paywall_enabled`** (`ACCESS_PAYWALL_ENABLED`), **на бою выключен**:

| что | рубильник включён |
|---|---|
| бесплатный план | первый план ученика по `created_at`, удалённый тоже |
| день 1 бесплатного плана | открыт целиком, с разговором |
| дни 2+, любой день другого плана | `status: locked`, `lock_reason: subscription`; открыть — 409 `plan_day_locked`, `meta.lock_reason: subscription` (проверяется первым) |
| день в работе, закрытый день | не отнимается никогда: подписка кончилась посреди дня — день доходится |
| `POST /plans` без подписки | первый — 202, любой следующий — **402 `plan_subscription_required`** |
| `POST /plans` с подпиской | до трёх планов в работе — четвёртый **409 `plan_active_limit`** |
| напоминание дня | не зовёт на день, запертый подпиской |

**`lock_reason`** у каждого дня (маршрут, `current_day`, `day` кабинета): `date` — прежние запоры (дата, день перед ним, план
не начат), `subscription` — пейволл, null — не заперт (`building` — не запор). Выключенный рубильник — `subscription` не
бывает, ни Identity, ни планы не спрашиваются.

**Толкование «кап 3 активных плана».** Стартованный (`active`) план у ученика и так один: частичный уникальный индекс
`plans_one_active_uidx` и 409 `plan_already_active` на «Начать» стоят с PLAN-GEN, а снос чего-либо, кроме §1 и §3, наряд
запрещает. Кап три на статус `active` был бы пустым, поэтому «активные» прочитаны как **«в работе»** — не `finished` и не
`deleted` (собирается, не распознан, не собрался, готов, идёт). Проверка — в транзакции записи плана под advisory-блокировкой
ученика: две быстрые кнопки не станут обе бесплатным планом и не пройдут обе под кап. Если архитектор имел в виду три
стартованных плана разом — это снос `plans_one_active_uidx` и новый наряд.

**Что осталось за PAY-1** (DECISIONS п. 336 уточнён): `NextDayAccess::nextDayAllowed` не тронут — при включённом рубильнике
урок дня 2 бесплатного плана до PAY-1 собирается при закрытии дня 1 и ждёт подписки (≈ $0.03–0.06 на ученика без
подписки). Покупки (RevenueCat → `entitlements` с источниками apple / google) и сборка урока по факту оплаты — PAY-1.
**`profiles.tier`** (free/premium стора, лимита генераций и практики) — прежний отдельный переключатель, с `entitlements`
не связан: свести их — решение для PAY-1.

**Канон** (`tests/Feature/Plan/PaywallApiTest.php` + юниты `AccessRuleTest`, `PaywallTest`, `tests/Feature/Identity/AccessTest.php`):
первый план бесплатный — день 1 открыт и проходится с разговором, день 2 — `subscription` на маршруте, в кабинете и на двери
(409); второй план — 402, и после удаления первого; grant снимает всё, revoke и истёкший `--until` — снова заперто; день в
работе при конце подписки не отнимается, запирается следующий; подписчик — три плана в работе, четвёртый 409, после удаления
одного — снова 202; план не бесплатный — заперт с дня 1; напоминания нет; **рубильник выключен — как раньше** (день 2
открывается, пять планов подряд — 202, `access` в `/auth/me` есть). Мутанты: 9 из 9 пойманы.

### Выдано на бою: `access:grant {id} lifetime` — всем 24 пользователям

25.09, 16:32:05–16:32:16 UTC, после выката: у каждого — одна строка `admin · lifetime · active`, `expires_at` null;
пользователей без права — 0; `/auth/me` всех 24 — `{"plan": "premium", "expires_at": null, "source": "admin"}`. Когда
рубильник включат, у них не изменится ничего. Реальные аккаунты — без адресов (в репозитории нет личных данных), QA — по
имени ящика `@wt.test`; «планов» — все, и удалённые.

| id | аккаунт | создан | планов |
|---|---|---|---|
| `01M00SQQWYVWBP7XV0FXHAS419` | реальный (Google) | 14.08 | 0 |
| `01M00WXDP550CBKWJ4T444NXBG` | реальный (Google) | 14.08 | 0 |
| `01M0D0HDZZMA2S5PY0KGJHXHTD` | реальный (Google) | 19.08 | 2 |
| `01M0JMZY6EN5P01D7QYGQN4EGX` | QA `srch1-live` | 21.08 | 0 |
| `01M0MHA28XC92ZZHFJTT8DMXGR` | реальный (Google) | 22.08 | 0 |
| `01M0YWXCGHFPA16AHW79MD20AH` | QA `qa` | 26.08 | 0 |
| `01M0ZJWHJ7DS89HNTVJ8PWJ3ZS` | QA `qa-home1` | 26.08 | 0 |
| `01M129YBPYZSET6SR9MFTW0XMX` | QA `qa-home2` | 27.08 | 0 |
| `01M12HTZ1QHPNDZ5J8SPKB58QP` | реальный (Google) | 27.08 | 6 |
| `01M19YKP1AR9A4RW52FVZEYMZG` | QA `qa-plan1b` | 30.08 | 0 |
| `01M1P64WCDEZXNF1TMKXX3SV25` | QA `qa-day2` | 04.09 | 0 |
| `01M26KPKMVAHK4AB94Q4S7M87D` | QA `qa-dayui` | 10.09 | 0 |
| `01M26KTYPYX2DNDRJ9MV2E2D1N` | QA `qa-dayui2` | 10.09 | 0 |
| `01M26NH3DWKKWZ1B0TJANW37M1` | QA `qa-planui` | 10.09 | 0 |
| `01M283SHWPZF729PRMEWNY22TP` | QA `qa-planfix` | 11.09 | 0 |
| `01M2AZHFAFJQ08AS5SNFR5H7P5` | QA `qa-planui2` | 12.09 | 0 |
| `01M2AZJ6DZB4AXMVYE9JNKZ8WZ` | QA `qa-day` | 12.09 | 0 |
| `01M2AZJ6KTEJCC92MEZ41MZJE2` | QA `qa-planui-2` | 12.09 | 0 |
| `01M2BHF1B4XSRVXX9T91PJFH32` | QA `qa-planui3` | 12.09 | 0 |
| `01M2BM11HWP1M2KKN849E1CGZ0` | QA `qa-planui3live` | 12.09 | 0 |
| `01M2FFBJZXX06ZF263NWJBT114` | QA `qa-dayui2-fx` | 14.09 | 0 |
| `01M2FJYJCAFSDXQ75FWDWFQ1SC` | QA `qa-dayui2-live` | 14.09 | 0 |
| `01M2GF2MCD1B2MAM26D9M2TDYQ` | QA `qa-dayui3-fx` | 14.09 | 0 |
| `01M2GJSHN4TKWAYB41NTEGBS5N` | QA `qa-dayui3` | 14.09 | 1 |

Новый пользователь с этого момента права не получает — он и есть тот, кого пейволл встретит «одним планом, днём 1
бесплатно», когда рубильник включат. e2e не выдавалось (наряд называет бой): QA e2e — `free`, при выключенном рубильнике
это ничего не запирает.

## §3 Снос `has_conversation` и `PLAN_CONVERSATION_ENABLED`

**Бой (только чтение, 25.09 до выката): розданные дни на пяти этапах** (`has_conversation = false`, `opened_at` есть):

| план | день | тип | статус | открыт | план |
|---|---|---|---|---|---|
| `01M2N5VGKSGD87P7WRJ6P0YQRJ` | 1 | scene | **in_progress** | 15.09 13:26 | удалён |
| `01M2HV227644ZQAR37K7Y7HV00` | 1 | scene | **in_progress** | 15.09 14:00 | удалён |
| `01M2NPRKVCZ7130Y1TFHPMBBV4` | 1 | scene | **in_progress** | 16.09 18:15 | удалён |
| `01M2TJ85933DFD0X3G97A2A60T` | 1 | scene | **in_progress** | 18.09 15:33 | удалён |
| `01M2H35564226BG6B96ZW1J8V2` | 1 | scene | closed | 14.09 23:14 | active |
| `01M2NKKGFF8H7TQG8HB75NRJP1` | 1 | scene | closed | 15.09 17:20 | удалён |
| `01M2NKKGFF8H7TQG8HB75NRJP1` | 2 | scene | closed | 17.09 15:19 | удалён |
| `01M2TSRM3DJPGCR5VQNBE8N3S7` | 1 | scene | closed | 18.09 17:45 | удалён |
| `01M2TSRM3DJPGCR5VQNBE8N3S7` | 2 | scene | closed | 19.09 10:36 | удалён |
| `01M2WSW1H6EBJC0DN9NZJE0EVD` | 1 | scene | closed | 20.09 10:28 | active |

**Что с ними сделано.** Открытые — четыре, все удалённых планов (API их не отдаёт); закрыть их нельзя и ломать нельзя —
каждый переведён на шесть этапов с шестым **пропущенным**: строка `plan_stage_passages` этапа `conversation` без разговора
(`conversation_id` null, `passed_at` = момент раздачи дня). Пропущенный этап позади дня (закрытие его не держит), но не
пройден: разговор и «Ещё раз» — 422 `plan_conversation_not_in_day`, минут и возвратов нет, ряда на маршруте, в кабинете и в
окне нет — ровно то, что эти дни показывали на пяти этапах. Закрытые шесть получили такой же пропуск: иначе после сноса
колонки код увидел бы у них шестой этап без прохождения, а так их история читается как была (сверка «до/после» ниже —
разницы нет). Тот же пропуск теперь пишет раздача дня, у сцен которого нет урока (раньше — `has_conversation = false`).

**На бою** миграция отработала 25.09 в 16:31:38 UTC (47,69 мс): ровно эти 10 строк пропуска, у каждой `passed_at` =
`opened_at` дня (сверено SQL после выката), колонки нет; журнал миграции — `days_skipped: 10` в `laravel.log`. На e2e и в
`wordtrainer_test` пятиэтапных розданных дней не было — там только снос колонки. Два закрытых дня активных планов
(`01M2H355…` д. 1, `01M2WSW1…` д. 1) сверка «до/после» читает байт в байт как до выката.

**Снесено:** колонка `plan_days.has_conversation` (миграция `2026_09_25_120000`: сначала пропуски, потом `DROP COLUMN`;
`down()` возвращает колонку со значениями дней и снимает пропуски); рубильник `PLAN_CONVERSATION_ENABLED`
(`plan.conversation.enabled`, `ConversationRules::ENABLED` и параметр `enabled`); ветка `DayStages::walksConversation`
(«не роздан — рубильник, роздан — колонка») → `DayStages::walksTalk(TalkStage)`; три читателя колонки (`CloseDayHandler`,
`CloseStageHandler`, `StartConversationHandler`) и её запись (`OpenDayHandler`, `PlanDay::dealWithConversation`,
`PlanMapper`, `PlanDayModel`); `has_conversation` в обзоре дней админки (`openapi-admin.yaml`, тип и мок `wt_admin`);
`.env.example`, `phpunit.xml`, `config/plan.php`, `docs/plan-v2.md`, `docs/plan-api.md`; строка и устаревший комментарий в
боевом `.env`. `TalkStage` получил `skipped`, `StagePassage` — `skippedTalk()` / `skipsTalk()`.

**Канон** (`ConversationApiTest`, `SkippedTalkMigrationTest`): пропущенный день — 422 (и «Ещё раз»), без ряда, закрывается на
карточках; день без материала — пропуск при раздаче; миграция — пропуски ровно у пятиэтапных дней (в работе и закрытых),
не у дня с разговором и не у нерозданного; колонки нет; `down()` возвращает значения.

## §4 Изоляция тестов

**Девять тестов, падавших серийно** (`tests/Feature/Plan`, серийный прогон до наряда: 9 failed, 211 passed):
`PlanDayWindowTest` × 8 (озвучка: `plan:speak-backfill` видел две сцены вместо одной; фото: сцены чужих планов в статусах
поиска, чужой `sharp` в выборке) и `PlansBeforeFramesPurgeTest` (чужие счётчики проверок). **Причина** — три файла без
`RefreshDatabase`: `PlanCheckReportTest`, `SessionReturnsTest`, `SessionPhraseWindowsTest` писали планы и счётчики в
тестовую базу насовсем; в параллельном прогоне они попадали в другие процессы, в серийном — перед тестами озвучки. Всем
трём дан `RefreshDatabase` (у `PlanCheckReportTest` снята и уборка «прошлых прогонов»).

**Ещё семь — вне папки Plan.** Первый полный серийный прогон ветки (до наряда целиком серийно сьют не гонялся) дал 7
красных в Observability: `ApiLogWriterFailureTest` × 2 («24 is identical to 0») и `ModelCallJournalTest` × 5 (лишние
`generation` в журнале). Та же причина: два файла Generation без `RefreshDatabase` — `ElevenLabsSpeechTest` (21 строка
`api_request_logs`: каждый вызов по фейковому проводу идёт через журнал исходящих) и `GenerationStackTest` (3 строки и 2
строки `model_calls`) — оставляли строки насовсем. Проверено подсчётом всех таблиц тестовой базы после каждого файла без
сброса: писали только эти два; им дан `RefreshDatabase`. **Страж** — `tests/Feature/DatabaseIsolationGuardTest.php`:
у каждого файла Feature есть `uses(RefreshDatabase::class)`, кроме восьми, проверенных тем же подсчётом на «не пишут
ничего» (мутант — `GenerationStackTest` без сброса — пойман, файл назван).

**Файлы в `storage` дерева.** Серийный прогон одной папки Plan до наряда оставил в `storage/app/private/plan-audio` 337
файлов: 57 — звук разговоров, 280 — звук сцен (7 папок по 40 mp3 фейкового синтезатора). Теперь **диски плана
(`plan.audio_disk`, `plan.image_disk`) — `Storage::fake` во всём Feature-сьюте** (`tests/Pest.php`), после каждого теста
тестовый диск снимается — и только если он под `storage/framework/testing/disks`: мутант, убравший `Storage::fake`, с
незащищённой уборкой удалил настоящий `storage/app/private` worktree — в основном дереве это звук боя; проверка пути это
исключает (мутант с «часовым» файлом: страж падает, файл цел). Сверх звука: **лог сьюта** писал тысячи строк `testing.*` в
`storage/logs/laravel.log` — в основном дереве это лог боя — теперь канал `testing` в системном temp; **выгрузка
`TranslationKeyAuditSweepTest`** оставалась в `storage/app/testing` — теперь temp и уборка; **скомпилированные шаблоны**
сьюта — `VIEW_COMPILED_PATH` в temp. `composer test-serial` снят с таймаута Composer (300 с — серийный сьют дольше).

**Страж** — `tests/Feature/Plan/TestDiskGuardTest.php`: в любом Feature-тесте диски плана — тестовые; сцена и разговор,
озвученные фейковым вендором без своего `Storage::fake`, лежат на тестовом диске и ни одного файла под `storage` дерева.

**Итог.** Серийный прогон всего сьюта (`composer test-serial`, сайдкар ветки) — **2 549 passed, 0 failed** (25 078
assertions, 299,5 с); до правок папка Plan одна давала 9 failed, весь сьют — ещё 7. После прогона в `storage` ветки — ни
одного нового файла (сверка `find -newer` с меткой до прогона; пустая `framework/testing/disks` — корень тестовых дисков),
в тестовой базе — ни одной строки, кроме засева миграций (`learning_mode_settings`, 11).

**Мусор прошлых прогонов в основном дереве** (только счёт, ничего не удалено — снос вне §1/§3 наряд запрещает): в
`storage/app/private/plan-audio` основного дерева 66 папок сцен (2 640 файлов, 105 600 байт) и 134 папки разговоров (322
файла, 3,1 МБ), которых нет ни в базе боя, ни в e2e — это звук тестов, гонявшихся хуком ворот в `wt_app`, и остатки снесённых
планов. Чистить — сверкой путей с обеими базами; в ROADMAP хвостом.

## §5 `hints.native` снят

Сборка (21) (`d5efbf5e`, `mobile/lib/data/plan/conversation/conversation_models.dart:611–615`) читает у `hints` только
`enabled`, `sentence`, `target`, `scene_id`, `ref`; `mobile/lib` не менялся после (21). Снято: `ConversationHintView::$native`,
`hints.native` в `PlanJson`, поле и `required` в OpenAPI (описание `sentence` приняло правило выбора цели и null-ы), строки в
`docs/plan-api.md` и `docs/plan-v2.md`. `IntentClause` остаётся — правило задания «Говорю сам» (`task_clause_native`).
Канон: ключи `hints` — ровно `enabled, delay_ms, target, scene_id, ref, sentence`. Статические фикстуры разговора
(`docs/fixtures/conversation-*.json`) — снимки прошлых нарядов, тесты сервера их не держат; `native` в них остался до
клиентского наряда, который будет их освежать (хвост FIX-4c).

## §6 `conversation_agent.v3.4`

v3.4 = v3.3 + одна фраза в SCENES, сразу за правилом SCENE_END; sha256
`8d8c414e6656d3911de55da4a65d42c52b1f3b23102b2f3c617e3b6b2874387d`:

```diff
-CONVERSATION AGENT — v3.3
+CONVERSATION AGENT — v3.4
 …
-… and set end to "no" unless TURNS_LEFT is 0. When TURN is `start` and EARLIER is not none, …
+… and set end to "no" unless TURNS_LEFT is 0. On SCENE_END first accept what the learner has just said or offered, or thank them for it — never turn it down — and only then say goodbye, in one sentence. When TURN is `start` and EARLIER is not none, …
```

v3.3 удалён (git видит переименование в v3.4); `PlanPromptFiles`, `FakePlanModel`, счётчики `plan_check_counters`, реестр
промптов (`docs/prompts/REGISTRY.md`) — под v3.4. Канон `ConversationPromptTest`: v3.4 — ровно v3.3 и эта фраза на своём
месте; v3.3 рядом нет.

### Живая репетиция на e2e — 25.09, 16:33:37–16:33:56 UTC, после выката

Тот же сценарий, что FIX-4 / FIX-4b / FIX-4c (`tools/live-rehearsal.php`): план `01M2QRH5MY…` (qa-gen3-doctor), день 3
(«Вспомнить» + разговор с регистратором и врачом), «Ещё раз», «Без подсказок»; сервер — `php -S 127.0.0.1:8012` кода
`main` (`d4707480`) в `wt_app_e2e` на базе e2e, очередь синхронная, **голос выключен** (прощание — текст, покупается только
ход роли), повторов дня — 10 только этому процессу (три повтора 25.09 истратили CLIENT-FIX-4 и FIX-4c). Разговор
`01M3CPMKMHB5G3KEABW57ETPX0`; все ответы API — `live/live-rehearsal.json`.

```
план · дни:
   день 1 · closed · lock_reason null · этапы: words:done phrases:done dialogue:done listen:done speak:done conversation:done
   день 2 · open · lock_reason null · этапы: repetition:done conversation:done
   день 3 · closed · lock_reason null · этапы: recall:done conversation:done
   /auth/me access: {"plan":"free","expires_at":null,"source":null}
   окно дня 3: ряд разговора — «Поговори с регистратором и врачом» · done

разговор 01M3CPMKMHB5G3KEABW57ETPX0 · «Поговори с регистратором и врачом» · ходов 9
 1 РОЛЬ  [начало «Запись к врачу»] Hello. What hurts: his upper back or his lower back?
         Здравствуйте. Что болит: верхняя часть спины или поясница?
 2 УЧЕНИК It hurts in his lower back. · сказано: p1
 3 РОЛЬ  Did it start today, or earlier this week?
         Это началось сегодня или раньше на этой неделе?
 4 УЧЕНИК It started two days ago. · сказано: p2
 5 РОЛЬ  Okay. Is the pain sharp, or more of a dull ache when he bends?
         Хорошо. Боль острая или скорее тупая, когда он наклоняется?
 6 УЧЕНИК The pain is sharp when she bends.
 7 РОЛЬ  Okay. Does he have a fever?
         Хорошо. Температуры у него нет?
 8 УЧЕНИК The pain is sharp when he bends. · сказано: p3
 9 РОЛЬ  I see. He doesn’t have a fever, right?
         Понятно. Температуры у него нет, верно?
10 УЧЕНИК He doesn't have a fever. · сказано: p4
11 РОЛЬ  [прощание «Запись к врачу»] Thank you. I’ll note that down. Goodbye.
         Спасибо. Я это запишу. До свидания.
12 РОЛЬ  [начало «Приём у врача»] What seems to be the problem with your son today?
         Что случилось с вашим сыном сегодня?
13 УЧЕНИК He has a fever and a sore throat. · сказано: p1
14 РОЛЬ  How long has he had these symptoms?
         Как долго у него эти симптомы?
15 УЧЕНИК He's been sick for three days. I gave him paracetamol. · сказано: p2 · ещё вспомнил: p4
16 РОЛЬ  Yes, that’s fine. What was his highest temperature?
         Да, хорошо. Какая у него была самая высокая температура?
17 УЧЕНИК Should I tell you his temperature? · сказано: p3
18 РОЛЬ  [прощание «Приём у врача»] Yes, please tell me the highest reading. Thank you, and please come back if he gets worse.
         Да, пожалуйста, скажите мне самое высокое значение. Спасибо, и, пожалуйста, приходите снова, если ему станет хуже.

итог: ended · {"ended_reason":"natural","ended_by_limit":false,"phrases_used":7,"phrases_total":7,"extra_said":["p4"]}
```

(Строки `hints` каждого хода — в выводе инструмента и в `live-rehearsal.json`: подсказка есть на каждом ходе, `enabled
false`; первый прогон печатал `lock_reason null` как «нет поля» — ошибка вывода `??`, значения в ответах — `null`; строка
инструмента исправлена, выше — те же ответы.)

| проверка | итог |
|---|---|
| **§6 ход 18: прощание после «Should I tell you his temperature?» принимает, не отказывает** | ✅ «**Yes, please** tell me the highest reading. Thank you, and please come back if he gets worse.» (FIX-4c, v3.3: «No, that’s okay. We can finish here.») |
| §6 каждое прощание сцены принимает сказанное или благодарит | ✅ ход 11 «Thank you. I’ll note that down. Goodbye.», ход 18 — выше |
| §5 ни в одном документе нет `hints.native` (9 ответов хода + GET) | ✅ |
| §2 у каждого дня `lock_reason` (null — не заперт); `/auth/me` → `access` | ✅ `null` × 3; `free` — у QA e2e права нет, рубильник выключен |
| §3 ряд разговора дня, прошедшего разговор, на месте | ✅ «Поговори с регистратором и врачом» · done |
| перевод роли (FIX-4c) | 10/10 ходов с переводом, `native_missing` 0 |
| страж своей реплики (FIX-4) | ход 9: первый ответ роли — слово в слово ход 7 («Okay. Does he have a fever?», ученик на ходе 8 поправлял p3) — один перезапрос (`conversation.own_line`); второй ответ оставлен как есть (`own_line_kept`) — отсюда 11 вызовов на 10 ходов роли |

**Наблюдение для архитектора (не правилось).** Принятие на прощании приглашает к ответу, которого уже не будет: «Yes,
please tell me the highest reading» — и сцена закрыта, хода у ученика нет. Буква v3.4 соблюдена (сначала принять, потом
одно предложение прощания), смысл — «роль просит то, что уже не услышит». Если нужно «принять и сразу закрыть» («Yes,
thank you — I'll note it at the check-up. Goodbye.»), это уточнение фразы (v3.5) и новый наряд.

**Цена:** 11 вызовов роли (`conversation`, gpt-5.4-mini) — 37 876 токенов входа (27 136 из кеша), 647 выхода —
**$0.013001**; голос не покупался (исходящих строк вендора голоса — 0); на бою за время выката вызовов модели — 0. Итог
наряда — **$0.013001 из $0.10**.

## Что удалено

- `app/Modules/Plan/Infrastructure/Prompt/conversation_agent.v3.3.md` (в git — переименование в v3.4).
- `plan_days.has_conversation`, `PLAN_CONVERSATION_ENABLED` / `plan.conversation.enabled` / `ConversationRules::ENABLED`,
  `DayStages::walksConversation`, `PlanDay::hasConversation()` / `dealWithConversation()`, `has_conversation` в обзоре
  админки и в `wt_admin` (тип, мок), в `tests/Fixtures/plan-gym/zal-days-1-2.json`.
- `hints.native`, `ConversationHintView::$native`.
- Тесты «пять этапов при выключенном рубильнике» и «день до разговора по колонке» — заменены пропуском.
- В DECISIONS «Отменено»: `conversation_agent.v3.3`; п. 355 в части колонки и п. 378 (рубильник); «`hints.native` до перехода
  клиента» (пп. 375, 408, 412); «тело `NextDayAccess` — единственное, что поменяет PAY-1» (п. 336) — уточнено.

## Ворота

Один раз в конце, в сайдкаре ветки, на коде этой ветки:

| ворота | итог |
|---|---|
| `composer check` (параллельный Pest, код `6098cf1c`) | OpenAPI **ok × 2** (`openapi.yaml`, `openapi-admin.yaml`); deptrac — **0 нарушений** (8 096 allowed, 3 uncovered, 0 warnings/errors); PHPStan L8 — **0 ошибок** (1 758 файлов); Pest `--parallel` — **2 549 passed**, 25 078 assertions, 103,4 с |
| `composer test-serial` (весь сьют серийно, тот же код) | **2 549 passed, 0 failed**, 25 078 assertions, 299,5 с; в `storage` ветки ничего нового, в тестовой базе — только засев миграций |
| invariant-reviewer (диф кода наряда, до `122c7c16`; дальше — только тесты и документы) | **CLEAN** (UPDATE `reviews` в `QaTimeTravelCommand` — прежнее исключение, п. 112, не ACC-1) |
| `flutter analyze` (`mobile/` ветки; клиент не менялся) | **No issues found** |
| тесты клиента на обновлённых фикстурах дня (`day-doctor*.json` + `lock_reason`) | **322 passed** |
| мутанты | §1 — **8 из 8** пойманы; §2 — **9 из 9**; §4 — страж диска (мутант без `Storage::fake` и с «часовым» файлом) и страж базы (`GenerationStackTest` без сброса) — пойманы |

Pest: FIX-4c — 2 515 → ACC-1 — 2 549. Новые файлы (число `it`): `AccountErasureCanonTest` 4, `AccessTest` 4,
`PaywallApiTest` 7, `AccessRuleTest` 4 (один — с набором данных), `PaywallTest` 5, `SkippedTalkMigrationTest` 1,
`TestDiskGuardTest` 2, `DatabaseIsolationGuardTest` 1; в `ConversationApiTest` два теста рубильника переписаны на пропуск.

## Бэкап, миграции, выкат

Expand/contract: сначала аддитивное кодом ветки (старый `main` новых таблиц не замечает), потом влитие, потом снос колонки
кодом `main` (новый код колонку не читает). Время — UTC, 25.09.

| шаг | что | итог |
|---|---|---|
| 16:30:27 | бэкап боя `scripts/db-backup.sh --safety` | `storage/db-backups/wordtrainer-20260925-193027.sql.gz` (21 МБ, 68 таблиц) |
| 16:30:33 | бэкап e2e (`DB=wordtrainer_e2e_test`, `--safety`) | `wordtrainer_e2e_test-20260925-193033.sql.gz` (2,4 МБ) |
| 16:30 | копия боевого `.env` | вне репозитория (scratchpad сессии) |
| 16:30 | `migrate --pretend` → `migrate --path=…` двух новых таблиц кодом ветки (`wt_acc1`, `DB_DATABASE=wordtrainer`, затем `wordtrainer_e2e_test`) | бой: `entitlements` 22,96 мс, `account_deletions` 3,37 мс; e2e: 24,87 / 5,38 мс |
| 16:31 | `migrate --pretend` сноса кодом ветки на бою | `select … where has_conversation = false` + `DROP COLUMN` — ничего лишнего |
| 16:31:27 | `git merge --ff-only acc-1` | `main` = `d4707480` (8 коммитов ветки) |
| 16:31 | боевой `.env`: блок `PLAN_CONVERSATION_ENABLED=true` с устаревшим комментарием CONV-1 снят; блок `ACCESS_PAYWALL_ENABLED=false` | конфиг: `access.paywall_enabled` false, `open_plans_cap` 3, `plan.conversation.enabled` — ключа нет |
| 16:31:37 | `docker compose restart horizon scheduler` | Horizon running |
| 16:31:38 | `docker compose exec app php artisan migrate --force` | `2026_09_25_120000_drop_has_conversation_from_plan_days` 47,69 мс: 10 пропусков, колонки нет |
| 16:31 | e2e (`wt_app_e2e`) и `wordtrainer_test` — `migrate --force` | e2e: снос 20,79 мс; `wordtrainer_test`: три миграции |
| 16:32:05–16 | `access:grant {id} lifetime` × 24 | 24 права, без права — 0 (список выше) |
| 16:32 | `docker compose build admin && up -d admin` | админка :5175 — 200 (тип `has_conversation` снят) |
| 16:32 | `scripts/stamp-build.sh` | `d4707480`; `GET /api/v1/health` через ngrok — `ok`, `d4707480` |
| 16:32 | сверка «до/после» (`tools/prod-smoke.php` только чтение → `tools/compare-smoke.py`), часы заморожены на 16:30:00 | 3 плана, 2 кабинета дня, 24 аккаунта; вне `lock_reason`/`access` разница — только `versions.build` (`dfbbe991` → `d4707480`) × 3 |
| 16:33:37–56 | живая репетиция на e2e | выше, §6 |

Сверка «до» снята кодом `main` до выката (16:13), «после» — после; оба снимка — вне репозитория (там адреса и речь
учеников). Что показал «после»: `lock_reason` — у дней 3–5 плана `01M2WSW1…` `date` (заперты датой, как и были), у
остальных null; `access` — 24 × `{"plan": "premium", "expires_at": null, "source": "admin"}`.

**Откат** (если понадобится): данные — только бэкап боя 16:30:27 (`migrate:rollback` на `wordtrainer` запрещён правилом
репозитория); `down()` всех трёх миграций проверен на одноразовой базе ветки (`migrate:rollback --step=3` → таблиц нет,
колонка есть → `migrate` → обратно), снос — ещё и тестом `SkippedTalkMigrationTest` (колонка возвращается со значениями
дней, пропуски снимаются). Код — `git revert` коммитов ACC-1; в `.env` — вернуть блок
`PLAN_CONVERSATION_ENABLED=true` (временная копия прежнего `.env` снята в scratchpad сессии).

## Коммиты

Ветка `acc-1` от `dfbbe991` (FIX-4c), влита в `main` fast-forward:

| хеш | что |
|---|---|
| `967177af` | test(plan): §4 — изоляция тестов: база без утечек, озвучка на тестовом диске, лог вне дерева |
| `6fba58f0` | feat(plan): §3 — снос `plan_days.has_conversation` и рубильника `PLAN_CONVERSATION_ENABLED` |
| `b0e76bbc` | feat(plan): §5 — `hints.native` снят |
| `82fb4896` | feat(plan): §6 — промпт `conversation_agent.v3.4` |
| `285d7045` | feat(identity): §1 — удаление аккаунта до последней строки и файла, `account_deletions`, 404 на повтор |
| `122c7c16` | feat(access): §2 — `entitlements`, «один план, день 1 бесплатно», рубильник |
| `6098cf1c` | test: §4 — серийный прогон всего сьюта зелёный: ещё две утечки, выгрузка и шаблоны вне дерева |
| `d4707480` | docs: контракт, канон §11, DECISIONS пп. 420–426, ROADMAP, README модулей, инструменты выката |

Отчёт, стенограмма репетиции, handoff и правка вывода `live-rehearsal.php` — следующий коммит в `main` (точный хеш — `git
log`).

## Хвосты (ROADMAP, раздел ACC-1)

- исходящие строки журнала запросов (цель плана и речь ученика в телах вызовов вендоров) при удалении аккаунта не
  чистятся — пользователя в них нет; их берёт будущая ротация `api_request_logs`;
- мусор тестов в `storage` основного дерева (§4) — чистить сверкой путей с базами боя и e2e;
- страница плана в админке читает статус дня без пейволла (`OverviewReport::statusOf`) — сделать с ADM-2;
- письмо `day_ready` при включённом пейволле уйдёт и ученику без подписки — решить с клиентским нарядом пейволла;
- `NextDayAccess` и покупки — PAY-1; свести `profiles.tier` с `entitlements` — там же;
- ключи Redis судьи окна `plan:slot_judge:{user}:{дата}` при удалении аккаунта не снимаются — истекают к полуночи ученика;
  QA-отчёты `storage/qa-reports` — только QA-аккаунты, не трогались.

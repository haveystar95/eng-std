# LANG-1 · часть D · 13 сборок дня 1 в живом прогоне: что нашёл валидатор, что чинил P2R, что осталось

База `wordtrainer_e2e_test`, код ветки (сайдкар `wt_lang1`, `/wt`), окно 2026-09-26 00:04–00:17 UTC. Разобраны три пары:
ru→de (план `01M3DGEQ5JN32N97SH0PQ66DEN`), pl→en (план `01M3DGPN35MXD4HJEF9B5H1868`) и be→en (план
`01M3DGRD9FJXR2REFTR0VQ6RPR`). Сборка № 1 каждой пары — это сборка при создании плана. Остальные сборки — нажатия «ещё раз»
учеником (`live/*.retries.json`). Всего сборок 13: ru→de — 5 (4 failed, 5-я ready), pl→en — 7 (все failed), be→en — 1 (ready).

**Как читалось.** Скрипт `live/tools/builds.php` работал только на чтение: сессия `READ ONLY`, транзакция откатывается.
Моделям он не обращался. Порядок работы для каждой сборки:

1. Взять сырой ответ урока из `api_request_logs` (outbound, purpose `plan`; вид вызова определяется по системному промту).
2. Разобрать его `LessonParser` ветки и проверить `LessonValidator` ветки в боевом контексте пары. Контекст строится так же,
   как при сборке: `LessonRequests::for(план, сцена дня 1)` → `LessonContexts::of()`. План и сцена берутся из базы.
3. **Переиграть ворота.** Цикл `LessonGateKeeper` прогоняется заново, а на P2R отвечают **записанные** ответы починок этой
   сборки, по порядку. Для этого подставлен фальшивый `PlanModelPort`.

Сверка: 24 из 24 починок попросили ту же карточку и те же коды, что в журнале. Настоящий `LessonGateKeeper` на тех же ответах
дал те же исходы. Для последней сборки каждой пары исход, `fail_reason`, `checks_json` и `cost_usd_lesson` совпали со строкой
`plan_scenes`. Вызовы `model_calls` сопоставлены со строками журнала один к одному (42 из 42) по виду вызова и времени
окончания. Все 13 вызовов урока несут строку v4.8 про чтения («Latin for the others»). Сырые данные — `live/tools/builds.json`.

Запуск: `docker exec -w /wt -e DB_DATABASE=wordtrainer_e2e_test wt_lang1 php docs/research/lang-1/live/tools/builds.php`.

Одна поправка к исходной постановке. Не все 7 сборок pl→en упали с «fatal: options.form_mismatch». `form_mismatch` есть в
причине провала всех семи, но единственным кодом он был в четырёх (№ 1, 3, 4, 6). В № 2, 5 и 7 в причине стоят ещё
`pronunciation.foreign_script`, `exchange.second_question` и `line.ne_frame`. В `retries.json` поле `fail_reason` пустое:
API его не вернуло. Причины здесь взяты из переигровки ворот, а для последних сборок сверены со строкой сцены.

## 1. Сборки

Столбец «фатальные на сыром ответе» — это фатальные находки на ответе модели до починок, по кодам. В скобках указано, сколько
разных карточек под ними стоит. Бюджет P2R — 2 карточки. Порядок карточек: кадр → обмен → строка → проверка → аудирование →
слово.

| пара | сборка № | время (UTC) | итог | фатальные на сыром ответе по кодам | что чинил P2R | что осталось |
|---|---|---|---|---|---|---|
| ru→de | 1 | 00:05:00 | **failed** | second_question ×1, form_mismatch ×3 (карточек 4) | x1 ← second_question ✓; x3.check ← form_mismatch ✗ («Температура» → «Жар», находка сменилась с «куска» на длину) | form_mismatch @x3, x6, x8 |
| ru→de | 2 | 00:06:23 | **failed** | second_question ×1, form_mismatch ×2 (3) | x1 ✓; x7.check ✓ («Четыре часа» → «В 16:00») | form_mismatch @x8 |
| ru→de | 3 | 00:07:21 | **failed** | second_question ×1, form_mismatch ×2 (3) | x1 ✓; x6.check ✓ (длинный вариант заменён) | form_mismatch @x8 |
| ru→de | 4 | 00:08:07 | **failed** | second_question ×1, form_mismatch ×2 (3) | x1 ✓; x5.check ✓ по букве («в» → «на»: копия реплики осталась, правило обойдено) | form_mismatch @x8 |
| ru→de | 5 | 00:11:46 | **ready** | second_question ×1 (1) | x1 ✓ | — (судья швов: 3 «нет» из 16) |
| pl→en | 1 | 00:09:19 | **failed** | second_question ×1, foreign_script ×1, form_mismatch ×2 (4) | x1 ✓; B6 ← foreign_script ✓ («იან» → «dżan») | form_mismatch @x7, x8 |
| pl→en | 2 | 00:11:09 | **failed** | second_question ×2, foreign_script ×2, form_mismatch ×3 (7) | x1 ✓ (попутно чтение B1); x6 ✓ (заодно снята находка с x6.check) | foreign_script @B7; form_mismatch @x4, x7 |
| pl→en | 3 | 00:12:46 | **failed** | second_question ×1, foreign_script ×1, form_mismatch ×1 (3) | x1 ✓; B6 ✓ | form_mismatch @x3 |
| pl→en | 4 | 00:13:35 | **failed** | second_question ×1, foreign_script ×1, form_mismatch ×2 (4) | x1 ✓; B6 ✓ | form_mismatch @x3, x4 |
| pl→en | 5 | 00:14:22 | **failed** | second_question ×1, foreign_script ×9, form_mismatch ×2 (9) | p1 ← foreign_script ✓; p2 ← foreign_script ✓ | second_question @x1; foreign_script @p3.f3, B1, B2, B7; form_mismatch @x5, x6 |
| pl→en | 6 | 00:15:06 | **failed** | second_question ×1, foreign_script ×1, form_mismatch ×1 (3) | x1 ✓; B7 ✓ | form_mismatch @x5 |
| pl→en | 7 | 00:15:42 | **failed** | second_question ×1, foreign_script ×2, filler.ungrammatical ×2, line.ne_frame ×1, form_mismatch ×2 (7) | p1 ← foreign_script ✓ (арабское «ارد»); p3 ← filler.ungrammatical ✓ (кадр переписан) | second_question @x1; foreign_script @B7; ne_frame @B3; form_mismatch @x4, x7 |
| be→en | 1 | 00:10:16 | **ready** | form_mismatch ×1 (1) | x6.check ✓ («Хатні адрас» → «Паштовы адрас») | — (судья швов: 2 «нет» из 24) |

Все 11 проваленных сборок уже в сыром ответе имели 3 и больше фатальных карточек. Обе сборки, которые дошли до ready, имели
по одной. При бюджете в 2 карточки исход был предрешён ещё до P2R. Выжить могла только сборка, где одна починка закрывает две
карточки: в pl→en № 2 починка x1 заодно исправила чтение B1, но это не помогло.

### Итоги по кодам (фатальные находки на сырых ответах)

| код | ru→de (5) | pl→en (7) | be→en (1) | всего | в скольких сборках |
|---|---|---|---|---|---|
| `options.form_mismatch` | 9 | 13 | 1 | **23** | 12 из 13 (кроме ru→de № 5) |
| `pronunciation.foreign_script` | 0 | 17 | 0 | 17 | 7 из 7 pl→en |
| `exchange.second_question` | 5 | 8 | 0 | 13 | 12 из 13: x1 во всех ru→de и pl→en, плюс x6 в pl→en № 2 |
| `filler.ungrammatical` | 0 | 2 | 0 | 2 | pl→en № 7 («For for three days») |
| `line.ne_frame` | 0 | 1 | 0 | 1 | pl→en № 7 |
| **всего** | 14 | 41 | 1 | 56 | |

`lang.pack_missing` — 0 во всех 13 сборках: пакеты ru, de, pl, en, be закрывают все проверки. Остальные фатальные коды
(`check.shape`, `exchange.repeats`, `*.known_repeat` и прочие) не встретились ни разу.

## 2. Подробно по сборкам

Текст находок приведён так, как его пишет валидатор, с сокращениями. «Кусок» — подпункт «вариант — это кусок реплики
собеседника на родном языке». «Длина» — вариант короче 0,5× или длиннее 2× верного (считаются буквы).

### ru→de · № 1 — failed · урок `01M3DGF32FYN3A08B5WK5A1KVS`
- Фатальные (4 шт., карточки x1, x3.check, x6.check, x8.check):
  - `exchange.second_question` @x1: закрывающая реплика A «Gern. Worum geht es?» кончается «?».
  - `options.form_mismatch` @x3.check: кусок, верный «Температура» ⊂ «У вас ещё и температура?».
  - `options.form_mismatch` @x6.check: кусок, верный «Завтра» ⊂ «Завтра в десять или в одиннадцать.».
  - `options.form_mismatch` @x8.check: кусок, верный «На десять минут раньше» ⊂ «Пожалуйста, придите завтра на десять минут раньше.».
- Предупреждения: variant.longer ×2, frame.adjacent_repeat ×1, frame.unresolved_pronoun ×1, vocab.used_in_wrong ×1.
- P2R:
  1. x1 (обмен) по `exchange.second_question` — починен, A1 теперь «Gern. Sagen Sie kurz den Grund.».
  2. x3.check по `options.form_mismatch` — ответ принят, но находка осталась: «Температура» → «Жар», и теперь «Боль в спине» — 10 букв против 3.
- Осталось: form_mismatch @x3.check (длина), @x6.check, @x8.check. Итог: «fatal: options.form_mismatch».

### ru→de · № 2 — failed · `01M3DGHM61RAWA0QQ92DB9XH7W`
- Фатальные (3):
  - `exchange.second_question` @x1: «Gern. Was ist das Problem?».
  - `options.form_mismatch` @x7.check: кусок «Четыре часа» ⊂ «Да, в четыре часа ещё есть свободная запись.».
  - `options.form_mismatch` @x8.check: кусок «Завтра в четыре» ⊂ «Хорошо. Ваша запись завтра в четыре часа.».
- Предупреждения: variant.longer ×2, frame.adjacent_repeat, listening.same_exchange, vocab.used_in_wrong.
- P2R: x1 по second_question — починен; x7.check по form_mismatch — починен («В 16:00»).
- Осталось: form_mismatch @x8.check.

### ru→de · № 3 — failed · `01M3DGKD7ZPAPNX6JKW3K95WGY`
- Фатальные (3):
  - `exchange.second_question` @x1: «Gern. Was ist das Problem?».
  - `options.form_mismatch` @x6.check: длина, «Электронную почту пациента» — 24 буквы против 11 у верного «Имя пациента».
  - `options.form_mismatch` @x8.check: кусок «Завтра в одиннадцать» ⊂ «Хорошо, запись на завтра в одиннадцать.».
- Предупреждения: variant.longer ×3, frame.native_agreement ×2, frame.adjacent_repeat, learner.restates_partner, vocab.free_combination, vocab.everyday_word.
- P2R: x1 — починен; x6.check — починен («Номер телефона пациента»).
- Осталось: form_mismatch @x8.check.

### ru→de · № 4 — failed · `01M3DGMT7C681VB41508SHD2YT`
- Фатальные (3):
  - `exchange.second_question` @x1: «Gern. Worum geht es?».
  - `options.form_mismatch` @x5.check: кусок «Завтра в десять или в одиннадцать» ⊂ «Завтра в десять или в одиннадцать можно.».
  - `options.form_mismatch` @x8.check: кусок «Номер телефона» ⊂ «…ваше имя и ваш номер телефона.».
- Предупреждения: variant.longer ×4, frame.adjacent_repeat, frame.native_agreement, listening.same_exchange, vocab.everyday_word.
- P2R:
  1. x1 — починен.
  2. x5.check — починен по букве: все три варианта переписаны с «в» на «на» («Завтра на десять или на одиннадцать»). Копия реплики осталась, валидатор её больше не видит.
- Осталось: form_mismatch @x8.check.

### ru→de · № 5 — ready · `01M3DGVG8S6JPPPERYXMN93KZJ`
- Фатальные (1): `exchange.second_question` @x1, «Gern. Worum geht es?».
- Предупреждения: variant.longer ×3, vocab.used_in_wrong ×1.
- P2R: x1 — починен («Gern. Sagen Sie bitte den Grund.»).
- Судья швов (`01M3DGWREJWHP90NV8W3XWCMMJ`): 16 предложений, 3 «нет» → `filler.native_seam` @p1.f1, p1.f2, p6.f2 (предупреждения).
- Итог: ready. Это единственный ответ ru→de без `options.form_mismatch`.

### pl→en · № 1 — failed · `01M3DGQ013SR3D7VSM6ZGCN1VJ`
- Фатальные (4):
  - `exchange.second_question` @x1: «Of course. What seems to be the problem?».
  - `pronunciation.foreign_script` @B6: «maj nejm iz იან kowalski» — грузинские ი, ა, ნ.
  - `options.form_mismatch` @x7.check: кусок «Numer telefonu» ⊂ «A numer telefonu?».
  - `options.form_mismatch` @x8.check: длина, «Jutro rano» — 9 букв против 22 у верного «Dziś o drugiej po południu».
- Предупреждения: frame.no_end_punct ×8, pronunciation.script ×1, vocab.everyday_word ×1.
- P2R: x1 — починен («Of course. Please tell me the problem.»); B6 по foreign_script и script — починен («maj nejm iz dżan kowalski»).
- Осталось: form_mismatch @x7.check, @x8.check.

### pl→en · № 2 — failed · `01M3DGTBZV163ZCNV04GPG4X33`
- Фатальные (7):
  - `exchange.second_question` @x1 («Of course. What seems to be the problem?») и @x6 («Okay. Can I have your full name, please?»).
  - `pronunciation.foreign_script` @B1: «ajd lajk ენ ეპojntment».
  - `pronunciation.foreign_script` @B7: «maj nejm იზ jan kowalski».
  - `options.form_mismatch` @x4.check: длина, «Ból brzucha» — 10 букв против 22 у «Podwyższona temperatura».
  - `options.form_mismatch` @x6.check: кусок «Imię i nazwisko» ⊂ «Dobrze. Poproszę imię i nazwisko.».
  - `options.form_mismatch` @x7.check: кусок «Imię i nazwisko» ⊂ «Poproszę imię i nazwisko.».
- Предупреждения: **pronunciation.script ×25** (IPA-буквы «ə», «ð»: латиница, но не польский алфавит), frame.no_end_punct ×7, native.gendered_past ×2 и ещё 6 разовых.
- P2R:
  1. x1 — починен, заодно исправлено чтение B1. A1 стало «Of course.», отсюда предупреждение partner.closer.
  2. x6 — починен, A6 стало «Okay, that works.», находка с x6.check ушла вместе с репликой.
- Осталось: foreign_script @B7, form_mismatch @x4.check, @x7.check. Итог: «fatal: pronunciation.foreign_script, options.form_mismatch».

### pl→en · № 3 — failed · `01M3DGXAYBXEBH2YJ5CT1N3FE0`
- Фатальные (3):
  - `exchange.second_question` @x1.
  - `pronunciation.foreign_script` @B6: «maj nejm yz იან kowalski».
  - `options.form_mismatch` @x3.check: кусок, верный «Jak długo to trwa» — это вся реплика «Jak długo to trwa?».
- Предупреждения: frame.no_end_punct ×7, vocab.everyday_word ×2, native.gendered_past ×2 и ещё 4 разовых.
- P2R: x1 — починен (A1 стало «Of course.», partner.closer); B6 — починен («dżan»).
- Осталось: form_mismatch @x3.check.

### pl→en · № 4 — failed · `01M3DGYTQYGZX3Q240BY80KKKM`
- Фатальные (4):
  - `exchange.second_question` @x1.
  - `pronunciation.foreign_script` @B6: «…iz იან kowalski».
  - `options.form_mismatch` @x3.check: кусок «Jak długo to trwa» (вся реплика).
  - `options.form_mismatch` @x4.check: длина, «Rano, o ósmej» — 10 букв против 21 у «Po południu, o piętnastej».
- Предупреждения: frame.no_end_punct ×7, vocab.used_in_wrong ×2 и ещё 4 разовых.
- P2R: x1 — починен; B6 — починен («jan»).
- Осталось: form_mismatch @x3.check, @x4.check.

### pl→en · № 5 — failed · `01M3DH08QZ4X3WP5DY6GNJV8ZB`
- Фатальные (12, карточек 9):
  - `exchange.second_question` @x1.
  - `pronunciation.foreign_script` ×9 — в чтениях кириллица: @p1.f1 «эн эпоjntmеnt», @p1.f2 «э czek-ap», @p2.f1 «э сор сроут», @p2.f2, @p2.f3, @p3.f3, @B1 (кириллица плюс грузинские буквы), @B2 «aj haww э сор сроут», @B7 «…iz იან kowalski».
  - `options.form_mismatch` @x5.check и @x6.check: кусок «Jutro o jedenastej» ⊂ «Tak, jutro o jedenastej też jest wolne.». Модель дала собеседнику одну и ту же реплику в x5 и x6.
- Предупреждения: pronunciation.script ×11, frame.no_end_punct ×7, vocab.used_in_wrong ×3, variant.longer, check.verbatim.
- P2R: p1 (кадр) — починен; p2 (кадр) — починен. Обе карточки ушли на кадры: по порядку они идут раньше обмена, и x1 не получил починки.
- Осталось: second_question @x1, foreign_script @p3.f3, B1, B2, B7, form_mismatch @x5.check, @x6.check.

### pl→en · № 6 — failed · `01M3DH1JS6KGPY8ZM296RHXRSH`
- Фатальные (3):
  - `exchange.second_question` @x1.
  - `pronunciation.foreign_script` @B7: «…iz იან kowalski».
  - `options.form_mismatch` @x5.check: кусок «Jutro o dziesiątej trzydzieści» ⊂ «Tak, mamy jutro o dziesiątej trzydzieści.».
- Предупреждения: native.gendered_past ×2 и ещё 5 разовых.
- P2R: x1 — починен; B7 — починен («dżan»).
- Осталось: form_mismatch @x5.check.

### pl→en · № 7 — failed · `01M3DH2P92A75BEER4CEWKYHSH` (последняя; совпадает со строкой сцены)
- Фатальные (8, карточек 7):
  - `exchange.second_question` @x1.
  - `pronunciation.foreign_script` @p1.f2: «an اردżent epointment» — арабские ا ر د.
  - `pronunciation.foreign_script` @B7: «…yz იან kowalski».
  - `filler.ungrammatical` @p3.f1 и @p3.f2: «For for three days».
  - `line.ne_frame` @B3.
  - `options.form_mismatch` @x4.check: длина, «Ból ucha» — 7 букв против 22 у «Podwyższoną temperaturę».
  - `options.form_mismatch` @x7.check: кусок «Imię i nazwisko» ⊂ «Proszę podać imię i nazwisko.».
- Предупреждения: frame.no_end_punct ×7, pronunciation.script ×2, filler.one_in_dialogue, variant.longer, check.about_learner, vocab.used_in_wrong.
- P2R: p1 — починен; p3 — починен, кадр стал «I've had it for ___.», но строка B3 «For three days.» под новый кадр не подходит.
- Осталось: second_question @x1, foreign_script @B7, ne_frame @B3, form_mismatch @x4.check, @x7.check.

### be→en · № 1 — ready · `01M3DGRRETJD30HBDYTQRKWFGC`
- Фатальные (1): `options.form_mismatch` @x6.check: длина, «Хатні адрас» — 10 букв против 22 у «Кантактны нумар тэлефона».
- Предупреждения: vocab.used_in_wrong ×3, native.gendered_past ×2, pronunciation.script ×1 (русская «и» в «фрайдэй эт илэвэн»), variant.longer, check.verbatim, listening.distractor_not_filler.
- P2R: x6.check — починен («Паштовы адрас»).
- Судья швов: 24 предложения, 2 «нет» (p7.f2, p7.f3). Итог: ready.
- Закрывающая реплика x1 в be→en — не вопрос. Это единственный из 13 ответов без `exchange.second_question`.

## 3. P2R: кто съедает бюджет

Все 24 починки ворот, по коду, ради которого карточку заказали:

| код-заказчик | починок | из них исправили находку | $ | где |
|---|---|---|---|---|
| `exchange.second_question` | **11** (46 %) | 11 | **0,1605** (58 %) | x1 во всех 5 ru→de и в 5 из 7 pl→en; x6 в pl→en № 2 |
| `pronunciation.foreign_script` | 7 (29 %) | 7 | 0,0630 | только pl→en: строка с именем ×4, кадры ×3 |
| `options.form_mismatch` | 5 (21 %), плюс 1 внутри обмена x6 | 4 из 5 (плюс 1) | 0,0458 | ru→de № 1–4 (второй картой), be→en |
| `filler.ungrammatical` | 1 | 1 (но ne_frame осталась) | 0,0074 | pl→en № 7 |
| **всего** | **24** | 23 | **0,2767** | |

Ни одной починки в pl→en не досталось карточке проверки: первую карту каждый раз брал x1 или кадр, вторую — строка или кадр
с чужими буквами. Единственная проверка pl→en, которую удалось снять, — x6.check в № 2. Её закрыла починка обмена x6.

## 4. Чтения (`pronunciation_native`) по родным языкам — сырые ответы

| родной (пара) | сборок | чтений | только латиница | только кириллица | латиница + кириллица | другие письменности | без букв | вне алфавита языка (предупреждение `pronunciation.script`) | чужие буквы (фатальная `foreign_script`) |
|---|---|---|---|---|---|---|---|---|---|
| ru (ru→de) | 5 | 195 | 0 | **195** | 0 | 0 | 0 | 0 | 0 |
| pl (pl→en) | 7 | 292 | **273** (93,5 %) | 1 | 6 | 10 (грузинские 9, арабские 1) | 2 | 42 | 17 |
| be (be→en) | 1 | 48 | 0 | **48** | 0 | 0 | 0 | 1 | 0 |

**Польские чтения на `lesson_day.v4.8` пришли ЛАТИНИЦЕЙ**, в польской орфографии: «ajd lajk e doktors epointment», «aj haw
e sor trout», «maj nejm iz … kowalski». Чисто латинских — 273 из 292. Кириллица была только в одной сборке из семи (№ 5):
восемь чтений со schwa, записанной русским «э», и целыми кириллическими словами («э сор сроут», «эн эпоjntmеnt»). Это разовый
срыв, не система.

Настоящая системная беда pl→en — **грузинские буквы в чтении строки «My name is Jan Kowalski»**. Они были во всех 7 сборках:
«maj nejm iz იან kowalski», в № 2 — «maj nejm იზ jan kowalski». Грузинское встречается и рядом («ajd lajk ენ ეპojntment» в
№ 2 и № 5), а в № 7 есть арабское «ارد» в «urgent». Модель подставляет буквы другой письменности, звучащие так же (ი=i, ა=a,
ნ=n, ზ=z). В разведке v4.7 было то же самое (комментарий в `config/lesson/lang/pl.php`), но тогда у pl не было
`script_letters`, и находка не считалась.

Пакет pl ловит это верно (`script_letters` — латиница). P2R чинит 7 из 7 раз, но каждая такая починка съедает вторую
карточку. Механическая правка парсера (LANG-1) это не лечит и не должна: она переводит только латинские двойники внутри
кириллических слов.

Остальное — предупреждения, день они не держат:

- В № 2 модель писала IPA-буквы «ə», «ð» (латиница, но не польский алфавит) — 25 × `pronunciation.script`.
- В № 5 встречается литовская «ė».
- У ru→de 195 из 195 чтений кириллические, нарушений нет.
- У be→en одно чтение с русской «и» вместо «і» («илэвэн») — предупреждение.

Сверх того, в 6 из 7 сборок pl→en есть 7–8 × `frame.no_end_punct` (польские кадры без точки). Это тоже предупреждение, на
исход не влияет.

## 5. `options.form_mismatch` — все 23 срабатывания на сырых ответах

Подпункты вычислены заново по коду ветки (`CheckRules::formMismatch`): все, а не только первый. Кроме того, в каждой проверке
сработал ровно один подпункт. Классы даны в смысле `baseline.md`:

- **ложное** — перефразировать нельзя: адрес, цена, число со счётным словом («Четыре часа»; `check.verbatim` на целевой
  стороне это прощает);
- **реальное** — скопированы содержательные слова, которые можно перефразировать, или верный вариант выделяется длиной;
- **пограничное** — время с числом и скопированным «завтра/jutro», одно слово-предмет, длина в пределах 2 букв от порога.

| # | пара · № | адрес | подпункт | вариант (родной) | сработал | класс |
|---|---|---|---|---|---|---|
| 1 | ru→de · 1 | x3.check | кусок | «Температура» (верный) | ⊂ «У вас ещё и температура?» | реальное, пограничное (одно слово-предмет; перефраз «Жар» есть) |
| 2 | ru→de · 1 | x6.check | кусок | «Завтра» (верный) | ⊂ «Завтра в десять или в одиннадцать.» | реальное, пограничное (одно слово времени) |
| 3 | ru→de · 1 | x8.check | кусок | «На десять минут раньше» (верный) | ⊂ «…придите завтра на десять минут раньше.» | реальное, пограничное (число + «раньше») |
| 4 | ru→de · 2 | x7.check | кусок | «Четыре часа» (верный) | ⊂ «Да, в четыре часа ещё есть свободная запись.» | **ложное, не языковое** (число со счётным словом — как ru-de x5 в baseline) |
| 5 | ru→de · 2 | x8.check | кусок | «Завтра в четыре» (верный) | ⊂ «Ваша запись завтра в четыре часа.» | реальное, пограничное (время + «завтра», как ru-fr x4) |
| 6 | ru→de · 3 | x6.check | длина | «Электронную почту пациента» | 24 буквы против 11 (порог 22, +2) | реальное, пограничное |
| 7 | ru→de · 3 | x8.check | кусок | «Завтра в одиннадцать» (верный) | ⊂ «Хорошо, запись на завтра в одиннадцать.» | реальное, пограничное (время) |
| 8 | ru→de · 4 | x5.check | кусок | «Завтра в десять или в одиннадцать» (верный) | ⊂ «Завтра в десять или в одиннадцать можно.» | реальное (почти вся реплика) |
| 9 | ru→de · 4 | x8.check | кусок | «Номер телефона» (верный) | ⊂ «…ваше имя и ваш номер телефона.» | реальное (как ru-es x6) |
| 10 | pl→en · 1 | x7.check | кусок | «Numer telefonu» (верный) | ⊂ «A numer telefonu?» | реальное |
| 11 | pl→en · 1 | x8.check | длина | «Jutro rano» | 9 букв против 22 у верного «Dziś o drugiej po południu» (порог 11, −2) | реальное, пограничное; верный выделяется длиной |
| 12 | pl→en · 2 | x4.check | длина | «Ból brzucha» | 10 букв против 22 у «Podwyższona temperatura» (порог 11, −1) | реальное, пограничное; верный — раздутый перефраз «gorączkę» |
| 13 | pl→en · 2 | x6.check | кусок | «Imię i nazwisko» (верный) | ⊂ «Dobrze. Poproszę imię i nazwisko.» | реальное (перефраз «Dane osobowe» есть) |
| 14 | pl→en · 2 | x7.check | кусок | «Imię i nazwisko» (верный) | ⊂ «Poproszę imię i nazwisko.» | реальное |
| 15 | pl→en · 3 | x3.check | кусок | «Jak długo to trwa» (верный) | = вся реплика «Jak długo to trwa?» | реальное |
| 16 | pl→en · 4 | x3.check | кусок | «Jak długo to trwa» (верный) | = вся реплика | реальное |
| 17 | pl→en · 4 | x4.check | длина | «Rano, o ósmej» | 10 букв против 21 у «Po południu, o piętnastej» (порог 10,5, −0,5) | реальное, пограничное (полбуквы) |
| 18 | pl→en · 5 | x5.check | кусок | «Jutro o jedenastej» (верный) | ⊂ «Tak, jutro o jedenastej też jest wolne.» | реальное, пограничное (время) |
| 19 | pl→en · 5 | x6.check | кусок | «Jutro o jedenastej» (верный) | ⊂ та же реплика (повтор в x5 и x6) | реальное, пограничное (время) |
| 20 | pl→en · 6 | x5.check | кусок | «Jutro o dziesiątej trzydzieści» (верный) | ⊂ «Tak, mamy jutro o dziesiątej trzydzieści.» | реальное, пограничное (время) |
| 21 | pl→en · 7 | x4.check | длина | «Ból ucha» | 7 букв против 22 у «Podwyższoną temperaturę» (порог 11, −4) | реальное |
| 22 | pl→en · 7 | x7.check | кусок | «Imię i nazwisko» (верный) | ⊂ «Proszę podać imię i nazwisko.» | реальное |
| 23 | be→en · 1 | x6.check | длина | «Хатні адрас» | 10 букв против 22 у «Кантактны нумар тэлефона» (порог 11, −1) | реальное, пограничное |

**Итог по 23 срабатываниям.**

- Подпункты: кусок — 17, **все 17 на ВЕРНОМ варианте**; длина — 6; строчная — 0 (в ветке она читается по первому символу,
  и цифр в начале вариантов не было).
- Реальных — 22, из них пограничных 13: время с числом — 7, длина в пределах 2 букв от порога — 5, одно слово-предмет — 1.
- Ложное — одно, русское «Четыре часа», и оно не языковое. **Ложных по вине языка — 0.**
- По парам: ru→de — 9 срабатываний, из них 1 ложное; pl→en — 13, ложных нет; be→en — 1, ложных нет.
- Частота на сырой ответ: ru→de 1,8, pl→en 1,86, be→en 1,0. Для сравнения, в разведке LANG-1 было 1,5 на день, а в ru→en
  дни 1 давали 2,9.

В 5 из 6 срабатываний длины **верный** вариант — самый длинный или делит первое место (pl→en № 1 x8: 22 и 22). Явная
подсказка формой видна дважды: в pl→en № 2 и № 7 на x4 верный вариант 22 буквы против 10–11 и 7–11 у неверных. В обоих
случаях («Podwyższona temperatura» вместо «Gorączka») модель раздувает верный вариант перефразом, чтобы уйти от «куска», и
попадает под «длину». Подпункты тянут в разные стороны. Это же видно в P2R ru→de № 1: «Температура» → «Жар», и теперь
вариант слишком короткий. А в ru→de № 4 P2R обходит «кусок» по букве, меняя «в» на «на».

## 6. Деньги (model_calls: lesson + repair + judge)

| пара | сборок | ready | урок | починки | судья швов | **итого** | на сборку | на готовый день |
|---|---|---|---|---|---|---|---|---|
| ru→de | 5 | 1 | $0,3852 | $0,1055 | $0,0017 | **$0,4924** | $0,0985 | $0,4924 |
| pl→en | 7 | 0 | $0,5171 | $0,1614 | $0,0000 | **$0,6785** | $0,0969 | — (дня нет) |
| be→en | 1 | 1 | $0,0685 | $0,0098 | $0,0024 | **$0,0807** | $0,0807 | $0,0807 |
| **всего** | 13 | 2 | $0,9708 | $0,2767 | $0,0042 | **$1,2517** | $0,0963 | |

По сборкам: ru→de — 0,1305 / 0,0800 / 0,1051 / 0,0809 / 0,0960; pl→en — 0,1138 / 0,0939 / 0,1110 / 0,1103 / 0,0848 /
0,0860 / 0,0786; be→en — 0,0807.

- На проваленные сборки ушло $1,0749 из $1,2517 (86 %).
- Урок — 8 361–8 378 токенов на входе, из них 7 936 из кэша (кроме первой сборки ru→de, $0,1018). Выход — 3 600–5 750 токенов.
- Сверх этого в том же окне: 3 вызова плана на $0,0495 (ru→de 0,0183, pl→en 0,0123, be→en 0,0188). После 00:17 шёл проход
  дня: судья окна ×4 на $0,0027 и разговор ×23 на $0,0241. В сборки это не входит.
- `cost_usd_lesson` последних сборок совпадает с суммой по model_calls: 0,095973 / 0,078595 / 0,080736.

## 7. Вывод

**Что убивает pl→en.**

- **Прямая причина** — `options.form_mismatch`. Он стоит в причине провала всех 7 сборок, а в 4 из 7 (№ 1, 3, 4, 6) это
  единственный код, который остался.
- **Механизм** — бюджет P2R в 2 карточки при порядке «кадр → обмен → строка → проверка». В сыром ответе pl→en 3–9 фатальных
  карточек.
- **Первую карточку** почти всегда забирает `exchange.second_question` на x1: 5 из 7 сборок, а в № 5 и № 7 — кадр с чужими
  буквами.
- **Вторую** забирает строка или кадр с чужими буквами в чтении: грузинское «იან» в строке «My name is Jan Kowalski» было во
  всех 7 сборках.
- До карточек проверок P2R в pl→en не дошёл ни разу.

**Язык и пакет или правило FIX-3? Правило.** Доводы:

1. ru→de с безупречными чтениями (195/195 кириллица, 0 чужих букв) провалилась 4 раза из 5 ровно на `options.form_mismatch`.
   Вторая карточка там шла на проверку, но проверок с находками было 2–3.
2. Частота `form_mismatch` на ответ у pl→en и ru→de одна и та же: 1,86 и 1,8.
3. Пакет pl не дал ни одного ложного срабатывания:
   - 0 × `lang.pack_missing`;
   - 0 срабатываний «строчной»;
   - 9 «кусков» на польском — все настоящие копии слов реплики на верном варианте;
   - 4 «длины» — все реальные, 3 из них пограничные.

   Из 23 срабатываний ложное одно, и оно русское.
4. Контрфакт на тех же записанных ответах и починках. Если `form_mismatch` сделать предупреждением, из 13 сборок прошли бы
   10: ru→de 5/5 (x1 чинится всегда), pl→en 4/7 (№ 1, 3, 4, 6 — после x1 и строки с именем фатального не остаётся),
   be→en 1/1. Сейчас проходят 2.

Языковой вклад pl→en есть, но он сидит в модели, а не в пакете. На v4.8 gpt-5.4 пишет польские чтения латиницей, как просит
промт, но в строке с «Jan» устойчиво вставляет грузинские буквы (7/7, как и в разведке v4.7). Пакет pl ловит это правильно,
P2R чинит 7/7, но это стоит карточку. Даже если бы этого сбоя не было, но `form_mismatch` оставался фатальным, pl→en прошёл бы
не больше чем в 2 из 7 (№ 3 и № 6, если бы удалась починка проверки). Главный рычаг — FIX-3 §5, второй — грузинское имя.

**Главные ли потребители P2R `exchange.second_question` и `form_mismatch`?** Да, но по-разному.

- `exchange.second_question` — главный потребитель по числу и по деньгам: 11 из 24 починок, $0,161 из $0,277. Он стоит на x1
  в 12 из 13 сырых ответов: ru→de 5/5 («Gern. Worum geht es?»), pl→en 7/7 («Of course. What seems to be the problem?»),
  be→en — нет. P2R чинит его всегда (11/11), так что он не убивает, а систематически съедает первую карточку.
- `form_mismatch` — главный **убийца**: 11 из 11 провалов, в 8 из них единственный код. Как заказчик P2R он только третий:
  5 из 24, исправлено 4. Второй по числу потребитель — `pronunciation.foreign_script`: 7 из 24, все в pl→en.

**Что из этого следует (решать архитектору).**

1. **Правило FIX-3 §5.** Три варианта:
   - убрать `form_mismatch` из фатальных;
   - не считать карточки проверок в бюджет 2: починка проверки стоит около $0,009;
   - хотя бы освободить в «куске» числа со счётным словом.

   Контрфакт выше показывает, что первый вариант поднимает выход с 2/13 до 10/13.
2. **Промт v4.8.** Закрывающая реплика A в обмене `ask`, который открывает ученик, — вопрос в 12/13 ответах. Если закрыть это
   в промте, освободится первая карточка каждой сборки.
3. **Грузинские буквы в чтениях pl.** Механически их не исправить. Нужна подсказка в промте или отдельная дешёвая починка
   чтений вне бюджета.

**Оговорки.**

- Контрфакт считался только там, где ответы починок те же, что записаны. Карточки, которые ворота в этом варианте не
  заказали бы, не учитывались. Вариант «убрать правило» от этого не зависит: во всех 10 названных сборках нужные починки уже
  записаны и удались.
- В системном промте v4.8 заголовок по-прежнему «v4.7 (frames)», но строка про чтения — из v4.8. В `plan_scenes` стоит
  `lesson_day.v4.8`.
- Классификация «пограничное / реальное» — моя, по меркам `baseline.md`. Код её не проверяет.

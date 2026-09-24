| # | правило канона | тест | дефект | под дефектом |
|---|---|---|---|---|
| 1 | §1: файл — по сцене узла, текст записи = текст строки | `tests/Feature/Plan/RecallPageSoundTest.php` | свой scene_id узла не читается | FAILED (caught): Tests:    1 failed, 1 passed (6 assertions) |
| 2 | §2: префикс подряд в начале фразы или после вводных слов | `tests/Unit/Plan/FrameJudgeTest.php` | начало каркаса — любое слово | FAILED (caught): Tests:    2 failed, 3 passed (28 assertions) |
| 3 | §2: ПОЧТИ = одно расхождение слова | `tests/Unit/Plan/FrameJudgeTest.php` | порог почти 2 | FAILED (caught): Tests:    4 failed, 1 passed (18 assertions) |
| 4 | §2, п. 395: отрицание — та же конструкция | `tests/Unit/Plan/FrameJudgeTest.php` | свободного not нет | FAILED (caught): Tests:    1 failed, 4 passed (33 assertions) |
| 5 | §2, п. 395: do/does/did not, глагол по основе | `tests/Unit/Plan/FrameJudgeTest.php` | do-support не прощается | FAILED (caught): Tests:    1 failed, 4 passed (30 assertions) |
| 6 | §2: окно ≥ 1 своё слово («I'm working on» — обрыв) | `tests/Unit/Plan/FrameJudgeTest.php` | окно без своего слова принимается | FAILED (caught): Tests:    1 failed, 4 passed (32 assertions) |
| 7 | §2: артикли a/an/the — вне сравнения | `tests/Unit/Plan/FrameJudgeTest.php` | артикли остаются | FAILED (caught): Tests:    1 failed, 4 passed (27 assertions) |
| 8 | §2: судим только каркасы текущей сцены | `tests/Feature/Plan/ConversationApiTest.php` «judges a move by the constructions of the scene» | все каркасы разговора | FAILED (caught): Tests:    1 failed (220 assertions) |
| 9 | §2: повторно сказанное не засчитываем | `tests/Feature/Plan/ConversationApiTest.php` «reads a move for the construction as a phrase» | сказанные каркасы судятся снова | FAILED (caught): Tests:    1 failed (39 assertions) |
| 10 | §3: открытие — только цель текущей сцены | `tests/Feature/Plan/ConversationApiTest.php` «drops a door the role names outside its scene» | проверки сцены нет | FAILED (caught): Tests:    1 failed (217 assertions) |
| 11 | §3: открытие — только несказанная цель | `tests/Feature/Plan/ConversationApiTest.php` «drops a door the role names outside its scene» | проверки «уже сказана» нет | FAILED (caught): Tests:    1 failed (217 assertions) |
| 12 | §3: цели — короткими id T1…T7 | `tests/Feature/Plan/ConversationApiTest.php` «tells the role only its scene» | id цели — <scene>:<ref> | FAILED (caught): Tests:    1 failed (217 assertions) |
| 13 | §4: бюджет сцены = целей + 1 | `tests/Feature/Plan/ConversationApiTest.php` «closes a scene by its rule» | запас сцены 2 | FAILED (caught): Tests:    1 failed (215 assertions) |
| 14 | §4: сцена закрыта, когда все её цели сказаны ИЛИ бюджет исчерпан | `tests/Feature/Plan/ConversationApiTest.php` «closes a scene by its rule» | только бюджет | FAILED (caught): Tests:    1 failed (235 assertions) |
| 15 | §4: закрытие = прощание роли этой сцены, scene_event end | `tests/Feature/Plan/ConversationApiTest.php` «closes a scene by its rule» | вместо прощания — обычный ответ | FAILED (caught): Tests:    1 failed (245 assertions) |
| 16 | §4, §6: голос по полу роли, меняется ходом start | `tests/Feature/Plan/ConversationApiTest.php` «changes the voice on the next role» | голос всегда женский | FAILED (caught): Tests:    1 failed (218 assertions) |
| 17 | §4: лимит раньше — ended_by_limit = true | `tests/Unit/Plan/ConversationTest.php` «knows a talk the limit ended» | только ended_reason limit | FAILED (caught): Tests:    1 failed (2 assertions) |
| 18 | §5: ближайшая = только что открытая, иначе первая несказанная | `tests/Feature/Plan/ConversationApiTest.php` «takes the door the role names» | только что открытая пропущена | FAILED (caught): Tests:    1 failed (6 assertions) |
| 19 | §5: после «почти» по X — hint_target = точная строка X | `tests/Feature/Plan/ConversationApiTest.php` «reads a move for the construction as a phrase» | target всегда null | FAILED (caught): Tests:    1 failed (26 assertions) |
| 20 | §5: hint_native = ПОЛНАЯ фраза с наполнением урока | `tests/Feature/Plan/ConversationApiTest.php` «opens with the role» | каркас, окно — многоточием | FAILED (caught): Tests:    1 failed (26 assertions) |
| 21 | §3, §6: отбросить + журнал с причиной | `tests/Feature/Plan/ConversationApiTest.php` «drops a door the role names outside its scene» | запись в журнал выпала | FAILED (caught): Tests:    1 failed (218 assertions) |
| 22 | §6: каждая попытка — своя запись с номером попытки | `tests/Feature/Plan/ConversationApiTest.php` «takes the second answer whatever it says» | номер попытки 1 | FAILED (caught): Tests:    1 failed (14 assertions) |
| 23 | §6: правило «p.m..» — одна точка | `tests/Unit/Plan/FrameFillEndsTest.php`<br>`tests/Feature/Plan/RebuildCardTextsTest.php` | сокращение не узнаётся | FAILED (caught): Tests:    2 failed (2 assertions) | Tests:    1 failed (5 assertions) |
| 24 | §6: plan_scenes.built_at = конец сборки урока | `tests/Feature/Admin/AdminPlanPageTest.php` «stamps the end of a scene» | конец сборки не записан | FAILED (caught): Tests:    1 failed (3 assertions) |

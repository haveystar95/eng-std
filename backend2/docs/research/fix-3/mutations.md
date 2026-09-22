| # | правило канона | тест | дефект | под дефектом |
|---|---|---|---|---|
| 1 | §1: голос ученика — пол профиля | `tests/Feature/Plan/PlanDayWindowTest.php` «casts a scene» | ученик всегда мужской | FAILED (caught): Tests:    1 failed (26 assertions) |
| 2 | §1: dry-run по умолчанию | `tests/Feature/Plan/PlanDayWindowTest.php` «names what the learner» | флаг --apply не читается | FAILED (caught): Tests:    1 failed (7 assertions) |
| 3 | §1: родовые строки ученика по профилю | `tests/Unit/Plan/LearnerGenderStringsTest.php` | женская форма не читается | FAILED (caught): Tests:    1 failed (1 assertions) |
| 4 | §2: у плана — снимок plan.pace | `tests/Feature/Plan/PlanPaceTest.php` «gives a new plan» | минуты по конфигу, не по плану | FAILED (caught): Tests:    1 failed (7 assertions) |
| 5 | §2: plan:repace идемпотентна | `tests/Feature/Plan/PlanPaceTest.php` «once» | план с нынешним списком пишется снова | FAILED (caught): Tests:    1 failed (7 assertions) |
| 6 | §2: план, сделанный раньше, держит свой прейскурант до repace | `tests/Feature/Plan/PlanPaceTest.php` «made before» | миграция не заполняет снимок | FAILED (caught): Tests:    1 failed (2 assertions) |
| 7 | §2: PAUSE_SECONDS 600 → 120 | `tests/Unit/Plan/Session/SessionDayAssemblyTest.php` «pauses over two minutes» | пауза 600 с | FAILED (caught): Tests:    1 failed (1 assertions) |
| 8 | §3: значений ≥ 2 → кругов ≥ 2 | `tests/Unit/Plan/Session/SessionSeamFillersTest.php` «two rounds all the same» | минимум кругов — 1 | FAILED (caught): Tests:    1 failed (10 assertions) |
| 9 | §3: ступень 2 — вторые узнавания | `tests/Unit/Plan/Session/PhrasesStageTest.php` «a rung at a time» | ступень 2 ничего не снимает | FAILED (caught): Tests:    1 failed (12 assertions) |
| 10 | §4: составные складываются, «one hundred and twenty» = 120 | `tests/Unit/Shared/SpeechMatchTest.php` «joins a hundred» | связка не читается | FAILED (caught): Tests:    1 failed (1 assertions) |
| 11 | §4: не подходит — два числа | `tests/Unit/Shared/SpeechMatchTest.php` «folds a number in the middle» | порог 20 → 10 | FAILED (caught): Tests:    1 failed (6 assertions) |
| 12 | §5: options.form_mismatch — длина 0,5–2× | `tests/Unit/Plan/LessonValidatorTest.php` «counts the one rule a lesson breaks» | границы длины сняты | FAILED (caught): Tests:    1 failed, 55 passed (56 assertions) |
| 13 | §6: вопрос — только со своим первым словом | `tests/Unit/Plan/PhraseUseTest.php` «credits a question only» | проверка открывающего слова снята | FAILED (caught): Tests:    1 failed (4 assertions) |
| 14 | §6: окно — своё значение в том же предложении | `tests/Unit/Plan/PhraseUseTest.php` «own value out of the window» | граница предложения снята | FAILED (caught): Tests:    1 failed (6 assertions) |
| 15 | §6: «висячее» слово окна не заполняет | `tests/Unit/Plan/PhraseUseTest.php` «own value out of the window» | dangling_words не читаются | FAILED (caught): Tests:    1 failed (8 assertions) |
| 16 | §7: turn_limit = целей + 2 | `tests/Feature/Plan/ConversationApiTest.php` «a move per target» | ходов всегда 4 | FAILED (caught): Tests:    1 failed (5 assertions) |
| 17 | §7: минуты — жёсткий стоп | `tests/Feature/Plan/ConversationApiTest.php` «a move per target» | кап по времени снят | FAILED (caught): Tests:    1 failed (16 assertions) |
| 18 | §7 (живой прогон): роль не повторяет свою реплику | `tests/Feature/Plan/ConversationApiTest.php` «naming the line» | страж own_line снят | FAILED (caught): Tests:    1 failed (6 assertions) |
| 19 | §7: «цели покрыты» концом не является | `tests/Feature/Plan/ConversationApiTest.php` «closes the talk with moves left» | страж early_end снят | FAILED (caught): Tests:    1 failed (6 assertions) |
| 20 | §7: обрывок ≠ «не понял» | `tests/Feature/Plan/ConversationApiTest.php` «broke off» | суждение с обрывка не снимается | FAILED (caught): Tests:    1 failed (8 assertions) |
| 21 | §7: подсказка = цель, отвечающая на последний вопрос роли | `tests/Feature/Plan/ConversationApiTest.php` «takes the door the role names» | реплика без двери не держит прежнюю | FAILED (caught): Tests:    1 failed (7 assertions) |
| 22 | §7: LEAD_TO — сначала сцена, где разговор, и следующие | `tests/Feature/Plan/ConversationApiTest.php` «leads a rehearsal» | порядок сцен не учитывается | FAILED (caught): Tests:    1 failed (216 assertions) |
| 23 | §7 (живой прогон): роль знает, что уже случилось | `tests/Feature/Plan/ConversationApiTest.php` «marks the exchange» | DONE не ставится | FAILED (caught): Tests:    1 failed (8 assertions) |
| 24 | §7: цель хода по правилу кода известна роли | `tests/Feature/Plan/ConversationApiTest.php` «marks the exchange» | saidNow пуст | FAILED (caught): Tests:    1 failed (8 assertions) |
| 25 | §8: stages[].again — у этапов карточек всегда true | `tests/Unit/Plan/StageSummaryTest.php` «walked again» | again у карточек false | FAILED (caught): Tests:    1 failed (1 assertions) |
| 26 | §9: summary.returns = число вернувшихся | `tests/Feature/Plan/GymDaysTest.php` «seven that came back» | returns всегда 0 | FAILED (caught): Tests:    1 failed (4 assertions) |
| 27 | §10: first_try — все карточки единицы с первой попытки | `tests/Unit/Plan/StageSummaryTest.php` «counts a stage of cards by its units» | число попыток не читается | FAILED (caught): Tests:    1 failed (1 assertions) |
| 28 | §11: эхо-страж по всем ходам | `tests/Feature/Plan/ConversationApiTest.php` «a move the learner made earlier» | страж читает последний ход | FAILED (caught): Tests:    1 failed (7 assertions) |
| 29 | §11: задание 35-2 — придаточным | `tests/Unit/Plan/Session/SpeakStageTest.php` «exactly its keys» | task_clause_native — предложение | FAILED (caught): Tests:    1 failed (7 assertions) |

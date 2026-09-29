# GEN-4 · прогон ворот — таблицы

_Собрано `tools/gate.php table` из `runs/` (без вызовов). «Чистый» — ПЕРВЫЙ ответ ступени без единой находки; «без фатальных» — без фатальных кодов (жирным)._

## Дни — `luna` (скелет и диалог на `gpt-5.6-luna`; починки и судья — по конфигу)

| план | пара | сцена | скелет, 1-й ответ | повт. | диалог, 1-й ответ | повт. | починки отпр. / оставл. / помогли | судья: прочтений, «не читается» | осталось у дня | итог | цена | время, с |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 01 | ru→ro | Основные вопросы | vocab.definition_language ×5, vocab.used_in_wrong ×2 | 0 | check.verbatim ×2, speaking_key.wrong | 0 | 4 / 4 / 4 | 1, 0 | check.verbatim, vocab.definition_language ×4 | ok | $0.0787 | 79 |
| 02 | ru→en | Приём у врача | filler.common_prefix, partner.names_filler, **vocab.not_found**, vocab.used_in_wrong | 1 | check.answer_is_filler, check.verbatim ×7, native.foreign_letters, speaking_key.wrong, variant.longer | 0 | 2 / 2 / 2 | 1, 0 | check.answer_is_filler, check.verbatim ×7, native.foreign_letters | ok | $0.1178 | 136 |
| 03 | pl→en | Oglądanie lokalu | filler.repeats_frame ×3, partner.names_filler, vocab.used_in_wrong | 0 | check.verbatim ×3, speaking_key.wrong ×2, variant.longer | 0 | 4 / 4 / 4 | 2, 5 | check.verbatim ×3, filler.native_seam ×2, partner.names_filler, speaking_key.wrong, vocab.used_in_wrong | ok | $0.0835 | 85 |
| 04 | ru→de | Открытие счёта | partner.names_filler ×4, vocab.definition_language ×2, **vocab.not_found**, vocab.used_in_wrong | 1 | check.answer_is_filler, check.verbatim ×3, **frame.unused** ×2, listening.distractor_not_filler, speaking_key.wrong ×4, variant.longer | 1 | 2 / 1 / 1 | 1, 0 | check.verbatim ×4, **frame.unused** ×2, partner.names_filler ×3, speaking_key.wrong ×5 | fatal: frame.unused | $0.1235 | 154 |
| 05 | es→en | Control migratorio | partner.names_filler, **vocab.not_found**, vocab.used_in_wrong | 1 | check.verbatim | 0 | 2 / 2 / 1 | 1, 0 | check.verbatim | ok | $0.0945 | 124 |
| 06 | uk→en | Основні питання | **pronunciation.foreign_script**, **vocab.not_found**, vocab.used_in_wrong ×2 | 1 | check.verbatim ×2, listening.distractor_not_filler, **partner.missing**, **partner.twice**, speaking_key.wrong, variant.longer | 1 | 4 / 4 / 4 | 2, 0 | check.verbatim, speaking_key.wrong, vocab.used_in_wrong ×2 | ok | $0.1485 | 159 |
| 07 | ru→it | Разговор с учит. | partner.names_filler ×3, **pronunciation.foreign_script**, vocab.definition_language ×4, **vocab.not_found**, vocab.used_in_wrong ×2 | 1 | — | 0 | 0 / 0 / 0 | 0, 0 | partner.names_filler ×4, **pronunciation.foreign_script**, vocab.definition_language ×4, **vocab.not_found**, vocab.used_in_wrong | fatal: vocab.not_found, pronunciation.foreign_script | $0.0667 | 93 |
| 08 | ru→en | Приём у врача | **vocab.not_found**, vocab.stop_word, vocab.used_in_wrong ×3 | 1 | check.answer_is_filler, check.verbatim ×4, **frame.unused** | 1 | 2 / 2 / 2 | 1, 0 | check.verbatim ×2, **frame.unused**, speaking_key.wrong, variant.longer | fatal: frame.unused | $0.1181 | 150 |
| 09 | be→pl | Прыём у лекара | partner.names_filler ×2, **pronunciation.foreign_script** ×4, pronunciation.near_native ×2, vocab.definition_language ×2, vocab.stop_word ×3, vocab.used_in_wrong | 1 | check.verbatim, listening.distractor_not_filler, **partner.unlinked** | 1 | 2 / 2 / 2 | 2, 1 | check.verbatim ×2, listening.distractor_not_filler, partner.names_filler ×2, **partner.unlinked**, pronunciation.near_native, variant.longer, vocab.definition_language ×4, vocab.used_in_wrong | fatal: partner.unlinked | $0.1336 | 171 |
| 10 | ro→fr | Întrebări-cheie | **pronunciation.foreign_script**, pronunciation.near_native ×3, vocab.definition_language ×4, **vocab.not_found** ×3, vocab.stop_word ×2, vocab.used_in_wrong ×3 | 1 | — | 0 | 0 / 0 / 0 | 0, 0 | pronunciation.near_native ×2, vocab.definition_language, **vocab.not_found**, vocab.stop_word, vocab.used_in_wrong | fatal: vocab.not_found | $0.0568 | 77 |
| 11 | de→es | Besichtigung | partner.names_filler ×2, vocab.definition_language ×7, **vocab.not_found**, vocab.stop_word, vocab.used_in_wrong | 1 | — | 0 | 0 / 0 / 0 | 0, 0 | partner.names_filler ×2, **pronunciation.equals_native**, pronunciation.near_native ×13, vocab.definition_language ×6, vocab.stop_word | fatal: pronunciation.equals_native | $0.0503 | 67 |
| 12 | fr→en | Accueil banque | vocab.stop_word ×3 | 0 | check.verbatim ×4 | 0 | 4 / 4 / 4 | 1, 0 | check.verbatim ×2, vocab.stop_word | ok | $0.0650 | 69 |
| 13 | it→en | Stipendio | **pronunciation.foreign_script** | 1 | — | 0 | 0 / 0 / 0 | 0, 0 | frame.too_long, **pronunciation.foreign_script** ×2, **vocab.not_found**, vocab.used_in_wrong | fatal: vocab.not_found, pronunciation.foreign_script | $0.0560 | 69 |
| 14 | en→ro | Interview | filler.common_prefix, vocab.definition_language ×3 | 0 | check.verbatim ×4, variant.longer | 0 | 4 / 4 / 4 | 2, 3 | check.verbatim ×3, vocab.definition_language ×3 | ok | $0.0839 | 87 |
| 15 | en→de | Pet rules | partner.names_filler ×2, **pronunciation.foreign_script**, vocab.definition_language ×3 | 1 | — | 0 | 0 / 0 / 0 | 0, 0 | filler.common_prefix ×3, pronunciation.near_native, vocab.definition_language ×6, **vocab.not_found**, vocab.used_in_wrong ×5 | fatal: vocab.not_found | $0.0552 | 70 |
| 16 | ru→en | Повторный приём | partner.names_filler, vocab.stop_word | 0 | check.verbatim ×6, speaking_key.wrong | 0 | 4 / 3 / 3 | 1, 1 | check.verbatim ×5, filler.native_seam, vocab.stop_word, vocab.used_in_wrong ×2 | ok | $0.0727 | 73 |

## Дни — `gpt54` (скелет и диалог на `gpt-5.4`; починки и судья — по конфигу)

| план | пара | сцена | скелет, 1-й ответ | повт. | диалог, 1-й ответ | повт. | починки отпр. / оставл. / помогли | судья: прочтений, «не читается» | осталось у дня | итог | цена | время, с |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 01 | ru→ro | Основные вопросы | — | 0 | check.verbatim ×6 | 0 | 2 / 2 / 2 | 1, 0 | check.verbatim ×4 | ok | $0.1113 | 44 |
| 02 | ru→en | Приём у врача | pronunciation.near_native, vocab.used_in_wrong | 0 | check.verbatim ×6, **partner.unlinked** ×2, speaking_key.wrong | 1 | 2 / 2 / 1 | 2, 0 | check.verbatim ×7, **partner.unlinked** ×2, pronunciation.near_native, speaking_key.wrong | fatal: partner.unlinked | $0.1653 | 80 |
| 03 | pl→en | Oglądanie lokalu | partner.names_filler ×4, **pronunciation.foreign_script** ×3 | 1 | check.answer_is_filler, check.verbatim ×5, speaking_key.wrong ×2, variant.longer ×2 | 0 | 4 / 4 / 4 | 2, 1 | check.answer_is_filler, check.verbatim ×5, partner.names_filler ×3, variant.longer | ok | $0.1679 | 93 |
| 04 | ru→de | Открытие счёта | partner.names_filler ×3, **partner.pairs_many**, **pronunciation.foreign_script** | 1 | check.verbatim ×6, listening.distractor_not_filler | 0 | 4 / 4 / 4 | 1, 0 | check.verbatim ×4, listening.distractor_not_filler, partner.names_filler ×3, vocab.used_in_wrong | ok | $0.1566 | 86 |
| 05 | es→en | Control migratorio | partner.names_filler, **partner.pairs_many**, vocab.used_in_wrong | 1 | — | 0 | 0 / 0 / 0 | 0, 0 | filler.repeats_frame, partner.names_filler, **pronunciation.foreign_script**, vocab.used_in_wrong | fatal: pronunciation.foreign_script | $0.0571 | 32 |
| 06 | uk→en | Основні питання | filler.common_prefix, learner.gender ×3, partner.names_filler ×3 | 0 | check.verbatim ×5, listening.distractor_not_filler, speaking_key.wrong, variant.longer | 0 | 4 / 4 / 3 | 2, 3 | check.verbatim ×4, filler.common_prefix, listening.distractor_not_filler, partner.names_filler ×3, speaking_key.wrong | ok | $0.1334 | 73 |
| 07 | ru→it | Разговор с учит. | filler.common_prefix, learner.gender ×3, partner.names_filler ×3, **partner.pairs_many**, vocab.used_in_wrong | 1 | check.verbatim ×6, speaking_key.wrong | 0 | 4 / 3 / 3 | 2, 0 | check.verbatim ×5, filler.common_prefix, partner.names_filler ×3, vocab.stop_word | ok | $0.1802 | 116 |
| 08 | ru→en | Приём у врача | partner.names_filler, vocab.stop_word ×2 | 0 | check.verbatim ×4 | 0 | 4 / 4 / 3 | 1, 0 | check.verbatim ×3, vocab.stop_word, vocab.used_in_wrong | ok | $0.1101 | 58 |
| 09 | be→pl | Прыём у лекара | filler.repeats_frame ×3, partner.names_filler ×3, **partner.pairs_many**, **pronunciation.foreign_script** | 1 | check.verbatim ×6 | 0 | 4 / 3 / 3 | 1, 0 | check.verbatim ×4, partner.names_filler, vocab.used_in_wrong | ok | $0.1570 | 112 |
| 10 | ro→fr | Întrebări-cheie | **pronunciation.foreign_script** ×32, vocab.stop_word, vocab.used_in_wrong | 1 | check.verbatim ×3, speaking_key.wrong | 0 | 4 / 3 / 3 | 2, 6 | check.verbatim ×2, filler.native_seam ×5, vocab.stop_word | ok | $0.1768 | 131 |
| 11 | de→es | Besichtigung | partner.names_filler, **partner.pairs_many**, **pronunciation.foreign_script** ×40 | 1 | check.verbatim ×6, variant.longer | 0 | 3 / 3 / 2 | 1, 0 | check.verbatim ×6, vocab.used_in_wrong | ok | $0.1421 | 90 |
| 12 | fr→en | Accueil banque | partner.names_filler, **vocab.not_found**, vocab.stop_word, vocab.used_in_wrong | 1 | check.verbatim ×6 | 0 | 4 / 4 / 4 | 1, 0 | check.verbatim ×4, vocab.used_in_wrong | ok | $0.1169 | 79 |
| 13 | it→en | Stipendio | partner.names_filler ×4, **partner.pairs_many**, **pronunciation.foreign_script** ×37, vocab.used_in_wrong | 1 | check.answer_is_filler, check.verbatim ×5, speaking_key.wrong, variant.longer | 0 | 4 / 4 / 4 | 2, 3 | check.answer_is_filler, check.verbatim ×5, partner.names_filler ×4, vocab.used_in_wrong | ok | $0.1727 | 124 |
| 14 | en→ro | Interview | **pronunciation.foreign_script** ×37 | 1 | — | 0 | 0 / 0 / 0 | 0, 0 | **partner.pairs_many**, vocab.reading | fatal: partner.pairs_many | $0.0708 | 61 |
| 15 | en→de | Pet rules | partner.names_filler ×2, **partner.pairs_many**, vocab.stop_word, vocab.used_in_wrong | 1 | check.verbatim ×6 | 0 | 4 / 4 / 4 | 2, 1 | check.verbatim ×4, partner.names_filler ×3, vocab.used_in_wrong ×3 | ok | $0.1720 | 126 |
| 16 | ru→en | Повторный приём | partner.names_filler ×2, **partner.pairs_many**, vocab.stop_word | 1 | check.answer_is_filler, check.verbatim ×5 | 0 | 4 / 4 / 4 | 2, 2 | check.answer_is_filler, check.verbatim ×3, partner.names_filler ×3 | ok | $0.1409 | 98 |

## Итог

_Доли — первый ответ ступени каждого дня, одной меркой (финальный код проверок, `recheck.json`); «в прогоне» — как прочёл его код прогона, где отличается. Починки, повторы, цена, время — как прошёл прогон._

| модель ступеней | дней собрано | чистых скелетов | скелетов без фатальных | чистых диалогов | диалогов без фатальных | починок на день (отпр.) | оставлено / помогло | повторов скелета / диалога | средняя цена дня | среднее время дня, с |
|---|---|---|---|---|---|---|---|---|---|---|
| `gpt-5.6-luna` | 8 / 16 (50 %) | 0 / 16 (0 %) | 5 / 16 (31 %) | 0 / 11 (0 %) | 7 / 11 (64 %) | 2.12 | 32 / 31 | 11 / 4 | $0.0878 | 104 |
| `gpt-5.4` | 13 / 16 (81 %) | 1 / 16 (6 %) | 4 / 16 (25 %) | 0 / 14 (0 %) | 13 / 14 (93 %) | 3.19 | 48 / 44 | 12 / 1 | $0.1394 | 88 |

### Все ответы ступеней, одной меркой (первые и повторы)

| прогон | скелетов | чистых | без фатальных | не по схеме | диалогов | чистых | без фатальных | не по схеме |
|---|---|---|---|---|---|---|---|---|
| `days/luna` | 27 | 1 / 27 (4 %) | 9 / 27 (33 %) | 0 | 15 | 0 / 15 (0 %) | 8 / 15 (53 %) | 0 |
| `days/gpt54` | 28 | 1 / 28 (4 %) | 14 / 28 (50 %) | 0 | 15 | 0 / 15 (0 %) | 13 / 15 (87 %) | 0 |
| `skeletons/luna-high` | 8 | 2 / 8 (25 %) | 6 / 8 (75 %) | 0 | 0 | — | — | 0 |

### Где финальный код прочёл бы ответ иначе (фатальные коды)

| прогон | план | ответ | фатальные в прогоне | фатальные финальным кодом |
|---|---|---|---|---|
| `luna` | 04 | skeleton 1 | vocab.not_found | partner.pairs_many, vocab.not_found |
| `luna` | 04 | skeleton 2 | — | partner.pairs_many |
| `luna` | 05 | skeleton 1 | vocab.not_found | partner.pairs_many, vocab.not_found |
| `luna` | 07 | skeleton 1 | pronunciation.foreign_script, vocab.not_found | partner.pairs_many, pronunciation.foreign_script, vocab.not_found |
| `luna` | 08 | skeleton 2 | — | partner.pairs_many |
| `luna` | 09 | skeleton 2 | — | partner.pairs_many |
| `luna` | 10 | skeleton 1 | pronunciation.foreign_script, vocab.not_found | pronunciation.foreign_script |
| `luna` | 10 | skeleton 2 | vocab.not_found | — |
| `luna` | 11 | skeleton 1 | vocab.not_found | partner.pairs_many, vocab.not_found |
| `luna` | 11 | skeleton 2 | pronunciation.equals_native | partner.pairs_many, pronunciation.equals_native |
| `luna` | 15 | skeleton 2 | vocab.not_found | partner.pairs_many |

## Скелет отдельно — `luna-high` (`gpt-5.6-luna`, reasoning_effort `high`) рядом с первым скелетом `luna` (обе колонки — финальным кодом)

| план | сцена | `luna-high`: находки | токены рассуждения | цена | `luna` (без effort): находки 1-го скелета | токены рассуждения |
|---|---|---|---|---|---|---|
| 01 | Основные вопросы | vocab.definition_language ×4, vocab.stop_word | 10238 | $0.0777 | vocab.definition_language ×5 | 2448 |
| 02 | Приём у врача | vocab.used_in_wrong ×2 | 14193 | $0.0992 | filler.common_prefix, partner.names_filler, **vocab.not_found**, vocab.used_in_wrong | 1993 |
| 03 | Oglądanie lokalu | — | 11257 | $0.0823 | filler.repeats_frame ×3, partner.names_filler, vocab.used_in_wrong | 1536 |
| 04 | Открытие счёта | нет ответа за 180 с (таймаут сборки) | — | ? (не записана) | partner.names_filler ×4, **partner.pairs_many**, vocab.definition_language ×2, **vocab.not_found**, vocab.used_in_wrong | 2157 |
| 05 | Control migratorio | — | 12088 | $0.0838 | partner.names_filler, **partner.pairs_many**, **vocab.not_found**, vocab.used_in_wrong | 3346 |
| 06 | Основні питання | **partner.pairs_many** | 11998 | $0.0880 | **pronunciation.foreign_script**, **vocab.not_found**, vocab.used_in_wrong ×2 | 1658 |
| 07 | Разговор с учит. | partner.names_filler, **partner.pairs_many**, vocab.stop_word | 12737 | $0.0926 | partner.names_filler ×3, **partner.pairs_many**, **pronunciation.foreign_script**, vocab.definition_language ×4, **vocab.not_found**, vocab.used_in_wrong ×2 | 3072 |
| 08 | Приём у врача | filler.repeats_frame ×3, partner.names_filler, vocab.stop_word ×2 | 11684 | $0.0840 | **vocab.not_found**, vocab.stop_word, vocab.used_in_wrong ×3 | 2014 |
| 09 | Прыём у лекара | нет ответа за 180 с (таймаут сборки) | — | ? (не записана) | partner.names_filler ×2, **pronunciation.foreign_script** ×4, pronunciation.near_native ×2, vocab.definition_language ×2, vocab.stop_word ×3, vocab.used_in_wrong | 2255 |
| 10 | Întrebări-cheie | vocab.definition_language ×3, vocab.stop_word | 14337 | $0.0993 | **pronunciation.foreign_script**, pronunciation.near_native ×3, vocab.definition_language ×4, vocab.stop_word ×2 | 2797 |

На 10 сценах: ответили 8, без ответа за 180 с — 2. Чистых скелетов: `luna-high` — 2 / 10 (20 %), `luna` — 0 / 10 (0 %); без фатальных: 6 / 10 (60 %) против 2 / 10 (20 %) (сцена без ответа — не «без фатальных»); средняя цена ответившего скелета `luna-high` — $0.0884, время — 143 с.

## Коды на первых ответах ступеней (одной меркой; число дней, где код найден)

| код | gpt54/dialogue | gpt54/skeleton | luna-high/skeleton | luna/dialogue | luna/skeleton |
|---|---|---|---|---|---|
| check.answer_is_filler | 3 | 0 | 0 | 3 | 0 |
| check.verbatim | 14 | 0 | 0 | 11 | 0 |
| filler.common_prefix | 0 | 2 | 0 | 0 | 2 |
| filler.repeats_frame | 0 | 1 | 1 | 0 | 1 |
| **frame.unused** | 0 | 0 | 0 | 2 | 0 |
| learner.gender | 0 | 2 | 0 | 0 | 0 |
| listening.distractor_not_filler | 2 | 0 | 0 | 3 | 0 |
| native.foreign_letters | 0 | 0 | 0 | 1 | 0 |
| **partner.missing** | 0 | 0 | 0 | 1 | 0 |
| partner.names_filler | 0 | 12 | 2 | 0 | 9 |
| **partner.pairs_many** | 0 | 8 | 2 | 0 | 4 |
| **partner.twice** | 0 | 0 | 0 | 1 | 0 |
| **partner.unlinked** | 1 | 0 | 0 | 1 | 0 |
| **pronunciation.foreign_script** | 0 | 7 | 0 | 0 | 6 |
| pronunciation.near_native | 0 | 1 | 0 | 0 | 2 |
| speaking_key.wrong | 6 | 0 | 0 | 6 | 0 |
| variant.longer | 4 | 0 | 0 | 5 | 0 |
| vocab.definition_language | 0 | 0 | 2 | 0 | 8 |
| **vocab.not_found** | 0 | 1 | 0 | 0 | 7 |
| vocab.stop_word | 0 | 5 | 4 | 0 | 6 |
| vocab.used_in_wrong | 0 | 7 | 1 | 0 | 9 |

Потрачено на OpenAI за наряд до этой таблицы (spend.json): **$4.9341** (кап наряда $5.00; кап прогона $5.00).

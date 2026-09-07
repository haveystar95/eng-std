# Словарь подписей плана — «ключ ARB → русская подпись → где стоит»

Наряд DAY-FIX-2, Ч.6. **Источник правды — `mobile/lib/l10n/app_ru.arb`**; этот файл — его
витрина для плановых экранов. Правило: каждая строка на экранах плана берётся из ARB, а каждый
ключ ARB с префиксом `plan*` стоит в этой таблице. Гард: `mobile/test/l10n/plan_glossary_test.dart`
— падает, если ключ есть в ARB и отсутствует здесь. Обновлять таблицу: `python3 glossary.py`
(скрипт наряда) или вручную той же строкой.

## Обязательные слова (наряд, Ч.6.2)

| Слово | Ключ | Где стоит |
|---|---|---|
| Спасатели | `planDialogueRescue` | экран дня (секция), кнопка в диалоге, панель спасателей |
| Слова | `planShelfWordsOnly` | экран дня (секция) |
| Связки | `planShelfChunks` | экран дня (секция) |
| Тебе скажут | `planShelfHear` | экран дня (секция), шов посадки |
| Ты ответишь | `planShelfSay` | экран дня (секция), шов посадки |
| Ты спросишь | `planShelfAsk` | экран дня (секция), шов посадки |
| Диалог сцены | `planSectionDialogue` | шапка присеста, шапка диалога |
| Прогон сцены | `planSectionSceneRun` | шапка присеста, шов перед прогоном |
| Что тебе сказали? | `planDialogueAskHeard` | такт 1 диалога |
| Что ответишь? | `planDialogueAskSay` | такт 2 диалога |
| Что спросишь? | `planDialogueAskAsk` | такт 2 диалога (полка ask) |
| собеседник | `planDialogueRoleName` | первый пузырь роли в диалоге и в итоге сцены |
| сказано вслух | `planDialogueSaidAloud` | под СВОИМ пузырём, когда сказано голосом |
| не начат | `planStateNotStarted` | вкладка «План», экран дня, шапка присеста, главная |
| идёт | `planStateInProgress` | там же; рядом — минуты (`planStateMinutes`) |
| материал пройден | `planStateMaterialDone` | там же (day_state = material_done); рядом — `planStateConversationAbout` |
| пройден | `planStateDone` | там же |
| материал около N минут · разговор около N минут | `planStateMaterialAbout` · `planStateConversationAbout` | экран дня под названием сцены (оба присеста); вкладка «План» при «материал пройден» |
| К разговору | `planSittingToConversation` | вкладка «План», экран дня (material_done), итог материала между присестами |
| Позже | `planSittingLater` | итог материала между присестами |
| Материал пройден | `planMaterialDoneTitle` + `planMaterialDoneLead` | итог материала между присестами |
| познакомишься · переведёшь / соберёшь из плиток / соберёшь из блоков / выберешь ответ / скажешь голосом | `planStepMeet` · `planStepTranslate` / `planStepTiles` / `planStepAssemble` / `planStepChoose` / `planStepSay` | экран дня, строка под секцией (`next_step` + `then_steps`) |
| познакомился / применяешь / говоришь сам | `planMarkMet` / `planMarkApplying` / `planMarkSaidSelf` | экран дня, отметка у строки |
| по теме | `planTermTopical` | экран дня, у тематического слова секции «Слова» |
| познакомился | `planLadderA` | итог дня |
| применяешь | `planLadderB` | итог дня |
| говоришь сам | `planLadderC` | итог дня |
| Начать день | `planRowStartDay` | вкладка «План», экран дня (day_state = not_started) |
| Продолжить | `planSittingContinue` | вкладка «План», экран дня (in_progress), экран перед прогоном |
| Пройти ещё раз | `planDayRepeat` | вкладка «План», экран дня (done) |
| Ещё раз | `planDialogueReplay` | пузырь роли — единственный способ переслушать |
| Скрыть текст | `planDialogueHideText` | пузырь роли |
| Пропустить | `sessionSkip` | карточка в посадке |
| Ещё в этой сцене | `planDialogueTail` | хвостовая карточка сцены после разговора |
| Отвечал сам · на все / N раз подсказали | `planDialogueAnsweredSelf` + `planDialogueAnsweredAll` / `planDialogueAnsweredHinted` | итог сцены |
| Разобрал реплику на слух · все реплики / N подсказали | `planDialogueHeardOut` + `planDialogueHeardAll` / `planDialogueHeardHinted` | итог сцены |
| Прошёл сам · всю сцену / кроме N реплик | `planSceneRunSaidSelf` + `planSceneRunSaidAll` / `planSceneRunSaidSome` | итог прогона |
| Сразу · все / не все / пока нет | `planSceneRunSaidFast` + `planSceneRunFastAll` / `planSceneRunFastSome` / `planSceneRunFastNone` | итог прогона |
| около N минут | `planStateMinutes` | единственная цифра на экранах плана |

## Все ключи `plan*` (и дев-ключи наряда)

Колонка «где стоит» — по вхождениям `l.<ключ>` в `mobile/lib/`; «не используется» значит, что ключ
жив только в ARB (уведомления по коду, запас) — кандидат на удаление своим нарядом.

| Ключ | Русская подпись | Где стоит |
|---|---|---|
| `planSectionSceneRun` | Прогон сцены | посадка (шапка, швы, присест) |
| `planSceneRunHint` | скажи свою реплику — текста не будет | карточка в посадке |
| `planSceneRunNext` | Сцена сказана голосом. Что не прозвучало — вернётся своим чередом. | диалог сцены / итог сцены |
| `planSceneRunSaidSelf` | Прошёл сам | диалог сцены / итог сцены |
| `planDialogueSayIt` | Сказать вслух | диалог сцены / итог сцены |
| `planDialogueNotHeard` | Не расслышали — попробуйте ещё раз. | диалог сцены / итог сцены |
| `planSceneRunSaidFast` | Сразу | диалог сцены / итог сцены |
| `planSittingContinue` | Продолжить | общие подписи плана; посадка (шапка, швы, присест) |
| `planSittingStop` | Хватит на сегодня | посадка (шапка, швы, присест) |
| `planBuilderTitle` | Составить план | — (не используется в lib/) |
| `planBuilderSubtitle` | Три ответа — и ИИ соберёт дни подготовки из фраз, которые ты реально скажешь. | — (не используется в lib/) |
| `planStepGoalQuestion` | К чему готовишься? | — (не используется в lib/) |
| `planStepGoalClosed` | Цель | — (не используется в lib/) |
| `planStepLanguageQuestion` | Язык и уровень | — (не используется в lib/) |
| `planStepLanguageClosed` | Язык и уровень | — (не используется в lib/) |
| `planStepWhenQuestion` | Когда это случится? | — (не используется в lib/) |
| `planStepWhenClosed` | Когда и сколько | — (не используется в lib/) |
| `planStepEdit` | Изм. | вход в план (V4) |
| `planGoalPlaceholder` | Например, приём у врача | — (не используется в lib/) |
| `planGoalChipInterview` | Собеседование | — (не используется в lib/) |
| `planGoalChipDoctor` | Приём у врача | — (не используется в lib/) |
| `planGoalChipRent` | Аренда квартиры | — (не используется в lib/) |
| `planGoalChipTrip` | Поездка | — (не используется в lib/) |
| `planGoalChipVet` | Ветклиника | — (не используется в lib/) |
| `planGoalChipSchool` | Школа ребёнка | — (не используется в lib/) |
| `planLevelLabel` | Как сейчас говоришь | — (не используется в lib/) |
| `planLevelZero` | С нуля | вход в план (V4) |
| `planLevelBasic` | Понимаю простое | вход в план (V4) |
| `planLevelConversational` | Объясняюсь | вход в план (V4) |
| `planLevelFluent` | Свободно | вход в план (V4) |
| `planWhenToday` | Сегодня | — (не используется в lib/) |
| `planWhenTomorrow` | Завтра | — (не используется в lib/) |
| `planWhenSheetTitle` | Когда это случится? | вход в план (V4); превью плана |
| `planMinutesLabel` | Минут в день | — (не используется в lib/) |
| `planMinutesPerDay` | {minutes} мин/день | — (не используется в lib/) |
| `planBuilderSubmit` | Собрать план | — (не используется в lib/) |
| `planBuilderWorking` | Собираю… | «Как прошло?» |
| `planBuilderBusyLine` | Разбираю цель — обычно 15–30 секунд | превью плана |
| `planBuilderHintToday` | Событие сегодня — соберём короткую подготовку на один заход. | — (не используется в lib/) |
| `planBuilderHintDays` | {days, plural, one{Ориентир: {days} день до события} few{Ориентир: {days} дня до события} many{Ориентир: {days} дней до события} other{Ориентир: {days} дня до события}} · {minutes} мин в день | — (не используется в lib/) |
| `planErrorOffline` | Нет соединения. План собирается на сервере — попробуй, когда появится сеть. | экран дня; «Как прошло?»; превью плана; финал плана; быстрая репетиция; вкладка «План» (D·07); вкладка «План» (пусто/архив) |
| `planErrorBuildFailed` | Не получилось собрать план. Попробуй ещё раз. | «Как прошло?»; превью плана |
| `planErrorStartFailed` | Не получилось начать план. Попробуй ещё раз. | превью плана |
| `planErrorLoadFailed` | Не удалось загрузить план. | экран дня; быстрая репетиция; вкладка «План» (D·07); вкладка «План» (пусто/архив) |
| `planPreviewBadge` | Превью плана | — (не используется в lib/) |
| `planPrepDays` | {days, plural, one{Подготовка {days} день + прогон} few{Подготовка {days} дня + прогон} many{Подготовка {days} дней + прогон} other{Подготовка {days} дня + прогон}} | — (не используется в lib/) |
| `planEventOn` | Событие {date}. | — (не используется в lib/) |
| `planApproxTerms` | ~{count} фраз и слов. | — (не используется в lib/) |
| `planDayNumber` | День {index} | превью плана |
| `planDayNoNewWords` | без новых слов | — (не используется в lib/) |
| `planWordsCount` | {count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}} | — (не используется в lib/) |
| `planPhrasesCount` | {count, plural, one{{count} фраза} few{{count} фразы} many{{count} фраз} other{{count} фразы}} | — (не используется в lib/) |
| `planChunksCount` | {count, plural, one{{count} связка} few{{count} связки} many{{count} связок} other{{count} связки}} | — (не используется в lib/) |
| `planDaysCount` | {count, plural, one{{count} день} few{{count} дня} many{{count} дней} other{{count} дня}} | вкладка «План» (D·07); вкладка «План» (пусто/архив) |
| `planPreviewDropDay` | Убрать день | превью плана |
| `planPreviewRebuild` | Перестроить | — (не используется в lib/) |
| `planPreviewStart` | Начать | — (не используется в lib/) |
| `planPricePlaceholder` | [цена / условия — placeholder] | превью плана |
| `planDropDaySheet` | Какой день убрать? | превью плана |
| `planDropLastDay` | Последний день подготовки убрать нельзя. | превью плана |
| `planTightTitle` | {days, plural, one{За {days} день по {minutes} минут закроем половину: вот эти умения.} few{За {days} дня по {minutes} минут закроем половину: вот эти умения.} many{За {days} дней по {minutes} минут закроем половину: вот эти умения.} other{За {days} дня по {minutes} минут закроем половину: вот эти умения.}} | превью плана |
| `planTightAddMinutes` | Добавить {minutes} минут в день | превью плана |
| `planTightKeep` | Оставить | превью плана |
| `planBuildingTitle` | Собираю день {index} | сборка дня |
| `planBuildingBody` | Подбираю реплики события и слова, которые в них подставляются. | сборка дня |
| `planBuildingStep1` | Цель разобрана | сборка дня |
| `planBuildingStep2` | Реплики подобраны | сборка дня |
| `planBuildingStep3` | Слова и примеры | сборка дня |
| `planBuildingFailed` | День собирается дольше обычного. План уже создан — его можно открыть и вернуться к дню позже. | сборка дня |
| `planBuildingOpenAnyway` | Открыть план | сборка дня |
| `planActiveBadge` | Активный план | — (не используется в lib/) |
| `planReadinessCaption` | готовность к событию | — (не используется в lib/) |
| `planEventInDays` | {days, plural, one{Событие через {days} день} few{Событие через {days} дня} many{Событие через {days} дней} other{Событие через {days} дня}} | — (не используется в lib/) |
| `planEventToday` | Событие сегодня | вкладка «План» (D·07) |
| `planEventPassed` | Событие прошло | вкладка «План» (D·07) |
| `planDayOfTotal` | День {index} из {total} | — (не используется в lib/) |
| `planCanAlready` | Ты уже можешь · {hit} из {total} | — (не используется в lib/) |
| `planTrainThis` | Потренировать | — (не используется в lib/) |
| `planDaysHeader` | Дни · {index} из {total} | — (не используется в lib/) |
| `planContinueDay` | Продолжить день {index} | — (не используется в lib/) |
| `planDayPassed` | День {index} пройден | — (не используется в lib/) |
| `planDayFinalHint` | Без новых слов · можно открыть раньше | — (не используется в lib/) |
| `planDayBuilding` | Собирается | — (не используется в lib/) |
| `planSpeakerRoleShort` | он | экран дня |
| `planSpeakerRole` | Собеседник: | посадка (шапка, швы, присест) |
| `planDayQueued` | В очереди | — (не используется в lib/) |
| `planDayNotBuilt` | Не собрался | — (не используется в lib/) |
| `planDayOpenFailed` | Открыть день {index} | — (не используется в lib/) |
| `planDayOpenEarly` | можно открыть раньше | — (не используется в lib/) |
| `planDayOfPlan` | День {index} из {total} | экран дня |
| `planDayCanDo` | Ты сможешь | — (не используется в lib/) |
| `planDayPhrases` | Фразы дня | экран дня |
| `planDayWords` | Слова в этих фразах | — (не используется в lib/) |
| `planDaySoftNote` | Это день впереди текущего. Тренировка настоящая: реплики этого дня встречаешь впервые, ответы засчитываются. | экран дня |
| `planDayTrain` | Тренировать | — (не используется в lib/) |
| `planFromDay` | · со дня {index} | — (не используется в lib/) |
| `planConversationLabel` | Разговор | — (не используется в lib/) |
| `planConversationDefaultRole` | Собеседник | быстрая репетиция |
| `planConversationLocked` | Откроется в следующем обновлении: разговор в роли и зачёт чек-пойнтов. | — (не используется в lib/) |
| `planConversationSoon` | Разговор в роли | — (не используется в lib/) |
| `planDayNotWritten` | Этот день ещё не собран. План пишет по одному дню — можно попросить собрать его сейчас. | экран дня |
| `planDayFailed` | День не собрался с первого раза. Можно попробовать ещё раз. | экран дня |
| `planDayExhausted` | Этот день не собрался дважды подряд — сервер больше не будет пытаться. Такое случается, когда модель возвращает материал не на том языке. План придётся собрать заново. | — (не используется в lib/) |
| `planDayExhaustedLead` | Этот день не собрался дважды подряд — сервер больше не будет пытаться. План придётся собрать заново. | экран дня |
| `planFailWhy` | Что пошло не так: {reason} | экран дня |
| `planFailExampleIsATerm` | пример к карточке повторял другую карточку этого дня, а не показывал слово в предложении | причина несобравшегося дня |
| `planFailExampleDuplicated` | два примера оказались одним предложением с подменённым словом | причина несобравшегося дня |
| `planFailExampleWithoutTranslation` | к примеру не приехал перевод, и читать его было бы нечем | причина несобравшегося дня |
| `planFailNotTargetLanguage` | материал вернулся не на том языке | причина несобравшегося дня |
| `planFailKeyIsTheTerm` | перевод карточки повторял саму карточку — теми же буквами или другими | причина несобравшегося дня |
| `planFailTermIsAName` | именем собственным нельзя занимать карточку — его не переводят | причина несобравшегося дня |
| `planFailSlotOutsideFrame` | пропуск для подстановки оказался не в той строке | причина несобравшегося дня |
| `planFailGapMissing` | в реплике не оказалось пропуска, в который встаёт карточка | причина несобравшегося дня |
| `planFailTranslationHasGap` | в переводе остался пропуск — читать такую подсказку нечем | причина несобравшегося дня |
| `planFailTranslationMissingKey` | в переводе реплики не нашлось самого слова, которому она учит | причина несобравшегося дня |
| `planFailFillerNotCard` | в пропуск встало не то, чему учит карточка | причина несобравшегося дня |
| `planFailWordIsBasic` | карточкой стало слово из самого начального минимума | причина несобравшегося дня |
| `planFailKindSize` | карточка вышла за длину, отведённую её виду | причина несобравшегося дня |
| `planFailSkillRefInvalid` | карточка не назвала умение сцены, ради которого она здесь | причина несобравшегося дня |
| `planFailNumberValueMismatch` | число в реплике не сошлось с числом, по которому карточку проверяют | причина несобравшегося дня |
| `planFailShelfMissing` | в сцене не оказалось целой полки — того, что тебе скажут, что ты ответишь или из чего это собрано | причина несобравшегося дня |
| `planFailTermRepeated` | карточка повторяла другую — этого дня, спасательного набора или прошлого дня | причина несобравшегося дня |
| `planFailUnknown` | не удалось собрать день | причина несобравшегося дня |
| `planAbandonLink` | Отказаться от плана | вкладка «План» (D·07) |
| `planDayRebuildDay` | Собрать заново | экран дня; вкладка «План» (D·07) |
| `planDayRebuildPlan` | Собрать план заново | экран дня |
| `planAbandonTitle` | Отказаться от этого плана? | вкладка «План» (пусто/архив) |
| `planAbandonBody` | План уйдёт в архив, а его слова — в общее повторение. Собранные дни останутся обычными коллекциями. | вкладка «План» (пусто/архив) |
| `planAbandonConfirm` | Отказаться | вкладка «План» (пусто/архив) |
| `planBuildingRefused` | Сервер отказался собирать этот день. Ждать дальше нечего — открой план: на экране дня написано, что именно случилось. | сборка дня |
| `planDayBuildNow` | Собрать день | экран дня |
| `planDayDone` | День {index} пройден | итог дня |
| `planDaySittingDone` | Занятие пройдено | итог дня |
| `planDayNotClosed` | День {index} ещё не закрыт | — (не используется в lib/) |
| `planDayNotClosedNote` | Часть карточек ответена неверно, и они остались недоученными. Открой день ещё раз: он раздаст только то, что осталось. | итог дня |
| `planDaySoftDone` | День {index} повторён | итог дня |
| `planReviewRow` | Повторение | итог дня |
| `planReviewCount` | {count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}} | итог дня |
| `planReviewSection` | Повторение · из прошлых дней | посадка (шапка, швы, присест) |
| `planWarmupSection` | Разогрев | посадка (шапка, швы, присест) |
| `planShelfHear` | Тебе скажут | экран дня; посадка (шапка, швы, присест) |
| `planShelfSay` | Ты ответишь | экран дня; посадка (шапка, швы, присест) |
| `planShelfAsk` | Ты спросишь | экран дня; посадка (шапка, швы, присест) |
| `planShelfWords` | Слова и связки | посадка (шапка, швы, присест) |
| `planSectionDialogueIntro` | Знакомство с репликами | посадка (шапка, швы, присест) |
| `planSectionDialogue` | Диалог сцены | диалог сцены / итог сцены; посадка (шапка, швы, присест) |
| `planSectionNumbers` | Цифры на слух | экран дня; посадка (шапка, швы, присест) |
| `planSectionRehearsal` | Прогон сцены | посадка (шапка, швы, присест) |
| `planSectionOfScene` | {section} · сцена {index} | посадка (шапка, швы, присест) |
| `planWarmupWhy` | чтобы было чем ответить, если растеряешься | посадка (шапка, швы, присест) |
| `planDialogueScene` | Сцена {index} | экран дня; диалог сцены / итог сцены |
| `planDialogueLabel` | Диалог | диалог сцены / итог сцены |
| `planDialogueLead` | Собеседник говорит — вы отвечаете. Отвечать будете выбором из фраз плана, включая выученные в прошлых сценах. | диалог сцены / итог сцены |
| `planDialogueRescueAtHand` | Спасатели под рукой | диалог сцены / итог сцены |
| `planDialogueRescueLead` | Кнопка остаётся на экране весь диалог: можно попросить повторить или помедленнее. | диалог сцены / итог сцены |
| `planDialogueStart` | Начать диалог | диалог сцены / итог сцены |
| `planDialogueSound` | со звуком · наденьте наушники | диалог сцены / итог сцены |
| `planDialogueRoleSpeaks` | говорит собеседник · текст скрыт | диалог сцены / итог сцены |
| `planDialogueReplay` | Ещё раз | диалог сцены / итог сцены |
| `planDialogueShowText` | Показать текст | диалог сцены / итог сцены |
| `planDialogueHideText` | Скрыть текст | диалог сцены / итог сцены |
| `planDialogueUnderstoodMark` | понял | — (не используется в lib/) |
| `planDialogueSaidAloud` | сказано вслух | диалог сцены / итог сцены |
| `planDialogueFamiliar` | знакомая реплика · разбор не нужен | диалог сцены / итог сцены |
| `planDialogueVoicePreparing` | Готовим озвучку | диалог сцены / итог сцены |
| `planDialogueVoicePreparingBody` | Реплика прозвучит, когда голос будет готов. Диалог начнётся с неё — тишины не будет. | диалог сцены / итог сцены |
| `planDialogueRescue` | Спасатели | экран дня; диалог сцены / итог сцены |
| `planDialogueRescuePhrases` | {count, plural, one{{count} фраза} few{{count} фразы} other{{count} фраз}} | диалог сцены / итог сцены |
| `planDialogueRescueNote` | Это нормальный ход разговора, а не ошибка: носители просят повторить так же часто. | диалог сцены / итог сцены |
| `planDialogueRescueBack` | Вернуться к диалогу | диалог сцены / итог сцены |
| `planDialogueDone` | диалог пройден | диалог сцены / итог сцены |
| `planDialogueDoneLead` | Разговор целиком — ваши ответы стоят в ленте. | диалог сцены / итог сцены |
| `planDialogueResult` | Итог по сцене | диалог сцены / итог сцены |
| `planDialogueAnsweredSelf` | Отвечал сам | диалог сцены / итог сцены |
| `planDialogueHeardOut` | Разобрал реплику на слух | диалог сцены / итог сцены |
| `planDialogueAskedRepeat` | Просил повторить | диалог сцены / итог сцены |
| `planDialogueTimes` | {count, plural, one{{count} раз} few{{count} раза} other{{count} раз}} | диалог сцены / итог сцены |
| `planDialogueNextAssembly` | Дальше — сборка: те же обмены, но ответ собираете из связок сцены сами. | диалог сцены / итог сцены |
| `planDialogueBackToSession` | Вернуться к сессии | диалог сцены / итог сцены |
| `planDialogueAskHeard` | Что тебе сейчас сказали? | посадка (шапка, швы, присест) |
| `planDialogueAskSay` | Что ты ответишь? | посадка (шапка, швы, присест) |
| `planDialogueAskAsk` | Что ты спросишь? | посадка (шапка, швы, присест) |
| `planDialogueYourAnswer` | Ваш ответ | диалог сцены / итог сцены |
| `planDialogueYourQuestion` | Ваш вопрос | диалог сцены / итог сцены |
| `planDialogueListenHow` | Послушать, как это звучит | диалог сцены / итог сцены |
| `planDialogueSayAloudNote` | Закрепляем произношение. Мы не оцениваем и не сравниваем — скажите и идём дальше. | диалог сцены / итог сцены |
| `planDialogueSaidIt` | Сказал вслух | диалог сцены / итог сцены |
| `planListTitle` | План подготовки | вкладка «План» (D·07) |
| `planRowPassed` | пройден | — (не используется в lib/) |
| `planRowBuilding` | собирается | вкладка «План» (D·07) |
| `planRowNotBuilt` | не собрался | вкладка «План» (D·07) |
| `planRowWaiting` | ждёт очереди | вкладка «План» (D·07) |
| `planRowStartDay` | Начать день | общие подписи плана |
| `planRowNotBuiltWhy` | Сорвалась сборка реплик. Займёт около минуты. | вкладка «План» (D·07) |
| `planRowRehearsalWhen` | Накануне | вкладка «План» (D·07) |
| `planRowRehearsalLead` | Все сцены подряд, вслух | вкладка «План» (D·07) |
| `planMaturityMeeting` | Знакомишься с материалом | общие подписи плана |
| `planMaturityApplying` | Применяешь в разговоре | общие подписи плана |
| `planMaturitySpeaking` | Говоришь сам | общие подписи плана |
| `planEventAt` | событие {date} | вкладка «План» (D·07) |
| `planDaysLeft` | {days, plural, one{осталось {days} день} few{осталось {days} дня} many{осталось {days} дней} other{осталось {days} дня}} | вкладка «План» (D·07) |
| `planRowDayScene` | День {day} · сцена {scene} | вкладка «План» (D·07) |
| `planDayRescueLead` | С них начинается день — чтобы было чем ответить, если растеряешься. | — (не используется в lib/) |
| `planDayRoleOnlyUnderstand` | говорит собеседник · только понимать | — (не используется в lib/) |
| `planSceneNamed` | Сцена: {title} | итог дня |
| `planDayAlmost` | День {index} · почти | итог дня |
| `planDayAlmostLead` | {count, plural, one{Осталось дотренировать {count} карточку} few{Осталось дотренировать {count} карточки} other{Осталось дотренировать {count} карточек}} | итог дня |
| `planDayGotIt` | Далось | итог дня |
| `planDayMissed` | Не далось | итог дня |
| `planDayMissedNote` | Непослушные карточки вернутся в разогреве. | итог дня |
| `planDayTrainMore` | Дотренировать | итог дня |
| `planDayLeaveForTomorrow` | Оставить на завтра | итог дня |
| `planDayLeaveNote` | Если оставить — они придут в разогреве следующего дня, а этот останется открытым. | итог дня |
| `planLadderLegend` | Зрелость материала | итог дня |
| `planLadderNoReadiness` | Готовность к сцене появится, когда сервер её посчитает. Пока — то, докуда дошёл материал. | итог дня |
| `planLadderA` | познакомился | итог дня |
| `planLadderB` | применяешь | итог дня |
| `planLadderC` | говоришь сам | итог дня |
| `planNext` | Дальше | итог дня |
| `planNextDay` | День {index} — {title} | итог дня |
| `planDayBackToPlan` | К плану | итог дня |
| `planSessionBadge` | План · день {index} | посадка (шапка, швы, присест) |
| `planSessionCarried` | Слово «{term}» идёт со дня {index} — сегодня оно возвращается. | посадка (шапка, швы, присест) |
| `planEmptyTitle` | Подготовиться к чему-то конкретному | вкладка «План» (пусто/архив) |
| `planEmptyBody` | Коллекции — про темы, которые хочется знать. План — про день, когда придётся говорить: приём, собеседование, подпись договора. | вкладка «План» (пусто/архив) |
| `planEmptyStep1` | Говоришь цель и дату | вкладка «План» (пусто/архив) |
| `planEmptyStep2` | Каждый день — фразы, которые реально скажешь, и слова из них | вкладка «План» (пусто/архив) |
| `planEmptyStep3` | В конце — разговор в роли и прогон всей ситуации | вкладка «План» (пусто/архив) |
| `planEmptyCta` | Составить план | вкладка «План» (пусто/архив) |
| `planFinishedBadge` | Подготовка завершена | финал плана; вкладка «План» (пусто/архив) |
| `planFinishedSummary` | {days, plural, one{За {days} день подготовки. Событие было {date}.} few{За {days} дня подготовки. Событие было {date}.} many{За {days} дней подготовки. Событие было {date}.} other{За {days} дня подготовки. Событие было {date}.}} | вкладка «План» (пусто/архив) |
| `planWordsReleasedTitle` | Слова плана остались в архиве | вкладка «План» (пусто/архив) |
| `planWordsReleasedBody` | Они никуда не делись — прогресс, расписание и вся история на месте. Сами в ежедневные занятия они не придут: чтобы вернуть слово в работу, открой его карточку и нажми «Учить это слово». | вкладка «План» (пусто/архив) |
| `planRehearsalDoneTitle` | Подготовка завершена | финал плана |
| `planRehearsalDoneBody` | Ты прошёл весь материал плана. Он ушёл в архив: слова, прогресс и вся история сохранены. | финал плана |
| `planRehearsalDoneAction` | К плану | финал плана |
| `planCompleteAction` | Завершить план | экран дня |
| `planCompleteTitle` | Завершить план? | — (не используется в lib/) |
| `planCompleteBody` | План закроется, слова уйдут в архив. Вернуть их в «Учить» можно будет вручную. | — (не используется в lib/) |
| `planCompleteConfirm` | Завершить | — (не используется в lib/) |
| `planRehearsalScenes` | Сцены плана | экран дня |
| `planRehearsalSceneUntrained` | не тренировали | экран дня |
| `planRehearsalScenePassed` | пройдена | экран дня |
| `planRehearsalSceneReady` | в работе | экран дня |
| `planRehearsalUntrainedNote` | Войдёт в прогон как есть — реплики с подсказкой. | экран дня |
| `planRehearsalAloudNote` | Вслух, без остановок. Спасатели под рукой. | экран дня |
| `planRehearsalNoPercent` | Готовность по сценам появится, когда сервер её посчитает. Пока — статус каждой сцены. | экран дня |
| `planDoneScenes` | Сцены пройдены | финал плана |
| `planDoneStageA` | Познакомились с материалом | финал плана |
| `planDoneArchiveNote` | Слова и реплики плана останутся в архиве — открыть можно с его карточки. Автоматических повторений не будет: план закончился вместе с событием. | финал плана |
| `planRehearsalStart` | Пройти прогон | экран дня |
| `planRehearsalLead` | Финальный день ничего не добавляет — это прогон всего, чему план научил. Пройди его перед событием. | экран дня |
| `planArchive` | Архив | вкладка «План» (пусто/архив) |
| `planFinishedNewPlan` | Составить новый | вкладка «План» (пусто/архив) |
| `planNotifyChannelName` | План подготовки | lib/features/plan/plan_notification_host.dart |
| `planNotifyChannelBody` | Напоминания перед событием, к которому идёт подготовка | lib/features/plan/plan_notification_host.dart |
| `planNotifyBeforeTitle` | {days, plural, one{До события {days} день. День {index} ждёт} few{До события {days} дня. День {index} ждёт} many{До события {days} дней. День {index} ждёт} other{До события {days} дня. День {index} ждёт}} | lib/features/plan/plan_notification_host.dart |
| `planNotifyBeforeBody` | Готовность {percent}%. Сегодня — {title}. | lib/features/plan/plan_notification_host.dart |
| `planNotifyMorningTitle` | {count, plural, one{Сегодня событие. {count} фраза за 3 минуты} few{Сегодня событие. {count} фразы за 3 минуты} many{Сегодня событие. {count} фраз за 3 минуты} other{Сегодня событие. {count} фразы за 3 минуты}} | lib/features/plan/plan_notification_host.dart |
| `planNotifyMorningBody` | Быстрая репетиция перед выходом — только то, что скажешь. | lib/features/plan/plan_notification_host.dart |
| `planNotifyEveningTitle` | Как прошло? Отметь, что сказал | lib/features/plan/plan_notification_host.dart |
| `planNotifyEveningBody` | Отметь умения, которые пригодились — план закроется этим. | lib/features/plan/plan_notification_host.dart |
| `planRehearsalBadge` | Репетиция · событие сегодня | быстрая репетиция |
| `planRehearsalSayIt` | Скажи вслух | быстрая репетиция |
| `planRehearsalHint` | Скажи фразу — или пролистай дальше, если она уже звучит сама. | быстрая репетиция |
| `planRehearsalListening` | Слушаю… | быстрая репетиция |
| `planRehearsalPlay` | Прочитать пример | быстрая репетиция |
| `planRehearsalCue` | {role} скажет: «{cue}» | быстрая репетиция |
| `planRehearsalOpen` | Быстрая репетиция | вкладка «План» (D·07) |
| `planRehearsalDone` | Готово | быстрая репетиция |
| `planRehearsalEmpty` | В этом плане пока нет фраз для репетиции. | быстрая репетиция |
| `planFeedbackTitle` | Как прошло? | «Как прошло?»; вкладка «План» (D·07) |
| `planFeedbackBody` | {count, plural, one{Отметь умение, которое пригодилось на событии.} few{Отметь умения, которые пригодились на событии — из {count}.} many{Отметь умения, которые пригодились на событии — из {count}.} other{Отметь умения, которые пригодились на событии — из {count}.}} | «Как прошло?» |
| `planFeedbackSubmit` | Сохранить и завершить | «Как прошло?» |
| `planFeedbackClosesPlan` | План завершится, а его слова уйдут в общее повторение. | «Как прошло?» |
| `planFinishedAtEvent` | На событии сказал {used} из {total}. | вкладка «План» (пусто/архив) |
| `planNoDate` | Без даты | вход в план (V4); карточка плана на главной; общие подписи плана |
| `planEntryKicker` | План подготовки | вход в план (V4) |
| `planEntryGoalTitle` | К чему готовишься? | вход в план (V4) |
| `planEntryGoalSubtitleLong` | Расскажи своими словами: где будешь, с кем, что нужно сказать и понять. Подробности — это те самые фразы, которые пригодятся. | вход в план (V4) |
| `planEntryGoalSubtitle` | Расскажи своими словами: где будешь, с кем, что нужно сказать и понять. | вход в план (V4) |
| `planEntryGoalPlaceholder` | Например: иду к врачу с ребёнком, надо объяснить симптомы и понять назначение | вход в план (V4) |
| `planEntryLinesHint` | можно 4–6 строк | вход в план (V4) |
| `planEntryEnough` | хватит для плана | вход в план (V4) |
| `planEntryDetailed` | подробно — это хорошо | вход в план (V4) |
| `planEntryTooShort` | Пары слов мало. Добавь: к какому врачу, с кем идёшь, что нужно понять. | вход в план (V4) |
| `planEntryExamplesTitle` | Так тоже подходит | вход в план (V4) |
| `planEntryAdditionsTitle` | Можно добавить | вход в план (V4) |
| `planEntryFinishTitle` | Дописать за тебя | вход в план (V4) |
| `planEntryExample1` | Иду к врачу, болит спина, надо объяснить и понять назначение | вход в план (V4) |
| `planEntryExample2` | Онлайн-собеседование PHP-разработчика, удалённо, английская команда | вход в план (V4) |
| `planEntryExample3` | Летим в отпуск с ребёнком, аэропорт, отель, аптека | вход в план (V4) |
| `planEntryNext` | Дальше | вход в план (V4) |
| `planEntryDictate` | Продиктовать | вход в план (V4) |
| `planEntryLangTitle` | Какой язык учишь? | вход в план (V4) |
| `planEntryTranslationsInto` | Переводы на {language} | вход в план (V4) |
| `planEntrySettingsLink` | изменить в настройках | вход в план (V4) |
| `planEntryLevelTitle` | Как сейчас говоришь? | вход в план (V4) |
| `planLevelZeroHint` | Знаю отдельные слова, фразу не соберу | вход в план (V4) |
| `planLevelBasicHint` | Читаю переписку, но говорю с паузами | вход в план (V4) |
| `planLevelConversationalHint` | Договорюсь о бытовом, сложное — подбираю слова | вход в план (V4) |
| `planLevelFluentHint` | Говорю без подготовки, шлифую точность | вход в план (V4) |
| `planListenKicker` | Необязательный шаг | вход в план (V4) |
| `planListenOfferTitle` | Хочешь, настрою точнее? | вход в план (V4) |
| `planListenOfferBody` | Послушай три реплики из твоей ситуации — как они прозвучат на самом деле. Минута. | вход в план (V4) |
| `planListenListen` | Послушать | вход в план (V4) |
| `planListenSkip` | Пропустить | вход в план (V4) |
| `planListenReassure` | Это не тест. Ответы никто не увидит, план соберётся и без этого шага. | вход в план (V4) |
| `planListenEnough` | Хватит | вход в план (V4) |
| `planListenLine` | Реплика {index} | вход в план (V4) |
| `planListenLineAt` | Реплика {index} · {place} | вход в план (V4) |
| `planListenReplayHint` | Слушай столько раз, сколько нужно. | вход в план (V4) |
| `planListenShowText` | Показать текст | вход в план (V4) |
| `planListenFeelLabel` | Как ощущается | вход в план (V4) |
| `planListenGot` | Понял | вход в план (V4) |
| `planListenNotQuite` | Не совсем | вход в план (V4) |
| `planListenNoRightAnswer` | Правильного ответа нет — это про то, что подобрать в план. | вход в план (V4) |
| `planListenResultUnderstanding` | Понял: сделаю упор на понимание на слух | вход в план (V4) |
| `planListenResultUnderstandingBody` | Речь идёт быстрее, чем удобно. В плане будет больше прослушивания и меньше зубрёжки слов. | вход в план (V4) |
| `planListenResultSpeaking` | Понимаешь на слух уверенно: сделаю упор на говорение | вход в план (V4) |
| `planListenResultSpeakingBody` | Реплики тебе даются — в плане будет больше твоих ответов вслух и меньше зубрёжки слов. | вход в план (V4) |
| `planListenResultFootnote` | Настройку можно поменять в плане в любой день. | вход в план (V4) |
| `planEntryRibbonListenUnderstanding` | Слух · упор на понимание | вход в план (V4) |
| `planEntryRibbonListenSpeaking` | Слух · упор на говорение | вход в план (V4) |
| `planEntryRibbonListenSkipped` | Слух · шаг пропущен | вход в план (V4) |
| `planEntryPass` | Пройти | вход в план (V4) |
| `planEntryRibbonLangLevel` | {language} · {level} | вход в план (V4) |
| `planEntryWhenTitle` | Когда это случится? | вход в план (V4) |
| `planEntryPickDate` | Выбрать дату | вход в план (V4) |
| `planEntryHintDays` | {days, plural, one{Через {days} день · сервер разложит подготовку по этим дням} few{Через {days} дня · сервер разложит подготовку по этим дням} many{Через {days} дней · сервер разложит подготовку по этим дням} other{Через {days} дня · сервер разложит подготовку по этим дням}} | вход в план (V4) |
| `planEntryHintToday` | Сегодня · вся подготовка уместится в один подход | вход в план (V4) |
| `planEntryHintNoDate` | Даты нет · идём в своём темпе, по одной сцене за подход | вход в план (V4); вкладка «План» (D·07) |
| `planEntryMinutesTitle` | Сколько минут в день? | вход в план (V4) |
| `planEntryMinutesUnit` | минут | вход в план (V4) |
| `planEntryBuild` | Собрать план | вход в план (V4) |
| `planBuildKicker` | Собираю твой план | вход в план (V4) |
| `planBuildStep1` | Разбираю цель | вход в план (V4) |
| `planBuildStep2` | Подбираю реплики | вход в план (V4) |
| `planBuildStep3` | Собираю слова | вход в план (V4) |
| `planBuildFootnote` | Реплики берём из живой речи, не из учебника. Это занимает несколько секунд. | вход в план (V4) |
| `planBuildRetryKicker` | Сборка идёт | вход в план (V4) |
| `planBuildRetryBody` | Не получилось собрать с первого раза — пробую ещё. | вход в план (V4) |
| `planBuildRetryFootnote` | Ответы на месте. Вторая попытка идёт с того же шага. | вход в план (V4) |
| `planBuildOfflineKicker` | Сборка приостановлена | вход в план (V4) |
| `planBuildOfflineTitle` | Пропала связь на середине | вход в план (V4) |
| `planBuildOfflineBody` | Ответы сохранены — ничего вводить заново не придётся. Продолжим, как только сеть вернётся. | вход в план (V4) |
| `planBuildRetryButton` | Попробовать снова | вход в план (V4) |
| `planBuildNotifyButton` | Сообщить, когда будет готов | вход в план (V4) |
| `planBuildNotifyUnavailable` | Уведомления ещё не подключены | вход в план (V4) |
| `planBuildFailedKicker` | Не собралось | вход в план (V4) |
| `planBuildFailedTitle` | Не собралось. Твои ответы сохранены | вход в план (V4) |
| `planBuildFailedBody` | Ничего вводить заново не придётся. Можно вернуться к ответам и запустить сборку снова — или написать нам. | вход в план (V4) |
| `planBuildBackToAnswers` | Вернуться к ответам | вход в план (V4) |
| `planBuildWriteUs` | Написать нам | вход в план (V4) |
| `planBuildMailSubject` | Не собрался план | вход в план (V4) |
| `planPreviewKicker` | Твой план готов | превью плана |
| `planPreviewSubtitle` | По твоим словам — {scenes, plural, one{{scenes} сцена} few{{scenes} сцены} many{{scenes} сцен} other{{scenes} сцены}}: {topics}. | превью плана |
| `planPreviewSubtitleNoDate` | По твоим словам — {scenes, plural, one{{scenes} сцена} few{{scenes} сцены} many{{scenes} сцен} other{{scenes} сцены}}: {topics}. Даты нет, идём в своём темпе. | превью плана |
| `planPreviewOrientation` | {days, plural, one{{days} ДЕНЬ ПОДГОТОВКИ} few{{days} ДНЯ ПОДГОТОВКИ} many{{days} ДНЕЙ ПОДГОТОВКИ} other{{days} ДНЯ ПОДГОТОВКИ}} · {minutes} МИНУТ В ДЕНЬ | превью плана |
| `planPreviewOrientationNoDate` | {scenes, plural, one{{scenes} СЦЕНА} few{{scenes} СЦЕНЫ} many{{scenes} СЦЕН} other{{scenes} СЦЕНЫ}} · {minutes} МИНУТ В ДЕНЬ · ПО ОДНОЙ ЗА ПОДХОД | превью плана |
| `planPreviewDaysTitle` | Твои дни | превью плана |
| `planPreviewScenesTitle` | Твои сцены | превью плана |
| `planPreviewDayLabel` | ДЕНЬ {index} | превью плана |
| `planPreviewSceneLabel` | СЦЕНА {index} | превью плана |
| `planPreviewRescueBody` | Пять фраз-спасателей — с первого дня, на случай если растерялся. | превью плана |
| `planPreviewRescueQuote` | «Помедленнее, пожалуйста» — и ещё четыре таких, с первого дня. | превью плана |
| `planPreviewRehearsalEveLabel` | НАКАНУНЕ | превью плана |
| `planPreviewRehearsalEndLabel` | В КОНЦЕ | превью плана |
| `planPreviewRehearsalTitle` | Прогон перед событием | превью плана |
| `planPreviewRehearsalEveBody` | {scenes, plural, one{Одна сцена} few{Все {scenes} сцены} many{Все {scenes} сцен} other{Все {scenes} сцены}} подряд, вслух, за один присест. | превью плана |
| `planPreviewRehearsalEndBody` | Откроется, когда пройдёшь все сцены. Дату можно поставить в любой день. | превью плана |
| `planPreviewStartDay` | Начать первый день | превью плана |
| `planPreviewStartScene` | Начать первую сцену | превью плана |
| `planPreviewEditAnswers` | Изменить ответы | превью плана |
| `planPreviewSetDate` | Поставить дату | превью плана |
| `planStateNotStarted` | не начат | общие подписи плана |
| `planStateInProgress` | идёт | общие подписи плана |
| `planStateDone` | пройден | вкладка «План» (D·07); общие подписи плана |
| `planStateMaterialDone` | материал пройден | общие подписи плана |
| `planStateMaterialAbout` | {minutes, plural, one{материал около {minutes} минуты} few{материал около {minutes} минут} many{материал около {minutes} минут} other{материал около {minutes} минуты}} | общие подписи плана (экран дня) |
| `planStateConversationAbout` | {minutes, plural, one{разговор около {minutes} минуты} few{разговор около {minutes} минут} many{разговор около {minutes} минут} other{разговор около {minutes} минуты}} | общие подписи плана (вкладка «План», экран дня) |
| `planSittingToConversation` | К разговору | общие подписи плана; посадка (итог материала) |
| `planSittingLater` | Позже | посадка (итог материала) |
| `planMaterialDoneTitle` | Материал пройден | посадка (итог материала) |
| `planMaterialDoneLead` | Слова, связки и реплики сцены разобраны. Дальше — разговор: услышишь собеседника и ответишь сам. | посадка (итог материала) |
| `planStateMinutes` | {minutes, plural, one{около {minutes} минуты} few{около {minutes} минут} many{около {minutes} минут} other{около {minutes} минуты}} | общие подписи плана |
| `planDayRepeat` | Пройти ещё раз | общие подписи плана |
| `planShelfWordsOnly` | Слова | экран дня |
| `planShelfChunks` | Связки | экран дня |
| `planDayStepLead` | сегодня — {step} | экран дня |
| `planStepMeet` | познакомишься | экран дня |
| `planStepTranslate` | переведёшь | экран дня |
| `planStepTiles` | соберёшь из плиток | экран дня |
| `planStepRecognize` | узнаешь по переводу | экран дня |
| `planStepHear` | услышишь и разберёшь на слух | экран дня |
| `planStepChoose` | выберешь ответ | экран дня |
| `planStepAssemble` | соберёшь из блоков | экран дня |
| `planStepSay` | скажешь голосом | экран дня |
| `planMarkMet` | познакомился | экран дня |
| `planMarkApplying` | применяешь | экран дня |
| `planMarkSaidSelf` | говоришь сам | экран дня |
| `planTermTopical` | по теме | экран дня |
| `planDialogueRoleName` | собеседник | диалог сцены / итог сцены |
| `planDialogueSceneWord` | Сцена | посадка (шапка, швы, присест) |
| `planDialogueTail` | Ещё в этой сцене | посадка (шапка, швы, присест) |
| `planDialogueAnsweredAll` | на все | диалог сцены / итог сцены |
| `planDialogueAnsweredHinted` | {count, plural, one{{count} раз подсказали} few{{count} раза подсказали} other{{count} раз подсказали}} | диалог сцены / итог сцены |
| `planDialogueHeardAll` | все реплики | диалог сцены / итог сцены |
| `planDialogueHeardHinted` | {count, plural, one{одну подсказали} few{{count} подсказали} other{{count} подсказали}} | диалог сцены / итог сцены |
| `planSceneRunSaidAll` | всю сцену | диалог сцены / итог сцены |
| `planSceneRunSaidSome` | {count, plural, one{кроме одной реплики} few{кроме {count} реплик} other{кроме {count} реплик}} | диалог сцены / итог сцены |
| `planSceneRunFastAll` | все | диалог сцены / итог сцены |
| `planSceneRunFastSome` | не все | диалог сцены / итог сцены |
| `planSceneRunFastNone` | пока нет | диалог сцены / итог сцены |
| `planSittingRunNext` | Дальше — прогон сцены | посадка (шапка, швы, присест) |
| `planSittingRunLead` | Скажешь реплики сцены голосом — текста на экране не будет. | посадка (шапка, швы, присест) |
| `planDoneScenesAll` | все | финал плана |
| `planDoneScenesSome` | не все | финал плана |
| `planDoneMaterialAll` | со всем | финал плана |
| `planDoneMaterialSome` | не со всем | финал плана |
| `planSummaryMetToday` | сегодняшняя сцена | итог дня |
| `planSummaryAppliedAll` | весь материал плана | итог дня |
| `planSummaryAppliedSome` | часть материала плана | итог дня |
| `planHomeDayState` | День {index} · {state} | карточка плана на главной |
| `devQaClockTitle` | QA · «сегодня» плана | профиль → Разработка |
| `devQaClockShift` | {days, plural, =0{без сдвига} one{сдвиг: {days} день} few{сдвиг: {days} дня} other{сдвиг: {days} дней}} | профиль → Разработка |
| `devQaClockPlus` | +1 день | профиль → Разработка |
| `devQaClockReset` | Сбросить | профиль → Разработка |

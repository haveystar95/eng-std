# Словарь подписей плана — «ключ ARB → русская подпись → где стоит»

Наряд DAY-FIX-2, Ч.6. **Источник правды — `mobile/lib/l10n/app_ru.arb`**; этот файл — его
витрина для плановых экранов. Правило: каждая строка на экранах плана берётся из ARB, а каждый
ключ ARB с префиксом `plan*` стоит в этой таблице. Гард: `mobile/test/l10n/plan_glossary_test.dart`
— падает, если ключ есть в ARB и отсутствует здесь. Обновлять таблицу: `python3 glossary.py`
(скрипт наряда) или вручную той же строкой.

## Обязательные слова

> **DAY-FIX-2, Ч.6.2 ОТМЕНЕНА нарядом DAY-GATE-1 (доработка окна 2)** в части четырёх слов. Она
> объявляла обязательными «Спасатели», «Прогон сцены» и «Материал пройден»; владелец решил, что это
> имена механики, а не продукта, и назвал замены. Отмена записана здесь, а не молча в коммите:
> правило, отменённое молча, возвращается следующим нарядом.
>
> | было (внутреннее) | стало (на экране) |
> |---|---|
> | Материал | **Слова и фразы** («Слова и фразы пройдены · разговор около N минут») |
> | Прогон сцены | **Скажи сам** |
> | Разогрев | **Из прошлых дней** |
> | Спасатели | **На всякий случай** + подпись `planRescueHint` |
>
> **Уточнение (доработка, п. 3): «Из прошлых дней» — это ТОЛЬКО реплики прошлых дней.** Секция
> `warmup` держит две разные вещи, и подпись теперь читается по КАРТОЧКАМ, а не по коду секции:
> набор (полка `rescue`) зовётся «На всякий случай», промахи прошлых дней — «Из прошлых дней». На
> дне 1 второй половины не бывает, и секции там нет по построению. Замок:
> `mobile/test/features/plan/plan_session_seam_test.dart`.
>
> Старые слова остаются ВНУТРЕННИМИ: `section_code` сервера (`warmup`, `rehearsal`), имена классов
> и docstring'и их не меняют — это язык кода, и переименовывать его вслед за экраном значит терять
> связь с сервером. Гард на экраны: `mobile/test/l10n/no_internal_words_in_plan_test.dart`.

| Слово | Ключ | Где стоит |
|---|---|---|
| если не понял или не расслышал | `planRescueHint` | подпись под «На всякий случай» |
| не начат | `planStateNotStarted` | вкладка «План», экран дня, шапка присеста, главная |
| идёт | `planStateInProgress` | там же; рядом — минуты (`planStateMinutes`) |
| слова и фразы пройдены | `planStateMaterialDone` | там же (day_state = material_done); рядом — `planStateConversationAbout` |
| пройден | `planStateDone` | там же |
| слова и фразы около N минут · разговор около N минут | `planStateMaterialAbout` · `planStateConversationAbout` | экран дня под названием сцены (оба присеста); вкладка «План» при «материал пройден» |
| К разговору | `planSittingToConversation` | вкладка «План», экран дня (material_done), итог материала между присестами |
| Начать день | `planRowStartDay` | вкладка «План», экран дня (day_state = not_started) |
| Продолжить | `planSittingContinue` | вкладка «План», экран дня (in_progress), экран перед прогоном |
| Пройти ещё раз | `planDayRepeat` | вкладка «План», экран дня (done) |
| Пропустить | `sessionSkip` | карточка в посадке |
| около N минут | `planStateMinutes` | единственная цифра на экранах плана |

## Все ключи `plan*` (и дев-ключи наряда)

Колонка «где стоит» — по вхождениям `l.<ключ>` в `mobile/lib/`; «не используется» значит, что ключ
жив только в ARB (уведомления по коду, запас) — кандидат на удаление своим нарядом.

| Ключ | Русская подпись | Где стоит |
|---|---|---|
| `planSittingContinue` | Продолжить | общие подписи плана; посадка (шапка, швы, присест) |
| `planStepEdit` | Изм. | вход в план (V4) |
| `planLevelZero` | С нуля | вход в план (V4) |
| `planLevelBasic` | Понимаю простое | вход в план (V4) |
| `planLevelConversational` | Объясняюсь | вход в план (V4) |
| `planLevelFluent` | Свободно | вход в план (V4) |
| `planWhenSheetTitle` | Когда это случится? | вход в план (V4); превью плана |
| `planBuilderBusyLine` | Разбираю цель — обычно 15–30 секунд | превью плана |
| `planErrorOffline` | Нет соединения. План собирается на сервере — попробуй, когда появится сеть. | экран дня; «Как прошло?»; превью плана; финал плана; быстрая репетиция; вкладка «План» (D·07); вкладка «План» (пусто/архив) |
| `planErrorBuildFailed` | Не получилось собрать план. Попробуй ещё раз. | «Как прошло?»; превью плана |
| `planErrorStartFailed` | Не получилось начать план. Попробуй ещё раз. | превью плана |
| `planErrorLoadFailed` | Не удалось загрузить план. | экран дня; быстрая репетиция; вкладка «План» (D·07); вкладка «План» (пусто/архив) |
| `planDayNumber` | День {index} | превью плана |
| `planDaysCount` | {count, plural, one{{count} день} few{{count} дня} many{{count} дней} other{{count} дня}} | вкладка «План» (D·07); вкладка «План» (пусто/архив) |
| `planPreviewDropDay` | Убрать день | превью плана |
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
| `planDayOfTotal` | День {index} из {total} | — (не используется в lib/) |
| `planCanAlready` | Ты уже можешь · {hit} из {total} | — (не используется в lib/) |
| `planDaysHeader` | Дни · {index} из {total} | — (не используется в lib/) |
| `planDayOfPlan` | День {index} из {total} | экран дня |
| `planAbandonTitle` | Отказаться от этого плана? | вкладка «План» (пусто/архив) |
| `planAbandonBody` | План уйдёт в архив, а его слова — в общее повторение. Собранные дни останутся обычными коллекциями. | вкладка «План» (пусто/архив) |
| `planAbandonConfirm` | Отказаться | вкладка «План» (пусто/архив) |
| `planBuildingRefused` | Сервер отказался собирать этот день. Ждать дальше нечего — открой план: на экране дня написано, что именно случилось. | сборка дня |
| `planRowStartDay` | Начать день | общие подписи плана |
| `planMaturityMeeting` | Знакомишься со словами и фразами | общие подписи плана |
| `planMaturityApplying` | Применяешь в разговоре | общие подписи плана |
| `planMaturitySpeaking` | Говоришь сам | общие подписи плана |
| `planEmptyTitle` | Подготовиться к чему-то конкретному | вкладка «План» (пусто/архив) |
| `planEmptyBody` | Коллекции — про темы, которые хочется знать. План — про день, когда придётся говорить: приём, собеседование, подпись договора. | вкладка «План» (пусто/архив) |
| `planEmptyStep1` | Говоришь цель и дату | вкладка «План» (пусто/архив) |
| `planEmptyStep2` | Каждый день — фразы, которые реально скажешь, и слова из них | вкладка «План» (пусто/архив) |
| `planEmptyStep3` | В конце — разговор в роли и вся ситуация вслух | вкладка «План» (пусто/архив) |
| `planEmptyCta` | Составить план | вкладка «План» (пусто/архив) |
| `planFinishedBadge` | Подготовка завершена | финал плана; вкладка «План» (пусто/архив) |
| `planFinishedSummary` | {days, plural, one{За {days} день подготовки. Событие было {date}.} few{За {days} дня подготовки. Событие было {date}.} many{За {days} дней подготовки. Событие было {date}.} other{За {days} дня подготовки. Событие было {date}.}} | вкладка «План» (пусто/архив) |
| `planWordsReleasedTitle` | Слова плана остались в архиве | вкладка «План» (пусто/архив) |
| `planWordsReleasedBody` | Они никуда не делись — прогресс, расписание и вся история на месте. Сами в ежедневные занятия они не придут: чтобы вернуть слово в работу, открой его карточку и нажми «Учить это слово». | вкладка «План» (пусто/архив) |
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
| `planPreviewRescueBody` | Пять фраз на всякий случай — с первого дня, если не понял или не расслышал. | превью плана |
| `planPreviewRescueQuote` | «Помедленнее, пожалуйста» — и ещё четыре таких, с первого дня. | превью плана |
| `planPreviewRehearsalEveLabel` | НАКАНУНЕ | превью плана |
| `planPreviewRehearsalEndLabel` | В КОНЦЕ | превью плана |
| `planPreviewRehearsalTitle` | Скажи сам перед событием | превью плана |
| `planPreviewRehearsalEveBody` | {scenes, plural, one{Одна сцена} few{Все {scenes} сцены} many{Все {scenes} сцен} other{Все {scenes} сцены}} подряд, вслух, за один присест. | превью плана |
| `planPreviewRehearsalEndBody` | Откроется, когда пройдёшь все сцены. Дату можно поставить в любой день. | превью плана |
| `planPreviewStartDay` | Начать первый день | превью плана |
| `planPreviewStartScene` | Начать первую сцену | превью плана |
| `planPreviewEditAnswers` | Изменить ответы | превью плана |
| `planPreviewSetDate` | Поставить дату | превью плана |
| `planStateNotStarted` | не начат | общие подписи плана |
| `planStateInProgress` | идёт | общие подписи плана |
| `planStateDone` | пройден | вкладка «План» (D·07); общие подписи плана |
| `planStateMaterialDone` | слова и фразы пройдены | общие подписи плана |
| `planStateMaterialAbout` | {minutes, plural, one{слова и фразы около {minutes} минуты} few{слова и фразы около {minutes} минут} many{слова и фразы около {minutes} минут} other{слова и фразы около {minutes} минуты}} | общие подписи плана (экран дня) |
| `planStateConversationAbout` | {minutes, plural, one{разговор около {minutes} минуты} few{разговор около {minutes} минут} many{разговор около {minutes} минут} other{разговор около {minutes} минуты}} | общие подписи плана (вкладка «План», экран дня) |
| `planSittingToConversation` | К разговору | общие подписи плана; посадка (итог материала) |
| `planStateMinutes` | {minutes, plural, one{около {minutes} минуты} few{около {minutes} минут} many{около {minutes} минут} other{около {minutes} минуты}} | общие подписи плана |
| `planDayRepeat` | Пройти ещё раз | общие подписи плана |
| `planHomeDayState` | День {index} · {state} | карточка плана на главной |
| `devQaClockTitle` | QA · «сегодня» плана | профиль → Разработка |
| `devQaClockShift` | {days, plural, =0{без сдвига} one{сдвиг: {days} день} few{сдвиг: {days} дня} other{сдвиг: {days} дней}} | профиль → Разработка |
| `devQaClockPlus` | +1 день | профиль → Разработка |
| `devQaClockReset` | Сбросить | профиль → Разработка |

## Этапы дня и замок — наряд DAY-GATE-1, Ч.2

День состоит из ЭТАПОВ, и все они приходят кодами с сервера (`PlanDay.stages`); подписи ниже
клиентские, как у всех кодов плана. Замок дня — поле `locked_by_day_index`, и выводить его
самостоятельно нельзя. Чисел «N из M» в этих строках нет намеренно.

| Ключ | Русская подпись | Где стоит |
|---|---|---|
| `planRescueHint` | если не понял или не расслышал | экран дня (секция «На всякий случай»), панель в диалоге |

## День плана — наряд DAY-UI (11.09.2026)

Строки дня живут под префиксом `day*` (таблица `day.*` в канве «План», раздел «План · день»);
гард словаря их не проверяет — источник правды по-прежнему `mobile/lib/l10n/app_ru.arb`. Задания
карточек («Что он спросил», вопросы, переводы реплик) приходят с сервера и в ARB не дублируются.
Экраны прежних серий (день v1, диалог v1, прогон, «Как прошло?») удалены вместе с их ключами —
270 ключей `plan*`; список — `backend2/docs/research/day-ui/README.md`, §1.

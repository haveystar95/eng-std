# Словарь подписей плана — «ключ ARB → русская подпись → где стоит»

Наряд PLAN-UI (2026-09-11). **Источник правды — `mobile/lib/l10n/app_ru.arb`**; этот файл — его
витрина для экранов плана: таба «План» (кадры 21-x) и входа в план (22-x). Тексты перенесены из
таблицы «Тексты плана · основа для переводов» канваса «План» (`backend2/docs/design/plan.dc.html`):
ключ таблицы `plan.empty.title` → ключ ARB `planEmptyTitle`, `entry.goal.title` → `planEntryGoalTitle`.

Правило: каждая строка на экранах плана берётся из ARB, каждый ключ ARB с префиксом `plan*` стоит
здесь, и каждый такой ключ читается из кода — гард `mobile/test/l10n/plan_glossary_test.dart`
падает на ключе без строки в словаре и на строке без экрана. Словарь 4к-4 (ступень, такт, промпт,
модель, генерация, умение, чек-пойнт, готовность, A/B, ИИ) стережёт `no_internal_words_in_plan_test.dart`.

Обновлять таблицу после правки ARB:

```bash
python3 docs/plan-ui-glossary.py
```

Строки, которых нет в таблице канваса и которые добавил наряд PLAN-UI (каждая названа в описании
ключа и в отчёте наряда): `planKitCollapse`, `planMenuLabel`, `planDateTitleNoEvent`, `planDateRemove`,
`planDeleteBodyNoCollection`, `planTabOffline`, `planTabLoadFailedTitle`, `planTabRetry`, `planDayStub*`,
`planRouteDayRepeatSubOne`, `planEntryGoalTemplateRent/Interview/Trip`, `planEntryGoalDictate*`,
`planEntryTapeLanguageValue`, `planEntryTapeDaysValueDated`, `planEntryOffline`.

| Ключ | Подпись | Где стоит |
|---|---|---|
| `planTitle` | План | Шапка таба «План» (plan.title). |
| `planEmptyTitle` | Разговор, к которому готовишься | Заголовок витрины, Literata 30 — обещание результата, а не вопрос (кадр 21-1, plan.empty.title). |
| `planEmptySub` | сцена в день · 20 минут · репетиция вслух | Подпись витрины в ОДНУ строку (кадр 21-1, plan.empty.sub). |
| `planRuleSituation` | Каждый день — одна ситуация. Дни открываются по одному | Первое правило плана — витрина 21-1 и лист 21-8 (plan.rule.situation). |
| `planRuleStages` | Пять этапов по порядку: слова → фразы → диалог → слушаю и отвечаю → говорю сам | Второе правило плана; в листе 21-8 под ним стоит ряд пяти значков этапов (plan.rule.stages). |
| `planRuleReturn` | То, что не получилось, вернётся в следующий день. Ничего не потеряется | Третье правило плана — витрина 21-1 и лист 21-8 (plan.rule.return). |
| `planExampleTitle` | Пример · приём у врача | Метка карточки-примера на витрине (кадр 21-1, plan.example.title). |
| `planExampleDay1` | Запись к врачу | Первый узел карточки-примера (кадр 21-1, plan.example.day1). |
| `planExampleDay1Sub` | спросить время приёма и страховку | Описание первого узла примера (кадр 21-1, plan.example.day1.sub). |
| `planExampleDay2` | Приём у врача | Второй узел карточки-примера (кадр 21-1, plan.example.day2). |
| `planExampleDay2Sub` | описать боль, понять назначения | Описание второго узла примера (кадр 21-1, plan.example.day2.sub). |
| `planExampleDay3` | Аптека | Третий узел карточки-примера (кадр 21-1, plan.example.day3). |
| `planExampleDay3Sub` | понять дозировку, спросить аналог | Описание третьего узла примера (кадр 21-1, plan.example.day3.sub). |
| `planEmptyCta` | Собрать план | Кадр 21-1, кнопка (plan.empty.cta). |
| `planFinishedTitle` | Завершённые планы | Кадры 21-1, 21-2b (plan.finished.title). |
| `planFinishedItemDate` | завершён {date} | Кадры 21-1, 21-2b; дата по локали (plan.finished.item.date). |
| `planHeaderBrow` | План · день {n} из {total} | Бровь шапки плана, 11/700 caps (кадр 21-2, plan.header.brow). |
| `planPlateLabel` | День {n} | Плита дня, лейбл латунью (plan.plate.label). |
| `planCardsCount` | {n, plural, one{{n} карточка} few{{n} карточки} many{{n} карточек} other{{n} карточки}} | Счётные формы карточек: 1 карточка / 2 карточки / 5 карточек (plan.plate.meta, plan.closed.meta, plan.closed.return). |
| `planMinutesCount` | {n, plural, one{{n} минута} few{{n} минуты} many{{n} минут} other{{n} минуты}} | Счётные формы минут: 1 минута / 2 минуты / 5 минут (plan.plate.meta, plan.closed.meta). |
| `planPlateStageWords` | Слова | Плита дня, этап (plan.plate.stage.words). |
| `planPlateStagePhrases` | Фразы | Плита дня, этап (plan.plate.stage.phrases). |
| `planPlateStageDialog` | Диалог | Плита дня, этап (plan.plate.stage.dialog). |
| `planPlateStageListen` | Слушаю и отвечаю | Плита дня, этап (plan.plate.stage.listen). |
| `planPlateStageSpeak` | Говорю сам | Плита дня, этап (plan.plate.stage.speak). |
| `planPlateStageCount` | {done} / {total} | Плита дня, счётчик этапа (plan.plate.stage.count). |
| `planNewWordsCount` | {n, plural, one{{n} новое слово} few{{n} новых слова} many{{n} новых слов} other{{n} новых слова}} | Формы «1 новое слово / 2 новых слова / 5 новых слов» (plan.plate.stage.sub.start). |
| `planPlateStageSubStart` | начни отсюда · {words} | Плита дня, вторая строка текущего этапа (plan.plate.stage.sub.start); words — planNewWordsCount. |
| `planPlateStageSubUnfinished` | не закончен · {cards} | Плита дня, вторая строка брошенного этапа (plan.plate.stage.sub.unfinished); cards — planCardsCount. Оценки минут в контракте нет — часть «≈ N мин» не рисуется, как и вся строка plan.plate.meta «{n} карточек · ≈ {min} минут» (вопрос архитектору). |
| `planPlateCtaStart` | Начать | Плита дня, кнопка (plan.plate.cta.start). |
| `planPlateBuildingTitle` | Собираем день {n} | Строка вместо этапов, пока день пишется (кадр 22-5a, plan.plate.building.title). |
| `planPlateBuildingSub` | около минуты · можно закрыть приложение | Подпись под «Собираем день N»: срок назван, уходить разрешено (кадр 22-5a, plan.plate.building.sub). |
| `planPlateFailedTitle` | День не собрался | Строка вместо этапов, когда день не собрался (кадр 22-5c, plan.plate.failed.title). |
| `planPlateFailedSub` | Сеть пропала. Маршрут на месте, пропал только день {n} | Подпись под «День не собрался» (кадр 22-5c, plan.plate.failed.sub). |
| `planPlateCtaRetry` | Повторить | Кнопка плиты у несобравшегося дня (кадр 22-5c, plan.plate.cta.retry). |
| `planPlateCtaContinue` | Продолжить | Плита дня, кнопка (plan.plate.cta.continue). |
| `planClosedTitle` | День {n} закрыт | Кадр 21-4 (plan.closed.title). |
| `planClosedCount` | {cards} · {minutes} | Счёт закрытого дня в шапке плиты: «75 карточек · 19 минут» (кадр 21-4, plan.closed.count). |
| `planClosedNextTomorrow` | День {n} откроется завтра, {date} | Первая строка подвала закрытого дня (кадр 21-4, plan.closed.next.tomorrow). |
| `planClosedNextOn` | День {n} откроется {date} | Подвал закрытого дня, когда следующий день не «завтра» (кадр 21-4, plan.closed.next.on). |
| `planClosedReturn` | {k, plural, one{{k} карточка вернётся в день {n} →} few{{k} карточки вернутся в день {n} →} many{{k} карточек вернутся в день {n} →} other{{k} карточки вернутся в день {n} →}} | Вторая строка подвала закрытого дня, терракотой (кадр 21-4, plan.closed.return). |
| `planRouteDayRepeat` | День повторения | Строка маршрута, tertiary (plan.route.day.repeat). |
| `planRouteDayRepeatSub` | слова и фразы дней {a}–{b} | Строка маршрута (plan.route.day.repeat.sub). |
| `planRouteDayRepeatSubOne` | слова и фразы дня {a} | Строка маршрута, когда повторению предшествует один день ситуации (день 1 → повторение день 2). |
| `planRouteDayRehearsal` | Репетиция | Строка маршрута, tertiary (plan.route.day.rehearsal). |
| `planRouteDayRehearsalSub` | весь маршрут вслух | Строка маршрута (plan.route.day.rehearsal.sub). |
| `planRouteMetaDay` | День {n} | Мета-строка узла маршрута, первое слово — номер дня (кадр 21-2b, plan.route.meta.day). |
| `planRouteMetaPassed` | пройден | Мета-строка пройденного дня маршрута (кадр 21-2b, plan.route.meta.passed). |
| `planMinutesShort` | {n} мин | Минуты в мета-строке маршрута, сокращённо (кадр 21-2b, plan.route.meta.minutes). |
| `planRouteMetaOpensAfter` | откроется после дня {n} | Мета-строка ПЕРВОГО запертого дня маршрута (кадр 21-2b, plan.route.meta.opens.after). |
| `planRouteMetaOpensTomorrow` | откроется завтра | Мета-строка первого запертого дня, когда предыдущий уже пройден (кадр 21-4, plan.route.meta.opens.tomorrow). |
| `planRouteEventNoDate` | указать дату | Мишень события без даты — пунктирный узел маршрута (кадр 21-2b, plan.route.event.nodate). |
| `planRouteEventFallback` | Событие | Заголовок мишени, когда сервер не назвал событие (кадр 21-2b, plan.route.event.fallback). |
| `planDoneTitle` | План пройден | Кадр 21-7 (plan.done.title). |
| `planDaysCount` | {n, plural, one{{n} день} few{{n} дня} many{{n} дней} other{{n} дня}} | Счётные формы дней: 1 день / 2 дня / 5 дней (plan.done.meta, plan.overdue.meta, entry.preview.sub). |
| `planDoneMeta` | {days} | Кадр 21-7 (plan.done.meta): «7 дней». Части «фраз и слов в работе» в контракте нет — не рисуются (вопрос архитектору). |
| `planDoneCollection` | Слова и фразы плана остались в коллекции «{name}» — они будут приходить на повторение | Кадр 21-7 (plan.done.collection); name — сервер. |
| `planDoneCta` | Собрать новый план | Кадр 21-7 (plan.done.cta). |
| `planDoneCtaReadonly` | Открыть коллекцию | Кадр 21-7, режим чтения (plan.done.cta.readonly). |
| `planMenuDate` | Изменить дату | Кадр 21-9 (plan.menu.date). |
| `planMenuNew` | Собрать новый план | Кадр 21-9 (plan.menu.new). |
| `planMenuCollection` | Открыть коллекцию | Кадр 21-9 (plan.menu.collection). |
| `planMenuDelete` | Удалить план | Кадр 21-9, терракота (plan.menu.delete). |
| `planMenuLabel` | Меню плана | Подпись кнопки-меню в шапке таба для читалки экрана; кадра нет. |
| `planDateTitle` | Когда {event}? | Кадр 21-10 (plan.date.title). По таблице строку склоняет сервер; в контракте её нет — собрана из event_native (вопрос архитектору). |
| `planDateTitleNoEvent` | Когда событие? | Кадр 21-10 у плана без даты и без названного события. |
| `planDateOptionCurrent` | {date} · как сейчас | Кадр 21-10 (plan.date.option.current); дата по локали. |
| `planDateOptionOther` | Другая дата | Кадры 21-10, 22-3b (plan.date.option.other, entry.date.option.other). |
| `planDateOptionOtherSub` | выбрать в календаре | Кадры 21-10, 22-3b (plan.date.option.other.sub, entry.date.option.other.sub). |
| `planDateCta` | Применить | Кадр 21-10 (plan.date.cta). |
| `planDateCancel` | Отменить | Кадры 21-10, 21-12 (plan.date.cancel). |
| `planDateRemove` | Без даты | Кадр 21-10, план с датой: снять дату (event_date: null). Строки в таблице нет — добавлена нарядом PLAN-UI по контракту PATCH /schedule. |
| `planNewTitle` | Начать другой план? | Кадр 21-11 (plan.new.title). |
| `planNewBody` | Этот план завершится на дне {n} из {total}. Всё, что уже в работе, останется в коллекции «{name}» и будет приходить на повторение | Кадр 21-11 (plan.new.body); name — сервер. |
| `planNewCta` | Собрать новый | Кадр 21-11 (plan.new.cta). |
| `planNewKeep` | Оставить этот | Кадр 21-11 (plan.new.keep). |
| `planDeleteTitle` | Удалить план? | Кадр 21-12 (plan.delete.title). |
| `planDeleteBody` | План исчезнет из истории. Коллекция «{name}» и её слова останутся | Кадр 21-12 (plan.delete.body); name — сервер. |
| `planDeleteBodyNoCollection` | План исчезнет из истории | Кадр 21-12 у плана, у которого коллекции ещё нет (ни один день не закрыт). |
| `planDeleteConfirm` | Удалить | Кадр 21-12, терракота (plan.delete.confirm). |
| `planOverdueMeta` | Пройдено {days} из {total} | Кадр 21-14 (plan.overdue.meta): «Пройдено 4 дня из 7»; days — planDaysCount. Части «фраз и слов в работе» в контракте нет. |
| `planOverdueFinish` | Завершить план | Кадр 21-14 (plan.overdue.finish). |
| `planOverdueContinue` | Дозаниматься · {days} | Второй выход прошедшего события — вернуться к текущему дню (кадр 21-14, plan.overdue.continue). |
| `planHintFirstStart` | Начни с этапа «Слова». Остальные откроются по порядку | Кадр 21-2c (plan.hint.first.start). |
| `planHintFirstRoute` | День {n} открыт. Следующий откроется, когда пройдёшь этот | Подсказка первого плана под заголовком маршрута, один раз (кадр 21-2c, plan.hint.first.route). |
| `planRebuiltTitle` | Маршрут пересобран: было {from} дней, стало {to} | Плашка пересборки над плитой — состояние плана, не сообщение (кадр 21-13, plan.rebuilt.title). |
| `planHintFirstReturn` | Эти карточки придут в следующий день ещё раз — так они и запоминаются | Кадр 21-4c (plan.hint.first.return). |
| `planSheetTitle` | Как устроен план | Кадр 21-8 (plan.sheet.title). |
| `planSheetCta` | Понятно | Кадр 21-8 (plan.sheet.cta). |
| `planTabOffline` | нет сети | Тихая строка на табе, когда показано последнее известное состояние из кэша (наряд PLAN-UI, §6). |
| `planTabLoadFailedTitle` | Не получилось загрузить план | Таб без кэша, когда сервер не ответил (§6: состояние с «Повторить»). Строки в таблице нет — добавлена нарядом PLAN-UI. |
| `planTabRetry` | Повторить | Кнопка «Повторить» на табе и на плите (entry.day.failed.retry). |
| `planEntryNext` | Далее | Шапка входа, справа, 17/700 (entry.next). |
| `planEntryGoalTitle` | К чему готовишься? | Вопрос шага цели, Literata 26 (кадр 22-1, entry.goal.title). |
| `planEntryGoalSub` | Расскажи ситуацию своими словами: что будет, с кем говоришь, чего боишься | Подпись под вопросом цели (кадр 22-1, entry.goal.sub). |
| `planEntryGoalTyping1` | Собеседование в пятницу, боюсь… | Печатающийся плейсхолдер поля цели, кадр 1 — примеры НЕ те, что в списке историй (кадр 22-1, entry.goal.typing.1). |
| `planEntryGoalTyping2` | Звоню в банк, не понимаю по телефону… | Печатающийся плейсхолдер поля цели, кадр 2 (кадр 22-1, entry.goal.typing.2). |
| `planEntryGoalTyping3` | Иду к врачу… | Печатающийся плейсхолдер поля цели, кадр 3 (кадр 22-1, entry.goal.typing.3). |
| `planEntryGoalStoriesTitle` | Так пишут другие | Метка списка историй под полем цели (кадр 22-1, entry.goal.stories.title). |
| `planEntryGoalStory1` | Собеседование в пятницу, боюсь вопросов про опыт | История «так пишут другие» — тап подставляет её в поле (кадр 22-1, entry.goal.story.1). |
| `planEntryGoalStory2` | К врачу с ребёнком, первый раз в местной клинике | История «так пишут другие» (кадр 22-1, entry.goal.story.2). |
| `planEntryGoalStory3` | Звонок арендодателю про залог | История «так пишут другие» (кадр 22-1, entry.goal.story.3). |
| `planEntryGoalShortHint` | Добавь, с кем и что важно — план будет точнее | Подсказка под коротким ответом; НЕ блокирует «Далее» (кадр 22-1c, entry.goal.short.hint). |
| `planEntryGoalDictate` | или надиктуй | Подпись у микрофона в покое (кадр 22-1, entry.goal.dictate). |
| `planEntryGoalDictateStop` | тап — остановить | Подпись у микрофона во время записи (кадр 22-1, entry.goal.dictate.stop). |
| `planEntryGoalDictateEdit` | можно поправить руками | Подпись у микрофона после распознавания (кадр 22-1, entry.goal.dictate.edit). |
| `planEntryGoalListening` | {time} · говори, я слушаю | Таймер и приглашение над волной записи (кадр 22-1, entry.goal.listening). |
| `planEntryGoalRecognising` | распознаю… | Состояние микрофона между записью и текстом в поле (кадр 22-1, entry.goal.recognising). |
| `planEntryTapeGoal` | Цель | Лента ответов (entry.tape.goal). |
| `planEntryTapeLanguage` | Язык | Лента ответов (entry.tape.language). |
| `planEntryTapeDays` | Дни | Лента ответов (entry.tape.days). |
| `planEntryTapeLanguageValue` | {language} · {level} | Лента ответов: «Английский · Средний». |
| `planEntryLanguageTitle` | На каком языке говорить? | Вопрос шага языка (кадр 22-2, entry.language.title). |
| `planEntryLanguageLabel` | Язык | Метка зоны языков (кадр 22-2, entry.language.label). |
| `planEntryLevelLabel` | Уровень | Кадр 22-2 (entry.level.label). |
| `planEntryLevelBeginner` | Начальный | Уровень — название (кадр 22-2, entry.level.beginner). |
| `planEntryLevelBeginnerSub` | знаю отдельные слова | Уровень описан тем, что человек умеет (кадр 22-2, entry.level.beginner.sub). |
| `planEntryLevelIntermediate` | Средний | Кадр 22-2 (entry.level.intermediate). |
| `planEntryLevelIntermediateSub` | понимаю простую речь, говорю с ошибками | Уровень описан тем, что человек умеет (кадр 22-2, entry.level.intermediate.sub). |
| `planEntryDaysTitle` | Сколько дней до разговора? | Вопрос шага длины плана (кадр 22-3a, entry.days.title). |
| `planEntryDaysScenes` | {n, plural, one{{n} ситуация} few{{n} ситуации} many{{n} ситуаций} other{{n} ситуации}} | Состав длины плана — ситуации (кадр 22-3a, entry.days.scenes). |
| `planEntryDaysReviews` | {n, plural, one{{n} повторение} few{{n} повторения} many{{n} повторений} other{{n} повторения}} | Состав длины плана — дни повторения (кадр 22-3a, entry.days.reviews). |
| `planEntryDaysRehearsal` | репетиция | Состав длины плана — репетиция, она есть всегда (кадр 22-3a, entry.days.rehearsal). |
| `planEntryDateTitle` | Когда разговор? | Вопрос шага даты (кадр 22-3b, entry.date.title). |
| `planEntryDateLabel` | Дата | Метка зоны выбора даты (кадр 22-3b, entry.date.label). |
| `planEntryDateIn` | {weekday} · {n, plural, one{через {n} день} few{через {n} дня} many{через {n} дней} other{через {n} дня}} | Подпись ближней даты: день недели и сколько до неё (кадр 22-3b, entry.date.in). |
| `planEntryDateUnknown` | Дата пока неизвестна | Равноправный вариант выбора даты (кадр 22-3b, entry.date.unknown). |
| `planEntryDateUnknownSub` | план без даты, дни идут подряд | Подпись варианта без даты (кадр 22-3b, entry.date.unknown.sub). |
| `planEntryDateOther` | Другая дата | Третий вариант выбора даты (кадр 22-3b, entry.date.other). |
| `planEntryDateOtherSub` | выбрать в календаре | Подпись варианта «Другая дата» (кадр 22-3b, entry.date.other.sub). |
| `planEntryDateRehearsalOn` | Репетиция встанет на {date} — день перед разговором | Строка следствия под выбором даты (кадр 22-3b, entry.date.rehearsal.on). |
| `planEntryDateCta` | Собрать план | Кнопка шага даты — называет результат, а не «Готово» (кадр 22-3b, entry.date.cta). |
| `planEntryPreviewTitle` | Твой план готов | Заголовок готового превью (кадр 22-4b, entry.preview.title). |
| `planEntryPreviewLoadingTitle` | Собираю план | Заголовок превью во время сборки (кадр 22-4a, entry.preview.loading.title). |
| `planEntryPreviewAbout` | Около 10 секунд | Срок сборки человеческими словами, без процента (кадр 22-4a, entry.preview.about). |
| `planEntryPreviewLoadingSub` | Подбираю ситуации под твой разговор и расставляю их по дням | Что именно происходит во время сборки (кадр 22-4a, entry.preview.loading.sub). |
| `planEntryPreviewCta` | Начать | Кадр 22-4 (entry.preview.cta). |
| `planEntryPreviewErrorTitle` | План не собрался | Заголовок неудачной сборки (кадр 22-4c, entry.preview.error.title). |
| `planEntryPreviewErrorWhat` | Сеть пропала на середине | Что случилось при неудачной сборке (кадр 22-4c, entry.preview.error.what). |
| `planEntryPreviewErrorSub` | Ответы сохранены — попробуй ещё раз, заново рассказывать не придётся | Что уцелело при неудачной сборке (кадр 22-4c, entry.preview.error.sub). |
| `planEntryPreviewErrorRetry` | Попробовать ещё | Кнопка неудачной сборки; повтор не уводит на первый шаг (кадр 22-4c, entry.preview.error.retry). |
| `planEntryPreviewUnclearTitle` | Нужно чуть больше | Заголовок, когда цель непонятна — просьба, не упрёк (кадр 22-4d, entry.preview.unclear.title). |
| `planEntryPreviewUnclearQuote` | «{goal}» — это про что? | Цитата ответа человека в кадре «цель непонятна» (кадр 22-4d, entry.preview.unclear.quote). |
| `planEntryPreviewUnclearSub` | Напиши, где будешь говорить и с кем: приём у врача, звонок в банк, разговор с соседом | Три примера того, чего не хватает (кадр 22-4d, entry.preview.unclear.sub). |
| `planEntryPreviewUnclearCta` | К цели | Кнопка возврата к полю цели с сохранённым текстом (кадр 22-4d, entry.preview.unclear.cta). |
| `planEntryPushTitle` | План готов | Кадр 22-6 (entry.push.title). |
| `planEntryPushBody` | {until}. День 1 — «{dayTitle}» | Тело уведомления «План готов»: срок до события и первый день (кадр 22-6, entry.push.body). |
| `planEntryPushBodyNoDate` | {days}. День 1 — «{dayTitle}» | Тело уведомления у плана без даты события (кадр 22-6, entry.push.body.nodate). |
| `planEntryOffline` | Без сети план не собрать | Вход офлайн (§6): нельзя начать сборку. Строки в таблице нет — добавлена нарядом PLAN-UI. |

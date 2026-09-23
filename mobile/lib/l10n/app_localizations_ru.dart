// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Russian (`ru`).
class AppLocalizationsRu extends AppLocalizations {
  AppLocalizationsRu([String locale = 'ru']) : super(locale);

  @override
  String triageCounter(int current, int total) {
    return '$current из $total';
  }

  @override
  String get triageSwipeHint => 'Свайпай или жми кнопки · тап — перевернуть';

  @override
  String get triageVerdictUnknown => 'Не знаю';

  @override
  String get triageVerdictUnsure => 'Не уверен';

  @override
  String get triageVerdictKnown => 'Знаю';

  @override
  String get triageUndo => 'Вернуть слово';

  @override
  String get triageTermTypeWord => 'слово';

  @override
  String get triageTermTypePhrase => 'фраза';

  @override
  String get triageTermTypeIdiom => 'идиома';

  @override
  String get triageTermTypePhrasalVerb => 'фраз. глагол';

  @override
  String get triageAllDoneTitle => 'Всё разобрано';

  @override
  String get triageAllDoneBody => 'В этом наборе не осталось новых слов для разбора.';

  @override
  String get triageMoreLaterTitle => 'На сейчас всё';

  @override
  String triageMoreLaterBody(int count) {
    return 'Ещё $count после синхронизации — зайдите снова, когда будет сеть.';
  }

  @override
  String get triageDone => 'Готово';

  @override
  String get triageSummaryBatchTitle => 'Пачка разобрана';

  @override
  String get triageSummaryDoneTitle => 'Разбор завершён';

  @override
  String get triageTallyKnown => 'Знаю';

  @override
  String get triageTallyLearning => 'Учу';

  @override
  String get triageTallyUnsure => 'Не уверен';

  @override
  String triageRemainingAfterSync(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Ещё $count слов после синхронизации',
      many: 'Ещё $count слов после синхронизации',
      few: 'Ещё $count слова после синхронизации',
      one: 'Ещё $count слово после синхронизации',
    );
    return '$_temp0';
  }

  @override
  String triageLoadError(String error) {
    return 'Не удалось загрузить: $error';
  }

  @override
  String get homeGeneratePlaceholder => 'Например: визит к врачу';

  @override
  String get homeGenerateChipDoctor => 'У врача';

  @override
  String get homeGenerateChipRent => 'Аренда';

  @override
  String get homeGenerateChipInterview => 'Собеседование';

  @override
  String homeCollectionProgress(int done, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      total,
      locale: localeName,
      other: '$done из $total слов',
      many: '$done из $total слов',
      few: '$done из $total слов',
      one: '$done из $total слова',
    );
    return '$_temp0';
  }

  @override
  String get tabHome => 'Сегодня';

  @override
  String get tabCollections => 'Коллекции';

  @override
  String get homeSessionTitle => 'Занятие';

  @override
  String collectionWordsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count слова',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
    );
    return '$_temp0';
  }

  @override
  String collectionDueSuffix(int count) {
    return '$count к повторению сегодня';
  }

  @override
  String collectionDensityMastered(int count) {
    return 'Освоено $count';
  }

  @override
  String collectionDensityInWork(int count) {
    return 'В работе $count';
  }

  @override
  String collectionDensityToSort(int count) {
    return 'Разобрать $count';
  }

  @override
  String collectionTriageButton(int count) {
    return 'Разобрать $count';
  }

  @override
  String get collectionTriageSubtitle => 'Новые слова этой коллекции';

  @override
  String collectionLearnButton(int count) {
    return 'Учить $count';
  }

  @override
  String get collectionLearnSubtitle => 'Новые слова — выучить';

  @override
  String collectionReviewButton(int count) {
    return 'Повторить $count';
  }

  @override
  String get collectionReviewSubtitle => 'Срок повторения подошёл';

  @override
  String get collectionPracticeButton => 'Свободная тренировка';

  @override
  String get collectionPracticeSubtitle => 'Ничего не горит — можно просто позаниматься';

  @override
  String get collectionWordsLabel => 'Слова';

  @override
  String get collectionReferenceBadge => 'справочник';

  @override
  String pairBadgeSemantics(String learned, String support) {
    return 'Языковая пара: $learned на $support';
  }

  @override
  String get collectionReferenceHint =>
      'Справочная коллекция: слова можно читать и слушать. Тренажёров для этого языка пока нет.';

  @override
  String get collectionAddWord => 'Добавить слово';

  @override
  String get collectionEmptyTitle => 'Слов пока нет';

  @override
  String get collectionEmptyBody => 'Нажми «Добавить слово», чтобы добавить';

  @override
  String get collectionTriageBannerTitle => 'Разбери коллекцию';

  @override
  String get collectionTriageBannerBody => 'Отметь, что уже знаешь — остальное пойдёт в тренировку';

  @override
  String get collectionTriageBannerStart => 'Начать';

  @override
  String get actionEdit => 'Изменить';

  @override
  String get actionDelete => 'Удалить';

  @override
  String collectionDeleteWordTitle(String term) {
    return 'Удалить «$term»?';
  }

  @override
  String get collectionDeleteWordMessage =>
      'Слово останется в других коллекциях, прогресс сохранится.';

  @override
  String get wordSheetAddTitle => 'Добавить слово';

  @override
  String get wordSheetEditTitle => 'Изменить слово';

  @override
  String get wordFieldTerm => 'Термин';

  @override
  String get wordFieldTranslation => 'Перевод';

  @override
  String get wordTermHint => 'слово или фраза';

  @override
  String get wordTranslationHintOptional => 'необязательно — подберём сами';

  @override
  String get wordSheetAddHelper => 'Транскрипция, пример и фото подберутся автоматически.';

  @override
  String get wordSheetEditHelper => 'Пример и фото останутся прежними, если не менять термин.';

  @override
  String get wordSheetAddButton => 'Добавить в коллекцию';

  @override
  String get wordSheetSaveButton => 'Сохранить';

  @override
  String get wordSheetDeleteLink => 'Удалить из коллекции';

  @override
  String get collectionMoveWord => 'Перенести в…';

  @override
  String get collectionMoveWordTitle => 'Куда перенести';

  @override
  String collectionMoveWordDone(String folder) {
    return 'Перенесено в «$folder»';
  }

  @override
  String get collectionMoveWordFailed => 'Не удалось перенести';

  @override
  String get collectionMoveWordNowhere => 'Других своих коллекций пока нет';

  @override
  String collectionDefaultUndeletable(String title) {
    return '«$title» — коллекция для сохранённых слов, её нельзя удалить. Переименовать можно.';
  }

  @override
  String get collectionMenuRename => 'Переименовать';

  @override
  String get collectionMenuDelete => 'Удалить коллекцию';

  @override
  String get collectionMenuRemoveFromMine => 'Убрать из моих';

  @override
  String collectionUnsubscribeTitle(String title) {
    return 'Убрать «$title» из моих?';
  }

  @override
  String get collectionUnsubscribeMessage =>
      'Набор пропадёт из «Моих». Слова и прогресс по ним сохранятся, набор снова можно добавить из стора.';

  @override
  String collectionDeleteTitle(String title) {
    return 'Удалить «$title»?';
  }

  @override
  String get collectionDeleteMessage =>
      'Коллекция удалится, слова останутся в тренировке. Убрать слово из тренировки можно только на его карточке.';

  @override
  String get commonCancel => 'Отмена';

  @override
  String get commonCloseMenu => 'Закрыть меню';

  @override
  String approxWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count слова',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
    );
    return '$_temp0';
  }

  @override
  String get collectionsTitle => 'Коллекции';

  @override
  String get collectionsEmptyTitle => 'Пока нет коллекций';

  @override
  String get collectionsEmptyBody => 'Опиши ситуацию — и ИИ соберёт первый набор.';

  @override
  String get collectionsCreateManual => 'Создать вручную';

  @override
  String get collectionsCreateManualHint => 'Пустая коллекция — слова добавишь сам';

  @override
  String get collectionsCreateGenerate => 'Сгенерировать';

  @override
  String get collectionsCreateGenerateHint => 'ИИ соберёт набор по описанию ситуации';

  @override
  String get collectionsNewCollection => 'Новая коллекция';

  @override
  String collectionsTileMastered(int count, int mastered) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count слова',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
    );
    return '$_temp0 · освоено $mastered';
  }

  @override
  String get generationGeneratingTitle => 'Собираем коллекцию…';

  @override
  String generationGeneratingMeta(String topic, String levels, String size) {
    return '$topic · $levels · $size';
  }

  @override
  String get generationGeneratingNote => 'Подбираем слова и фотографии · обычно 20–30 секунд';

  @override
  String get generationQueuedNote => 'Отправим, как только появится сеть';

  @override
  String get generationFailedTitle => 'Не получилось';

  @override
  String generationFailedBody(String topic) {
    return 'Сервис не ответил на запросе «$topic». Генерация не потрачена.';
  }

  @override
  String get generationQuotaTitle => 'Генерации на сегодня закончились';

  @override
  String generationQuotaBody(String topic, String time) {
    return 'Коллекцию «$topic» не создали. Лимит обновится в $time — тогда можно повторить.';
  }

  @override
  String generationQuotaBodyNoTime(String topic) {
    return 'Коллекцию «$topic» не создали: дневной лимит генераций исчерпан.';
  }

  @override
  String get generationQuotaPremium => 'Открыть Premium';

  @override
  String get generationRetry => 'Повторить';

  @override
  String get generationHide => 'Скрыть';

  @override
  String generateEnqueueFailed(String error) {
    return 'Не удалось поставить генерацию в очередь: $error';
  }

  @override
  String get generationReadyLabel => 'Готово';

  @override
  String generationReadyLoading(String topic) {
    return 'Готово — загружаю «$topic»…';
  }

  @override
  String generationUnderBadge(int delivered, int requested) {
    return '$delivered из $requested';
  }

  @override
  String get generationReadyUnder => 'Готова · собрано меньше';

  @override
  String get generateScreenTitle => 'Новая коллекция';

  @override
  String get generateSituationLabel => 'Опиши ситуацию';

  @override
  String get generateSituationHelper =>
      'Чем конкретнее ситуация, тем точнее подборка. Например: «первый приём у врача, жалобы и запись на анализы».';

  @override
  String get generatePlaceholder0 => 'Снимаю квартиру — разговор с агентом';

  @override
  String get generatePlaceholder1 => 'Первый приём у врача — жалобы и анализы';

  @override
  String get generatePlaceholder2 => 'Собеседование в IT — рассказ о проектах';

  @override
  String get generatePlaceholder3 => 'Открываю счёт в банке';

  @override
  String get generatePlaceholder4 => 'Заказываю еду в кафе';

  @override
  String get generateSizeLabel => 'Размер';

  @override
  String get generateSizeSmall => 'Маленькая';

  @override
  String get generateSizeMedium => 'Средняя';

  @override
  String get generateSizeLarge => 'Большая';

  @override
  String get generateLevelLabel => 'Уровень';

  @override
  String get generateLevelMulti => 'можно несколько';

  @override
  String get generateLanguageLabel => 'Язык изучения';

  @override
  String get generateLanguageDefault => 'по умолчанию';

  @override
  String generateQuotaRemaining(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Осталось $count генераций сегодня',
      many: 'Осталось $count генераций сегодня',
      few: 'Осталось $count генерации сегодня',
      one: 'Осталось $count генерация сегодня',
    );
    return '$_temp0';
  }

  @override
  String generateQuotaExhausted(String time) {
    return 'Генерации на сегодня закончились · обновятся в $time';
  }

  @override
  String get generateSubmit => 'Сгенерировать';

  @override
  String get generatePremiumUpsell => 'Нужно больше? Premium — до 20 в день';

  @override
  String get generateManual => 'Собрать коллекцию вручную';

  @override
  String generateVoiceListening(String time) {
    return 'Слушаю · $time';
  }

  @override
  String get generateVoiceStop => 'Стоп';

  @override
  String get generateVoiceHelper =>
      'Текст появляется в поле по мере распознавания — после остановки его можно править руками.';

  @override
  String get generateVoiceRecordingNote => 'Говори — клавиатура вернётся, когда остановишь запись';

  @override
  String get generateVoicePermissionDenied =>
      'Нужен доступ к микрофону и распознаванию речи — включите в Настройках';

  @override
  String get collectionSheetCreateTitle => 'Новая коллекция';

  @override
  String get collectionSheetEditTitle => 'Изменить коллекцию';

  @override
  String get collectionNameLabel => 'Название';

  @override
  String get collectionNameHint => 'напр.: Путешествия';

  @override
  String get collectionSheetCreateButton => 'Создать';

  @override
  String get searchTitle => 'Поиск слова';

  @override
  String get searchFieldHint => 'Найти слово';

  @override
  String get searchRecentLabel => 'Вы искали';

  @override
  String searchPressEnter(String query) {
    return 'Нажмите Enter, чтобы искать «$query» целиком';
  }

  @override
  String get searchOpenCard => 'Открыть карточку';

  @override
  String get searchSimilar => 'Похожие';

  @override
  String get searchBuildCard => 'Собрать карточку';

  @override
  String get searchBuildCardNote => 'Значение и пример. Повторно — бесплатно';

  @override
  String get searchLooking => 'Ищем…';

  @override
  String get searchBuildTranslation => 'перевод';

  @override
  String get searchBuildMeaning => 'значение';

  @override
  String get searchBuildExample => 'пример';

  @override
  String get searchBuildNote => 'Пара секунд. Можно закрыть — карточка появится в поиске.';

  @override
  String searchLimitUsed(int used, int cap) {
    return '$used из $cap на сегодня';
  }

  @override
  String get searchLimitTitle => 'Сборки с моделью вернутся в полночь';

  @override
  String get searchLookupFailed => 'Не удалось найти это слово';

  @override
  String get searchNotRecognized => 'Не получилось распознать, проверьте написание';

  @override
  String get searchQueryTooLong => 'Поиск — для слов и коротких фраз';

  @override
  String get searchSaveToDefault => '+ Сохранённые';

  @override
  String searchAlreadyIn(String collection) {
    return 'Уже в коллекции «$collection»';
  }

  @override
  String searchSavedShelf(String collection) {
    return 'Сохранено в «$collection» · в очереди на разбор';
  }

  @override
  String searchSavedLearning(String collection) {
    return 'Сохранено в «$collection» · учится';
  }

  @override
  String get searchLearnNow => 'Учить сразу';

  @override
  String get searchAddToCollection => 'Добавить в коллекцию';

  @override
  String get searchNewCollection => 'Новая коллекция';

  @override
  String searchNewCollectionInPair(String pair) {
    return 'Новая коллекция · $pair';
  }

  @override
  String get searchSaveFailed => 'Не удалось сохранить';

  @override
  String get searchPairFrom => 'С какого';

  @override
  String get searchPairTo => 'На какой';

  @override
  String get searchPairSwap => 'Поменять языки местами';

  @override
  String get searchPairNoDefault =>
      '«Сохранённые» — коллекция другой пары. Выберите коллекцию этой пары или создайте новую.';

  @override
  String get searchPairMismatchTitle => 'Слово другого языка';

  @override
  String searchPairMismatchMessage(String expected, String actual) {
    return 'Эта коллекция изучает $expected, а слово — на $actual. Одна коллекция — одна пара, поэтому нужна коллекция другой пары.';
  }

  @override
  String get searchPairMismatchCreate => 'Создать коллекцию';

  @override
  String get wordCardExampleLabel => 'Пример';

  @override
  String wordCardAlso(String words) {
    return 'также: $words';
  }

  @override
  String get wordCardFolderHint => 'Справа — выбрать другую коллекцию';

  @override
  String wordCardSavedIn(String folder) {
    return 'В коллекции «$folder»';
  }

  @override
  String get wordCardAddToAnother => 'Добавить в другую коллекцию';

  @override
  String get wordCardProgressLabel => 'Прогресс слова';

  @override
  String wordCardProgressCount(int step, int total) {
    return '$step из $total';
  }

  @override
  String wordCardPhotoCredit(String author) {
    return 'Фото: $author';
  }

  @override
  String get wordCardSpeak => 'Произнести';

  @override
  String get wordCardBack => 'Назад';

  @override
  String get wordCardMenu => 'Ещё';

  @override
  String get wordCardNoPhoto => 'Без фото';

  @override
  String get progressTitle => 'Прогресс';

  @override
  String progressStreakDays(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count дня подряд',
      many: '$count дней подряд',
      few: '$count дня подряд',
      one: '$count день подряд',
    );
    return '$_temp0';
  }

  @override
  String progressBestResult(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Лучший результат — $count дня',
      many: 'Лучший результат — $count дней',
      few: 'Лучший результат — $count дня',
      one: 'Лучший результат — $count день',
    );
    return '$_temp0';
  }

  @override
  String get progressDayMon => 'Пн';

  @override
  String get progressDayTue => 'Вт';

  @override
  String get progressDayWed => 'Ср';

  @override
  String get progressDayThu => 'Чт';

  @override
  String get progressDayFri => 'Пт';

  @override
  String get progressDaySat => 'Сб';

  @override
  String get progressDaySun => 'Вс';

  @override
  String get progressLearnedTotal => 'Выучено всего';

  @override
  String get progressThisWeek => 'За неделю';

  @override
  String get progressToday => 'Повторений сегодня';

  @override
  String get progressActivityMonth => 'Активность за месяц';

  @override
  String progressMonth(String month) {
    String _temp0 = intl.Intl.selectLogic(month, {
      '1': 'январь',
      '2': 'февраль',
      '3': 'март',
      '4': 'апрель',
      '5': 'май',
      '6': 'июнь',
      '7': 'июль',
      '8': 'август',
      '9': 'сентябрь',
      '10': 'октябрь',
      '11': 'ноябрь',
      '12': 'декабрь',
      'other': '',
    });
    return '$_temp0';
  }

  @override
  String progressAllWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Все $count слов',
      many: 'Все $count слов',
      few: 'Все $count слова',
      one: 'Все $count слово',
    );
    return '$_temp0';
  }

  @override
  String get homeLimitReachedTitle => 'Лимит новых на сегодня';

  @override
  String get homeLimitReachedHint =>
      'Новые слова — завтра. Сейчас можно повторять свободной тренировкой в коллекциях.';

  @override
  String get homeOfflineBanner =>
      'Нет сети. Повторения идут как обычно — синхронизируем, когда связь вернётся.';

  @override
  String get homeUnreachableTitle => 'Сервер не отвечает';

  @override
  String get homeUnreachableBody =>
      'День загрузить не удалось. Потяни вниз, чтобы попробовать снова — всё сохранённое на месте.';

  @override
  String get homeGenerateOfflineNote =>
      'Генерация недоступна без сети. Тема сохранится и уйдёт в работу, когда связь вернётся.';

  @override
  String get appWordmark => 'Слова';

  @override
  String get authTagline => 'Слова для реальных ситуаций — от банка до собеседования.';

  @override
  String get authContinueGoogle => 'Продолжить с Google';

  @override
  String get authContinueApple => 'Продолжить с Apple';

  @override
  String get authTerms => 'Условия';

  @override
  String get authPrivacy => 'Конфиденциальность';

  @override
  String get authOfflineHint => 'Нет сети. Для первого входа нужно подключение.';

  @override
  String get authAppleUnavailable => 'Вход через Apple пока недоступен.';

  @override
  String get onbLangTitle => 'Какой язык учим?';

  @override
  String get onbLangSubtitle => 'Можно поменять в профиле в любой момент.';

  @override
  String get onbLevelTitle => 'Насколько уверенно читаешь?';

  @override
  String get onbLevelSubtitle => 'Примерно — потом уточним по твоим ответам в разборе.';

  @override
  String onbLevelExample(String level) {
    return 'На $level в коллекции попадают слова вроде «wire transfer» и «make ends meet».';
  }

  @override
  String get onbGoalTitle => 'Сколько слов в день?';

  @override
  String get onbGoalSubtitle => 'Цель влияет только на напоминания и прогресс.';

  @override
  String onbGoalMinutes(int count) {
    return '≈ $count минут в день';
  }

  @override
  String get onbGoalRecommended => 'рекомендуем';

  @override
  String get onbFooterNote =>
      'Всё это меняется в профиле — уровень, цель и язык не заперты за онбордингом.';

  @override
  String get onbNext => 'Далее';

  @override
  String get onbStart => 'Начать';

  @override
  String get cefrHintA1 => 'начало';

  @override
  String get cefrHintA2 => 'базовый';

  @override
  String get cefrHintB1 => 'средний';

  @override
  String get cefrHintB2 => 'уверенный';

  @override
  String get cefrHintC1 => 'свободный';

  @override
  String get cefrHintC2 => 'почти носитель';

  @override
  String get profileTitle => 'Профиль';

  @override
  String get profileSectionLearning => 'Обучение';

  @override
  String get profileSectionApp => 'Приложение';

  @override
  String get profileSectionSubscription => 'Подписка';

  @override
  String get profileSectionAccount => 'Аккаунт';

  @override
  String get profileRowLevel => 'Уровень';

  @override
  String get profileRowGoal => 'Дневная цель';

  @override
  String get profileRowTargetLang => 'Язык изучения';

  @override
  String get profileRowUiLang => 'Язык интерфейса';

  @override
  String get profileRowAutoPronounce => 'Автопроизношение';

  @override
  String get profileAutoPronounceHint => 'Озвучивать слово при показе карточки';

  @override
  String get profileRowTransliteration => 'Подсказка произношения';

  @override
  String get profileTransliterationHint => 'Показывать, как читается слово, вашими буквами';

  @override
  String get profileRowReminders => 'Напоминания';

  @override
  String get profileRemindersHint => 'Одно в день, если есть что повторить';

  @override
  String get profileRowReminderTime => 'Время';

  @override
  String get profileFreeTier => 'Бесплатный тариф';

  @override
  String get profileFreeTierHint => '3 генерации в день';

  @override
  String get profileSoon => 'Скоро';

  @override
  String get profileSignOut => 'Выйти';

  @override
  String get profileDeleteAccount => 'Удалить аккаунт';

  @override
  String profileGoalValue(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count слов',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
    );
    return '$_temp0';
  }

  @override
  String get uiLangSystem => 'Системный';

  @override
  String get uiLangRussian => 'Русский';

  @override
  String get uiLangEnglish => 'English';

  @override
  String get profileUiLangSheet => 'Язык интерфейса';

  @override
  String get profileLevelSheet => 'Уровень';

  @override
  String get profileGoalSheet => 'Дневная цель';

  @override
  String get reminderSheetTitle => 'Когда напомнить';

  @override
  String get reminderSheetSubtitle =>
      'Лучше всего работает время, когда у тебя обычно есть пять свободных минут.';

  @override
  String get commonSave => 'Сохранить';

  @override
  String get deleteAccountTitle => 'Удалить аккаунт?';

  @override
  String deleteAccountBody(String words, String streak) {
    return 'Все данные и прогресс будут удалены безвозвратно: $words, $streak и все коллекции.';
  }

  @override
  String deleteAccountWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count слов',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
    );
    return '$_temp0';
  }

  @override
  String deleteAccountStreak(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count дней стрика',
      many: '$count дней стрика',
      few: '$count дня стрика',
      one: '$count день стрика',
    );
    return '$_temp0';
  }

  @override
  String get deleteAccountConfirm => 'Удалить';

  @override
  String get sessionPhaseIntro => 'Знакомство';

  @override
  String get sessionPhaseAssemble => 'Сборка';

  @override
  String get sessionPhaseReview => 'Повторение';

  @override
  String get sessionPhasePractice => 'Свободная тренировка';

  @override
  String sessionInstrChoose(String lang) {
    return 'выбери $lang эквивалент';
  }

  @override
  String get sessionInstrAssemble => 'собери из слов';

  @override
  String get sessionInstrAssembleLetters => 'собери из букв';

  @override
  String get sessionAssemblyEmptyHint => 'Собери из слов ниже';

  @override
  String get sessionAssemblyEmptyHintLetters => 'Собери из букв ниже';

  @override
  String get sessionInstrAssembleSentence => 'собери предложение из слов';

  @override
  String sessionInstrType(String lang) {
    return 'напиши $lang';
  }

  @override
  String get sessionInstrListenChoose => 'прослушай и выбери перевод · можно повторить';

  @override
  String sessionInstrListenType(String lang) {
    return 'прослушай и напиши $lang';
  }

  @override
  String get sessionInstrDictation => 'прослушай и запиши предложение';

  @override
  String get sessionInstrPickCorrect => 'выбери верное предложение';

  @override
  String get sessionInstrDescriptionMatch => 'выбери слово по описанию';

  @override
  String sessionPickCorrectShouldBe(String correction) {
    return 'должно быть: $correction';
  }

  @override
  String get sessionClozeInsert => 'Вставь слово';

  @override
  String get sessionChipReturnHint => 'Тап по слову в строке возвращает его вниз';

  @override
  String get sessionHintFirstLetter => 'Подсказка: первая буква';

  @override
  String get sessionDontRemember => 'Не помню';

  @override
  String get sessionWrongKeyboard =>
      'Похоже, раскладка не та — переключи клавиатуру и введи ещё раз';

  @override
  String get sessionCheck => 'Проверить';

  @override
  String get sessionIntroBadge => 'новое слово';

  @override
  String get sessionIntroGot => 'Понятно';

  @override
  String get sessionIntroAlso => 'также:';

  @override
  String get sessionInstrSpeakWord => 'скажи слово вслух';

  @override
  String get sessionInstrSpeakExample => 'прочитай предложение вслух';

  @override
  String get sessionSpeakStart => 'Сказать';

  @override
  String get sessionSpeakStop => 'Готово';

  @override
  String get sessionSpeakListening => 'Слушаю…';

  @override
  String get sessionSpeakNotHeard => 'Не расслышал. Попробуй ещё раз — ближе к микрофону.';

  @override
  String get sessionSpeakNoMic => 'Микрофон недоступен. Можно пропустить эту карточку.';

  @override
  String buildStamp(String client, String server) {
    return 'клиент $client · сервер $server';
  }

  @override
  String get buildStampUnstamped => 'без метки';

  @override
  String get buildStampWaiting => '…';

  @override
  String get buildStampNoServer => 'нет связи';

  @override
  String get qaReportButton => 'Жалоба';

  @override
  String qaReportSent(String id) {
    return 'Жалоба сохранена: $id';
  }

  @override
  String get qaReportFailed => 'Жалоба не ушла';

  @override
  String get speechPermissionRecognitionDenied =>
      'Распознавание речи выключено. Разреши распознавание речи в настройках — без него телефон слышит, но не понимает.';

  @override
  String get speechPermissionMicDenied =>
      'Микрофон выключен. Разреши доступ к микрофону в настройках.';

  @override
  String get speechPermissionBothDenied =>
      'Разреши в настройках микрофон и распознавание речи — нужны оба.';

  @override
  String get speechPermissionOpenSettings => 'Открыть настройки';

  @override
  String get sessionSpeakCutOff => 'Не расслышали до конца — скажи ещё раз.';

  @override
  String get sessionSpeakSkip => 'Пропустить';

  @override
  String get sessionSpeakSkipHint => 'Пропуск ничего не испортит: слово вернётся своим чередом.';

  @override
  String get sessionSpeakYourTurn => 'Твоя очередь — нажми и говори';

  @override
  String get sessionSpeakWaitForRole => 'Собеседник говорит';

  @override
  String get sessionSpeakRecording => 'Пишу — скажи и нажми «Готово»';

  @override
  String get sessionSpeakVerdictCorrect => 'Верно';

  @override
  String sessionSpeakVerdictAlmost(String words) {
    return 'Почти — не хватило: $words';
  }

  @override
  String get sessionSpeakVerdictWrong => 'Не то';

  @override
  String get sessionSpeakHint => 'Проверяем, вспомнил ли ты слово, а не произношение.';

  @override
  String sessionSpeakHintKey(String key) {
    return 'Скажи фразу, главное — «$key».';
  }

  @override
  String get sessionSpeakHintWhole => 'Скажи фразу целиком.';

  @override
  String sessionSpeakHeard(String text) {
    return 'Услышали: «$text»';
  }

  @override
  String get sessionEchoTry => 'Повторить вслух';

  @override
  String get sessionEchoHeard => 'Услышал тебя';

  @override
  String get sessionEchoAgain => 'Попробуй ещё';

  @override
  String get sessionEchoEnable => 'Включить микрофон';

  @override
  String get sessionHeaderIntro => 'Знакомство';

  @override
  String get sessionHeaderRecognition => 'Узнавание';

  @override
  String get sessionInstrRecogniseTranslation => 'выбери перевод';

  @override
  String get sessionRecogniseJustMet => 'вы только что познакомились с этим словом';

  @override
  String get ladderStep0 => 'знакомство';

  @override
  String get ladderStep1 => 'узнавание';

  @override
  String get ladderStep3 => 'сборка';

  @override
  String get ladderStep4 => 'написание';

  @override
  String get ladderStep5 => 'диктант';

  @override
  String get statusToSort => 'Разобрать';

  @override
  String get statusInWork => 'В работе';

  @override
  String get statusMastered => 'Освоено';

  @override
  String get statusPaused => 'Отложено';

  @override
  String statusLadderStep(int step, int total, String rung) {
    return 'Ступень $step из $total: $rung';
  }

  @override
  String statusCountToSort(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Разобрать $count слова',
      many: 'Разобрать $count слов',
      few: 'Разобрать $count слова',
      one: 'Разобрать $count слово',
    );
    return '$_temp0';
  }

  @override
  String get statusLegendTitle => 'Что значат точки';

  @override
  String get poolKnownLegend => 'Слово помечено «знаю» — по лестнице оно не шло.';

  @override
  String get ladderTitle => 'ЛЕСТНИЦА СЛОВА';

  @override
  String get ladderKnownDash => 'знаю';

  @override
  String get ladderTrainWord => 'Тренировать слово';

  @override
  String get sessionNext => 'Дальше';

  @override
  String get sessionDone => 'Готово';

  @override
  String get sessionFeedbackCorrect => 'Верно';

  @override
  String get sessionFeedbackAlmost => 'Почти:';

  @override
  String get sessionFeedbackWrong => 'Не то — правильная форма ниже';

  @override
  String get sessionFeedbackWrongAbove => 'Не то — верный ответ отмечен выше';

  @override
  String get sessionDueToday => 'сегодня';

  @override
  String get sessionDueTomorrow => 'завтра';

  @override
  String sessionDueInDays(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'через $days дней',
      many: 'через $days дней',
      few: 'через $days дня',
      one: 'через $days день',
    );
    return '$_temp0';
  }

  @override
  String sessionSeeAgain(String when) {
    return 'Увидишь снова $when';
  }

  @override
  String get sessionSummaryTitle => 'Сессия закончена';

  @override
  String sessionStatReviewed(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Повторено',
      many: 'Повторено',
      few: 'Повторено',
      one: 'Повторено',
    );
    return '$_temp0';
  }

  @override
  String sessionStatNew(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Новых',
      many: 'Новых',
      few: 'Новых',
      one: 'Новое',
    );
    return '$_temp0';
  }

  @override
  String sessionStatErrors(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Ошибки',
      many: 'Ошибок',
      few: 'Ошибки',
      one: 'Ошибка',
    );
    return '$_temp0';
  }

  @override
  String sessionPracticeStatDone(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Пройдено',
      many: 'Пройдено',
      few: 'Пройдено',
      one: 'Пройдено',
    );
    return '$_temp0';
  }

  @override
  String get sessionPracticeAgain => 'Ещё раз';

  @override
  String get sessionDailyGoal => 'Дневная цель';

  @override
  String get sessionGoalClosed => 'Дневная цель закрыта';

  @override
  String sessionStreak(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'Стрик — $days дней',
      many: 'Стрик — $days дней',
      few: 'Стрик — $days дня',
      one: 'Стрик — $days день',
    );
    return '$_temp0';
  }

  @override
  String get sessionSessionWords => 'Слова этой сессии';

  @override
  String sessionStrugglingTitle(String term) {
    return 'Проседает: $term';
  }

  @override
  String get sessionStrugglingBody =>
      'Даётся тяжело. Можно собрать другой пример — иногда дело в контексте, а не в слове.';

  @override
  String get sessionNewExample => 'Новый пример';

  @override
  String get sessionNewExampleExhausted => 'Лимит примеров на сегодня исчерпан';

  @override
  String get sessionPracticeBanner => 'Свободная тренировка — прогресс не меняется';

  @override
  String get sessionExitTitle => 'Прервать сессию?';

  @override
  String get sessionExitBody => 'Отвеченные слова сохранятся — вернуться можно в любой момент.';

  @override
  String get sessionExitConfirm => 'Выйти';

  @override
  String get sessionExitCancel => 'Продолжить';

  @override
  String get sessionClose => 'Закрыть';

  @override
  String get sessionListenReplay => 'Повторить озвучку';

  @override
  String get sessionInstrSituationalHear => 'выбери, что он сказал';

  @override
  String get sessionInstrSituationalSay => 'выбери, что ответишь';

  @override
  String get sessionInstrAssembleTurn => 'собери свой ответ из блоков';

  @override
  String get sessionSceneRunHint => 'скажи свою реплику — текста не будет';

  @override
  String get sessionInstrSituationalAsk => 'выбери, что спросишь';

  @override
  String get sessionSituationLabel => 'Ситуация';

  @override
  String get sessionSituationHearLabel => 'Сейчас услышите';

  @override
  String get sessionSituationRevealText => 'Показать текст';

  @override
  String sessionSituationTask(String outcome) {
    return 'Твоя задача — $outcome.';
  }

  @override
  String get sessionListenReplaySlow => 'Замедленно';

  @override
  String get sessionEmpty => 'Здесь пока нечего повторять';

  @override
  String get sessionDailyNewLimit => 'Дневной лимит новых слов достигнут. Возвращайся завтра';

  @override
  String sessionLoadError(String error) {
    return 'Не удалось загрузить сессию: $error';
  }

  @override
  String get authErrorOffline => 'Нет подключения к интернету. Для входа нужна сеть.';

  @override
  String get authErrorGoogleUnsupported => 'Вход через Google не поддерживается на этой платформе.';

  @override
  String get authErrorCancelled => 'Вход отменён.';

  @override
  String get authErrorGoogle => 'Не удалось войти через Google. Попробуй ещё раз.';

  @override
  String get authErrorGoogleToken => 'Не удалось получить токен Google.';

  @override
  String get authErrorLoginFailed => 'Не удалось войти. Попробуй ещё раз.';

  @override
  String get authErrorApple => 'Вход через Apple пока недоступен.';

  @override
  String get authErrorAppleToken => 'Не удалось получить токен Apple.';

  @override
  String get practiceDialogEntry => 'Разговор · 3 мин';

  @override
  String get practiceDialogEntrySubtitle => 'Голосовая практика с ИИ';

  @override
  String get practiceDialogOfflineHint => 'Нужен интернет';

  @override
  String get practiceDialogPrestartTitle => 'Разговор с ИИ';

  @override
  String practiceDialogPrestartBody(String lang) {
    return 'ИИ будет говорить с тобой на языке коллекции — $lang. Отвечай вслух и старайся использовать эти слова.';
  }

  @override
  String get practiceDialogPrestartWordsLabel => 'Слова для разговора';

  @override
  String get practiceDialogStart => 'Начать разговор';

  @override
  String get practiceDialogStateConnecting => 'соединяемся…';

  @override
  String get practiceDialogStateSpeaking => 'говорит';

  @override
  String get practiceDialogStateListening => 'слушаю тебя';

  @override
  String practiceDialogCoverageLabel(int used, int total) {
    return '$used / $total';
  }

  @override
  String get practiceDialogExitTitle => 'Завершить разговор?';

  @override
  String get practiceDialogExitMessage => 'Разговор закончится, и ты увидишь итог.';

  @override
  String get practiceDialogExitConfirm => 'Завершить';

  @override
  String get practiceDialogExitCancel => 'Продолжить';

  @override
  String get practiceDialogFinaleTitle => 'Разговор окончен';

  @override
  String practiceDialogFinaleWords(int used, int total) {
    return 'Слов прозвучало: $used из $total';
  }

  @override
  String get practiceDialogFinaleDone => 'Готово';

  @override
  String get practiceDialogErrorSubscription => 'Разговоры доступны в Premium.';

  @override
  String practiceDialogErrorRateLimited(String time) {
    return 'На сегодня разговоры закончились. Новые — после $time.';
  }

  @override
  String get practiceDialogErrorRateLimitedNoTime =>
      'На сегодня разговоры закончились. Попробуй завтра.';

  @override
  String get practiceDialogErrorOffline => 'Нет сети. Для разговора нужен интернет.';

  @override
  String get practiceDialogErrorGeneric => 'Не удалось начать разговор. Попробуй ещё раз.';

  @override
  String get practiceDialogClose => 'Закрыть';

  @override
  String get practiceDialogRepeat => 'Пройти ещё раз';

  @override
  String practiceDialogResultWords(int used, int total) {
    return 'слов: $used из $total';
  }

  @override
  String get storeSegmentMine => 'Мои';

  @override
  String get storeSegmentReady => 'Готовые';

  @override
  String get storeSectionOther => 'Разное';

  @override
  String storeWordsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count слова',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
    );
    return '$_temp0';
  }

  @override
  String get storeInLibrary => 'В моих';

  @override
  String get storeAddToMine => 'Добавить в мои';

  @override
  String get storeAvailableWithPremium => 'Доступно с Premium';

  @override
  String storeAllSetsUnlock(int count) {
    return 'Открываются все $count наборов сразу';
  }

  @override
  String get storeInsideLabel => 'Что внутри';

  @override
  String storeMoreWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count слова',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
    );
    return 'и ещё $_temp0';
  }

  @override
  String get storeLangPairSheetTitle => 'Языковая пара';

  @override
  String get storeEmptyTitle => 'Скоро здесь появятся наборы';

  @override
  String get storeEmptyBody => 'Готовые коллекции по ситуациям добавим в ближайшее время.';

  @override
  String get storePreviewAdded => 'Набор добавлен в «Мои»';

  @override
  String get storeSubscribeError => 'Не удалось добавить набор. Попробуйте ещё раз.';

  @override
  String get paywallClose => 'Закрыть';

  @override
  String get paywallTitleQuota => 'Больше коллекций за один вечер';

  @override
  String get paywallTitleGeneric => 'Premium без ограничений';

  @override
  String paywallTitleStore(String title, int count) {
    return '$title и ещё $count наборов';
  }

  @override
  String get paywallSubtitleQuota => 'Premium поднимает дневной лимит до двадцати генераций.';

  @override
  String get paywallSubtitleStore =>
      'Премиум-коллекции собраны редакцией и открываются все сразу — по одной их не продаём.';

  @override
  String get paywallSubtitleGeneric => 'Один тариф открывает всё, что делает изучение быстрее.';

  @override
  String get paywallBenefitGenerations => 'До 20 генераций в день';

  @override
  String get paywallBenefitStore => 'Все премиум-коллекции в сторе';

  @override
  String get paywallBenefitModes => 'Будущие режимы тренировок';

  @override
  String get paywallFreeForever => 'Повторения, разбор и офлайн — бесплатно всегда.';

  @override
  String get paywallPeriodYear => 'Год';

  @override
  String get paywallPeriodMonth => 'Месяц';

  @override
  String get paywallPriceYear => '\$29.99';

  @override
  String get paywallPriceMonth => '\$4.99';

  @override
  String get paywallYearPerMonth => '\$2.50 в месяц';

  @override
  String get paywallPerMonth => 'в месяц';

  @override
  String get paywallDiscountBadge => '−50%';

  @override
  String get paywallContinue => 'Продолжить';

  @override
  String paywallLegalYear(String price) {
    return 'Подписка продлевается автоматически. $price за год списываются с Apple ID; отменить можно в настройках App Store не позднее чем за 24 часа до конца периода.';
  }

  @override
  String paywallLegalMonth(String price) {
    return 'Подписка продлевается автоматически. $price в месяц списываются с Apple ID; отменить можно в настройках App Store не позднее чем за 24 часа до конца периода.';
  }

  @override
  String get paywallRestore => 'Восстановить покупки';

  @override
  String get paywallTerms => 'Условия';

  @override
  String get paywallPrivacy => 'Конфиденциальность';

  @override
  String get paywallDevPurchased => 'Premium активирован (dev-режим)';

  @override
  String get paywallNeedsRealPremium => 'Нужен настоящий Premium (StoreKit — отдельный блок)';

  @override
  String get profileTryPremium => 'Попробовать Premium';

  @override
  String profileFreeTierReset(String time) {
    return '3 генерации в день · сбрасываются в $time';
  }

  @override
  String get profilePremiumActive => 'Premium';

  @override
  String get profilePremiumBadge => 'активна';

  @override
  String get profilePremiumHint => 'Подписка активна';

  @override
  String get profileManageSubscription => 'Управлять подпиской';

  @override
  String get profileRestorePurchases => 'Восстановить покупки';

  @override
  String get profileSectionDev => 'Разработка';

  @override
  String get devFlagStore => 'Стор коллекций';

  @override
  String get devFlagPaywall => 'Пейволл';

  @override
  String get devFlagPremium => 'Premium (dev)';

  @override
  String get perfMonitorTitle => 'Подвисания';

  @override
  String get perfMonitorToggle => 'Записывать подвисания, кадры и тапы';

  @override
  String get perfMonitorToggleHint => 'По умолчанию выключено — пока выключено, ничего не стоит';

  @override
  String get perfMonitorEmpty => 'записей нет';

  @override
  String get perfMonitorCopy => 'Скопировать в буфер';

  @override
  String get perfMonitorClear => 'Очистить';

  @override
  String perfMonitorCopied(String path) {
    return 'Скопировано. Файл: $path';
  }

  @override
  String get sessionOffline => 'Нет соединения';

  @override
  String get sessionLoadFailed => 'Не удалось загрузить сессию';

  @override
  String get syncStuckBanner => 'Ответы не уходят на сервер — проверь соединение';

  @override
  String get syncUnreachableBanner => 'Сервер недоступен · показываю сохранённое';

  @override
  String get poolNotStudyingNote => 'Слово на полке — ты его пока не учишь.';

  @override
  String get poolEnrollAction => 'Учить это слово';

  @override
  String get poolEnrollNote => 'Слово встанет в очередь и начнёт приходить на тренировках.';

  @override
  String get poolUnenrollAction => 'Убрать из изучения';

  @override
  String poolUnenrollTitle(String term) {
    return 'Убрать «$term» из изучения?';
  }

  @override
  String get poolUnenrollMessage =>
      'Слово перестанет приходить на тренировках. Прогресс и история сохранятся — слово можно вернуть в любой момент.';

  @override
  String get poolUnenrollConfirm => 'Убрать';

  @override
  String get myWordsTitle => 'Мои слова';

  @override
  String myWordsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count слова',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
    );
    return '$_temp0';
  }

  @override
  String get myWordsSearchHint => 'Поиск по словам';

  @override
  String get myWordsFilterAll => 'Все';

  @override
  String get myWordsFilterNew => 'Новые';

  @override
  String get myWordsFilterLearning => 'Узнавание';

  @override
  String get myWordsFilterReview => 'Повторение';

  @override
  String get myWordsSourceAll => 'Все коллекции';

  @override
  String get myWordsSourceNone => 'Без коллекции';

  @override
  String get myWordsEmptyTitle => 'Пока пусто';

  @override
  String get myWordsEmptyMessage =>
      'Слова попадают сюда, когда ты разбираешь коллекцию свайпами «не знаю» и «не уверен» — или нажимаешь «Учить это слово» на карточке слова.';

  @override
  String get myWordsNothingFound => 'Ничего не нашлось';

  @override
  String get topicSessionAction => 'Тренировка по теме';

  @override
  String get topicSessionTitle => 'Выбери тему';

  @override
  String homeStreakBadge(int count) {
    return 'Стрик $count';
  }

  @override
  String get homeSessionCardTitle => 'Сессия на сегодня';

  @override
  String get challengeLabel => 'Слово-вызов';

  @override
  String challengeStreak(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'угадано $count подряд',
      many: 'угадано $count подряд',
      few: 'угадано $count подряд',
      one: 'угадано $count подряд',
    );
    return '$_temp0';
  }

  @override
  String get challengeStreakReset => 'серия сброшена';

  @override
  String get challengePraise => 'Знаешь!';

  @override
  String challengeAnswer(String term, String translation) {
    return '$term — $translation';
  }

  @override
  String challengeExample(String sentence, String translation) {
    return '$sentence — $translation';
  }

  @override
  String challengeMistake(String chosen, String term) {
    return 'Вы выбрали «$chosen» — это $term';
  }

  @override
  String get challengeLearn => 'Учить';

  @override
  String get challengeTomorrow => 'Завтра новое';

  @override
  String get challengeCollapsed => 'Завтра новое слово';

  @override
  String get challengeLearning => 'Слово в очереди';

  @override
  String get homeSessionBadge => 'Сессия';

  @override
  String homeSessionUnitWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'слова',
      many: 'слов',
      few: 'слова',
      one: 'слово',
    );
    return '$_temp0';
  }

  @override
  String get homeSessionRowRepeat => 'Повторить';

  @override
  String get homeSessionRowNew => 'Новых';

  @override
  String get homeSessionRowTriage => 'Разобрать';

  @override
  String homeSessionCardWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count слова',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
    );
    return '$_temp0';
  }

  @override
  String homeSessionCardMinutes(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '≈ $count минуты',
      many: '≈ $count минут',
      few: '≈ $count минуты',
      one: '≈ $count минута',
    );
    return '$_temp0';
  }

  @override
  String sessionSizeCards(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count карточки',
      many: '$count карточек',
      few: '$count карточки',
      one: '$count карточка',
    );
    return '~$_temp0';
  }

  @override
  String homeSessionPartRepeat(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count повторить',
      many: '$count повторить',
      few: '$count повторить',
      one: '$count повторить',
    );
    return '$_temp0';
  }

  @override
  String homeSessionPartNew(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count новых',
      many: '$count новых',
      few: '$count новых',
      one: '$count новое',
    );
    return '$_temp0';
  }

  @override
  String homeSessionPartTriage(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count разобрать',
      many: '$count разобрать',
      few: '$count разобрать',
      one: '$count разобрать',
    );
    return '$_temp0';
  }

  @override
  String get homeSessionStart => 'Начать';

  @override
  String homeInWorkTitle(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'В работе — $count слова',
      many: 'В работе — $count слов',
      few: 'В работе — $count слова',
      one: 'В работе — $count слово',
    );
    return '$_temp0';
  }

  @override
  String homeInWorkWaiting(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count ждут очереди',
      many: '$count ждут очереди',
      few: '$count ждут очереди',
      one: '$count ждёт очереди',
    );
    return '$_temp0';
  }

  @override
  String homeInWorkPace(int perDay, int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'при $perDay в день новым до очереди ~$days дня',
      many: 'при $perDay в день новым до очереди ~$days дней',
      few: 'при $perDay в день новым до очереди ~$days дня',
      one: 'при $perDay в день новым до очереди ~$days день',
    );
    return '$_temp0';
  }

  @override
  String homeInWorkQueueStands(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'возьмёте $count сейчас — очередь двинется сегодня',
      many: 'возьмёте $count сейчас — очередь двинется сегодня',
      few: 'возьмёте $count сейчас — очередь двинется сегодня',
      one: 'возьмёте $count сейчас — очередь двинется сегодня',
    );
    return '$_temp0';
  }

  @override
  String get homeEdgeTitle => 'На грани забывания';

  @override
  String get homeEdgeTomorrow => 'выпадет завтра';

  @override
  String homeEdgeInDays(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'через $count дня',
      many: 'через $count дней',
      few: 'через $count дня',
      one: 'через $count день',
    );
    return '$_temp0';
  }

  @override
  String get homeHardestTitle => 'Далось труднее всего';

  @override
  String homeHardestErrors(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count ошибки',
      many: '$count ошибок',
      few: '$count ошибки',
      one: '$count ошибка',
    );
    return '$_temp0';
  }

  @override
  String homeSectionCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count слова',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
    );
    return '$_temp0';
  }

  @override
  String get homeDoneTitle => 'Сегодня закрыто';

  @override
  String homeDoneOfWords(int done, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      total,
      locale: localeName,
      other: '$done из $total слов',
      many: '$done из $total слов',
      few: '$done из $total слов',
      one: '$done из $total слова',
    );
    return '$_temp0';
  }

  @override
  String homeDoneCards(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count карточки',
      many: '$count карточек',
      few: '$count карточки',
      one: '$count карточка',
    );
    return '$_temp0';
  }

  @override
  String homeDoneMinutes(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count мин',
      many: '$count мин',
      few: '$count мин',
      one: '$count мин',
    );
    return '$_temp0';
  }

  @override
  String homeChainProgress(int position, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      total,
      locale: localeName,
      other: 'карточка $position из $total',
      many: 'карточка $position из $total',
      few: 'карточка $position из $total',
      one: 'карточка $position из $total',
    );
    return '$_temp0';
  }

  @override
  String homeDoneOf(int done, int total) {
    return '$done из $total';
  }

  @override
  String homeDoneDuration(int minutes, int seconds) {
    return '$minutes мин $seconds с';
  }

  @override
  String homeDoneDurationSeconds(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count секунды',
      many: '$count секунд',
      few: '$count секунды',
      one: '$count секунда',
    );
    return '$_temp0';
  }

  @override
  String get homeIdleTitle => 'Всё повторено';

  @override
  String get homeIdleTakeNew => 'Взять новые слова';

  @override
  String get homeIdleQueueStalled => 'Новые слова на сегодня не взяты — очередь стоит.';

  @override
  String homeNextReviewLine(String when, int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Следующий повтор — $when, $count слова.',
      many: 'Следующий повтор — $when, $count слов.',
      few: 'Следующий повтор — $when, $count слова.',
      one: 'Следующий повтор — $when, $count слово.',
    );
    return '$_temp0';
  }

  @override
  String get homeWhenTomorrow => 'завтра';

  @override
  String homeExtraFromCollection(int count, String title) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Можно добить $count слова из «$title» сверх плана.',
      many: 'Можно добить $count слов из «$title» сверх плана.',
      few: 'Можно добить $count слова из «$title» сверх плана.',
      one: 'Можно добить $count слово из «$title» сверх плана.',
    );
    return '$_temp0';
  }

  @override
  String homeExtraNew(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Можно взять $count слова, которые уже ждут очереди.',
      many: 'Можно взять $count слов, которые уже ждут очереди.',
      few: 'Можно взять $count слова, которые уже ждут очереди.',
      one: 'Можно взять $count слово, которое уже ждёт очереди.',
    );
    return '$_temp0';
  }

  @override
  String homeExtraButton(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Ещё $count слова',
      many: 'Ещё $count слов',
      few: 'Ещё $count слова',
      one: 'Ещё $count слово',
    );
    return '$_temp0';
  }

  @override
  String get homeContinueLabel => 'Продолжить';

  @override
  String homeContinueAbandoned(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'брошено $count дня назад',
      many: 'брошено $count дней назад',
      few: 'брошено $count дня назад',
      one: 'брошено $count день назад',
    );
    return '$_temp0';
  }

  @override
  String get homeGenerateRow => 'Собрать коллекцию по теме';

  @override
  String homeStoreLink(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'или взять из $count готовых',
      many: 'или взять из $count готовых',
      few: 'или взять из $count готовых',
      one: 'или взять из $count готового',
    );
    return '$_temp0';
  }

  @override
  String get homeStatLearned => 'Выучено';

  @override
  String get homeStatWeek => 'За неделю';

  @override
  String get homeStatInWork => 'В работе';

  @override
  String homeTomorrowRow(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Завтра выпадет $count слова',
      many: 'Завтра выпадет $count слов',
      few: 'Завтра выпадет $count слова',
      one: 'Завтра выпадет $count слово',
    );
    return '$_temp0';
  }

  @override
  String homeAwardPromoted(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '+$count слова продвинулись',
      many: '+$count слов продвинулись',
      few: '+$count слова продвинулись',
      one: '+$count слово продвинулось',
    );
    return '$_temp0';
  }

  @override
  String homeAwardExample(String term, String rung) {
    return '$term дошло до «$rung»';
  }

  @override
  String get homeGenerateCardTitle => 'Сгенерировать набор';

  @override
  String get homeGenerateCardHint => 'Опишите ситуацию — соберём набор под неё';

  @override
  String get homeStoreShowcaseTitle => 'Готовые наборы';

  @override
  String homeStoreShowcaseAll(int count) {
    return 'все $count';
  }

  @override
  String get homeFirstDayPromise => '5 минут в день — 20 слов в неделю';

  @override
  String get homeGenerateChipVet => 'Ветклиника';

  @override
  String get homeGenerateChipMoving => 'Переезд';

  @override
  String get homeFirstDayTitle => 'Начнём с первого набора';

  @override
  String homeFirstDayReadyTitle(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Взять готовый набор ($count темы)',
      many: 'Взять готовый набор ($count тем)',
      few: 'Взять готовый набор ($count темы)',
      one: 'Взять готовый набор ($count тема)',
    );
    return '$_temp0';
  }

  @override
  String get homeFirstDayReadyHint => 'Слова уже отобраны, озвучены и размечены по уровню';

  @override
  String get homeFirstDayOwnTitle => 'Собрать свою по описанию';

  @override
  String get homeFirstDayOwnHint => 'Опишите ситуацию — ИИ подберёт слова и фразы под неё';

  @override
  String homeSortOffer(int count, String title) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Можно разобрать ещё $count слова из «$title»',
      many: 'Можно разобрать ещё $count слов из «$title»',
      few: 'Можно разобрать ещё $count слова из «$title»',
      one: 'Можно разобрать ещё $count слово из «$title»',
    );
    return '$_temp0';
  }

  @override
  String homeTriageAction(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Разобрать $count слова',
      many: 'Разобрать $count слов',
      few: 'Разобрать $count слова',
      one: 'Разобрать $count слово',
    );
    return '$_temp0';
  }

  @override
  String get homeSortFirstTitle => 'Пора разобрать слова';

  @override
  String get onbNativeTitle => 'На каком языке показывать переводы?';

  @override
  String get onbNativeSubtitle =>
      'На нём будут переводы, объяснения и планы подготовки. Можно поменять в профиле.';

  @override
  String get profileRowNativeLang => 'Родной язык';

  @override
  String get profileNativeLangHint => 'Существующие коллекции останутся как есть';

  @override
  String profileNativeLangConfirmTitle(String language) {
    return 'Переводы на «$language»?';
  }

  @override
  String get profileNativeLangConfirmBody =>
      'Новые коллекции и планы будут на нём. Существующие коллекции останутся как есть — переводы в них не переписываются.';

  @override
  String get tabPlan => 'План';

  @override
  String get searchOpen => 'Поиск';

  @override
  String get commonBack => 'Назад';

  @override
  String sessionSayIntent(String intent) {
    return 'Скажи: $intent';
  }

  @override
  String homePlanCardBadge(int index, int total) {
    return 'План · день $index из $total';
  }

  @override
  String homePlanCardEventIn(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'Событие через $days дня',
      many: 'Событие через $days дней',
      few: 'Событие через $days дня',
      one: 'Событие через $days день',
    );
    return '$_temp0';
  }

  @override
  String get homePlanCardEventToday => 'Событие сегодня';

  @override
  String get homePlanCardContinue => 'Продолжить';

  @override
  String get homePlanInviteTitle => 'Есть дата и цель?';

  @override
  String get homePlanInviteBody => 'Соберём дни подготовки — от приёма у врача до собеседования.';

  @override
  String get homePlanInviteCta => 'Составить';

  @override
  String get devVoicesTitle => 'Голоса реплик';

  @override
  String get devVoicesLead =>
      'Одни и те же пять реплик qa-плана, прочитанные каждым кандидатом одним темпом. Слушайте на телефоне, а не в файлах: динамик и наушники решают больше, чем спектрограмма.';

  @override
  String get devVoicesSystem => 'Системный голос телефона';

  @override
  String get devVoicesSystemNote =>
      'Играется вживую, темпом реплик. Голос — тот, что стоит в Настройках iOS; enhanced-голос надо скачать там же.';

  @override
  String devVoicesPrice(String price) {
    return '≈ $price за план';
  }

  @override
  String get devVoicesPlayAll => 'Подряд';

  @override
  String get devVoicesStop => 'Стоп';

  @override
  String get devVoicesFavourite => 'Фаворит сессии';

  @override
  String devVoiceTrouble(int silent, int failed) {
    return 'Озвучка: $silent реплик системным голосом, $failed не скачалось';
  }

  @override
  String get devQaClockTitle => 'QA · «сегодня» плана';

  @override
  String devQaClockShift(int days) {
    String _temp0 = intl.Intl.pluralLogic(
      days,
      locale: localeName,
      other: 'сдвиг: $days дней',
      few: 'сдвиг: $days дня',
      one: 'сдвиг: $days день',
      zero: 'без сдвига',
    );
    return '$_temp0';
  }

  @override
  String get devQaClockPlus => '+1 день';

  @override
  String get devQaClockReset => 'Сбросить';

  @override
  String get planTitle => 'План';

  @override
  String get planEmptyTitle => 'Разговор, к которому готовишься';

  @override
  String get planEmptySub => 'сцена в день · 20 минут · репетиция вслух';

  @override
  String get planRuleSituation => 'Каждый день — одна ситуация. Дни открываются по одному';

  @override
  String get planRuleStages =>
      'Шесть этапов по порядку: слова → фразы → диалог → слушаю и отвечаю → говорю сам → разговор';

  @override
  String get planRuleReturn =>
      'То, что не получилось, вернётся в следующий день. Ничего не потеряется';

  @override
  String get planExampleDoctorTitle => 'Приём у врача';

  @override
  String get planExampleDoctorDay1 => 'Запись к врачу';

  @override
  String get planExampleDoctorGoal11 => 'спросить время приёма';

  @override
  String get planExampleDoctorGoal12 => 'договориться о приёме';

  @override
  String get planExampleDoctorGoal13 => 'назвать страховку';

  @override
  String get planExampleDoctorDay2 => 'Приём у врача';

  @override
  String get planExampleDoctorGoal21 => 'описать боль';

  @override
  String get planExampleDoctorGoal22 => 'ответить про лекарства';

  @override
  String get planExampleDoctorGoal23 => 'понять назначения';

  @override
  String get planExampleDoctorDay3 => 'Аптека';

  @override
  String get planExampleDoctorGoal31 => 'назвать рецепт';

  @override
  String get planExampleDoctorGoal32 => 'понять дозировку';

  @override
  String get planExampleDoctorGoal33 => 'спросить аналог';

  @override
  String get planExampleInterviewTitle => 'Собеседование';

  @override
  String get planExampleInterviewDay1 => 'Знакомство';

  @override
  String get planExampleInterviewGoal11 => 'рассказать о себе';

  @override
  String get planExampleInterviewGoal12 => 'назвать свой опыт';

  @override
  String get planExampleInterviewGoal13 => 'объяснить, почему ушёл';

  @override
  String get planExampleInterviewDay2 => 'Вопросы о работе';

  @override
  String get planExampleInterviewGoal21 => 'описать проект';

  @override
  String get planExampleInterviewGoal22 => 'ответить про сроки';

  @override
  String get planExampleInterviewGoal23 => 'признать ошибку';

  @override
  String get planExampleInterviewDay3 => 'Зарплата';

  @override
  String get planExampleInterviewGoal31 => 'назвать вилку';

  @override
  String get planExampleInterviewGoal32 => 'спросить про бонусы';

  @override
  String get planExampleInterviewGoal33 => 'обсудить выход';

  @override
  String get planExampleLandlordTitle => 'Звонок арендодателю';

  @override
  String get planExampleLandlordDay1 => 'Про залог';

  @override
  String get planExampleLandlordGoal11 => 'спросить сумму';

  @override
  String get planExampleLandlordGoal12 => 'узнать, когда вернут';

  @override
  String get planExampleLandlordGoal13 => 'назвать свой счёт';

  @override
  String get planExampleLandlordDay2 => 'Про ремонт';

  @override
  String get planExampleLandlordGoal21 => 'описать поломку';

  @override
  String get planExampleLandlordGoal22 => 'попросить мастера';

  @override
  String get planExampleLandlordGoal23 => 'договориться о времени';

  @override
  String get planExampleLandlordDay3 => 'Про договор';

  @override
  String get planExampleLandlordGoal31 => 'спросить про срок';

  @override
  String get planExampleLandlordGoal32 => 'уточнить про питомцев';

  @override
  String get planExampleLandlordGoal33 => 'назвать дату выезда';

  @override
  String planExampleMeta(String days, String level) {
    return '$days · $level';
  }

  @override
  String planExampleMore(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'ещё $n дня',
      many: 'ещё $n дней',
      few: 'ещё $n дня',
      one: 'ещё $n день',
    );
    return '$_temp0';
  }

  @override
  String get planExampleWhole => 'весь план';

  @override
  String get planEmptyCta => 'Собрать план';

  @override
  String get planFinishedTitle => 'Завершённые планы';

  @override
  String planFinishedItemDate(String date) {
    return 'завершён $date';
  }

  @override
  String planHeaderBrow(int n, int total) {
    return 'План · день $n из $total';
  }

  @override
  String planPlateLabel(int n) {
    return 'День $n';
  }

  @override
  String planCardsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n карточки',
      many: '$n карточек',
      few: '$n карточки',
      one: '$n карточка',
    );
    return '$_temp0';
  }

  @override
  String planMinutesCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n минуты',
      many: '$n минут',
      few: '$n минуты',
      one: '$n минута',
    );
    return '$_temp0';
  }

  @override
  String get planPlateStageWords => 'Слова';

  @override
  String get planPlateStagePhrases => 'Фразы';

  @override
  String get planPlateStageDialog => 'Диалог';

  @override
  String get planPlateStageListen => 'Слушаю и отвечаю';

  @override
  String get planPlateStageSpeak => 'Говорю сам';

  @override
  String get planPlateStateDone => 'пройдено';

  @override
  String get planPlateStateCurrent => 'идёт';

  @override
  String get planPlateStateAhead => 'впереди';

  @override
  String planNewWordsCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n новых слова',
      many: '$n новых слов',
      few: '$n новых слова',
      one: '$n новое слово',
    );
    return '$_temp0';
  }

  @override
  String planPlateStageSubStart(String words) {
    return 'начни отсюда · $words';
  }

  @override
  String planPlateStageSubUnfinished(String cards) {
    return 'не закончен · $cards';
  }

  @override
  String get planPlateCtaStart => 'Начать';

  @override
  String planPlateLabelCatchUp(int n) {
    return 'День $n · догоняем';
  }

  @override
  String planPlateBuildingTitle(int n) {
    return 'Собираем день $n';
  }

  @override
  String get planPlateBuildingSub => 'около минуты · можно закрыть приложение';

  @override
  String get planPlateFailedTitle => 'День не собрался';

  @override
  String planPlateFailedSub(int n) {
    return 'Сеть пропала. Маршрут на месте, пропал только день $n';
  }

  @override
  String get planPlateCtaRetry => 'Повторить';

  @override
  String get planPlateCtaContinue => 'Продолжить';

  @override
  String planClosedTitle(int n) {
    return 'День $n закрыт';
  }

  @override
  String planClosedCount(String cards, String minutes) {
    return '$cards · $minutes';
  }

  @override
  String planClosedNextTomorrow(int n, String date) {
    return 'День $n откроется завтра, $date';
  }

  @override
  String planClosedNextOn(int n, String date) {
    return 'День $n откроется $date';
  }

  @override
  String planClosedReturn(int n, int k) {
    String _temp0 = intl.Intl.pluralLogic(
      k,
      locale: localeName,
      other: '$k карточки вернутся в день $n →',
      many: '$k карточек вернутся в день $n →',
      few: '$k карточки вернутся в день $n →',
      one: '$k карточка вернётся в день $n →',
    );
    return '$_temp0';
  }

  @override
  String planRouteDayRepeatSub(int a, int b) {
    return 'слова и фразы дней $a–$b';
  }

  @override
  String planRouteDayRepeatSubOne(int a) {
    return 'слова и фразы дня $a';
  }

  @override
  String planRouteDayTitle(int n, String title) {
    return 'День $n · $title';
  }

  @override
  String get planRouteDayReview => 'Повторение';

  @override
  String get planRouteDayRehearsal => 'Репетиция';

  @override
  String get planRouteDayRehearsalSub => 'весь маршрут вслух';

  @override
  String get planRouteMetaPassed => 'пройден';

  @override
  String planMinutesShort(int n) {
    return '$n мин';
  }

  @override
  String planRouteMetaOpensAfter(int n) {
    return 'откроется после дня $n';
  }

  @override
  String get planRouteMetaOpensTomorrow => 'откроется завтра';

  @override
  String get planRouteEventNoDate => 'указать дату';

  @override
  String get planRouteEventFallback => 'Событие';

  @override
  String get planDoneTitle => 'План пройден';

  @override
  String planDaysCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n дня',
      many: '$n дней',
      few: '$n дня',
      one: '$n день',
    );
    return '$_temp0';
  }

  @override
  String planDoneMeta(String days) {
    return '$days';
  }

  @override
  String planDoneCollection(String name) {
    return 'Слова и фразы плана остались в коллекции «$name» — они будут приходить на повторение';
  }

  @override
  String get planDoneCta => 'Собрать новый план';

  @override
  String get planDoneCtaReadonly => 'Открыть коллекцию';

  @override
  String get planMenuDate => 'Изменить дату';

  @override
  String get planMenuNew => 'Собрать новый план';

  @override
  String get planMenuCollection => 'Открыть коллекцию';

  @override
  String get planMenuDelete => 'Удалить план';

  @override
  String get planMenuLabel => 'Меню плана';

  @override
  String planDateTitle(String event) {
    return 'Когда $event?';
  }

  @override
  String get planDateTitleNoEvent => 'Когда событие?';

  @override
  String planDateOptionCurrent(String date) {
    return '$date · как сейчас';
  }

  @override
  String get planDateOptionOther => 'Другая дата';

  @override
  String get planDateOptionOtherSub => 'выбрать в календаре';

  @override
  String get planDateCta => 'Применить';

  @override
  String get planDateCancel => 'Отменить';

  @override
  String get planDateRemove => 'Без даты';

  @override
  String get planNewTitle => 'Начать другой план?';

  @override
  String planNewBody(int n, int total, String name) {
    return 'Этот план завершится на дне $n из $total. Всё, что уже в работе, останется в коллекции «$name» и будет приходить на повторение';
  }

  @override
  String get planNewCta => 'Собрать новый';

  @override
  String get planNewKeep => 'Оставить этот';

  @override
  String get planDeleteTitle => 'Удалить план?';

  @override
  String planDeleteBody(String name) {
    return 'План исчезнет из истории. Коллекция «$name» и её слова останутся';
  }

  @override
  String get planDeleteBodyNoCollection => 'План исчезнет из истории';

  @override
  String get planDeleteConfirm => 'Удалить';

  @override
  String planOverdueMeta(String days, int total) {
    return 'Пройдено $days из $total';
  }

  @override
  String get planOverdueFinish => 'Завершить план';

  @override
  String planOverdueContinue(String days) {
    return 'Дозаниматься · $days';
  }

  @override
  String planHintFirstStart(String stage) {
    return 'Начни с этапа «$stage». Остальные откроются по порядку';
  }

  @override
  String planHintFirstRoute(int n) {
    return 'День $n открыт. Следующий откроется, когда пройдёшь этот';
  }

  @override
  String planRebuiltTitle(int from, int to) {
    return 'Маршрут пересобран: было $from дней, стало $to';
  }

  @override
  String get planHintFirstReturn =>
      'Эти карточки придут в следующий день ещё раз — так они и запоминаются';

  @override
  String get planSheetTitle => 'Как устроен план';

  @override
  String get planSheetCta => 'Понятно';

  @override
  String get planTabOffline => 'нет сети';

  @override
  String get planTabLoadFailedTitle => 'Не получилось загрузить план';

  @override
  String get planTabRetry => 'Повторить';

  @override
  String get planEntryNext => 'Далее';

  @override
  String get planEntryGoalTitle => 'К чему готовишься?';

  @override
  String get planEntryGoalSub =>
      'Расскажи ситуацию своими словами: что будет, с кем говоришь, чего боишься';

  @override
  String get planEntryGoalTyping1 => 'Собеседование в пятницу, боюсь…';

  @override
  String get planEntryGoalTyping2 => 'Звоню в банк, не понимаю по телефону…';

  @override
  String get planEntryGoalTyping3 => 'Иду к врачу…';

  @override
  String get planEntryGoalStoriesTitle => 'Так пишут другие';

  @override
  String get planEntryGoalStory1 => 'Собеседование в пятницу, боюсь вопросов про опыт';

  @override
  String get planEntryGoalStory2 => 'К врачу с ребёнком, первый раз в местной клинике';

  @override
  String get planEntryGoalStory3 => 'Звонок арендодателю про залог';

  @override
  String get planEntryGoalShortHint => 'Добавь, с кем и что важно — план будет точнее';

  @override
  String get planEntryGoalDictate => 'или надиктуй';

  @override
  String get planEntryGoalDictateEdit => 'можно поправить руками';

  @override
  String planEntryGoalListening(String time) {
    return '$time · говори, я слушаю';
  }

  @override
  String get planEntryTapeGoal => 'Цель';

  @override
  String get planEntryTapeLanguage => 'Язык';

  @override
  String get planEntryTapeDays => 'Дни';

  @override
  String planEntryTapeLanguageValue(String language, String level) {
    return '$language · $level';
  }

  @override
  String get planEntryLanguageTitle => 'На каком языке говорить?';

  @override
  String get planEntryLanguageLabel => 'Язык';

  @override
  String get planEntryLevelLabel => 'Уровень';

  @override
  String get planEntryLevelBeginner => 'Начальный';

  @override
  String get planEntryLevelBeginnerSub => 'знаю отдельные слова';

  @override
  String get planEntryLevelIntermediate => 'Средний';

  @override
  String get planEntryLevelIntermediateSub => 'понимаю простую речь, говорю с ошибками';

  @override
  String get planEntryDaysTitle => 'Сколько дней до разговора?';

  @override
  String planEntryDaysScenes(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n ситуации',
      many: '$n ситуаций',
      few: '$n ситуации',
      one: '$n ситуация',
    );
    return '$_temp0';
  }

  @override
  String planEntryDaysReviews(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n повторения',
      many: '$n повторений',
      few: '$n повторения',
      one: '$n повторение',
    );
    return '$_temp0';
  }

  @override
  String get planEntryDaysRehearsal => 'репетиция';

  @override
  String get planEntryDateTitle => 'Когда разговор?';

  @override
  String get planEntryDateLabel => 'Дата';

  @override
  String planEntryDateIn(String weekday, int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'через $n дня',
      many: 'через $n дней',
      few: 'через $n дня',
      one: 'через $n день',
    );
    return '$weekday · $_temp0';
  }

  @override
  String get planEntryDateUnknown => 'Дата пока неизвестна';

  @override
  String get planEntryDateUnknownSub => 'план без даты, дни идут подряд';

  @override
  String get planEntryDateOther => 'Другая дата';

  @override
  String get planEntryDateOtherSub => 'выбрать в календаре';

  @override
  String planEntryDateRehearsalOn(String date) {
    return 'Репетиция встанет на $date — день перед разговором';
  }

  @override
  String get planEntryDateCta => 'Собрать план';

  @override
  String get planEntryPreviewTitle => 'Твой план готов';

  @override
  String get planEntryPreviewLoadingTitle => 'Собираю план';

  @override
  String get planEntryPreviewAbout => 'Около 10 секунд';

  @override
  String get planEntryPreviewLine1 => 'Подбираю ситуации под твой разговор';

  @override
  String get planEntryPreviewLine2 => 'Раскладываю их по дням';

  @override
  String get planEntryPreviewLine3 => 'Собираю слова и фразы';

  @override
  String get planEntryPreviewHowLabel => 'Как это будет';

  @override
  String get planEntryPreviewEdit => 'Изменить';

  @override
  String get planEntryPreviewCta => 'Начать';

  @override
  String get planEntryPreviewErrorTitle => 'План не собрался';

  @override
  String get planEntryPreviewErrorWhat => 'Сеть пропала на середине';

  @override
  String get planEntryPreviewErrorSub =>
      'Ответы сохранены — попробуй ещё раз, заново рассказывать не придётся';

  @override
  String get planEntryPreviewErrorRetry => 'Попробовать ещё';

  @override
  String get planEntryPreviewUnclearTitle => 'Нужно чуть больше';

  @override
  String planEntryPreviewUnclearQuote(String goal) {
    return '«$goal» — это про что?';
  }

  @override
  String get planEntryPreviewUnclearSub =>
      'Напиши, где будешь говорить и с кем: приём у врача, звонок в банк, разговор с соседом';

  @override
  String get planEntryPreviewUnclearCta => 'К цели';

  @override
  String get planEntryPushTitle => 'План готов';

  @override
  String planEntryPushBody(String until, String dayTitle) {
    return '$until. День 1 — «$dayTitle»';
  }

  @override
  String planEntryPushBodyNoDate(String days, String dayTitle) {
    return '$days. День 1 — «$dayTitle»';
  }

  @override
  String planNotifyDayReadyTitle(int n) {
    return 'День $n собран';
  }

  @override
  String planNotifyDayReadyBody(String title) {
    return '«$title» — можно начинать';
  }

  @override
  String planNotifyReminderTitle(int n) {
    return 'День $n ждёт';
  }

  @override
  String planNotifyReminderBody(String title) {
    return '«$title» — начни с того места, где остановился';
  }

  @override
  String planNotifyEventTodayTitle(String event) {
    return 'Сегодня $event';
  }

  @override
  String get planNotifyEventTodayTitleNoName => 'Сегодня разговор';

  @override
  String get planNotifyEventTodayBody => 'Скажи сам перед разговором — прогони его вслух';

  @override
  String planNotifySkippedTitle(int n) {
    return 'День $n ждёт со вчера';
  }

  @override
  String get planNotifySkippedBody => 'Маршрут сдвинулся: следующие дни пойдут от сегодня';

  @override
  String get planBannerNow => 'сейчас';

  @override
  String get planBannerAppMark => 'С';

  @override
  String get planEntryOffline => 'Без сети план не собрать';

  @override
  String get planWindowBack => 'Назад';

  @override
  String get planWindowStateNotStarted => 'не начат';

  @override
  String get planWindowStateInProgress => 'идёт';

  @override
  String planWindowApprox(String minutes) {
    return '≈ $minutes';
  }

  @override
  String planWindowJoin(String first, String second) {
    return '$first · $second';
  }

  @override
  String planWindowJoinNumber(String first, String second) {
    return '$first · $second';
  }

  @override
  String planWindowPassedLine(String minutes) {
    return 'День пройден · $minutes';
  }

  @override
  String planWindowBrowDone(int n) {
    return '$n пройдено';
  }

  @override
  String planWindowBrowReturns(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n вернутся завтра',
      many: '$n вернутся завтра',
      few: '$n вернутся завтра',
      one: '$n вернётся завтра',
    );
    return '$_temp0';
  }

  @override
  String get planWindowListen => 'Прослушать';

  @override
  String planWindowGoalsLearn(String goals) {
    return 'Научишься $goals';
  }

  @override
  String get planWindowGoalsLearned => 'Научился:';

  @override
  String planWindowGoalsJoin(String head, String next) {
    return '$head, $next';
  }

  @override
  String planWindowGoalsJoinLast(String head, String last) {
    return '$head и $last';
  }

  @override
  String get planWindowSheetTalk => 'В разговоре';

  @override
  String get planWindowSheetNotStarted => 'не начато';

  @override
  String planWindowSheetReturnsOn(int n) {
    return 'вернётся в день $n';
  }

  @override
  String get planWindowSheetReturnsTomorrow => 'вернётся завтра';

  @override
  String get planWindowSheetClose => 'Закрыть';

  @override
  String get profileRowSounds => 'Звуки';

  @override
  String get profileSoundsHint => 'Верно · неверно · этап закрыт · день закрыт';

  @override
  String get profileRowSessionSounds => 'Звуки в сессии';

  @override
  String get profileSessionSoundsHint => 'Верно · мимо · запись · этап пройден';

  @override
  String dayCards(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n карточек',
      few: '$n карточки',
      one: '$n карточка',
    );
    return '$_temp0';
  }

  @override
  String dayMinutes(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n минут',
      few: '$n минуты',
      one: '$n минута',
    );
    return '$_temp0';
  }

  @override
  String get planSessionStateDone => 'пройден';

  @override
  String get planSessionStateAhead => 'впереди';

  @override
  String planSessionApproxMinutes(int n) {
    return '≈ $n мин';
  }

  @override
  String planSessionDescWords(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n слова дня',
      many: '$n слов дня',
      few: '$n слова дня',
      one: '$n слово дня',
    );
    return '$_temp0 — посмотри, послушай и скажи вслух';
  }

  @override
  String planSessionDescPhrases(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n фразы дня',
      many: '$n фраз дня',
      few: '$n фразы дня',
      one: '$n фраза дня',
    );
    return '$_temp0 — одно окно меняется, фраза остаётся';
  }

  @override
  String get planSessionDescDialogue => 'Разговор по шагам: пойми реплику и ответь фразами дня';

  @override
  String get planSessionDescListen => 'Весь разговор на слух, потом вопросы о нём';

  @override
  String get planSessionDescSpeak =>
      'Собеседник спрашивает — отвечай про себя, своими словами. Каркасы ты знаешь, окно — твоё';

  @override
  String get planSessionNoHints => 'Без подсказок';

  @override
  String get planSessionNoHintsSub => 'Диалог и «Говорю сам» — сразу голосом';

  @override
  String get planSessionNoHintsTalk => 'В разговоре — без подсказок, текст собеседника закрыт';

  @override
  String get planSessionStart => 'Начать';

  @override
  String planSessionBuild(String version) {
    return 'сборка $version';
  }

  @override
  String planSessionLeftWords(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'ещё $n слова',
      many: 'ещё $n слов',
      few: 'ещё $n слова',
      one: 'ещё $n слово',
    );
    return '$_temp0';
  }

  @override
  String planSessionLeftPhrases(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'ещё $n фразы',
      many: 'ещё $n фраз',
      few: 'ещё $n фразы',
      one: 'ещё $n фраза',
    );
    return '$_temp0';
  }

  @override
  String planSessionSceneLine(String scene, String role) {
    return '$scene · $role';
  }

  @override
  String get planSessionClose => 'Закрыть';

  @override
  String get planSessionTaskRememberWord => 'Запомни слово';

  @override
  String get planSessionTaskSayWord => 'Скажи слово вслух';

  @override
  String planSessionCompanionSayWord(String role) {
    return 'Скажи, как слышишь — $role поймёт';
  }

  @override
  String get planSessionTaskChooseTranslation => 'Выбери перевод';

  @override
  String get planSessionTaskChooseWord => 'Выбери слово';

  @override
  String get planSessionTaskChooseHeard => 'Выбери, что услышал';

  @override
  String get planSessionTaskAssembleParts => 'Собери из частей';

  @override
  String get planSessionTaskInsertWord => 'Вставь слово в окно';

  @override
  String get planSessionTaskLookListen => 'Посмотри и послушай';

  @override
  String get planSessionTaskAssemblePhrase => 'Собери фразу';

  @override
  String get planSessionTaskInsert => 'Вставь в окно';

  @override
  String get planSessionTaskSayPhrase => 'Скажи фразу вслух';

  @override
  String get planSessionTaskSayEachMeaning => 'Скажи фразу с каждым значением';

  @override
  String get planSessionOwnWordNow => 'а теперь со своим словом';

  @override
  String get planSessionStateReplay => 'повтор';

  @override
  String get planSessionTaskWhatAnswer => 'Что ты ответишь?';

  @override
  String get planSessionBrowWord => 'Слово';

  @override
  String get planSessionBrowTranslation => 'Перевод';

  @override
  String get planSessionBrowByEar => 'На слух';

  @override
  String get planSessionBrowSlot => 'Окно';

  @override
  String get planSessionBrowFrame => 'Каркас';

  @override
  String get planSessionBrowPhrase => 'Фраза';

  @override
  String get planSessionBrowAssembled => 'Собрано';

  @override
  String planSessionBrowPartnerAsks(String role) {
    return '$role · спрашивает';
  }

  @override
  String get planSessionWhatHeard => 'Что ты услышал?';

  @override
  String get planSessionUnderstood => 'Понятно';

  @override
  String get planSessionCheck => 'Проверить';

  @override
  String get planSessionNext => 'Дальше';

  @override
  String get planSessionByParts => 'по частям';

  @override
  String get planSessionMicTap => 'тап — говорить';

  @override
  String get planSessionMicListening => 'говори, я слушаю';

  @override
  String get planSessionMicHeard => 'услышал';

  @override
  String get planSessionPlaying => 'играет';

  @override
  String get planSessionMicMissed => 'не расслышал, ещё раз';

  @override
  String get planSessionEcho => 'услышал — так же, как в записи';

  @override
  String get planSessionSkip => 'Пропустить';

  @override
  String get planSessionDebugHeard => 'что услышал';

  @override
  String get planSessionNoMicTitle => 'Нужен микрофон';

  @override
  String get planSessionNoMicBody =>
      'Без него этапы «Слушаю и отвечаю» и «Говорю сам» не пройти. Слова и фразы можно делать выбором и плитками.';

  @override
  String get planSessionNoMicAllow => 'Разрешить';

  @override
  String get planSessionTryAgain => 'Ещё раз';

  @override
  String get planSessionReturnsTomorrow => 'Вернётся завтра';

  @override
  String get planSessionExitTitle => 'Выйти? Прогресс сохранится';

  @override
  String planSessionExitBody(String stage) {
    return 'Сделанные карточки этапа «$stage» останутся закрытыми — продолжишь с того же места.';
  }

  @override
  String get planSessionExitStay => 'Продолжить';

  @override
  String get planSessionExitLeave => 'Выйти';

  @override
  String get planSessionOffline => 'нет связи — ответ отправится, как только сеть вернётся';

  @override
  String get planSessionLoadFailed => 'День не загрузился';

  @override
  String planSessionLeftExchanges(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'ещё $n реплики',
      many: 'ещё $n реплик',
      few: 'ещё $n реплики',
      one: 'ещё $n реплика',
    );
    return '$_temp0';
  }

  @override
  String planSessionLeftQuestions(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'ещё $n вопроса',
      many: 'ещё $n вопросов',
      few: 'ещё $n вопроса',
      one: 'ещё $n вопрос',
    );
    return '$_temp0';
  }

  @override
  String get planSessionLastQuestion => 'последний вопрос';

  @override
  String get planSessionWholeTalk => 'разговор целиком';

  @override
  String get planSessionReviewLabel => 'разбор';

  @override
  String get planSessionPaceLabel => 'темп';

  @override
  String get planSessionTaskCollectAnswer => 'Собери ответ';

  @override
  String get planSessionTaskSayLine => 'Скажи свою реплику';

  @override
  String get planSessionTaskRescue => 'Не понял — переспроси';

  @override
  String get planSessionAnyChip => 'любое — твой ответ';

  @override
  String get planSessionNotUnderstood => 'Не понял';

  @override
  String get planSessionSlowly => 'медленно';

  @override
  String get planSessionTaskListenTalk => 'Послушай разговор';

  @override
  String get planSessionByPartsAction => 'По частям';

  @override
  String get planSessionContinue => 'Продолжить';

  @override
  String get planSessionReplay => 'Ещё раз';

  @override
  String planSessionExchangesCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n обмена',
      many: '$n обменов',
      few: '$n обмена',
      one: '$n обмен',
    );
    return '$_temp0';
  }

  @override
  String planSessionPlayerPaused(int n) {
    return 'пауза · обмен $n';
  }

  @override
  String get planSessionPause => 'Пауза';

  @override
  String planSessionPlayerRoles(String role) {
    return '$role и ты';
  }

  @override
  String get planSessionPlayerDone => 'дослушал';

  @override
  String get planSessionYou => 'ты';

  @override
  String get planSessionTaskWhatUnderstood => 'Что ты понял?';

  @override
  String get planSessionBrowQuestion => 'Вопрос';

  @override
  String get planSessionFromMemory => 'по памяти · звука нет';

  @override
  String get planSessionTaskWhereHeard => 'Где это прозвучало';

  @override
  String get planSessionTaskListenChoose => 'Послушай и выбери ответ';

  @override
  String get planSessionThisIsAnswer => 'Это ответ';

  @override
  String get planSessionTaskNormalPace => 'А теперь в обычном темпе';

  @override
  String planSessionBrowSlowRate(String rate) {
    return 'Медленно · $rate×';
  }

  @override
  String get planSessionBrowNormalPace => 'В обычном темпе';

  @override
  String get planSessionTextOpen => 'текст открыт';

  @override
  String get planSessionTextClosed => 'текст закрыт';

  @override
  String get planSessionUnderstoodAction => 'Понял';

  @override
  String get planSessionTaskCatchNumber => 'Поймай число';

  @override
  String get planSessionWhichNumber => 'Какое число прозвучало?';

  @override
  String get planSessionTaskAskYourself => 'Спроси сам';

  @override
  String planSessionAskIntent(String text) {
    return 'Спроси: «$text»';
  }

  @override
  String get planSessionTaskAnswerOwnWords => 'Ответь своими словами';

  @override
  String get planSessionTaskRepeatPause => 'Повтори через паузу';

  @override
  String get planSessionTaskRetell => 'Повтори свою реплику';

  @override
  String get planSessionBrowLine => 'Реплика';

  @override
  String get planSessionListenCue => 'слушай';

  @override
  String get planSessionWaiting => 'жду';

  @override
  String get planSessionNotThat => 'не то — попробуем ещё';

  @override
  String get planSessionHintAction => 'Подсказать';

  @override
  String get planSessionDayTotal => 'Итог дня';

  @override
  String planSessionDayDoneTitle(String minutes) {
    return 'День пройден · $minutes';
  }

  @override
  String get planSessionCloseDay => 'Закрыть день';

  @override
  String planSessionDayReturns(String cards, String parts) {
    return '$cards: $parts.';
  }

  @override
  String planSessionReturnWords(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n слова',
      many: '$n слов',
      few: '$n слова',
      one: '$n слово',
    );
    return '$_temp0';
  }

  @override
  String planSessionReturnPhrases(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n фразы',
      many: '$n фраз',
      few: '$n фразы',
      one: '$n фраза',
    );
    return '$_temp0';
  }

  @override
  String planSessionReturnExchanges(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n реплики',
      many: '$n реплик',
      few: '$n реплики',
      one: '$n реплика',
    );
    return '$_temp0';
  }

  @override
  String planSessionAnd(String a, String b) {
    return '$a и $b';
  }

  @override
  String planSessionNextDayBuilding(int n) {
    return 'День $n — собираю';
  }

  @override
  String planSessionNextDayReady(int n) {
    return 'День $n — готов';
  }

  @override
  String get planSessionCloseFailed => 'День не закрылся — нет связи';

  @override
  String get planPlateStageTalk => 'Разговор';

  @override
  String get planPlateStageRecall => 'Вспомнить';

  @override
  String planTalkEntryMinutes(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'около $n минуты',
      many: 'около $n минут',
      few: 'около $n минут',
      one: 'около $n минуты',
    );
    return '$_temp0';
  }

  @override
  String get planTalkEntryWhole => 'Разговор целиком';

  @override
  String planTalkEntryScenes(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n сцены',
      many: '$n сцен',
      few: '$n сцены',
      one: '$n сцена',
    );
    return '$_temp0';
  }

  @override
  String get planTalkEntryRuleStart => 'Собеседник начнёт первым. Отвечай и спрашивай сам.';

  @override
  String get planTalkEntryRuleRescue => 'Не понял — нажми «Не понял», и он повторит проще.';

  @override
  String get planTalkEntryRuleCounts => 'Считается: сказал сам, фразы дня, понял вопросы.';

  @override
  String get planTalkStart => 'Начать разговор';

  @override
  String get planTalkRescueAction => 'Не понял';

  @override
  String planTalkHintChip(String intent) {
    return 'Скажи, что $intent';
  }

  @override
  String get planTalkOpenText => 'текст';

  @override
  String get planTalkInterrupted => 'прервано';

  @override
  String get planTalkSilenceEnds => 'тишина — конец';

  @override
  String get planTalkUnheard => 'не расслышал — скажи ещё раз';

  @override
  String get planTalkOffline => 'Связь пропала — разговор продолжится отсюда';

  @override
  String get planTalkSilent => 'Собеседник не отвечает — попробуй ещё раз';

  @override
  String get planTalkOpenFailed => 'Разговор не начался — попробуй ещё раз';

  @override
  String get planTalkNotInDay => 'В этом дне разговора нет';

  @override
  String get planTalkEnded => 'Разговор окончен';

  @override
  String get planTalkSummaryAction => 'Итог';

  @override
  String planTalkSaidLines(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'Сказал сам $n реплики',
      many: 'Сказал сам $n реплик',
      few: 'Сказал сам $n реплики',
      one: 'Сказал сам $n реплику',
    );
    return '$_temp0';
  }

  @override
  String get planTalkUnderstoodAll => 'Понял все вопросы';

  @override
  String planTalkUnderstoodExcept(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'Понял вопросы, кроме $n — вернутся',
      one: 'Понял вопросы, кроме одного — вернётся',
    );
    return '$_temp0';
  }

  @override
  String planTalkRescues(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'переспросил $n раза',
      many: 'переспросил $n раз',
      few: 'переспросил $n раза',
      one: 'переспросил $n раз',
    );
    return '$_temp0';
  }

  @override
  String get planTalkReady => 'Ты готов к приёму';

  @override
  String get planTalkHighlights => 'Что было хорошо';

  @override
  String get planSessionListenWholeOne => 'Слушай целиком — ответ один';

  @override
  String get planSessionFrameWhole => 'Эту фразу говорят целиком — в ней ничего не меняется';

  @override
  String get planSessionChangeable => 'эту часть можно менять';

  @override
  String get planSessionInTalk => 'В разговоре';

  @override
  String get planSessionOwnWordChip => 'своё слово';

  @override
  String get planTalkNoMic => 'Нужен микрофон — без него разговор не пройти';

  @override
  String get planSessionDescRecall => 'Свои реплики всех сцен — посмотри, послушай и скажи вслух';

  @override
  String get planSessionRecallTask => 'Вспомни свои реплики';

  @override
  String get planSessionRecallLast => 'Дальше — повтори вслух';

  @override
  String get planWindowRehearsalBefore => 'перед событием';

  @override
  String planWindowOnWeekday(String weekday) {
    String _temp0 = intl.Intl.selectLogic(weekday, {
      'mon': 'в понедельник',
      'tue': 'во вторник',
      'wed': 'в среду',
      'thu': 'в четверг',
      'fri': 'в пятницу',
      'sat': 'в субботу',
      'sun': 'в воскресенье',
      'other': '',
    });
    return '$_temp0';
  }

  @override
  String get planWindowReviewTitle => 'Что уже было';

  @override
  String get planWindowRehearsalLead => 'Проговоришь весь разговор с собеседником';

  @override
  String get planWindowReviewLead => 'Вернёшь фразы прошлых дней и поговоришь с собеседником';

  @override
  String get planWindowFromScenes => 'Из каких сцен';

  @override
  String get planWindowFromDays => 'Из каких дней';

  @override
  String get planSessionTaskUnderstood => 'Проверь, что понял';

  @override
  String planSessionHeardLine(String text) {
    return 'услышал: $text';
  }

  @override
  String get planPlateStageRepetition => 'Повторение';

  @override
  String get planSessionPassedWords => 'Слова пройдены';

  @override
  String get planSessionPassedPhrases => 'Фразы пройдены';

  @override
  String get planSessionPassedDialogue => 'Диалог пройден';

  @override
  String get planSessionPassedListen => 'Слушаю и отвечаю — пройдено';

  @override
  String get planSessionPassedSpeak => 'Говорю сам — пройдено';

  @override
  String get planSessionPassedRecall => 'Вспомнить — пройдено';

  @override
  String get planSessionPassedRepetition => 'Повторение пройдено';

  @override
  String planSessionPassedMinutes(String title, String minutes) {
    return '$title · $minutes';
  }

  @override
  String planSessionFirstTryWords(int n, int first) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n слова, $first с первого раза',
      many: '$n слов, $first с первого раза',
      few: '$n слова, $first с первого раза',
      one: '$n слово, $first с первого раза',
    );
    return '$_temp0';
  }

  @override
  String planSessionFirstTryPhrases(int n, int first) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n фразы, $first с первого раза',
      many: '$n фраз, $first с первого раза',
      few: '$n фразы, $first с первого раза',
      one: '$n фраза, $first с первого раза',
    );
    return '$_temp0';
  }

  @override
  String planSessionFirstTryLines(int n, int first) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n реплики, $first с первого раза',
      many: '$n реплик, $first с первого раза',
      few: '$n реплики, $first с первого раза',
      one: '$n реплика, $first с первого раза',
    );
    return '$_temp0';
  }

  @override
  String planSessionFirstTryQuestions(int n, int first) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n вопроса, $first с первого раза',
      many: '$n вопросов, $first с первого раза',
      few: '$n вопроса, $first с первого раза',
      one: '$n вопрос, $first с первого раза',
    );
    return '$_temp0';
  }

  @override
  String planSessionFirstTryCards(int n, int first) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n карточки, $first с первого раза',
      many: '$n карточек, $first с первого раза',
      few: '$n карточки, $first с первого раза',
      one: '$n карточка, $first с первого раза',
    );
    return '$_temp0';
  }

  @override
  String planSessionRecallLinesOfScenes(int n, int scenes) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n реплики',
      many: '$n реплик',
      few: '$n реплики',
      one: '$n реплика',
    );
    String _temp1 = intl.Intl.pluralLogic(
      scenes,
      locale: localeName,
      other: '$scenes сцены',
      many: '$scenes сцен',
      few: '$scenes сцен',
      one: '$scenes сцены',
    );
    return '$_temp0 из $_temp1';
  }

  @override
  String planSessionStageReturns(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n вернутся завтра',
      many: '$n вернутся завтра',
      few: '$n вернутся завтра',
      one: '$n вернётся завтра',
    );
    return '$_temp0';
  }

  @override
  String get planSessionStageNoReturns => 'завтра ничего не вернётся';

  @override
  String planSessionSaidAloud(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'сказал вслух $n своей реплики',
      many: 'сказал вслух $n своих реплик',
      few: 'сказал вслух $n свои реплики',
      one: 'сказал вслух $n свою реплику',
    );
    return '$_temp0';
  }

  @override
  String planSessionRescuedSlower(int n, String role) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n раза переспросил — $role повторил медленнее',
      many: '$n раз переспросил — $role повторил медленнее',
      few: '$n раза переспросил — $role повторил медленнее',
      two: 'дважды переспросил — $role повторил медленнее',
      one: 'переспросил — $role повторил медленнее',
    );
    return '$_temp0';
  }

  @override
  String get planSessionWarmWords =>
      'Эти слова ты теперь узнаёшь — дальше они встретятся во фразах';

  @override
  String get planSessionWarmPhrases => 'Фразы собраны и сказаны вслух — в диалоге они пригодятся';

  @override
  String get planSessionWarmListen => 'Реплики собеседника ты понимаешь на слух';

  @override
  String get planSessionWarmSpeak => 'Свои реплики ты сказал сам — дальше живой разговор';

  @override
  String get planSessionWarmRecall => 'Реплики на месте — дальше разговор целиком';

  @override
  String get planSessionWarmRepetition => 'Всё, что возвращалось, сказано ещё раз';

  @override
  String get planTalkEntrySay => 'Скажи в разговоре';

  @override
  String get planVoiceTitle => 'Каким голосом озвучивать твои реплики?';

  @override
  String get planVoiceMale => 'Мужской';

  @override
  String get planVoiceMaleHint => 'ниже и спокойнее';

  @override
  String get planVoiceFemale => 'Женский';

  @override
  String get planVoiceFemaleHint => 'выше и мягче';

  @override
  String get planVoiceInProfile => 'Можно поменять в профиле';

  @override
  String get profileRowVoice => 'Голос своих реплик';

  @override
  String get profileVoiceUnset => 'не выбран';

  @override
  String get planTalkConstructions => 'Конструкции в разговоре';

  @override
  String get planTalkConstruction => 'Конструкция';

  @override
  String get planTalkFromLesson => 'Из урока';

  @override
  String get planTalkYouSaidLabel => 'Ты сказал';

  @override
  String planTalkYouSaid(String said) {
    return 'ты сказал: $said';
  }

  @override
  String get planTalkRepeatBefore => 'повтори перед событием';

  @override
  String planWindowReturnedFromDay(int n) {
    return 'Вернулось из дня $n';
  }

  @override
  String get planWindowReturnedFrom => 'Вернулось';

  @override
  String get planWindowStageAgain => 'ещё раз';

  @override
  String get planWindowTalkLimitToday => 'лимит на сегодня';

  @override
  String get planWindowDaySummary => 'Итог дня';

  @override
  String get planWindowSummary => 'Итог';

  @override
  String get planWindowTalkReplayLimit => 'Разговор сегодня уже повторяли — вернись завтра';
}

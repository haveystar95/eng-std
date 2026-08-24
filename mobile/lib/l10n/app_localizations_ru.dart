// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Russian (`ru`).
class AppLocalizationsRu extends AppLocalizations {
  AppLocalizationsRu([String locale = 'ru']) : super(locale);

  @override
  String triageCounter(int current, int total) {
    return '$current из $total';
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
    return 'Ещё $count после синхронизации — зайдите снова, когда будет сеть.';
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
      other: 'Ещё $count слов после синхронизации',
      many: 'Ещё $count слов после синхронизации',
      few: 'Ещё $count слова после синхронизации',
      one: 'Ещё $count слово после синхронизации',
    );
    return '$_temp0';
  }

  @override
  String triageLoadError(String error) {
    return 'Не удалось загрузить: $error';
  }

  @override
  String get homeDailyGoal => 'Дневная цель';

  @override
  String homeGoalCount(int done, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      total,
      locale: localeName,
      other: '$done / $total слова',
      many: '$done / $total слов',
      few: '$done / $total слова',
      one: '$done / $total слово',
    );
    return '$_temp0';
  }

  @override
  String homeStreakActive(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Стрик — $count дня',
      many: 'Стрик — $count дней',
      few: 'Стрик — $count дня',
      one: 'Стрик — $count день',
    );
    return '$_temp0';
  }

  @override
  String get homeStreakStartToday => 'Стрик начнётся сегодня';

  @override
  String homeReviewButton(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Повторить $count слова',
      many: 'Повторить $count слов',
      few: 'Повторить $count слова',
      one: 'Повторить $count слово',
    );
    return '$_temp0';
  }

  @override
  String homeLearnButton(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Учить $count слова',
      many: 'Учить $count слов',
      few: 'Учить $count слова',
      one: 'Учить $count слово',
    );
    return '$_temp0';
  }

  @override
  String get homeLearnSubtitle => 'Новые слова — первый разбор';

  @override
  String homeTriageButton(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Разобрать $count слова',
      many: 'Разобрать $count слов',
      few: 'Разобрать $count слова',
      one: 'Разобрать $count слово',
    );
    return '$_temp0';
  }

  @override
  String get homePracticeButton => 'Повторить';

  @override
  String get homePracticeSubtitle => 'Учебная тренировка';

  @override
  String get homeGenerateTitle => 'Опиши тему — соберём коллекцию';

  @override
  String get homeGenerateSubtitle => 'ИИ подберёт слова и фразы, которые реально нужны';

  @override
  String get homeGeneratePlaceholder => 'Например: визит к врачу';

  @override
  String get homeGenerateChipDoctor => 'У врача';

  @override
  String get homeGenerateChipRent => 'Аренда';

  @override
  String get homeGenerateChipInterview => 'Собеседование';

  @override
  String get homeGenerateFreeTier => '3 генерации в день на бесплатном тарифе';

  @override
  String get homeWordOfDay => 'Слово дня';

  @override
  String get homeMyCollections => 'Мои коллекции';

  @override
  String get homeSeeAll => 'Все';

  @override
  String homeCollectionProgress(int done, int total) {
    String _temp0 = intl.Intl.pluralLogic(
      total,
      locale: localeName,
      other: '$done из $total слов',
      many: '$done из $total слов',
      few: '$done из $total слов',
      one: '$done из $total слова',
    );
    return '$_temp0';
  }

  @override
  String get tabHome => 'Главная';

  @override
  String get tabCollections => 'Коллекции';

  @override
  String get tabProfile => 'Профиль';

  @override
  String get homeSessionTitle => 'Занятие';

  @override
  String collectionWordsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count слова',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
    );
    return '$_temp0';
  }

  @override
  String collectionDueSuffix(int count) {
    return '$count к повторению сегодня';
  }

  @override
  String collectionDensityConfirmed(int count) {
    return 'Подтверждено $count';
  }

  @override
  String collectionDensityFamiliar(int count) {
    return 'Знакомое $count';
  }

  @override
  String collectionDensityInProgress(int count) {
    return 'В работе $count';
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
      other: '$count слова',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
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
      other: '$count слова',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
    );
    return '$_temp0 · освоено $mastered';
  }

  @override
  String get generationGeneratingTitle => 'Собираем коллекцию…';

  @override
  String generationGeneratingMeta(String topic, String levels, String size) {
    return '$topic · $levels · $size';
  }

  @override
  String get generationGeneratingNote => 'Подбираем слова и фотографии · обычно 20–30 секунд';

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
    return '$delivered из $requested';
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
      other: 'Осталось $count генераций сегодня',
      many: 'Осталось $count генераций сегодня',
      few: 'Осталось $count генерации сегодня',
      one: 'Осталось $count генерация сегодня',
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
  String get generatePremiumUpsell => 'Нужно больше? Premium — до 20 в день';

  @override
  String get generateManual => 'Собрать коллекцию вручную';

  @override
  String generateVoiceListening(String time) {
    return 'Слушаю · $time';
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
  String get tabSearch => 'Поиск';

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
  String get searchBuildCardNote => 'Значение, пример и фото. Повторно — бесплатно';

  @override
  String get searchLooking => 'Ищем…';

  @override
  String get searchBuildTranslation => 'перевод';

  @override
  String get searchBuildMeaning => 'значение';

  @override
  String get searchBuildExample => 'пример';

  @override
  String get searchBuildPhoto => 'фото';

  @override
  String get searchBuildNote => 'Пара секунд. Можно закрыть — карточка появится в поиске.';

  @override
  String searchLimitUsed(int used, int cap) {
    return '$used из $cap на сегодня';
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
  String searchSavedTo(String collection) {
    return 'Сохранено в коллекцию «$collection» — слово учится';
  }

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
    return '$step из $total';
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
  String get tabProgress => 'Прогресс';

  @override
  String get progressTitle => 'Прогресс';

  @override
  String progressStreakDays(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count дня подряд',
      many: '$count дней подряд',
      few: '$count дня подряд',
      one: '$count день подряд',
    );
    return '$_temp0';
  }

  @override
  String progressBestResult(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: 'Лучший результат — $count дня',
      many: 'Лучший результат — $count дней',
      few: 'Лучший результат — $count дня',
      one: 'Лучший результат — $count день',
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
      other: 'Все $count слов',
      many: 'Все $count слов',
      few: 'Все $count слова',
      one: 'Все $count слово',
    );
    return '$_temp0';
  }

  @override
  String get homeLimitReachedTitle => 'Лимит новых на сегодня';

  @override
  String get homeLimitReachedHint =>
      'Новые слова — завтра. Сейчас можно повторять свободной тренировкой в коллекциях.';

  @override
  String get homeAllDoneTitle => 'На сегодня всё';

  @override
  String homeAllDoneSubtitle(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count повторений сделано, новых слов в очереди нет.',
      many: '$count повторений сделано, новых слов в очереди нет.',
      few: '$count повторения сделано, новых слов в очереди нет.',
      one: '$count повторение сделано, новых слов в очереди нет.',
    );
    return '$_temp0';
  }

  @override
  String get homeAllDonePractice => 'Свободная тренировка';

  @override
  String get homeAllDoneGenerate => 'Собрать новую коллекцию';

  @override
  String get homeOfflineBanner =>
      'Нет сети. Повторения идут как обычно — синхронизируем, когда связь вернётся.';

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
    return '≈ $count минут в день';
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
  String get profileRowReminders => 'Напоминания';

  @override
  String get profileRemindersHint => 'Одно в день, если есть что повторить';

  @override
  String get profileRowReminderTime => 'Время';

  @override
  String get profileFreeTier => 'Бесплатный тариф';

  @override
  String get profileFreeTierHint => '3 генерации в день';

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
      other: '$count слов',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
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
      other: '$count слов',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
    );
    return '$_temp0';
  }

  @override
  String deleteAccountStreak(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count дней стрика',
      many: '$count дней стрика',
      few: '$count дня стрика',
      one: '$count день стрика',
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
  String get sessionInstrChoose => 'выбери английский эквивалент';

  @override
  String get sessionInstrAssemble => 'собери из слов';

  @override
  String get sessionAssemblyEmptyHint => 'Собери из слов ниже';

  @override
  String get sessionInstrAssembleSentence => 'собери предложение из слов';

  @override
  String get sessionInstrType => 'напиши по-английски';

  @override
  String get sessionInstrListenChoose => 'прослушай и выбери перевод · можно повторить';

  @override
  String get sessionInstrListenType => 'прослушай и напиши по-английски';

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
  String get sessionSpeakSkip => 'Пропустить';

  @override
  String get sessionSpeakSkipHint => 'Пропуск ничего не испортит: слово вернётся своим чередом.';

  @override
  String get sessionSpeakHint => 'Проверяем, вспомнил ли ты слово, а не произношение.';

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
  String get ladderTitle => 'ЛЕСТНИЦА СЛОВА';

  @override
  String get ladderKnownDash => 'знаю';

  @override
  String get ladderTrainWord => 'Тренировать слово';

  @override
  String get ladderTrainLockedIntro =>
      'Слово откроется для практики после знакомства с ним в учебной тренировке.';

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
      other: 'через $days дней',
      many: 'через $days дней',
      few: 'через $days дня',
      one: 'через $days день',
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
      other: 'Стрик — $days дней',
      many: 'Стрик — $days дней',
      few: 'Стрик — $days дня',
      one: 'Стрик — $days день',
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
  String get practiceDialogEntry => 'Разговор · 3 мин';

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
    return 'Слов прозвучало: $used из $total';
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
    return 'слов: $used из $total';
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
      other: '$count слова',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
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
    return 'Открываются все $count наборов сразу';
  }

  @override
  String get storeInsideLabel => 'Что внутри';

  @override
  String storeMoreWords(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count слова',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
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
    return '$title и ещё $count наборов';
  }

  @override
  String get paywallSubtitleQuota => 'Premium поднимает дневной лимит до двадцати генераций.';

  @override
  String get paywallSubtitleStore =>
      'Премиум-коллекции собраны редакцией и открываются все сразу — по одной их не продаём.';

  @override
  String get paywallSubtitleGeneric => 'Один тариф открывает всё, что делает изучение быстрее.';

  @override
  String get paywallBenefitGenerations => 'До 20 генераций в день';

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
  String get paywallYearPerMonth => '\$2.50 в месяц';

  @override
  String get paywallPerMonth => 'в месяц';

  @override
  String get paywallDiscountBadge => '−50%';

  @override
  String get paywallContinue => 'Продолжить';

  @override
  String paywallLegalYear(String price) {
    return 'Подписка продлевается автоматически. $price за год списываются с Apple ID; отменить можно в настройках App Store не позднее чем за 24 часа до конца периода.';
  }

  @override
  String paywallLegalMonth(String price) {
    return 'Подписка продлевается автоматически. $price в месяц списываются с Apple ID; отменить можно в настройках App Store не позднее чем за 24 часа до конца периода.';
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
    return '3 генерации в день · сбрасываются в $time';
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
  String get poolNotStudyingNote => 'Слово в каталоге — ты его пока не учишь.';

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
  String get poolInCatalogue => 'в каталоге';

  @override
  String get myWordsTitle => 'Мои слова';

  @override
  String myWordsCount(int count) {
    String _temp0 = intl.Intl.pluralLogic(
      count,
      locale: localeName,
      other: '$count слова',
      many: '$count слов',
      few: '$count слова',
      one: '$count слово',
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
}

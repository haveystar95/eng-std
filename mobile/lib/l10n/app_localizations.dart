import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_en.dart';
import 'app_localizations_ru.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale) : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate = _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[Locale('en'), Locale('ru')];

  /// Счётчик карточек в шапке триажа (кадр 2.2, «3 из 10»).
  ///
  /// In ru, this message translates to:
  /// **'{current} из {total}'**
  String triageCounter(int current, int total);

  /// Обучающая подсказка на лице первых карточек первой сессии (кадр 2.2 / 3a).
  ///
  /// In ru, this message translates to:
  /// **'Свайпай или жми кнопки · тап — перевернуть'**
  String get triageSwipeHint;

  /// Кнопка/вердикт: свайп влево.
  ///
  /// In ru, this message translates to:
  /// **'Не знаю'**
  String get triageVerdictUnknown;

  /// Кнопка/вердикт: свайп вверх.
  ///
  /// In ru, this message translates to:
  /// **'Не уверен'**
  String get triageVerdictUnsure;

  /// Кнопка/вердикт: свайп вправо.
  ///
  /// In ru, this message translates to:
  /// **'Знаю'**
  String get triageVerdictKnown;

  /// Третичная кнопка отмены последнего вердикта (кадр 2.2). Прежнее «Отменить последний» — прилагательное без существительного (QA-OBS-25).
  ///
  /// In ru, this message translates to:
  /// **'Вернуть слово'**
  String get triageUndo;

  /// Бейдж типа термина на обороте карточки.
  ///
  /// In ru, this message translates to:
  /// **'слово'**
  String get triageTermTypeWord;

  /// Бейдж типа термина (фраза; сюда же неизвестные типы).
  ///
  /// In ru, this message translates to:
  /// **'фраза'**
  String get triageTermTypePhrase;

  /// Бейдж типа термина.
  ///
  /// In ru, this message translates to:
  /// **'идиома'**
  String get triageTermTypeIdiom;

  /// Бейдж типа термина: фразовый глагол.
  ///
  /// In ru, this message translates to:
  /// **'фраз. глагол'**
  String get triageTermTypePhrasalVerb;

  /// Пустое состояние: на сервере не осталось новых терминов.
  ///
  /// In ru, this message translates to:
  /// **'Всё разобрано'**
  String get triageAllDoneTitle;

  /// Пояснение к «Всё разобрано».
  ///
  /// In ru, this message translates to:
  /// **'В этом наборе не осталось новых слов для разбора.'**
  String get triageAllDoneBody;

  /// Пустое состояние: страница пуста, но на сервере ещё есть.
  ///
  /// In ru, this message translates to:
  /// **'На сейчас всё'**
  String get triageMoreLaterTitle;

  /// Пояснение к «На сейчас всё».
  ///
  /// In ru, this message translates to:
  /// **'Ещё {count} после синхронизации — зайдите снова, когда будет сеть.'**
  String triageMoreLaterBody(int count);

  /// Кнопка выхода из триажа.
  ///
  /// In ru, this message translates to:
  /// **'Готово'**
  String get triageDone;

  /// Итог: разобрана страница, но на сервере ещё осталось.
  ///
  /// In ru, this message translates to:
  /// **'Пачка разобрана'**
  String get triageSummaryBatchTitle;

  /// Итог: на сервере ничего не осталось.
  ///
  /// In ru, this message translates to:
  /// **'Разбор завершён'**
  String get triageSummaryDoneTitle;

  /// Итог сессии: лейбл счётчика «Знаю». Три подписи повторяют три вердикта карточки одной формой — 1 л. ед. ч. (QA-OBS-26).
  ///
  /// In ru, this message translates to:
  /// **'Знаю'**
  String get triageTallyKnown;

  /// Итог сессии: лейбл счётчика «Не знаю» — слово ушло в изучение.
  ///
  /// In ru, this message translates to:
  /// **'Учу'**
  String get triageTallyLearning;

  /// Итог сессии: лейбл счётчика «Не уверен».
  ///
  /// In ru, this message translates to:
  /// **'Не уверен'**
  String get triageTallyUnsure;

  /// Итог сессии: сколько терминов ещё придёт после синхронизации (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Ещё {count} слово после синхронизации} few{Ещё {count} слова после синхронизации} many{Ещё {count} слов после синхронизации} other{Ещё {count} слов после синхронизации}}'**
  String triageRemainingAfterSync(int count);

  /// Ошибка загрузки колоды триажа.
  ///
  /// In ru, this message translates to:
  /// **'Не удалось загрузить: {error}'**
  String triageLoadError(String error);

  /// Плейсхолдер поля темы на карточке генерации.
  ///
  /// In ru, this message translates to:
  /// **'Например: визит к врачу'**
  String get homeGeneratePlaceholder;

  /// Чип-пример темы.
  ///
  /// In ru, this message translates to:
  /// **'Собеседование'**
  String get homeGenerateChipInterview;

  /// Таб-бар: главная.
  ///
  /// In ru, this message translates to:
  /// **'Сегодня'**
  String get tabHome;

  /// Таб-бар: коллекции.
  ///
  /// In ru, this message translates to:
  /// **'Коллекции'**
  String get tabCollections;

  /// Заголовок экрана сессии, запущенной с главной (повторение / свободная тренировка).
  ///
  /// In ru, this message translates to:
  /// **'Занятие'**
  String get homeSessionTitle;

  /// Количество слов в коллекции, «24 слова» (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}}'**
  String collectionWordsCount(int count);

  /// Хвост подзаголовка коллекции про due-слова.
  ///
  /// In ru, this message translates to:
  /// **'{count} к повторению сегодня'**
  String collectionDueSuffix(int count);

  /// Легенда плотности, СЛОВАРЬ СТАТУСОВ (Ч.4): слово прошло все ступени. Было «Подтверждено».
  ///
  /// In ru, this message translates to:
  /// **'Освоено {count}'**
  String collectionDensityMastered(int count);

  /// Легенда плотности: слово в очереди тренажёра. Было «Знакомое». Фраза «В работе» в этой легенде раньше стояла на ТРЕТЬЕМ сегменте и значила «ещё не тронуто» — одно словосочетание с двумя противоположными значениями на соседних экранах.
  ///
  /// In ru, this message translates to:
  /// **'В работе {count}'**
  String collectionDensityInWork(int count);

  /// Легенда плотности: слово на полке, ждёт решения. Было «В работе».
  ///
  /// In ru, this message translates to:
  /// **'Разобрать {count}'**
  String collectionDensityToSort(int count);

  /// Главная кнопка коллекции: есть неразобранные слова.
  ///
  /// In ru, this message translates to:
  /// **'Разобрать {count}'**
  String collectionTriageButton(int count);

  /// Подстрока кнопки «Разобрать».
  ///
  /// In ru, this message translates to:
  /// **'Новые слова этой коллекции'**
  String get collectionTriageSubtitle;

  /// Кнопка коллекции: выучить новые отриаженные «не знаю» слова.
  ///
  /// In ru, this message translates to:
  /// **'Учить {count}'**
  String collectionLearnButton(int count);

  /// Главная кнопка коллекции: подошёл срок повторения.
  ///
  /// In ru, this message translates to:
  /// **'Повторить {count}'**
  String collectionReviewButton(int count);

  /// Главная кнопка коллекции: долг закрыт, тихая контурная кнопка.
  ///
  /// In ru, this message translates to:
  /// **'Свободная тренировка'**
  String get collectionPracticeButton;

  /// Подстрока кнопки свободной тренировки.
  ///
  /// In ru, this message translates to:
  /// **'Ничего не горит — можно просто позаниматься'**
  String get collectionPracticeSubtitle;

  /// Лейбл секции списка слов.
  ///
  /// In ru, this message translates to:
  /// **'Слова'**
  String get collectionWordsLabel;

  /// Метка коллекции на справочном языке (zh/ja): читаем и слушаем, но не тренируем.
  ///
  /// In ru, this message translates to:
  /// **'справочник'**
  String get collectionReferenceBadge;

  /// Озвучка бейджа пары для VoiceOver.
  ///
  /// In ru, this message translates to:
  /// **'Языковая пара: {learned} на {support}'**
  String pairBadgeSemantics(String learned, String support);

  /// Пояснение на экране справочной коллекции — почему нет кнопок тренировки.
  ///
  /// In ru, this message translates to:
  /// **'Справочная коллекция: слова можно читать и слушать. Тренажёров для этого языка пока нет.'**
  String get collectionReferenceHint;

  /// Кнопка добавления слова в коллекцию.
  ///
  /// In ru, this message translates to:
  /// **'Добавить слово'**
  String get collectionAddWord;

  /// Пустая коллекция — заголовок.
  ///
  /// In ru, this message translates to:
  /// **'Слов пока нет'**
  String get collectionEmptyTitle;

  /// Пустая коллекция — пояснение.
  ///
  /// In ru, this message translates to:
  /// **'Нажми «Добавить слово», чтобы добавить'**
  String get collectionEmptyBody;

  /// Баннер первого контакта на свежесгенерированной коллекции.
  ///
  /// In ru, this message translates to:
  /// **'Разбери коллекцию'**
  String get collectionTriageBannerTitle;

  /// Пояснение баннера разбора.
  ///
  /// In ru, this message translates to:
  /// **'Отметь, что уже знаешь — остальное пойдёт в тренировку'**
  String get collectionTriageBannerBody;

  /// Кнопка запуска разбора из баннера.
  ///
  /// In ru, this message translates to:
  /// **'Начать'**
  String get collectionTriageBannerStart;

  /// Действие: изменить (свайп/меню строки слова).
  ///
  /// In ru, this message translates to:
  /// **'Изменить'**
  String get actionEdit;

  /// Действие: удалить (свайп/меню строки слова, подтверждение).
  ///
  /// In ru, this message translates to:
  /// **'Удалить'**
  String get actionDelete;

  /// Заголовок подтверждения удаления слова (кадр 5d).
  ///
  /// In ru, this message translates to:
  /// **'Удалить «{term}»?'**
  String collectionDeleteWordTitle(String term);

  /// Текст подтверждения удаления слова (кадр 5d).
  ///
  /// In ru, this message translates to:
  /// **'Слово останется в других коллекциях, прогресс сохранится.'**
  String get collectionDeleteWordMessage;

  /// Заголовок шита добавления слова (кадр 5b).
  ///
  /// In ru, this message translates to:
  /// **'Добавить слово'**
  String get wordSheetAddTitle;

  /// Заголовок шита редактирования слова (кадр 5c).
  ///
  /// In ru, this message translates to:
  /// **'Изменить слово'**
  String get wordSheetEditTitle;

  /// Лейбл поля термина.
  ///
  /// In ru, this message translates to:
  /// **'Термин'**
  String get wordFieldTerm;

  /// Лейбл поля перевода.
  ///
  /// In ru, this message translates to:
  /// **'Перевод'**
  String get wordFieldTranslation;

  /// Плейсхолдер поля термина.
  ///
  /// In ru, this message translates to:
  /// **'слово или фраза'**
  String get wordTermHint;

  /// Плейсхолдер необязательного перевода (кадр 5b).
  ///
  /// In ru, this message translates to:
  /// **'необязательно — подберём сами'**
  String get wordTranslationHintOptional;

  /// Пояснение под полями при добавлении.
  ///
  /// In ru, this message translates to:
  /// **'Транскрипция, пример и фото подберутся автоматически.'**
  String get wordSheetAddHelper;

  /// Пояснение под полями при редактировании.
  ///
  /// In ru, this message translates to:
  /// **'Пример и фото останутся прежними, если не менять термин.'**
  String get wordSheetEditHelper;

  /// Главная кнопка шита добавления.
  ///
  /// In ru, this message translates to:
  /// **'Добавить в коллекцию'**
  String get wordSheetAddButton;

  /// Главная кнопка шита редактирования.
  ///
  /// In ru, this message translates to:
  /// **'Сохранить'**
  String get wordSheetSaveButton;

  /// Деструктивная ссылка внизу шита редактирования (кадр 5c).
  ///
  /// In ru, this message translates to:
  /// **'Удалить из коллекции'**
  String get wordSheetDeleteLink;

  /// Пункт меню слова: перенести в другую свою коллекцию.
  ///
  /// In ru, this message translates to:
  /// **'Перенести в…'**
  String get collectionMoveWord;

  /// Заголовок листа выбора коллекции при переносе слова.
  ///
  /// In ru, this message translates to:
  /// **'Куда перенести'**
  String get collectionMoveWordTitle;

  /// Подтверждение переноса.
  ///
  /// In ru, this message translates to:
  /// **'Перенесено в «{folder}»'**
  String collectionMoveWordDone(String folder);

  /// Ошибка переноса слова между коллекциями.
  ///
  /// In ru, this message translates to:
  /// **'Не удалось перенести'**
  String get collectionMoveWordFailed;

  /// Переносить некуда — у пользователя одна коллекция.
  ///
  /// In ru, this message translates to:
  /// **'Других своих коллекций пока нет'**
  String get collectionMoveWordNowhere;

  /// Пункт меню коллекции: переименовать.
  ///
  /// In ru, this message translates to:
  /// **'Переименовать'**
  String get collectionMenuRename;

  /// Пункт меню коллекции: удалить (деструктив).
  ///
  /// In ru, this message translates to:
  /// **'Удалить коллекцию'**
  String get collectionMenuDelete;

  /// Пункт меню подписанного набора: отписаться (деструктив).
  ///
  /// In ru, this message translates to:
  /// **'Убрать из моих'**
  String get collectionMenuRemoveFromMine;

  /// Заголовок подтверждения отписки от набора стора.
  ///
  /// In ru, this message translates to:
  /// **'Убрать «{title}» из моих?'**
  String collectionUnsubscribeTitle(String title);

  /// Текст подтверждения отписки от набора стора.
  ///
  /// In ru, this message translates to:
  /// **'Набор пропадёт из «Моих». Слова и прогресс по ним сохранятся, набор снова можно добавить из стора.'**
  String get collectionUnsubscribeMessage;

  /// Заголовок подтверждения удаления коллекции.
  ///
  /// In ru, this message translates to:
  /// **'Удалить «{title}»?'**
  String collectionDeleteTitle(String title);

  /// Текст подтверждения удаления коллекции.
  ///
  /// In ru, this message translates to:
  /// **'Коллекция удалится, слова останутся в тренировке. Убрать слово из тренировки можно только на его карточке.'**
  String get collectionDeleteMessage;

  /// Кнопка отмены в подтверждениях.
  ///
  /// In ru, this message translates to:
  /// **'Отмена'**
  String get commonCancel;

  /// A11y-лейбл затемнения плавающего меню.
  ///
  /// In ru, this message translates to:
  /// **'Закрыть меню'**
  String get commonCloseMenu;

  /// Размер набора, «15 слов» (ICU plural). «Примерно» убрано: генерация режется до запрошенного size (GenerationPipeline), недобор подписан отдельным бейджем (QA-OBS-9 / правка 1.7).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}}'**
  String approxWords(int count);

  /// Заголовок экрана вкладки коллекций (кадр 2.5).
  ///
  /// In ru, this message translates to:
  /// **'Коллекции'**
  String get collectionsTitle;

  /// Пустая вкладка коллекций — заголовок.
  ///
  /// In ru, this message translates to:
  /// **'Пока нет коллекций'**
  String get collectionsEmptyTitle;

  /// Пустая вкладка коллекций — пояснение.
  ///
  /// In ru, this message translates to:
  /// **'Опиши ситуацию — и ИИ соберёт первый набор.'**
  String get collectionsEmptyBody;

  /// Выбор на «плюсе» вкладки коллекций: пустая коллекция, слова добавляются руками (SLV-6).
  ///
  /// In ru, this message translates to:
  /// **'Создать вручную'**
  String get collectionsCreateManual;

  /// Пояснение к «Создать вручную» в шите выбора.
  ///
  /// In ru, this message translates to:
  /// **'Пустая коллекция — слова добавишь сам'**
  String get collectionsCreateManualHint;

  /// Выбор на «плюсе» вкладки коллекций: экран генерации по теме (SLV-6).
  ///
  /// In ru, this message translates to:
  /// **'Сгенерировать'**
  String get collectionsCreateGenerate;

  /// Пояснение к «Сгенерировать» в шите выбора.
  ///
  /// In ru, this message translates to:
  /// **'ИИ соберёт набор по описанию ситуации'**
  String get collectionsCreateGenerateHint;

  /// A11y-лейбл кнопки «+» создания коллекции (кадр 2.5).
  ///
  /// In ru, this message translates to:
  /// **'Новая коллекция'**
  String get collectionsNewCollection;

  /// Подзаголовок плитки коллекции, «24 слова · освоено 18» (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}} · освоено {mastered}'**
  String collectionsTileMastered(int count, int mastered);

  /// Заголовок карточки идущей генерации (кадр 2.5).
  ///
  /// In ru, this message translates to:
  /// **'Собираем коллекцию…'**
  String get generationGeneratingTitle;

  /// Мета-строка генерации: тема · уровни · размер («Аренда жилья · A2–B1 · ≈15 слов»).
  ///
  /// In ru, this message translates to:
  /// **'{topic} · {levels} · {size}'**
  String generationGeneratingMeta(String topic, String levels, String size);

  /// Пояснение под индикатором идущей генерации (кадр 2.5).
  ///
  /// In ru, this message translates to:
  /// **'Подбираем слова и фотографии · обычно 20–30 секунд'**
  String get generationGeneratingNote;

  /// Пояснение под карточкой генерации, ожидающей сеть (офлайн-очередь).
  ///
  /// In ru, this message translates to:
  /// **'Отправим, как только появится сеть'**
  String get generationQueuedNote;

  /// Заголовок карточки ошибки генерации (кадр 2.5).
  ///
  /// In ru, this message translates to:
  /// **'Не получилось'**
  String get generationFailedTitle;

  /// Текст ошибки генерации — квота не потрачена (кадр 2.5).
  ///
  /// In ru, this message translates to:
  /// **'Сервис не ответил на запросе «{topic}». Генерация не потрачена.'**
  String generationFailedBody(String topic);

  /// Заголовок карточки генерации, отклонённой по дневному лимиту (429 generation_quota_exceeded).
  ///
  /// In ru, this message translates to:
  /// **'Генерации на сегодня закончились'**
  String get generationQuotaTitle;

  /// Текст карточки дневного лимита, когда известно время сброса.
  ///
  /// In ru, this message translates to:
  /// **'Коллекцию «{topic}» не создали. Лимит обновится в {time} — тогда можно повторить.'**
  String generationQuotaBody(String topic, String time);

  /// Текст карточки дневного лимита, когда время сброса неизвестно (офлайн).
  ///
  /// In ru, this message translates to:
  /// **'Коллекцию «{topic}» не создали: дневной лимит генераций исчерпан.'**
  String generationQuotaBodyNoTime(String topic);

  /// Кнопка перехода на пейволл с карточки дневного лимита генераций.
  ///
  /// In ru, this message translates to:
  /// **'Открыть Premium'**
  String get generationQuotaPremium;

  /// Кнопка повтора генерации (кадр 2.5).
  ///
  /// In ru, this message translates to:
  /// **'Повторить'**
  String get generationRetry;

  /// Убрать карточку ошибки генерации из списка (кадр 2.5).
  ///
  /// In ru, this message translates to:
  /// **'Скрыть'**
  String get generationHide;

  /// QA-23. Постановка генерации в очередь упала на ЛОКАЛЬНОМ хранилище — раньше это молча оставляло кнопку «Сгенерировать» серой навсегда.
  ///
  /// In ru, this message translates to:
  /// **'Не удалось поставить генерацию в очередь: {error}'**
  String generateEnqueueFailed(String error);

  /// Метка готовой коллекции на карточке генерации.
  ///
  /// In ru, this message translates to:
  /// **'Готово'**
  String get generationReadyLabel;

  /// Коллекция сгенерирована, ждём синхронизации перед открытием.
  ///
  /// In ru, this message translates to:
  /// **'Готово — загружаю «{topic}»…'**
  String generationReadyLoading(String topic);

  /// Контурный бейдж недобора, «13 из 15» (кадр 2.5).
  ///
  /// In ru, this message translates to:
  /// **'{delivered} из {requested}'**
  String generationUnderBadge(int delivered, int requested);

  /// Строка состояния готовой коллекции с недобором (кадр 2.5).
  ///
  /// In ru, this message translates to:
  /// **'Готова · собрано меньше'**
  String get generationReadyUnder;

  /// Заголовок экрана создания коллекции (кадр 2.4).
  ///
  /// In ru, this message translates to:
  /// **'Новая коллекция'**
  String get generateScreenTitle;

  /// Лейбл поля ситуации (кадр 2.4).
  ///
  /// In ru, this message translates to:
  /// **'Опиши ситуацию'**
  String get generateSituationLabel;

  /// Пояснение под полем ситуации (кадр 6a).
  ///
  /// In ru, this message translates to:
  /// **'Чем конкретнее ситуация, тем точнее подборка. Например: «первый приём у врача, жалобы и запись на анализы».'**
  String get generateSituationHelper;

  /// Плейсхолдер-ротация поля ситуации.
  ///
  /// In ru, this message translates to:
  /// **'Снимаю квартиру — разговор с агентом'**
  String get generatePlaceholder0;

  /// Плейсхолдер-ротация поля ситуации.
  ///
  /// In ru, this message translates to:
  /// **'Первый приём у врача — жалобы и анализы'**
  String get generatePlaceholder1;

  /// Плейсхолдер-ротация поля ситуации.
  ///
  /// In ru, this message translates to:
  /// **'Собеседование в IT — рассказ о проектах'**
  String get generatePlaceholder2;

  /// Плейсхолдер-ротация поля ситуации.
  ///
  /// In ru, this message translates to:
  /// **'Открываю счёт в банке'**
  String get generatePlaceholder3;

  /// Плейсхолдер-ротация поля ситуации.
  ///
  /// In ru, this message translates to:
  /// **'Заказываю еду в кафе'**
  String get generatePlaceholder4;

  /// Лейбл выбора размера набора (кадр 2.4).
  ///
  /// In ru, this message translates to:
  /// **'Размер'**
  String get generateSizeLabel;

  /// Размер набора: маленькая.
  ///
  /// In ru, this message translates to:
  /// **'Маленькая'**
  String get generateSizeSmall;

  /// Размер набора: средняя.
  ///
  /// In ru, this message translates to:
  /// **'Средняя'**
  String get generateSizeMedium;

  /// Размер набора: большая.
  ///
  /// In ru, this message translates to:
  /// **'Большая'**
  String get generateSizeLarge;

  /// Лейбл выбора уровней (кадр 2.4).
  ///
  /// In ru, this message translates to:
  /// **'Уровень'**
  String get generateLevelLabel;

  /// Подсказка «можно несколько» у выбора уровней.
  ///
  /// In ru, this message translates to:
  /// **'можно несколько'**
  String get generateLevelMulti;

  /// Лейбл выбора языка изучения (кадр 6b).
  ///
  /// In ru, this message translates to:
  /// **'Язык изучения'**
  String get generateLanguageLabel;

  /// Пометка «по умолчанию» у языка из профиля (кадр 6b).
  ///
  /// In ru, this message translates to:
  /// **'по умолчанию'**
  String get generateLanguageDefault;

  /// Строка оставшейся квоты генераций (кадр 6a, ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Осталось {count} генерация сегодня} few{Осталось {count} генерации сегодня} many{Осталось {count} генераций сегодня} other{Осталось {count} генераций сегодня}}'**
  String generateQuotaRemaining(int count);

  /// Строка исчерпанной квоты с локальным временем сброса (кадр 6b).
  ///
  /// In ru, this message translates to:
  /// **'Генерации на сегодня закончились · обновятся в {time}'**
  String generateQuotaExhausted(String time);

  /// Кнопка запуска генерации (кадр 2.4).
  ///
  /// In ru, this message translates to:
  /// **'Сгенерировать'**
  String get generateSubmit;

  /// Строка перехода на Premium под неактивной кнопкой (кадр 15c).
  ///
  /// In ru, this message translates to:
  /// **'Нужно больше? Premium — до 20 в день'**
  String get generatePremiumUpsell;

  /// Ссылка на ручное создание коллекции (кадр 6b).
  ///
  /// In ru, this message translates to:
  /// **'Собрать коллекцию вручную'**
  String get generateManual;

  /// Индикатор идущей записи с таймером (кадр 6c).
  ///
  /// In ru, this message translates to:
  /// **'Слушаю · {time}'**
  String generateVoiceListening(String time);

  /// Кнопка остановки голосового ввода (кадр 6c).
  ///
  /// In ru, this message translates to:
  /// **'Стоп'**
  String get generateVoiceStop;

  /// Пояснение под полем при голосовом вводе (кадр 6c).
  ///
  /// In ru, this message translates to:
  /// **'Текст появляется в поле по мере распознавания — после остановки его можно править руками.'**
  String get generateVoiceHelper;

  /// Сообщение при отказе в разрешении на микрофон/распознавание.
  ///
  /// In ru, this message translates to:
  /// **'Нужен доступ к микрофону и распознаванию речи — включите в Настройках'**
  String get generateVoicePermissionDenied;

  /// Заголовок шита создания коллекции вручную.
  ///
  /// In ru, this message translates to:
  /// **'Новая коллекция'**
  String get collectionSheetCreateTitle;

  /// Заголовок шита переименования коллекции.
  ///
  /// In ru, this message translates to:
  /// **'Изменить коллекцию'**
  String get collectionSheetEditTitle;

  /// Лейбл поля названия коллекции.
  ///
  /// In ru, this message translates to:
  /// **'Название'**
  String get collectionNameLabel;

  /// Плейсхолдер поля названия коллекции.
  ///
  /// In ru, this message translates to:
  /// **'напр.: Путешествия'**
  String get collectionNameHint;

  /// Кнопка создания коллекции.
  ///
  /// In ru, this message translates to:
  /// **'Создать'**
  String get collectionSheetCreateButton;

  /// Плейсхолдер поля поиска (кадр 01).
  ///
  /// In ru, this message translates to:
  /// **'Найти слово'**
  String get searchFieldHint;

  /// Лейбл над тремя последними запросами на пустом экране поиска (кадр 01).
  ///
  /// In ru, this message translates to:
  /// **'Вы искали'**
  String get searchRecentLabel;

  /// Подсказка под списком во время набора (кадр 02).
  ///
  /// In ru, this message translates to:
  /// **'Нажмите Enter, чтобы искать «{query}» целиком'**
  String searchPressEnter(String query);

  /// Ссылка терракотой под найденным словом (кадр 03).
  ///
  /// In ru, this message translates to:
  /// **'Открыть карточку'**
  String get searchOpenCard;

  /// Лейбл над остальными совпадениями (кадры 03 и 04). Про хранилище не говорит: где слово лежит — кухня приложения, а не дело читателя.
  ///
  /// In ru, this message translates to:
  /// **'Похожие'**
  String get searchSimilar;

  /// Главное действие на слове, которого у нас ещё нет (кадр 04). Продаёт не поиск — перевод уже показан — а то, чего нет: значение, пример, фото.
  ///
  /// In ru, this message translates to:
  /// **'Собрать карточку'**
  String get searchBuildCard;

  /// Подпись под кнопкой «Собрать карточку» (кадр 04). Фото здесь НЕ обещается: лукап покупает один вызов модели, а картинку ищет Pexels уже после сохранения (/search/add) — обещание фото на этом шаге не выполняется (телефон, 24.08).
  ///
  /// In ru, this message translates to:
  /// **'Значение и пример. Повторно — бесплатно'**
  String get searchBuildCardNote;

  /// Состояние во время вызова модели.
  ///
  /// In ru, this message translates to:
  /// **'Ищем…'**
  String get searchLooking;

  /// Строка чек-листа сборки карточки (кадр 05).
  ///
  /// In ru, this message translates to:
  /// **'перевод'**
  String get searchBuildTranslation;

  /// Строка чек-листа сборки карточки (кадр 05).
  ///
  /// In ru, this message translates to:
  /// **'значение'**
  String get searchBuildMeaning;

  /// Строка чек-листа сборки карточки (кадр 05).
  ///
  /// In ru, this message translates to:
  /// **'пример'**
  String get searchBuildExample;

  /// Строка под чек-листом сборки (кадр 05).
  ///
  /// In ru, this message translates to:
  /// **'Пара секунд. Можно закрыть — карточка появится в поиске.'**
  String get searchBuildNote;

  /// Подпись рядом с пятью точками лимита (кадр 08).
  ///
  /// In ru, this message translates to:
  /// **'{used} из {cap} на сегодня'**
  String searchLimitUsed(int used, int cap);

  /// Заголовок плашки исчерпанного дневного лимита (кадр 08).
  ///
  /// In ru, this message translates to:
  /// **'Сборки с моделью вернутся в полночь'**
  String get searchLimitTitle;

  /// Модель ответила, но ответ не прошёл гейты, либо сеть не дала.
  ///
  /// In ru, this message translates to:
  /// **'Не удалось найти это слово'**
  String get searchLookupFailed;

  /// Модель не нашла в запросе слова ни на одном языке пары. Совет, а не ошибка: про приложение здесь ничего не сломалось.
  ///
  /// In ru, this message translates to:
  /// **'Не получилось распознать, проверьте написание'**
  String get searchNotRecognized;

  /// Запрос длиннее 120 символов. Спокойная строка вместо запрета: говорит, для чего поле, а не что человек сделал не так.
  ///
  /// In ru, this message translates to:
  /// **'Поиск — для слов и коротких фраз'**
  String get searchQueryTooLong;

  /// Главная кнопка карточки: сохранить в личную папку по умолчанию.
  ///
  /// In ru, this message translates to:
  /// **'+ Сохранённые'**
  String get searchSaveToDefault;

  /// Строка шита неактивна: слово уже в этой коллекции. Плейсхолдер переименован с folder на collection в A-3 — «папка» ушла из словаря продукта.
  ///
  /// In ru, this message translates to:
  /// **'Уже в коллекции «{collection}»'**
  String searchAlreadyIn(String collection);

  /// Подтверждение после «Сохранить»: слово легло на полку и ждёт разбора. Тост и строка состояния — один текст, потому что это один факт.
  ///
  /// In ru, this message translates to:
  /// **'Сохранено в «{collection}» · в очереди на разбор'**
  String searchSavedShelf(String collection);

  /// Подтверждение после «Учить сразу»: слово на полке И в очереди тренажёра.
  ///
  /// In ru, this message translates to:
  /// **'Сохранено в «{collection}» · учится'**
  String searchSavedLearning(String collection);

  /// Второе, тихое действие рядом с «Сохранить»: слово идёт на полку и сразу в очередь тренажёра, минуя разбор.
  ///
  /// In ru, this message translates to:
  /// **'Учить сразу'**
  String get searchLearnNow;

  /// Заголовок шита выбора коллекции на карточке слова и подпись квадратной кнопки рядом с главной. Была обрывком фразы со строчной буквы — «в коллекцию…» (QA-OBS-20).
  ///
  /// In ru, this message translates to:
  /// **'Добавить в коллекцию'**
  String get searchAddToCollection;

  /// Пункт меню: создать коллекцию и сохранить туда.
  ///
  /// In ru, this message translates to:
  /// **'Новая коллекция'**
  String get searchNewCollection;

  /// Пункт шита: создать коллекцию в паре текущего лукапа. {pair} — «English → Русский», эндонимы из справочника.
  ///
  /// In ru, this message translates to:
  /// **'Новая коллекция · {pair}'**
  String searchNewCollectionInPair(String pair);

  /// Ошибка сохранения слова из поиска.
  ///
  /// In ru, this message translates to:
  /// **'Не удалось сохранить'**
  String get searchSaveFailed;

  /// Роль левой пилюли пары над полем поиска: язык, на котором задан запрос. Читается вслух, не рисуется.
  ///
  /// In ru, this message translates to:
  /// **'С какого'**
  String get searchPairFrom;

  /// Роль правой пилюли пары над полем поиска: язык, на котором придёт ответ.
  ///
  /// In ru, this message translates to:
  /// **'На какой'**
  String get searchPairTo;

  /// Кнопка-стрелка между пилюлями пары.
  ///
  /// In ru, this message translates to:
  /// **'Поменять языки местами'**
  String get searchPairSwap;

  /// Подпись под кнопкой на карточке слова, когда папка по умолчанию не совпадает по паре с лукапом: одна папка — одна пара (DECISIONS п. 81).
  ///
  /// In ru, this message translates to:
  /// **'«Сохранённые» — коллекция другой пары. Выберите коллекцию этой пары или создайте новую.'**
  String get searchPairNoDefault;

  /// Заголовок алерта на 422 term_language_mismatch.
  ///
  /// In ru, this message translates to:
  /// **'Слово другого языка'**
  String get searchPairMismatchTitle;

  /// Текст алерта на 422 term_language_mismatch. Языки названы эндонимами.
  ///
  /// In ru, this message translates to:
  /// **'Эта коллекция изучает {expected}, а слово — на {actual}. Одна коллекция — одна пара, поэтому нужна коллекция другой пары.'**
  String searchPairMismatchMessage(String expected, String actual);

  /// Кнопка алерта: создать коллекцию нужной пары и сохранить слово туда.
  ///
  /// In ru, this message translates to:
  /// **'Создать коллекцию'**
  String get searchPairMismatchCreate;

  /// Лейбл над примером на карточке слова (кадры 06/09).
  ///
  /// In ru, this message translates to:
  /// **'Пример'**
  String get wordCardExampleLabel;

  /// Строка синонимов под переводом на развёрнутой карточке слова. Рисуется только когда синонимы есть.
  ///
  /// In ru, this message translates to:
  /// **'также: {words}'**
  String wordCardAlso(String words);

  /// Подпись под парой кнопок на карточке слова (кадр 06).
  ///
  /// In ru, this message translates to:
  /// **'Справа — выбрать другую коллекцию'**
  String get wordCardFolderHint;

  /// Контурная кнопка-состояние после сохранения (кадр 07).
  ///
  /// In ru, this message translates to:
  /// **'В коллекции «{folder}»'**
  String wordCardSavedIn(String folder);

  /// Второе действие отдельной строкой на карточке слова (кадры 07/09). Канон — «коллекция»: кнопка и шит называют один объект одинаково (QA-OBS-21).
  ///
  /// In ru, this message translates to:
  /// **'Добавить в другую коллекцию'**
  String get wordCardAddToAnother;

  /// Лейбл лестницы на карточке, открытой из папки (кадр 09).
  ///
  /// In ru, this message translates to:
  /// **'Прогресс слова'**
  String get wordCardProgressLabel;

  /// Счётчик ступени рядом с лейблом лестницы (кадр 09).
  ///
  /// In ru, this message translates to:
  /// **'{step} из {total}'**
  String wordCardProgressCount(int step, int total);

  /// Атрибуция фотографа поверх фото-героя (лицензия Pexels).
  ///
  /// In ru, this message translates to:
  /// **'Фото: {author}'**
  String wordCardPhotoCredit(String author);

  /// Кнопка-динамик у термина — подпись для VoiceOver.
  ///
  /// In ru, this message translates to:
  /// **'Произнести'**
  String get wordCardSpeak;

  /// Кнопка «назад» поверх фото — подпись для VoiceOver.
  ///
  /// In ru, this message translates to:
  /// **'Назад'**
  String get wordCardBack;

  /// Кнопка «…» поверх фото — подпись для VoiceOver.
  ///
  /// In ru, this message translates to:
  /// **'Ещё'**
  String get wordCardMenu;

  /// Подпись на нейтральной подложке, когда у слова нет картинки.
  ///
  /// In ru, this message translates to:
  /// **'Без фото'**
  String get wordCardNoPhoto;

  /// Заголовок экрана прогресса (кадр 2.6).
  ///
  /// In ru, this message translates to:
  /// **'Прогресс'**
  String get progressTitle;

  /// Крупная антиква-строка стрика на экране прогресса.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} день подряд} few{{count} дня подряд} many{{count} дней подряд} other{{count} дня подряд}}'**
  String progressStreakDays(int count);

  /// Строка «Лучший результат» под стриком.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Лучший результат — {count} день} few{Лучший результат — {count} дня} many{Лучший результат — {count} дней} other{Лучший результат — {count} дня}}'**
  String progressBestResult(int count);

  /// Календарь-неделя: понедельник.
  ///
  /// In ru, this message translates to:
  /// **'Пн'**
  String get progressDayMon;

  /// Календарь-неделя: вторник.
  ///
  /// In ru, this message translates to:
  /// **'Вт'**
  String get progressDayTue;

  /// Календарь-неделя: среда.
  ///
  /// In ru, this message translates to:
  /// **'Ср'**
  String get progressDayWed;

  /// Календарь-неделя: четверг.
  ///
  /// In ru, this message translates to:
  /// **'Чт'**
  String get progressDayThu;

  /// Календарь-неделя: пятница.
  ///
  /// In ru, this message translates to:
  /// **'Пт'**
  String get progressDayFri;

  /// Календарь-неделя: суббота.
  ///
  /// In ru, this message translates to:
  /// **'Сб'**
  String get progressDaySat;

  /// Календарь-неделя: воскресенье.
  ///
  /// In ru, this message translates to:
  /// **'Вс'**
  String get progressDaySun;

  /// Лейбл счётчика: усвоено слов всего.
  ///
  /// In ru, this message translates to:
  /// **'Выучено всего'**
  String get progressLearnedTotal;

  /// Лейбл счётчика: повторений за текущую неделю.
  ///
  /// In ru, this message translates to:
  /// **'За неделю'**
  String get progressThisWeek;

  /// Лейбл счётчика: повторений сегодня.
  ///
  /// In ru, this message translates to:
  /// **'Повторений сегодня'**
  String get progressToday;

  /// Лейбл графика активности за месяц.
  ///
  /// In ru, this message translates to:
  /// **'Активность за месяц'**
  String get progressActivityMonth;

  /// Название текущего месяца рядом с графиком активности.
  ///
  /// In ru, this message translates to:
  /// **'{month, select, 1{январь} 2{февраль} 3{март} 4{апрель} 5{май} 6{июнь} 7{июль} 8{август} 9{сентябрь} 10{октябрь} 11{ноябрь} 12{декабрь} other{}}'**
  String progressMonth(String month);

  /// Лейбл глобальной полосы плотности.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Все {count} слово} few{Все {count} слова} many{Все {count} слов} other{Все {count} слов}}'**
  String progressAllWords(int count);

  /// Неактивная карточка на главном, когда дневная квота новых слов исчерпана (F13).
  ///
  /// In ru, this message translates to:
  /// **'Лимит новых на сегодня'**
  String get homeLimitReachedTitle;

  /// Подсказка под «Лимит новых на сегодня» — свободная тренировка не тратит квоту.
  ///
  /// In ru, this message translates to:
  /// **'Новые слова — завтра. Сейчас можно повторять свободной тренировкой в коллекциях.'**
  String get homeLimitReachedHint;

  /// Тихий офлайн-баннер на главной (кадр 9c).
  ///
  /// In ru, this message translates to:
  /// **'Нет сети. Повторения идут как обычно — синхронизируем, когда связь вернётся.'**
  String get homeOfflineBanner;

  /// Заголовок карточки на главной, когда дня нет: сохранённого плана не осталось, а последний синк не прошёл (BUG-1).
  ///
  /// In ru, this message translates to:
  /// **'Сервер не отвечает'**
  String get homeUnreachableTitle;

  /// Пояснение под homeUnreachableTitle: выход — pull-to-refresh, данные не потеряны (BUG-1).
  ///
  /// In ru, this message translates to:
  /// **'День загрузить не удалось. Потяни вниз, чтобы попробовать снова — всё сохранённое на месте.'**
  String get homeUnreachableBody;

  /// Заголовок экрана профиля (кадр 11a).
  ///
  /// In ru, this message translates to:
  /// **'Профиль'**
  String get profileTitle;

  /// Кнопка сохранения.
  ///
  /// In ru, this message translates to:
  /// **'Сохранить'**
  String get commonSave;

  /// Лейбл фазы сессии в шапке (кадр 12a) — новый термин, выбор из четырёх.
  ///
  /// In ru, this message translates to:
  /// **'Знакомство'**
  String get sessionPhaseIntro;

  /// Лейбл фазы сессии (кадр 12b) — сборка фразы из слов.
  ///
  /// In ru, this message translates to:
  /// **'Сборка'**
  String get sessionPhaseAssemble;

  /// Лейбл фазы сессии (кадры 12c–12i) — ввод/аудирование/пропуск.
  ///
  /// In ru, this message translates to:
  /// **'Повторение'**
  String get sessionPhaseReview;

  /// Лейбл шапки в тренировочной сессии (кадр 12f).
  ///
  /// In ru, this message translates to:
  /// **'Свободная тренировка'**
  String get sessionPhasePractice;

  /// Инструкция под промптом, выбор из четырёх (кадр 12a). {lang} — прилагательное изучаемой стороны ЭТОЙ карточки («итальянский»), languageAdjectiveFor: пул смешивает пары, и зашитый «английский» описывал чужую карточку.
  ///
  /// In ru, this message translates to:
  /// **'выбери {lang} эквивалент'**
  String sessionInstrChoose(String lang);

  /// Инструкция под промптом, сборка фразы из СЛОВЕСНЫХ фишек (кадр 12b).
  ///
  /// In ru, this message translates to:
  /// **'собери из слов'**
  String get sessionInstrAssemble;

  /// То же для одиночного слова: с BUGFIX-2 Ч.2б D2 оно собирается буквенными фишками, и инструкция обязана называть то, что лежит на экране.
  ///
  /// In ru, this message translates to:
  /// **'собери из букв'**
  String get sessionInstrAssembleLetters;

  /// Плейсхолдер в пустой строке сборки (фраза в word_bank и scramble) — куда кладутся слова.
  ///
  /// In ru, this message translates to:
  /// **'Собери из слов ниже'**
  String get sessionAssemblyEmptyHint;

  /// Тот же плейсхолдер для одиночного слова, которое собирается по буквам.
  ///
  /// In ru, this message translates to:
  /// **'Собери из букв ниже'**
  String get sessionAssemblyEmptyHintLetters;

  /// Инструкция под промптом, сборка предложения-примера из чипов (scramble).
  ///
  /// In ru, this message translates to:
  /// **'собери предложение из слов'**
  String get sessionInstrAssembleSentence;

  /// Инструкция под промптом, ввод с клавиатуры (кадр 12c). {lang} — наречие изучаемой стороны карточки («по-итальянски»), languageAdverbFor.
  ///
  /// In ru, this message translates to:
  /// **'напиши {lang}'**
  String sessionInstrType(String lang);

  /// Инструкция аудирования-узнавания (кадр 12g).
  ///
  /// In ru, this message translates to:
  /// **'прослушай и выбери перевод · можно повторить'**
  String get sessionInstrListenChoose;

  /// Инструкция аудирования-воспроизведения (кадр 12h). {lang} — наречие изучаемой стороны карточки («по-итальянски»), languageAdverbFor.
  ///
  /// In ru, this message translates to:
  /// **'прослушай и напиши {lang}'**
  String sessionInstrListenType(String lang);

  /// Инструкция диктанта: звучит предложение-пример целиком (dictation).
  ///
  /// In ru, this message translates to:
  /// **'прослушай и запиши предложение'**
  String get sessionInstrDictation;

  /// Инструкция pick_correct: три предложения, одно верное.
  ///
  /// In ru, this message translates to:
  /// **'выбери верное предложение'**
  String get sessionInstrPickCorrect;

  /// Инструкция description_match: описание на изучаемом языке, четыре слова.
  ///
  /// In ru, this message translates to:
  /// **'выбери слово по описанию'**
  String get sessionInstrDescriptionMatch;

  /// Под неверным выбором в pick_correct: чем должен был быть подчёркнутый фрагмент.
  ///
  /// In ru, this message translates to:
  /// **'должно быть: {correction}'**
  String sessionPickCorrectShouldBe(String correction);

  /// Лейбл над примером с пропуском (кадр 12i).
  ///
  /// In ru, this message translates to:
  /// **'Вставь слово'**
  String get sessionClozeInsert;

  /// Подсказка под строкой сборки (кадр 12b).
  ///
  /// In ru, this message translates to:
  /// **'Тап по слову в строке возвращает его вниз'**
  String get sessionChipReturnHint;

  /// Кнопка подсказки первой буквы в ввод/аудирование/пропуск (кадры 12c, 12h, 12i).
  ///
  /// In ru, this message translates to:
  /// **'Подсказка: первая буква'**
  String get sessionHintFirstLetter;

  /// Кнопка честного провала — показывает ответ, слово вернётся (кадры 12c, 12h, 12i).
  ///
  /// In ru, this message translates to:
  /// **'Не помню'**
  String get sessionDontRemember;

  /// Тихая подсказка под полем ввода: ответ целиком набран алфавитом, которым язык карточки не пишется. Ничего не засчитано, попытка не потрачена — поэтому это подсказка, а не вердикт.
  ///
  /// In ru, this message translates to:
  /// **'Похоже, раскладка не та — переключи клавиатуру и введи ещё раз'**
  String get sessionWrongKeyboard;

  /// Кнопка проверки собранного ответа (word_bank) — сабмит, до фидбека.
  ///
  /// In ru, this message translates to:
  /// **'Проверить'**
  String get sessionCheck;

  /// Бейдж контуром на карточке знакомства — нулевая ступень лестницы (кадр 16b).
  ///
  /// In ru, this message translates to:
  /// **'новое слово'**
  String get sessionIntroBadge;

  /// Единственная кнопка карточки знакомства: ни вариантов, ни вердикта (кадр 16b). Без стрелки в тексте — стрелку рисует сама кнопка.
  ///
  /// In ru, this message translates to:
  /// **'Понятно'**
  String get sessionIntroGot;

  /// Префикс списка принятых вариантов на карточке знакомства (кадр 16b).
  ///
  /// In ru, this message translates to:
  /// **'также:'**
  String get sessionIntroAlso;

  /// Инструкция speaking, ранняя форма: по переводу и фото вспомнить термин и произнести его.
  ///
  /// In ru, this message translates to:
  /// **'скажи слово вслух'**
  String get sessionInstrSpeakWord;

  /// Инструкция speaking, поздняя форма: пример уже на экране, его нужно прочитать вслух.
  ///
  /// In ru, this message translates to:
  /// **'прочитай предложение вслух'**
  String get sessionInstrSpeakExample;

  /// Кнопка остановки записи, пока телефон слушает.
  ///
  /// In ru, this message translates to:
  /// **'Готово'**
  String get sessionSpeakStop;

  /// Индикатор под кнопкой записи, пока идёт распознавание.
  ///
  /// In ru, this message translates to:
  /// **'Слушаю…'**
  String get sessionSpeakListening;

  /// Мягкая ошибка канала: распознавание вернулось пустым. Не является провалом ответа.
  ///
  /// In ru, this message translates to:
  /// **'Не расслышал. Попробуй ещё раз — ближе к микрофону.'**
  String get sessionSpeakNotHeard;

  /// Распознавание не запустилось: нет разрешения или движка. Тоже сбой канала, не ответа.
  ///
  /// In ru, this message translates to:
  /// **'Микрофон недоступен. Можно пропустить эту карточку.'**
  String get sessionSpeakNoMic;

  /// Строка версии внизу вкладки «План» (DAY-GATE-1, Ч.0.4): какая сборка клиента и какая сборка сервера сейчас перед человеком.
  ///
  /// In ru, this message translates to:
  /// **'клиент {client} · сервер {server}'**
  String buildStamp(String client, String server);

  /// Сборка собрана мимо scripts/build_ios.sh — версии у неё нет, и экран говорит это, а не выдумывает SHA.
  ///
  /// In ru, this message translates to:
  /// **'без метки'**
  String get buildStampUnstamped;

  /// Сервер ещё не ответил про свою сборку.
  ///
  /// In ru, this message translates to:
  /// **'…'**
  String get buildStampWaiting;

  /// За версией сервера не удалось сходить.
  ///
  /// In ru, this message translates to:
  /// **'нет связи'**
  String get buildStampNoServer;

  /// Плавающая QA-кнопка (DAY-GATE-1, Ч.0.5): снимок экрана и слепок состояния уходят на диск сервера.
  ///
  /// In ru, this message translates to:
  /// **'Жалоба'**
  String get qaReportButton;

  /// Отчёт записан; id — имя файла на сервере.
  ///
  /// In ru, this message translates to:
  /// **'Жалоба сохранена: {id}'**
  String qaReportSent(String id);

  /// Отправка отчёта не удалась — сеть или закрытая дверь.
  ///
  /// In ru, this message translates to:
  /// **'Жалоба не ушла'**
  String get qaReportFailed;

  /// Отказано в NSSpeechRecognition (DAY-GATE-1, Ч.0.2). iOS спрашивает один раз за установку, поэтому «нажми ещё раз» не выход — только Настройки.
  ///
  /// In ru, this message translates to:
  /// **'Распознавание речи выключено. Разреши распознавание речи в настройках — без него телефон слышит, но не понимает.'**
  String get speechPermissionRecognitionDenied;

  /// Отказано в NSMicrophone (DAY-GATE-1, Ч.0.2).
  ///
  /// In ru, this message translates to:
  /// **'Микрофон выключен. Разреши доступ к микрофону в настройках.'**
  String get speechPermissionMicDenied;

  /// Не хватает обоих разрешений сразу (DAY-GATE-1, Ч.0.2): называем оба, иначе второй заход в Настройки человек уже не сделает.
  ///
  /// In ru, this message translates to:
  /// **'Разреши в настройках микрофон и распознавание речи — нужны оба.'**
  String get speechPermissionBothDenied;

  /// Кнопка под строкой об отказанном разрешении — ведёт на страницу приложения в Настройках iOS.
  ///
  /// In ru, this message translates to:
  /// **'Открыть настройки'**
  String get speechPermissionOpenSettings;

  /// Обрыв на полуслове (DAY-FIX-3, Ч.1.4): человек начал говорить, канал не дослушал. Не ошибка, журнал не пишется, вторая попытка.
  ///
  /// In ru, this message translates to:
  /// **'Не расслышали до конца — скажи ещё раз.'**
  String get sessionSpeakCutOff;

  /// Пропуск карточки говорения — с первой же неудачи микрофона, вместе с сообщением о ней (QA-OBS-7): ничего не записывается, слово вернётся своим чередом.
  ///
  /// In ru, this message translates to:
  /// **'Пропустить'**
  String get sessionSpeakSkip;

  /// Пояснение под кнопкой пропуска — пропуск не пишет ни ревью, ни оценку.
  ///
  /// In ru, this message translates to:
  /// **'Пропуск ничего не испортит: слово вернётся своим чередом.'**
  String get sessionSpeakSkipHint;

  /// Подпись под кнопкой микрофона, когда собеседник договорил, а запись ещё не идёт (SPEECH-2, Ч.1.1). Состояние без таймаута.
  ///
  /// In ru, this message translates to:
  /// **'Твоя очередь — нажми и говори'**
  String get sessionSpeakYourTurn;

  /// Подпись под кнопкой микрофона, пока звучит реплика роли: кнопка ещё не зовёт и не нажимается.
  ///
  /// In ru, this message translates to:
  /// **'Собеседник говорит'**
  String get sessionSpeakWaitForRole;

  /// Подпись под кнопкой микрофона во время записи (SPEECH-2, Ч.1.1).
  ///
  /// In ru, this message translates to:
  /// **'Пишу — скажи и нажми «Готово»'**
  String get sessionSpeakRecording;

  /// Вердикт сказанной реплики: сказано достаточно (SPEECH-2, Ч.3.5).
  ///
  /// In ru, this message translates to:
  /// **'Верно'**
  String get sessionSpeakVerdictCorrect;

  /// Вердикт сказанной реплики: реплика узнана, но часть слов не прозвучала. Не зачёт (SPEECH-2, Ч.3.5).
  ///
  /// In ru, this message translates to:
  /// **'Почти — не хватило: {words}'**
  String sessionSpeakVerdictAlmost(String words);

  /// Вердикт сказанной реплики: сказано не это (SPEECH-2, Ч.3.5).
  ///
  /// In ru, this message translates to:
  /// **'Не то'**
  String get sessionSpeakVerdictWrong;

  /// Рамка тренажёра говорения: это не оценка акцента (показывается на карточке).
  ///
  /// In ru, this message translates to:
  /// **'Проверяем, вспомнил ли ты слово, а не произношение.'**
  String get sessionSpeakHint;

  /// Подпись говорения у реплики плана: карточка проверяет только ключ — слово в дырке каркаса, — и остальную фразу ученик читает с экрана.
  ///
  /// In ru, this message translates to:
  /// **'Скажи фразу, главное — «{key}».'**
  String sessionSpeakHintKey(String key);

  /// Подпись говорения у фразы без ключа: спрашивается вся реплика.
  ///
  /// In ru, this message translates to:
  /// **'Скажи фразу целиком.'**
  String get sessionSpeakHintWhole;

  /// Вердикт говорения — что распознал микрофон, на обоих исходах (QA-20).
  ///
  /// In ru, this message translates to:
  /// **'Услышали: «{text}»'**
  String sessionSpeakHeard(String text);

  /// Необязательная кнопка эха на карточке знакомства: послушал и повторил. Ничего не оценивается.
  ///
  /// In ru, this message translates to:
  /// **'Повторить вслух'**
  String get sessionEchoTry;

  /// Мягкая отметка эха на интро: распознавание что-то расслышало. Не оценка.
  ///
  /// In ru, this message translates to:
  /// **'Услышал тебя'**
  String get sessionEchoHeard;

  /// Мягкая отметка эха на интро: расслышать не удалось. Не оценка и не провал.
  ///
  /// In ru, this message translates to:
  /// **'Попробуй ещё'**
  String get sessionEchoAgain;

  /// Инструкция ступени 1: показан термин, выбрать перевод (кадр 16c-1).
  ///
  /// In ru, this message translates to:
  /// **'выбери перевод'**
  String get sessionInstrRecogniseTranslation;

  /// Подпись нулевой ступени лестницы в развёрнутой карточке слова (кадр 16e).
  ///
  /// In ru, this message translates to:
  /// **'знакомство'**
  String get ladderStep0;

  /// Подпись ступени узнавания (кадр 16e).
  ///
  /// In ru, this message translates to:
  /// **'узнавание'**
  String get ladderStep1;

  /// Подпись ступени сборки/выбора (кадр 16e).
  ///
  /// In ru, this message translates to:
  /// **'сборка'**
  String get ladderStep3;

  /// Подпись ступени печати (кадр 16e).
  ///
  /// In ru, this message translates to:
  /// **'написание'**
  String get ladderStep4;

  /// Подпись верхней ступени — диктант (кадр 16e).
  ///
  /// In ru, this message translates to:
  /// **'диктант'**
  String get ladderStep5;

  /// СЛОВАРЬ СТАТУСОВ (Ч.4), одно из пяти. Слово лежит на полке и ждёт решения. Заменил «В работе» в легенде коллекции (там оно значило противоположное — «ещё не тронуто») и «в каталоге» в списке слов.
  ///
  /// In ru, this message translates to:
  /// **'Разобрать'**
  String get statusToSort;

  /// СЛОВАРЬ СТАТУСОВ: слово в очереди тренажёра — само возвращается на тренировках. Заменил «Знакомое».
  ///
  /// In ru, this message translates to:
  /// **'В работе'**
  String get statusInWork;

  /// СЛОВАРЬ СТАТУСОВ: слово прошло все ступени. Заменил «Подтверждено».
  ///
  /// In ru, this message translates to:
  /// **'Освоено'**
  String get statusMastered;

  /// СЛОВАРЬ СТАТУСОВ: слово убрано из очереди — пауза, не удаление: ступень и дата возврата сохранены.
  ///
  /// In ru, this message translates to:
  /// **'Отложено'**
  String get statusPaused;

  /// СЛОВАРЬ СТАТУСОВ: где слово внутри очереди. Ступеней пять — два узнавания (прямое и обратное) читаются как одна, потому что счёт идёт про пройденный путь, а не про направление вопроса.
  ///
  /// In ru, this message translates to:
  /// **'Ступень {step} из {total}: {rung}'**
  String statusLadderStep(int step, int total, String rung);

  /// Заголовок шита-легенды, который открывается тапом по пяти точкам в «Моих словах» (Ч.4).
  ///
  /// In ru, this message translates to:
  /// **'Что значат точки'**
  String get statusLegendTitle;

  /// Строка легенды про прочерк вместо точек: слово вне лестницы, а не в её начале.
  ///
  /// In ru, this message translates to:
  /// **'Слово помечено «знаю» — по лестнице оно не шло.'**
  String get poolKnownLegend;

  /// Подпись вместо точек для слова, помеченного «знаю» в триаже — строка вне лестницы (кадр 16d).
  ///
  /// In ru, this message translates to:
  /// **'знаю'**
  String get ladderKnownDash;

  /// Кнопка развёрнутой карточки: практика, отфильтрованная по этому слову (кадр 16e).
  ///
  /// In ru, this message translates to:
  /// **'Тренировать слово'**
  String get ladderTrainWord;

  /// Кнопка перехода к следующему заданию (кадры 12c, 12d).
  ///
  /// In ru, this message translates to:
  /// **'Дальше'**
  String get sessionNext;

  /// Кнопка выхода с экрана итога сессии (кадр 12e).
  ///
  /// In ru, this message translates to:
  /// **'Готово'**
  String get sessionDone;

  /// Вердикт фидбека — верный ответ (кадр 12a/12j).
  ///
  /// In ru, this message translates to:
  /// **'Верно'**
  String get sessionFeedbackCorrect;

  /// Вердикт фидбека — принятая опечатка; следом правильная форма (кадр 12c).
  ///
  /// In ru, this message translates to:
  /// **'Почти:'**
  String get sessionFeedbackAlmost;

  /// Вердикт фидбека — ошибка на вводимом/собираемом ответе: правильная форма стоит ниже, в самом фидбеке (кадр 12d).
  ///
  /// In ru, this message translates to:
  /// **'Не то — правильная форма ниже'**
  String get sessionFeedbackWrong;

  /// Вердикт фидбека — ошибка на карточке узнавания: верный вариант подсвечен ВЫШЕ в списке вариантов, а ниже лишь напоминание термина (QA-8).
  ///
  /// In ru, this message translates to:
  /// **'Не то — верный ответ отмечен выше'**
  String get sessionFeedbackWrongAbove;

  /// Относительный срок — сегодня (итог/фидбек).
  ///
  /// In ru, this message translates to:
  /// **'сегодня'**
  String get sessionDueToday;

  /// Относительный срок — завтра (итог/фидбек).
  ///
  /// In ru, this message translates to:
  /// **'завтра'**
  String get sessionDueTomorrow;

  /// Относительный срок ≥2 дней (кадр 12e, «через 2 дня»). Вызывается только для days≥2.
  ///
  /// In ru, this message translates to:
  /// **'{days, plural, one{через {days} день} few{через {days} дня} many{через {days} дней} other{через {days} дней}}'**
  String sessionDueInDays(int days);

  /// Строка фидбека с реальным серверным сроком показа (кадр 12d); {when} — относительный срок.
  ///
  /// In ru, this message translates to:
  /// **'Увидишь снова {when}'**
  String sessionSeeAgain(String when);

  /// Заголовок экрана итога (кадр 12e).
  ///
  /// In ru, this message translates to:
  /// **'Сессия закончена'**
  String get sessionSummaryTitle;

  /// Счётчик итога — сколько карточек пройдено. Безличная форма не склоняется; plural заведён, чтобы все четыре счётчика итога вызывались одинаково — со своим числом (QA-OBS-12).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Повторено} few{Повторено} many{Повторено} other{Повторено}}'**
  String sessionStatReviewed(int count);

  /// Счётчик итога — сколько новых слов (ICU plural: «1 новое», «2 новых»).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Новое} few{Новых} many{Новых} other{Новых}}'**
  String sessionStatNew(int count);

  /// Счётчик итога — сколько ошибок (ICU plural: «1 ошибка», «2 ошибки», «5 ошибок»).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Ошибка} few{Ошибки} many{Ошибок} other{Ошибки}}'**
  String sessionStatErrors(int count);

  /// Счётчик компактного итога свободной тренировки — сколько карточек пройдено (F17). Безличная форма не склоняется; plural — ради единого вызова счётчиков итога (QA-OBS-12).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Пройдено} few{Пройдено} many{Пройдено} other{Пройдено}}'**
  String sessionPracticeStatDone(int count);

  /// Кнопка на итоге свободной тренировки — начать новую тренировочную сессию сразу (F17).
  ///
  /// In ru, this message translates to:
  /// **'Ещё раз'**
  String get sessionPracticeAgain;

  /// Лейбл блока дневной цели в итоге, когда цель ещё не закрыта.
  ///
  /// In ru, this message translates to:
  /// **'Дневная цель'**
  String get sessionDailyGoal;

  /// Лейбл блока дневной цели в итоге, когда цель закрыта (кадр 12e).
  ///
  /// In ru, this message translates to:
  /// **'Дневная цель закрыта'**
  String get sessionGoalClosed;

  /// Строка стрика в блоке дневной цели (кадр 12e).
  ///
  /// In ru, this message translates to:
  /// **'{days, plural, one{Стрик — {days} день} few{Стрик — {days} дня} many{Стрик — {days} дней} other{Стрик — {days} дней}}'**
  String sessionStreak(int days);

  /// Лейбл списка слов в итоге (кадр 12e).
  ///
  /// In ru, this message translates to:
  /// **'Слова этой сессии'**
  String get sessionSessionWords;

  /// Заголовок блока проседающего слова в итоге (кадр 12e).
  ///
  /// In ru, this message translates to:
  /// **'Проседает: {term}'**
  String sessionStrugglingTitle(String term);

  /// Пояснение блока проседающего слова (кадр 12e).
  ///
  /// In ru, this message translates to:
  /// **'Даётся тяжело. Можно собрать другой пример — иногда дело в контексте, а не в слове.'**
  String get sessionStrugglingBody;

  /// Кнопка перегенерации примера в итоге (кадр 12e, B6).
  ///
  /// In ru, this message translates to:
  /// **'Новый пример'**
  String get sessionNewExample;

  /// Сообщение при 429 на «Новый пример» (квота исчерпана).
  ///
  /// In ru, this message translates to:
  /// **'Лимит примеров на сегодня исчерпан'**
  String get sessionNewExampleExhausted;

  /// Тихая плашка сверху в тренировочной сессии (кадр 12f).
  ///
  /// In ru, this message translates to:
  /// **'Свободная тренировка — прогресс не меняется'**
  String get sessionPracticeBanner;

  /// Заголовок алерта выхода из сессии (кадр 12k).
  ///
  /// In ru, this message translates to:
  /// **'Прервать сессию?'**
  String get sessionExitTitle;

  /// Тело алерта выхода из сессии (кадр 12k).
  ///
  /// In ru, this message translates to:
  /// **'Отвеченные слова сохранятся — вернуться можно в любой момент.'**
  String get sessionExitBody;

  /// Кнопка подтверждения выхода (кадр 12k, терракотовый текст).
  ///
  /// In ru, this message translates to:
  /// **'Выйти'**
  String get sessionExitConfirm;

  /// Кнопка отмены выхода — остаётся дефолтом (кадр 12k).
  ///
  /// In ru, this message translates to:
  /// **'Продолжить'**
  String get sessionExitCancel;

  /// Метка доступности крестика выхода из сессии.
  ///
  /// In ru, this message translates to:
  /// **'Закрыть'**
  String get sessionClose;

  /// Метка доступности кнопки повтора аудио (кадры 12g–12h).
  ///
  /// In ru, this message translates to:
  /// **'Повторить озвучку'**
  String get sessionListenReplay;

  /// Кнопка замедленного повтора озвучки в аудировании (кадры 12g–12h).
  ///
  /// In ru, this message translates to:
  /// **'Замедленно'**
  String get sessionListenReplaySlow;

  /// Пустое состояние сессии — нет карточек.
  ///
  /// In ru, this message translates to:
  /// **'Здесь пока нечего повторять'**
  String get sessionEmpty;

  /// Пустая сессия «Учить N»: новые слова есть, но дневная квота новых исчерпана.
  ///
  /// In ru, this message translates to:
  /// **'Дневной лимит новых слов достигнут. Возвращайся завтра'**
  String get sessionDailyNewLimit;

  /// Кнопка входа в голосовой разговор на экране коллекции (только Premium).
  ///
  /// In ru, this message translates to:
  /// **'Разговор · 3 мин'**
  String get practiceDialogEntry;

  /// Подстрока кнопки разговора.
  ///
  /// In ru, this message translates to:
  /// **'Голосовая практика с ИИ'**
  String get practiceDialogEntrySubtitle;

  /// Подсказка на неактивной кнопке разговора в офлайне.
  ///
  /// In ru, this message translates to:
  /// **'Нужен интернет'**
  String get practiceDialogOfflineHint;

  /// Заголовок пре-стартового листа разговора.
  ///
  /// In ru, this message translates to:
  /// **'Разговор с ИИ'**
  String get practiceDialogPrestartTitle;

  /// Пояснение на пре-старте: язык разговора = язык коллекции.
  ///
  /// In ru, this message translates to:
  /// **'ИИ будет говорить с тобой на языке коллекции — {lang}. Отвечай вслух и старайся использовать эти слова.'**
  String practiceDialogPrestartBody(String lang);

  /// Лейбл над полосой target_words на пре-старте.
  ///
  /// In ru, this message translates to:
  /// **'Слова для разговора'**
  String get practiceDialogPrestartWordsLabel;

  /// Кнопка старта разговора на пре-стартовом листе.
  ///
  /// In ru, this message translates to:
  /// **'Начать разговор'**
  String get practiceDialogStart;

  /// Индикатор состояния: устанавливаем соединение.
  ///
  /// In ru, this message translates to:
  /// **'соединяемся…'**
  String get practiceDialogStateConnecting;

  /// Индикатор состояния: бот говорит.
  ///
  /// In ru, this message translates to:
  /// **'говорит'**
  String get practiceDialogStateSpeaking;

  /// Индикатор состояния: бот слушает пользователя.
  ///
  /// In ru, this message translates to:
  /// **'слушаю тебя'**
  String get practiceDialogStateListening;

  /// Счётчик прозвучавших слов над полосой coverage.
  ///
  /// In ru, this message translates to:
  /// **'{used} / {total}'**
  String practiceDialogCoverageLabel(int used, int total);

  /// Заголовок алерта выхода из разговора (крестик).
  ///
  /// In ru, this message translates to:
  /// **'Завершить разговор?'**
  String get practiceDialogExitTitle;

  /// Пояснение в алерте выхода из разговора.
  ///
  /// In ru, this message translates to:
  /// **'Разговор закончится, и ты увидишь итог.'**
  String get practiceDialogExitMessage;

  /// Кнопка подтверждения выхода из разговора.
  ///
  /// In ru, this message translates to:
  /// **'Завершить'**
  String get practiceDialogExitConfirm;

  /// Кнопка отмены выхода — продолжить разговор.
  ///
  /// In ru, this message translates to:
  /// **'Продолжить'**
  String get practiceDialogExitCancel;

  /// Заголовок карточки итога разговора (антиква).
  ///
  /// In ru, this message translates to:
  /// **'Разговор окончен'**
  String get practiceDialogFinaleTitle;

  /// Итог разговора: сколько target-слов прозвучало.
  ///
  /// In ru, this message translates to:
  /// **'Слов прозвучало: {used} из {total}'**
  String practiceDialogFinaleWords(int used, int total);

  /// Кнопка закрытия итога разговора.
  ///
  /// In ru, this message translates to:
  /// **'Готово'**
  String get practiceDialogFinaleDone;

  /// Ошибка: разговор недоступен без Premium (403).
  ///
  /// In ru, this message translates to:
  /// **'Разговоры доступны в Premium.'**
  String get practiceDialogErrorSubscription;

  /// Ошибка: исчерпан дневной лимит разговоров (429), время сброса в локальном времени.
  ///
  /// In ru, this message translates to:
  /// **'На сегодня разговоры закончились. Новые — после {time}.'**
  String practiceDialogErrorRateLimited(String time);

  /// Ошибка лимита разговоров без известного времени сброса.
  ///
  /// In ru, this message translates to:
  /// **'На сегодня разговоры закончились. Попробуй завтра.'**
  String get practiceDialogErrorRateLimitedNoTime;

  /// Ошибка: нет сети для старта разговора.
  ///
  /// In ru, this message translates to:
  /// **'Нет сети. Для разговора нужен интернет.'**
  String get practiceDialogErrorOffline;

  /// Общая ошибка старта разговора.
  ///
  /// In ru, this message translates to:
  /// **'Не удалось начать разговор. Попробуй ещё раз.'**
  String get practiceDialogErrorGeneric;

  /// Кнопка закрытия экрана разговора при ошибке.
  ///
  /// In ru, this message translates to:
  /// **'Закрыть'**
  String get practiceDialogClose;

  /// Кнопка запуска разговора, когда у коллекции уже есть результат прошлого.
  ///
  /// In ru, this message translates to:
  /// **'Пройти ещё раз'**
  String get practiceDialogRepeat;

  /// Строка результата последнего разговора на экране коллекции.
  ///
  /// In ru, this message translates to:
  /// **'слов: {used} из {total}'**
  String practiceDialogResultWords(int used, int total);

  /// Сегмент таба «Коллекции»: свои коллекции (кадр 2.8).
  ///
  /// In ru, this message translates to:
  /// **'Мои'**
  String get storeSegmentMine;

  /// Сегмент таба «Коллекции»: стор готовых наборов (кадр 2.8).
  ///
  /// In ru, this message translates to:
  /// **'Готовые'**
  String get storeSegmentReady;

  /// Размер набора в сторе, «16 слов».
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}}'**
  String storeWordsCount(int count);

  /// Бейдж на карточке стора: набор уже добавлен (кадр 2.8).
  ///
  /// In ru, this message translates to:
  /// **'В моих'**
  String get storeInLibrary;

  /// Кнопка добавления бесплатного набора из стора (кадр 8c).
  ///
  /// In ru, this message translates to:
  /// **'Добавить в мои'**
  String get storeAddToMine;

  /// Кнопка премиум-набора в сторе — ведёт на пейволл (кадр 15d).
  ///
  /// In ru, this message translates to:
  /// **'Доступно с Premium'**
  String get storeAvailableWithPremium;

  /// Подпись под кнопкой премиум-набора: подписка открывает все наборы сразу (кадр 15d).
  ///
  /// In ru, this message translates to:
  /// **'Открываются все {count} наборов сразу'**
  String storeAllSetsUnlock(int count);

  /// Заголовок списка терминов в превью-шите набора (кадр 8c).
  ///
  /// In ru, this message translates to:
  /// **'Что внутри'**
  String get storeInsideLabel;

  /// Строка под превью-списком: сколько слов ещё в наборе (кадр 8c).
  ///
  /// In ru, this message translates to:
  /// **'и ещё {count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}}'**
  String storeMoreWords(int count);

  /// Заголовок шита выбора языковой пары стора (кадр 2.8).
  ///
  /// In ru, this message translates to:
  /// **'Языковая пара'**
  String get storeLangPairSheetTitle;

  /// Пустой стор — контент ещё не опубликован.
  ///
  /// In ru, this message translates to:
  /// **'Скоро здесь появятся наборы'**
  String get storeEmptyTitle;

  /// Подпись пустого стора.
  ///
  /// In ru, this message translates to:
  /// **'Готовые коллекции по ситуациям добавим в ближайшее время.'**
  String get storeEmptyBody;

  /// Тост после добавления бесплатного набора из стора.
  ///
  /// In ru, this message translates to:
  /// **'Набор добавлен в «Мои»'**
  String get storePreviewAdded;

  /// Ошибка подписки на набор стора.
  ///
  /// In ru, this message translates to:
  /// **'Не удалось добавить набор. Попробуйте ещё раз.'**
  String get storeSubscribeError;

  /// Кнопка-крестик закрытия пейволла (кадр 2.13).
  ///
  /// In ru, this message translates to:
  /// **'Закрыть'**
  String get paywallClose;

  /// Заголовок пейволла при входе из исчерпанной квоты (кадр 14a).
  ///
  /// In ru, this message translates to:
  /// **'Больше коллекций за один вечер'**
  String get paywallTitleQuota;

  /// Заголовок пейволла при входе из профиля.
  ///
  /// In ru, this message translates to:
  /// **'Premium без ограничений'**
  String get paywallTitleGeneric;

  /// Заголовок пейволла при входе из премиум-набора: имя набора + остальные (кадр 14b).
  ///
  /// In ru, this message translates to:
  /// **'{title} и ещё {count} наборов'**
  String paywallTitleStore(String title, int count);

  /// Строка ценности пейволла для входа из квоты (кадр 14a).
  ///
  /// In ru, this message translates to:
  /// **'Premium поднимает дневной лимит до двадцати генераций.'**
  String get paywallSubtitleQuota;

  /// Строка ценности пейволла для входа из стора (кадр 14b).
  ///
  /// In ru, this message translates to:
  /// **'Премиум-коллекции собраны редакцией и открываются все сразу — по одной их не продаём.'**
  String get paywallSubtitleStore;

  /// Строка ценности пейволла для входа из профиля.
  ///
  /// In ru, this message translates to:
  /// **'Один тариф открывает всё, что делает изучение быстрее.'**
  String get paywallSubtitleGeneric;

  /// Пункт Premium: лимит генераций (кадр 14a).
  ///
  /// In ru, this message translates to:
  /// **'До 20 генераций в день'**
  String get paywallBenefitGenerations;

  /// Пункт Premium: премиум-наборы стора (кадр 14a).
  ///
  /// In ru, this message translates to:
  /// **'Все премиум-коллекции в сторе'**
  String get paywallBenefitStore;

  /// Пункт Premium: будущие режимы (кадр 14a).
  ///
  /// In ru, this message translates to:
  /// **'Будущие режимы тренировок'**
  String get paywallBenefitModes;

  /// Строка «бесплатно всегда» на пейволле (кадр 14a, правило 22).
  ///
  /// In ru, this message translates to:
  /// **'Повторения, разбор и офлайн — бесплатно всегда.'**
  String get paywallFreeForever;

  /// Ценовая карточка: годовой период (кадр 4ж).
  ///
  /// In ru, this message translates to:
  /// **'Год'**
  String get paywallPeriodYear;

  /// Ценовая карточка: месячный период (кадр 4ж).
  ///
  /// In ru, this message translates to:
  /// **'Месяц'**
  String get paywallPeriodMonth;

  /// Плейсхолдер годовой цены (реальная приходит из StoreKit, кадр 4ж).
  ///
  /// In ru, this message translates to:
  /// **'\$29.99'**
  String get paywallPriceYear;

  /// Плейсхолдер месячной цены (реальная приходит из StoreKit, кадр 4ж).
  ///
  /// In ru, this message translates to:
  /// **'\$4.99'**
  String get paywallPriceMonth;

  /// Подстрока годовой карточки: цена за месяц (плейсхолдер, кадр 4ж).
  ///
  /// In ru, this message translates to:
  /// **'\$2.50 в месяц'**
  String get paywallYearPerMonth;

  /// Подстрока месячной карточки (кадр 4ж).
  ///
  /// In ru, this message translates to:
  /// **'в месяц'**
  String get paywallPerMonth;

  /// Бейдж скидки на годовой карточке (кадр 4ж).
  ///
  /// In ru, this message translates to:
  /// **'−50%'**
  String get paywallDiscountBadge;

  /// Главная кнопка пейволла (кадр 14a).
  ///
  /// In ru, this message translates to:
  /// **'Продолжить'**
  String get paywallContinue;

  /// Юридическая строка авто-продления для годового периода (кадр 14a).
  ///
  /// In ru, this message translates to:
  /// **'Подписка продлевается автоматически. {price} за год списываются с Apple ID; отменить можно в настройках App Store не позднее чем за 24 часа до конца периода.'**
  String paywallLegalYear(String price);

  /// Юридическая строка авто-продления для месячного периода (кадр 14b).
  ///
  /// In ru, this message translates to:
  /// **'Подписка продлевается автоматически. {price} в месяц списываются с Apple ID; отменить можно в настройках App Store не позднее чем за 24 часа до конца периода.'**
  String paywallLegalMonth(String price);

  /// Ссылка восстановления покупок на пейволле (кадр 14a).
  ///
  /// In ru, this message translates to:
  /// **'Восстановить покупки'**
  String get paywallRestore;

  /// Ссылка на условия на пейволле.
  ///
  /// In ru, this message translates to:
  /// **'Условия'**
  String get paywallTerms;

  /// Ссылка на политику конфиденциальности на пейволле.
  ///
  /// In ru, this message translates to:
  /// **'Конфиденциальность'**
  String get paywallPrivacy;

  /// Тост после фейковой покупки в dev-режиме (StoreKit — отдельный блок).
  ///
  /// In ru, this message translates to:
  /// **'Premium активирован (dev-режим)'**
  String get paywallDevPurchased;

  /// Dev-секция профиля (только при DEV_MENU).
  ///
  /// In ru, this message translates to:
  /// **'Разработка'**
  String get profileSectionDev;

  /// Dev-переключатель: витрина стора.
  ///
  /// In ru, this message translates to:
  /// **'Стор коллекций'**
  String get devFlagStore;

  /// Dev-переключатель: пейволл и его входы.
  ///
  /// In ru, this message translates to:
  /// **'Пейволл'**
  String get devFlagPaywall;

  /// Dev-переключатель: фейковый premium.
  ///
  /// In ru, this message translates to:
  /// **'Premium (dev)'**
  String get devFlagPremium;

  /// Заголовок dev-экрана монитора подвисаний и пункт в секции «Разработка» (QA-OBS-24). Коротко по делу: «Монитор производительности» не влезал в AppBar и печатался как «Монитор производи…» — поймано живым прогоном на симуляторе.
  ///
  /// In ru, this message translates to:
  /// **'Подвисания'**
  String get perfMonitorTitle;

  /// Переключатель записи на экране монитора.
  ///
  /// In ru, this message translates to:
  /// **'Записывать подвисания, кадры и тапы'**
  String get perfMonitorToggle;

  /// Подпись под переключателем записи.
  ///
  /// In ru, this message translates to:
  /// **'По умолчанию выключено — пока выключено, ничего не стоит'**
  String get perfMonitorToggleHint;

  /// Пустой журнал монитора.
  ///
  /// In ru, this message translates to:
  /// **'записей нет'**
  String get perfMonitorEmpty;

  /// Главная кнопка монитора: копия журнала + дамп в файл.
  ///
  /// In ru, this message translates to:
  /// **'Скопировать в буфер'**
  String get perfMonitorCopy;

  /// Тихая кнопка монитора: очистить журнал.
  ///
  /// In ru, this message translates to:
  /// **'Очистить'**
  String get perfMonitorClear;

  /// Тост после копирования: путь к дампу на устройстве.
  ///
  /// In ru, this message translates to:
  /// **'Скопировано. Файл: {path}'**
  String perfMonitorCopied(String path);

  /// Сессия не построилась из-за отсутствия сети. Сессии строятся на сервере (пока — см. офлайн-практику).
  ///
  /// In ru, this message translates to:
  /// **'Нет соединения'**
  String get sessionOffline;

  /// Прочие ошибки построения сессии. Текст исключения уходит только в лог, не на экран.
  ///
  /// In ru, this message translates to:
  /// **'Не удалось загрузить сессию'**
  String get sessionLoadFailed;

  /// Очередь ответов упёрлась в потолок и содержит неотправленные ответы, влияющие на прогресс. Показывается вместо тихой потери данных (F20-r2).
  ///
  /// In ru, this message translates to:
  /// **'Ответы не уходят на сервер — проверь соединение'**
  String get syncStuckBanner;

  /// Последний синк не прошёл (SyncState.offline). Показывается и при живом Wi-Fi: канальный офлайн и молчащий сервер — разные вещи, а выглядели одинаково (BUG-1).
  ///
  /// In ru, this message translates to:
  /// **'Сервер недоступен · показываю сохранённое'**
  String get syncUnreachableBanner;

  /// Кнопка карточки слова: зачислить пару в пул (16e).
  ///
  /// In ru, this message translates to:
  /// **'Учить это слово'**
  String get poolEnrollAction;

  /// Пояснение под кнопкой «Учить это слово»: что именно произойдёт (16e).
  ///
  /// In ru, this message translates to:
  /// **'Слово встанет в очередь и начнёт приходить на тренировках.'**
  String get poolEnrollNote;

  /// Тихая кнопка карточки слова: пауза — слово перестаёт приходить (16e).
  ///
  /// In ru, this message translates to:
  /// **'Убрать из изучения'**
  String get poolUnenrollAction;

  /// Заголовок подтверждения паузы слова.
  ///
  /// In ru, this message translates to:
  /// **'Убрать «{term}» из изучения?'**
  String poolUnenrollTitle(String term);

  /// Текст подтверждения: это пауза, а не удаление.
  ///
  /// In ru, this message translates to:
  /// **'Слово перестанет приходить на тренировках. Прогресс и история сохранятся — слово можно вернуть в любой момент.'**
  String get poolUnenrollMessage;

  /// Подтверждающая кнопка паузы слова.
  ///
  /// In ru, this message translates to:
  /// **'Убрать'**
  String get poolUnenrollConfirm;

  /// Экран пула: все слова, которые пользователь взял в изучение.
  ///
  /// In ru, this message translates to:
  /// **'Мои слова'**
  String get myWordsTitle;

  /// Подпись под заголовком «Мои слова»: размер пула (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}}'**
  String myWordsCount(int count);

  /// Плейсхолдер поля поиска на экране «Мои слова».
  ///
  /// In ru, this message translates to:
  /// **'Поиск по словам'**
  String get myWordsSearchHint;

  /// Фильтр фазы на экране «Мои слова»: без фильтра.
  ///
  /// In ru, this message translates to:
  /// **'Все'**
  String get myWordsFilterAll;

  /// Фильтр фазы: слово в пуле, но ещё ни разу не показано.
  ///
  /// In ru, this message translates to:
  /// **'Новые'**
  String get myWordsFilterNew;

  /// Фильтр фазы: слово на ступенях узнавания.
  ///
  /// In ru, this message translates to:
  /// **'Узнавание'**
  String get myWordsFilterLearning;

  /// Фильтр фазы: слово вышло с лестницы и идёт по интервальным повторам.
  ///
  /// In ru, this message translates to:
  /// **'Повторение'**
  String get myWordsFilterReview;

  /// Фильтр по коллекции-источнику на экране «Мои слова»: без фильтра.
  ///
  /// In ru, this message translates to:
  /// **'Все коллекции'**
  String get myWordsSourceAll;

  /// Фильтр по источнику: слова, чья коллекция удалена или отписана — из пула они не уходят.
  ///
  /// In ru, this message translates to:
  /// **'Без коллекции'**
  String get myWordsSourceNone;

  /// Пустое состояние экрана «Мои слова».
  ///
  /// In ru, this message translates to:
  /// **'Пока пусто'**
  String get myWordsEmptyTitle;

  /// Пустое состояние объясняет два способа зачисления в пул.
  ///
  /// In ru, this message translates to:
  /// **'Слова попадают сюда, когда ты разбираешь коллекцию свайпами «не знаю» и «не уверен» — или нажимаешь «Учить это слово» на карточке слова.'**
  String get myWordsEmptyMessage;

  /// Пустое состояние экрана «Мои слова» под активным поиском или фильтром.
  ///
  /// In ru, this message translates to:
  /// **'Ничего не нашлось'**
  String get myWordsNothingFound;

  /// Бейдж стрика в шапке главной (кадр 17a).
  ///
  /// In ru, this message translates to:
  /// **'Стрик {count}'**
  String homeStreakBadge(int count);

  /// Лейбл карточки слова-вызова (кадр 19-4), брасовая мишень слева от него.
  ///
  /// In ru, this message translates to:
  /// **'Слово-вызов'**
  String get challengeLabel;

  /// Счётчик серии справа в шапке карточки (кадр 19-4). При нуле не рисуется — «угадано 0 подряд» не предложение.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{угадано {count} подряд} few{угадано {count} подряд} many{угадано {count} подряд} other{угадано {count} подряд}}'**
  String challengeStreak(int count);

  /// То же место после неверного ответа (кадр 19-4в). Без красного и без слова «неверно».
  ///
  /// In ru, this message translates to:
  /// **'серия сброшена'**
  String get challengeStreakReset;

  /// Похвала после верного ответа (кадр 19-4б) — одна и короткая, с галкой вместо эмодзи.
  ///
  /// In ru, this message translates to:
  /// **'Знаешь!'**
  String get challengePraise;

  /// Раскрытый ответ (кадры 19-4б/в): термин и перевод через тире.
  ///
  /// In ru, this message translates to:
  /// **'{term} — {translation}'**
  String challengeAnswer(String term, String translation);

  /// Пример употребления с переводом (кадры 19-4б/в).
  ///
  /// In ru, this message translates to:
  /// **'{sentence} — {translation}'**
  String challengeExample(String sentence, String translation);

  /// Разбор ошибки (кадр 19-4в): чем на самом деле является выбранный вариант. Рисуется только когда неверный вариант — перевод известного термина.
  ///
  /// In ru, this message translates to:
  /// **'Вы выбрали «{chosen}» — это {term}'**
  String challengeMistake(String chosen, String term);

  /// Кнопка: слово уходит в очередь тем же путём, что «Учить это слово» (кадр 19-4).
  ///
  /// In ru, this message translates to:
  /// **'Учить'**
  String get challengeLearn;

  /// Кнопка: свернуть карточку до завтра (кадр 19-4).
  ///
  /// In ru, this message translates to:
  /// **'Завтра новое'**
  String get challengeTomorrow;

  /// Свёрнутая карточка одной строкой (кадр 19-4г) — та же типографика, что у «Завтра выпадет N слов».
  ///
  /// In ru, this message translates to:
  /// **'Завтра новое слово'**
  String get challengeCollapsed;

  /// Состояние кнопки «Учить» после нажатия: акт совершён, слово зачислено.
  ///
  /// In ru, this message translates to:
  /// **'Слово в очереди'**
  String get challengeLearning;

  /// Бейдж брасом справа сверху на тёмной плите (кадр 19-1). Короче прежнего «Сессия на сегодня»: заголовок ушёл, вместо него метка.
  ///
  /// In ru, this message translates to:
  /// **'Сессия'**
  String get homeSessionBadge;

  /// Единица рядом с крупной цифрой на тёмной плите (кадр 19-1) — БЕЗ самого числа: цифра набрана антиквой в 60px, а слово рядом в 18px, и склеить их в одну строку нельзя.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{слово} few{слова} many{слов} other{слова}}'**
  String homeSessionUnitWords(int count);

  /// Подпись прейскурантной строки состава (кадр 19-1), число справа отдельно.
  ///
  /// In ru, this message translates to:
  /// **'Повторить'**
  String get homeSessionRowRepeat;

  /// Подпись прейскурантной строки состава (кадр 19-1).
  ///
  /// In ru, this message translates to:
  /// **'Новых'**
  String get homeSessionRowNew;

  /// Подпись прейскурантной строки состава (кадр 19-1) — свайп-проход по неразобранным словам коллекций.
  ///
  /// In ru, this message translates to:
  /// **'Разобрать'**
  String get homeSessionRowTriage;

  /// Размер сессии на тёмной карточке, «32 слова» (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}}'**
  String homeSessionCardWords(int count);

  /// Оценка времени сессии, «≈ 9 минут» (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{≈ {count} минута} few{≈ {count} минуты} many{≈ {count} минут} other{≈ {count} минуты}}'**
  String homeSessionCardMinutes(int count);

  /// Вторая половина честной подписи сессии: сколько КАРТОЧЕК получится из обещанных слов. Тильда неспроста — состав дня может дойти до тренажёра слегка другим. Склеивается со счётом слов через « · » (см. sessionSizeLabel), одной строкой на главной и на кнопках коллекции (Ч.3).
  ///
  /// In ru, this message translates to:
  /// **'~{count, plural, one{{count} карточка} few{{count} карточки} many{{count} карточек} other{{count} карточки}}'**
  String sessionSizeCards(int count);

  /// Кнопка тёмной карточки дня (кадр 17a).
  ///
  /// In ru, this message translates to:
  /// **'Начать'**
  String get homeSessionStart;

  /// Заголовок вечерней секции ошибок сессии (кадр 17b).
  ///
  /// In ru, this message translates to:
  /// **'Далось труднее всего'**
  String get homeHardestTitle;

  /// Сколько раз слово ответили неверно в последней сессии (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} ошибка} few{{count} ошибки} many{{count} ошибок} other{{count} ошибки}}'**
  String homeHardestErrors(int count);

  /// Заголовок вечерней карточки (кадр 17b).
  ///
  /// In ru, this message translates to:
  /// **'Сегодня закрыто'**
  String get homeDoneTitle;

  /// Итог дня в СЛОВАХ (кадр 19-2). Единица обязательна: «52 из 52» без неё — счёт карточек в форме счёта слов, и 52 не было числом слов ни у кого. Карточки и минуты идут подписью следом.
  ///
  /// In ru, this message translates to:
  /// **'{total, plural, one{{done} из {total} слова} few{{done} из {total} слов} many{{done} из {total} слов} other{{done} из {total} слов}}'**
  String homeDoneOfWords(int done, int total);

  /// Вторая единица итога дня (кадр 19-2) — сколько карточек стоили эти слова.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} карточка} few{{count} карточки} many{{count} карточек} other{{count} карточки}}'**
  String homeDoneCards(int count);

  /// Третья единица итога дня (кадр 19-2). Сокращённо: это хвост строки, а не самостоятельное утверждение.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} мин} few{{count} мин} many{{count} мин} other{{count} мин}}'**
  String homeDoneMinutes(int count);

  /// Внутренний прогресс одного слова, когда день дошёл до него одного: «1 слово · карточка 2 из 3». Пока слово в цепочке — строка живёт; цепочка кончилась — слово ушло из плана, и строки нет.
  ///
  /// In ru, this message translates to:
  /// **'{total, plural, one{карточка {position} из {total}} few{карточка {position} из {total}} many{карточка {position} из {total}} other{карточка {position} из {total}}}'**
  String homeChainProgress(int position, int total);

  /// Сколько заняла сессия, «6 мин 40 с».
  ///
  /// In ru, this message translates to:
  /// **'{minutes} мин {seconds} с'**
  String homeDoneDuration(int minutes, int seconds);

  /// Длительность сессии короче минуты (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} секунда} few{{count} секунды} many{{count} секунд} other{{count} секунды}}'**
  String homeDoneDurationSeconds(int count);

  /// Заголовок состояния Б: сессии нет, расписание впереди (кадр 17d).
  ///
  /// In ru, this message translates to:
  /// **'Всё повторено'**
  String get homeIdleTitle;

  /// Главная кнопка состояния Б (кадр 17d).
  ///
  /// In ru, this message translates to:
  /// **'Взять новые слова'**
  String get homeIdleTakeNew;

  /// Пояснение под «Всё повторено» (кадр 17d).
  ///
  /// In ru, this message translates to:
  /// **'Новые слова на сегодня не взяты — очередь стоит.'**
  String get homeIdleQueueStalled;

  /// Когда и сколько ждёт следующий повтор (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Следующий повтор — {when}, {count} слово.} few{Следующий повтор — {when}, {count} слова.} many{Следующий повтор — {when}, {count} слов.} other{Следующий повтор — {when}, {count} слова.}}'**
  String homeNextReviewLine(String when, int count);

  /// Подстановка «когда» для следующего повтора — завтра.
  ///
  /// In ru, this message translates to:
  /// **'завтра'**
  String get homeWhenTomorrow;

  /// Предложение добрать слова из недоразобранной коллекции (кадр 17b).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Можно добить {count} слово из «{title}» сверх плана.} few{Можно добить {count} слова из «{title}» сверх плана.} many{Можно добить {count} слов из «{title}» сверх плана.} other{Можно добить {count} слова из «{title}» сверх плана.}}'**
  String homeExtraFromCollection(int count, String title);

  /// Предложение на закрытом дне (кадр 19-2): слова, УЖЕ взятые в очередь, которые сегодняшняя квота ещё позволяет раздать. Идут прежде свайп-прохода — эти уже выбраны, а разбор это предложение выбрать ещё.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Можно взять {count} слово, которое уже ждёт очереди.} few{Можно взять {count} слова, которые уже ждут очереди.} many{Можно взять {count} слов, которые уже ждут очереди.} other{Можно взять {count} слова, которые уже ждут очереди.}}'**
  String homeExtraNew(int count);

  /// Кнопка «Ещё N слов» на вечерней карточке (кадр 17b).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Ещё {count} слово} few{Ещё {count} слова} many{Ещё {count} слов} other{Ещё {count} слова}}'**
  String homeExtraButton(int count);

  /// Тихая строка входа в магазин под генерацией (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{или взять из {count} готового} few{или взять из {count} готовых} many{или взять из {count} готовых} other{или взять из {count} готовых}}'**
  String homeStoreLink(int count);

  /// Подпись первого числа плиты статистики (кадр 19-1). Значение приходит из /stats.learned.
  ///
  /// In ru, this message translates to:
  /// **'Выучено'**
  String get homeStatLearned;

  /// Подпись второго числа плиты статистики (кадр 19-1) — слов, дошедших до «выучено» за семь дней.
  ///
  /// In ru, this message translates to:
  /// **'За неделю'**
  String get homeStatWeek;

  /// Подпись третьего числа плиты статистики (кадр 19-1) — размер пула.
  ///
  /// In ru, this message translates to:
  /// **'В работе'**
  String get homeStatInWork;

  /// Строка «завтра» (кадры 19-1, 19-2), тап ведёт в «Мои слова». Заменила список «На грани забывания»: три слова с датами отвечали на вопрос, которого никто не задавал, а «сколько будет завтра» — на тот, который задают. Нуля не бывает: при нуле строки нет.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Завтра выпадет {count} слово} few{Завтра выпадет {count} слова} many{Завтра выпадет {count} слов} other{Завтра выпадет {count} слова}}'**
  String homeTomorrowRow(int count);

  /// Первая половина строки-награды (кадр 19-2). Со знаком плюс: это прибавление, а не счёт.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{+{count} слово продвинулось} few{+{count} слова продвинулись} many{+{count} слов продвинулись} other{+{count} слова продвинулись}}'**
  String homeAwardPromoted(int count);

  /// Вторая половина строки-награды (кадр 19-2): слово, дошедшее дальше всех, и имя его новой ступени — из тех же ladderStep*, что и карточка слова.
  ///
  /// In ru, this message translates to:
  /// **'{term} дошло до «{rung}»'**
  String homeAwardExample(String term, String rung);

  /// Заголовок карточки генерации (кадры 19-1, 19-2, 19-3). Тап открывает экран генерации — поля на главной больше нет: форма на главной была зрительным центром экрана, чей центр — день.
  ///
  /// In ru, this message translates to:
  /// **'Сгенерировать набор'**
  String get homeGenerateCardTitle;

  /// Подзаголовок карточки генерации (кадры 19-1, 19-2, 19-3).
  ///
  /// In ru, this message translates to:
  /// **'Опишите ситуацию — соберём набор под неё'**
  String get homeGenerateCardHint;

  /// Заголовок витрины обложек (кадры 19-2, 19-3).
  ///
  /// In ru, this message translates to:
  /// **'Готовые наборы'**
  String get homeStoreShowcaseTitle;

  /// Ссылка в конце витрины (кадры 19-2, 19-3) — стрелка рисуется иконкой.
  ///
  /// In ru, this message translates to:
  /// **'все {count}'**
  String homeStoreShowcaseAll(int count);

  /// Строка-обещание в подвале первого дня (кадр 19-3). Единственная цифра на экране, где статистики ещё нет.
  ///
  /// In ru, this message translates to:
  /// **'5 минут в день — 20 слов в неделю'**
  String get homeFirstDayPromise;

  /// Чип-пример темы под карточкой генерации первого дня (кадр 19-3).
  ///
  /// In ru, this message translates to:
  /// **'Ветклиника'**
  String get homeGenerateChipVet;

  /// Чип-пример темы под карточкой генерации первого дня (кадр 19-3).
  ///
  /// In ru, this message translates to:
  /// **'Переезд'**
  String get homeGenerateChipMoving;

  /// Приветствие первого дня (кадр 17c).
  ///
  /// In ru, this message translates to:
  /// **'Начнём с первого набора'**
  String get homeFirstDayTitle;

  /// Предложение свайп-прохода, когда день закрыт (кадры 17b/17d).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Можно разобрать ещё {count} слово из «{title}»} few{Можно разобрать ещё {count} слова из «{title}»} many{Можно разобрать ещё {count} слов из «{title}»} other{Можно разобрать ещё {count} слова из «{title}»}}'**
  String homeSortOffer(int count, String title);

  /// Кнопка свайп-прохода в состоянии Б (кадр 17d).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Разобрать {count} слово} few{Разобрать {count} слова} many{Разобрать {count} слов} other{Разобрать {count} слова}}'**
  String homeTriageAction(int count);

  /// Заголовок вместо «Всё повторено», когда в работе ещё ничего нет.
  ///
  /// In ru, this message translates to:
  /// **'Пора разобрать слова'**
  String get homeSortFirstTitle;

  /// Заголовок подтверждения смены родного языка.
  ///
  /// In ru, this message translates to:
  /// **'Переводы на «{language}»?'**
  String profileNativeLangConfirmTitle(String language);

  /// Тело подтверждения смены родного языка.
  ///
  /// In ru, this message translates to:
  /// **'Новые коллекции и планы будут на нём. Существующие коллекции останутся как есть — переводы в них не переписываются.'**
  String get profileNativeLangConfirmBody;

  /// Таб-бар: план подготовки (центральная, акцентная вкладка).
  ///
  /// In ru, this message translates to:
  /// **'План'**
  String get tabPlan;

  /// Лупа в шапке главной и коллекций — открывает экран поиска.
  ///
  /// In ru, this message translates to:
  /// **'Поиск'**
  String get searchOpen;

  /// Ярлык кнопки «назад» для скринридера — план и поиск.
  ///
  /// In ru, this message translates to:
  /// **'Назад'**
  String get commonBack;

  /// Dev-экран прослушки голосов (наряд TTS-1, Ч.2.4) и пункт в секции «Разработка».
  ///
  /// In ru, this message translates to:
  /// **'Голоса реплик'**
  String get devVoicesTitle;

  /// Пояснение под заголовком дев-экрана голосов.
  ///
  /// In ru, this message translates to:
  /// **'Одни и те же пять реплик qa-плана, прочитанные каждым кандидатом одним темпом. Слушайте на телефоне, а не в файлах: динамик и наушники решают больше, чем спектрограмма.'**
  String get devVoicesLead;

  /// Заголовок последнего блока — то, чем приложение читает сейчас.
  ///
  /// In ru, this message translates to:
  /// **'Системный голос телефона'**
  String get devVoicesSystem;

  /// Подпись под системным голосом: он не файл, а живой синтез.
  ///
  /// In ru, this message translates to:
  /// **'Играется вживую, темпом реплик. Голос — тот, что стоит в Настройках iOS; enhanced-голос надо скачать там же.'**
  String get devVoicesSystemNote;

  /// Цена озвучки всего плана этим голосом.
  ///
  /// In ru, this message translates to:
  /// **'≈ {price} за план'**
  String devVoicesPrice(String price);

  /// Кнопка «проиграть все пять реплик подряд».
  ///
  /// In ru, this message translates to:
  /// **'Подряд'**
  String get devVoicesPlayAll;

  /// Остановить проигрывание.
  ///
  /// In ru, this message translates to:
  /// **'Стоп'**
  String get devVoicesStop;

  /// Метка у голоса, который предложен как фаворит. Решение — за владельцем.
  ///
  /// In ru, this message translates to:
  /// **'Фаворит сессии'**
  String get devVoicesFavourite;

  /// Шапка таба «План» (plan.title).
  ///
  /// In ru, this message translates to:
  /// **'План'**
  String get planTitle;

  /// Заголовок витрины, Literata 30 — обещание результата, а не вопрос (кадр 21-1, plan.empty.title).
  ///
  /// In ru, this message translates to:
  /// **'Разговор, к которому готовишься'**
  String get planEmptyTitle;

  /// Подпись витрины в ОДНУ строку (кадр 21-1, plan.empty.sub).
  ///
  /// In ru, this message translates to:
  /// **'сцена в день · 20 минут · репетиция вслух'**
  String get planEmptySub;

  /// Первое правило плана — витрина 21-1 и лист 21-8 (plan.rule.situation).
  ///
  /// In ru, this message translates to:
  /// **'Каждый день — одна ситуация. Дни открываются по одному'**
  String get planRuleSituation;

  /// Второе правило плана; в листе 21-8 под ним стоит ряд пяти значков этапов (plan.rule.stages).
  ///
  /// In ru, this message translates to:
  /// **'Шесть этапов по порядку: слова → фразы → диалог → слушаю и отвечаю → говорю сам → разговор'**
  String get planRuleStages;

  /// Третье правило плана — витрина 21-1 и лист 21-8 (plan.rule.return).
  ///
  /// In ru, this message translates to:
  /// **'То, что не получилось, вернётся в следующий день. Ничего не потеряется'**
  String get planRuleReturn;

  /// Витрина 21-1, лента примеров: заголовок карточки «Приём у врача» (Literata 20). Статический пример клиента, тексты из кадра.
  ///
  /// In ru, this message translates to:
  /// **'Приём у врача'**
  String get planExampleDoctorTitle;

  /// Витрина 21-1, пример «Приём у врача»: название дня 1 — идёт после «День 1 · ».
  ///
  /// In ru, this message translates to:
  /// **'Запись к врачу'**
  String get planExampleDoctorDay1;

  /// Витрина 21-1, пример «Приём у врача», день 1: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'спросить время приёма'**
  String get planExampleDoctorGoal11;

  /// Витрина 21-1, пример «Приём у врача», день 1: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'договориться о приёме'**
  String get planExampleDoctorGoal12;

  /// Витрина 21-1, пример «Приём у врача», день 1: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'назвать страховку'**
  String get planExampleDoctorGoal13;

  /// Витрина 21-1, пример «Приём у врача»: название дня 2 — идёт после «День 2 · ».
  ///
  /// In ru, this message translates to:
  /// **'Приём у врача'**
  String get planExampleDoctorDay2;

  /// Витрина 21-1, пример «Приём у врача», день 2: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'описать боль'**
  String get planExampleDoctorGoal21;

  /// Витрина 21-1, пример «Приём у врача», день 2: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'ответить про лекарства'**
  String get planExampleDoctorGoal22;

  /// Витрина 21-1, пример «Приём у врача», день 2: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'понять назначения'**
  String get planExampleDoctorGoal23;

  /// Витрина 21-1, пример «Приём у врача»: название дня 3 — идёт после «День 3 · ».
  ///
  /// In ru, this message translates to:
  /// **'Аптека'**
  String get planExampleDoctorDay3;

  /// Витрина 21-1, пример «Приём у врача», день 3: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'назвать рецепт'**
  String get planExampleDoctorGoal31;

  /// Витрина 21-1, пример «Приём у врача», день 3: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'понять дозировку'**
  String get planExampleDoctorGoal32;

  /// Витрина 21-1, пример «Приём у врача», день 3: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'спросить аналог'**
  String get planExampleDoctorGoal33;

  /// Витрина 21-1, лента примеров: заголовок карточки «Собеседование» (Literata 20). Статический пример клиента, тексты из кадра.
  ///
  /// In ru, this message translates to:
  /// **'Собеседование'**
  String get planExampleInterviewTitle;

  /// Витрина 21-1, пример «Собеседование»: название дня 1 — идёт после «День 1 · ».
  ///
  /// In ru, this message translates to:
  /// **'Знакомство'**
  String get planExampleInterviewDay1;

  /// Витрина 21-1, пример «Собеседование», день 1: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'рассказать о себе'**
  String get planExampleInterviewGoal11;

  /// Витрина 21-1, пример «Собеседование», день 1: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'назвать свой опыт'**
  String get planExampleInterviewGoal12;

  /// Витрина 21-1, пример «Собеседование», день 1: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'объяснить, почему ушёл'**
  String get planExampleInterviewGoal13;

  /// Витрина 21-1, пример «Собеседование»: название дня 2 — идёт после «День 2 · ».
  ///
  /// In ru, this message translates to:
  /// **'Вопросы о работе'**
  String get planExampleInterviewDay2;

  /// Витрина 21-1, пример «Собеседование», день 2: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'описать проект'**
  String get planExampleInterviewGoal21;

  /// Витрина 21-1, пример «Собеседование», день 2: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'ответить про сроки'**
  String get planExampleInterviewGoal22;

  /// Витрина 21-1, пример «Собеседование», день 2: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'признать ошибку'**
  String get planExampleInterviewGoal23;

  /// Витрина 21-1, пример «Собеседование»: название дня 3 — идёт после «День 3 · ».
  ///
  /// In ru, this message translates to:
  /// **'Зарплата'**
  String get planExampleInterviewDay3;

  /// Витрина 21-1, пример «Собеседование», день 3: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'назвать вилку'**
  String get planExampleInterviewGoal31;

  /// Витрина 21-1, пример «Собеседование», день 3: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'спросить про бонусы'**
  String get planExampleInterviewGoal32;

  /// Витрина 21-1, пример «Собеседование», день 3: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'обсудить выход'**
  String get planExampleInterviewGoal33;

  /// Витрина 21-1, лента примеров: заголовок карточки «Звонок арендодателю» (Literata 20). Статический пример клиента, тексты из кадра.
  ///
  /// In ru, this message translates to:
  /// **'Звонок арендодателю'**
  String get planExampleLandlordTitle;

  /// Витрина 21-1, пример «Звонок арендодателю»: название дня 1 — идёт после «День 1 · ».
  ///
  /// In ru, this message translates to:
  /// **'Про залог'**
  String get planExampleLandlordDay1;

  /// Витрина 21-1, пример «Звонок арендодателю», день 1: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'спросить сумму'**
  String get planExampleLandlordGoal11;

  /// Витрина 21-1, пример «Звонок арендодателю», день 1: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'узнать, когда вернут'**
  String get planExampleLandlordGoal12;

  /// Витрина 21-1, пример «Звонок арендодателю», день 1: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'назвать свой счёт'**
  String get planExampleLandlordGoal13;

  /// Витрина 21-1, пример «Звонок арендодателю»: название дня 2 — идёт после «День 2 · ».
  ///
  /// In ru, this message translates to:
  /// **'Про ремонт'**
  String get planExampleLandlordDay2;

  /// Витрина 21-1, пример «Звонок арендодателю», день 2: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'описать поломку'**
  String get planExampleLandlordGoal21;

  /// Витрина 21-1, пример «Звонок арендодателю», день 2: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'попросить мастера'**
  String get planExampleLandlordGoal22;

  /// Витрина 21-1, пример «Звонок арендодателю», день 2: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'договориться о времени'**
  String get planExampleLandlordGoal23;

  /// Витрина 21-1, пример «Звонок арендодателю»: название дня 3 — идёт после «День 3 · ».
  ///
  /// In ru, this message translates to:
  /// **'Про договор'**
  String get planExampleLandlordDay3;

  /// Витрина 21-1, пример «Звонок арендодателю», день 3: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'спросить про срок'**
  String get planExampleLandlordGoal31;

  /// Витрина 21-1, пример «Звонок арендодателю», день 3: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'уточнить про питомцев'**
  String get planExampleLandlordGoal32;

  /// Витрина 21-1, пример «Звонок арендодателю», день 3: что человек скажет после дня — строка у латунной точки.
  ///
  /// In ru, this message translates to:
  /// **'назвать дату выезда'**
  String get planExampleLandlordGoal33;

  /// Витрина 21-1: мета примера «5 дней · начальный»; days — planDaysCount, level — название уровня строчными.
  ///
  /// In ru, this message translates to:
  /// **'{days} · {level}'**
  String planExampleMeta(String days, String level);

  /// Витрина 21-1: подвал примера, когда в плане больше трёх дней («ещё 2 дня»).
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{ещё {n} день} few{ещё {n} дня} many{ещё {n} дней} other{ещё {n} дня}}'**
  String planExampleMore(int n);

  /// Витрина 21-1: подвал примера, где показаны все дни плана (3 дня).
  ///
  /// In ru, this message translates to:
  /// **'весь план'**
  String get planExampleWhole;

  /// Кадр 21-1, кнопка (plan.empty.cta).
  ///
  /// In ru, this message translates to:
  /// **'Собрать план'**
  String get planEmptyCta;

  /// Кадры 21-1, 21-2b (plan.finished.title).
  ///
  /// In ru, this message translates to:
  /// **'Завершённые планы'**
  String get planFinishedTitle;

  /// Кадры 21-1, 21-2b; дата по локали (plan.finished.item.date).
  ///
  /// In ru, this message translates to:
  /// **'завершён {date}'**
  String planFinishedItemDate(String date);

  /// Бровь шапки плана, 11/700 caps (кадр 21-2, plan.header.brow).
  ///
  /// In ru, this message translates to:
  /// **'План · день {n} из {total}'**
  String planHeaderBrow(int n, int total);

  /// Плита дня, лейбл латунью (plan.plate.label); окно дня — бровь «ДЕНЬ 2» светлой латунью на плите (23-0a…0c).
  ///
  /// In ru, this message translates to:
  /// **'День {n}'**
  String planPlateLabel(int n);

  /// Счётные формы карточек: 1 карточка / 2 карточки / 5 карточек (plan.plate.meta, plan.closed.meta, plan.closed.return).
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} карточка} few{{n} карточки} many{{n} карточек} other{{n} карточки}}'**
  String planCardsCount(int n);

  /// Счётные формы минут: 1 минута / 2 минуты / 5 минут (plan.plate.meta, plan.closed.meta); окно дня — «≈ 20 минут» не начатого, «19 минут» пройденного (23-0a, 23-0c).
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} минута} few{{n} минуты} many{{n} минут} other{{n} минуты}}'**
  String planMinutesCount(int n);

  /// Плита дня, этап (plan.plate.stage.words); окно дня — ряд этапа на плите и вкладка «Слова» с бровью (23-0a…0d).
  ///
  /// In ru, this message translates to:
  /// **'Слова'**
  String get planPlateStageWords;

  /// Плита дня, этап (plan.plate.stage.phrases); окно дня — ряд этапа на плите и вкладка «Фразы» с бровью (23-0a…0d).
  ///
  /// In ru, this message translates to:
  /// **'Фразы'**
  String get planPlateStagePhrases;

  /// Плита дня, этап (plan.plate.stage.dialog); окно дня — ряд этапа на плите и вкладка «Диалог» с бровью (23-0a…0d).
  ///
  /// In ru, this message translates to:
  /// **'Диалог'**
  String get planPlateStageDialog;

  /// Плита дня, этап (plan.plate.stage.listen); окно дня — ряд этапа на плите (23-0a…0c).
  ///
  /// In ru, this message translates to:
  /// **'Слушаю и отвечаю'**
  String get planPlateStageListen;

  /// Плита дня, этап (plan.plate.stage.speak); окно дня — ряд этапа на плите (23-0a…0c).
  ///
  /// In ru, this message translates to:
  /// **'Говорю сам'**
  String get planPlateStageSpeak;

  /// Плита дня (21-2 … 21-4) и окно дня (23-0b, 23-0c): состояние этапа словами справа — этап пройден.
  ///
  /// In ru, this message translates to:
  /// **'пройдено'**
  String get planPlateStateDone;

  /// Плита дня (21-2, 21-3) и окно дня (23-0b, «идёт · ≈ 8 мин»): состояние текущего этапа словами справа.
  ///
  /// In ru, this message translates to:
  /// **'идёт'**
  String get planPlateStateCurrent;

  /// Плита дня (21-2) и окно дня (23-0a, 23-0b): состояние этапа, до которого ещё не дошли.
  ///
  /// In ru, this message translates to:
  /// **'впереди'**
  String get planPlateStateAhead;

  /// Формы «1 новое слово / 2 новых слова / 5 новых слов» (plan.plate.stage.sub.start).
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} новое слово} few{{n} новых слова} many{{n} новых слов} other{{n} новых слова}}'**
  String planNewWordsCount(int n);

  /// Плита дня, вторая строка текущего этапа (plan.plate.stage.sub.start); words — planNewWordsCount.
  ///
  /// In ru, this message translates to:
  /// **'начни отсюда · {words}'**
  String planPlateStageSubStart(String words);

  /// Плита дня, вторая строка брошенного этапа (plan.plate.stage.sub.unfinished); cards — planCardsCount. Оценки минут в контракте нет — часть «≈ N мин» не рисуется, как и вся строка plan.plate.meta «{n} карточек · ≈ {min} минут» (вопрос архитектору).
  ///
  /// In ru, this message translates to:
  /// **'не закончен · {cards}'**
  String planPlateStageSubUnfinished(String cards);

  /// Плита дня, кнопка (plan.plate.cta.start); окно дня — одна кнопка внизу у не начатого дня (allowed_action = start, 23-0a).
  ///
  /// In ru, this message translates to:
  /// **'Начать'**
  String get planPlateCtaStart;

  /// Плита дня, лейбл латунью у дня плана, который догоняет дату события (catch_up сервера, наряд SESSION-2a §6): следующий день открывается сразу после закрытия предыдущего.
  ///
  /// In ru, this message translates to:
  /// **'День {n} · догоняем'**
  String planPlateLabelCatchUp(int n);

  /// Строка вместо этапов, пока день пишется (кадр 22-5a, plan.plate.building.title).
  ///
  /// In ru, this message translates to:
  /// **'Собираем день {n}'**
  String planPlateBuildingTitle(int n);

  /// Подпись под «Собираем день N»: срок назван, уходить разрешено (кадр 22-5a, plan.plate.building.sub).
  ///
  /// In ru, this message translates to:
  /// **'около минуты · можно закрыть приложение'**
  String get planPlateBuildingSub;

  /// Строка вместо этапов, когда день не собрался (кадр 22-5c, plan.plate.failed.title): урок не прошёл ворота дважды (lesson_status = failed) — это не сеть (CLIENT-START §6).
  ///
  /// In ru, this message translates to:
  /// **'Не получилось собрать день'**
  String get planPlateFailedTitle;

  /// Кнопка плиты у несобравшегося дня (кадр 22-5c, plan.plate.cta.retry).
  ///
  /// In ru, this message translates to:
  /// **'Повторить'**
  String get planPlateCtaRetry;

  /// Плита дня, кнопка (plan.plate.cta.continue); окно дня — одна кнопка внизу у идущего дня (allowed_action = continue, 23-0b).
  ///
  /// In ru, this message translates to:
  /// **'Продолжить'**
  String get planPlateCtaContinue;

  /// Кадр 21-4 (plan.closed.title).
  ///
  /// In ru, this message translates to:
  /// **'День {n} закрыт'**
  String planClosedTitle(int n);

  /// Счёт закрытого дня в шапке плиты: «75 карточек · 19 минут» (кадр 21-4, plan.closed.count).
  ///
  /// In ru, this message translates to:
  /// **'{cards} · {minutes}'**
  String planClosedCount(String cards, String minutes);

  /// Первая строка подвала закрытого дня (кадр 21-4, plan.closed.next.tomorrow).
  ///
  /// In ru, this message translates to:
  /// **'День {n} откроется завтра, {date}'**
  String planClosedNextTomorrow(int n, String date);

  /// Подвал закрытого дня, когда следующий день не «завтра» (кадр 21-4, plan.closed.next.on).
  ///
  /// In ru, this message translates to:
  /// **'День {n} откроется {date}'**
  String planClosedNextOn(int n, String date);

  /// Вторая строка подвала закрытого дня, терракотой (кадр 21-4, plan.closed.return).
  ///
  /// In ru, this message translates to:
  /// **'{k, plural, one{{k} карточка вернётся в день {n} →} few{{k} карточки вернутся в день {n} →} many{{k} карточек вернутся в день {n} →} other{{k} карточки вернутся в день {n} →}}'**
  String planClosedReturn(int n, int k);

  /// Строка маршрута (plan.route.day.repeat.sub).
  ///
  /// In ru, this message translates to:
  /// **'слова и фразы дней {a}–{b}'**
  String planRouteDayRepeatSub(int a, int b);

  /// Строка маршрута, когда повторению предшествует один день ситуации (день 1 → повторение день 2).
  ///
  /// In ru, this message translates to:
  /// **'слова и фразы дня {a}'**
  String planRouteDayRepeatSubOne(int a);

  /// Заголовок дня на маршруте (кадры 21-2b, 22-4b): номер дня и название; мета стоит строкой ниже. Окно дня — строка компактной шапки 56 «День 2 · Приём у врача» (23-0a…0d, прокручено).
  ///
  /// In ru, this message translates to:
  /// **'День {n} · {title}'**
  String planRouteDayTitle(int n, String title);

  /// Название дня повторения на маршруте (кадры 21-2b, 22-4b) и в окне дня повторения вместо названия сцены.
  ///
  /// In ru, this message translates to:
  /// **'Повторение'**
  String get planRouteDayReview;

  /// Строка маршрута, tertiary (plan.route.day.rehearsal); окно дня репетиции — название вместо названия сцены.
  ///
  /// In ru, this message translates to:
  /// **'Репетиция'**
  String get planRouteDayRehearsal;

  /// Строка маршрута (plan.route.day.rehearsal.sub).
  ///
  /// In ru, this message translates to:
  /// **'весь маршрут вслух'**
  String get planRouteDayRehearsalSub;

  /// Мета-строка пройденного дня маршрута (кадр 21-2b, plan.route.meta.passed).
  ///
  /// In ru, this message translates to:
  /// **'пройден'**
  String get planRouteMetaPassed;

  /// Минуты в мета-строке маршрута, сокращённо (кадр 21-2b, plan.route.meta.minutes); окно дня — «≈ 12 мин» идущего дня, текущего этапа и компактной шапки (23-0b).
  ///
  /// In ru, this message translates to:
  /// **'{n} мин'**
  String planMinutesShort(int n);

  /// Мета-строка ПЕРВОГО запертого дня маршрута (кадр 21-2b, plan.route.meta.opens.after).
  ///
  /// In ru, this message translates to:
  /// **'откроется после дня {n}'**
  String planRouteMetaOpensAfter(int n);

  /// Мета-строка первого запертого дня, когда предыдущий уже пройден (кадр 21-4, plan.route.meta.opens.tomorrow).
  ///
  /// In ru, this message translates to:
  /// **'откроется завтра'**
  String get planRouteMetaOpensTomorrow;

  /// Мишень события без даты — пунктирный узел маршрута (кадр 21-2b, plan.route.event.nodate).
  ///
  /// In ru, this message translates to:
  /// **'указать дату'**
  String get planRouteEventNoDate;

  /// Заголовок мишени, когда сервер не назвал событие (кадр 21-2b, plan.route.event.fallback).
  ///
  /// In ru, this message translates to:
  /// **'Событие'**
  String get planRouteEventFallback;

  /// Кадр 21-7 (plan.done.title).
  ///
  /// In ru, this message translates to:
  /// **'План пройден'**
  String get planDoneTitle;

  /// Счётные формы дней: 1 день / 2 дня / 5 дней (plan.done.meta, plan.overdue.meta, entry.preview.sub).
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} день} few{{n} дня} many{{n} дней} other{{n} дня}}'**
  String planDaysCount(int n);

  /// Кадр 21-7 (plan.done.meta): «7 дней». Части «фраз и слов в работе» в контракте нет — не рисуются (вопрос архитектору).
  ///
  /// In ru, this message translates to:
  /// **'{days}'**
  String planDoneMeta(String days);

  /// Кадр 21-7 (plan.done.collection); name — сервер.
  ///
  /// In ru, this message translates to:
  /// **'Слова и фразы плана остались в коллекции «{name}» — они будут приходить на повторение'**
  String planDoneCollection(String name);

  /// Кадр 21-7 (plan.done.cta).
  ///
  /// In ru, this message translates to:
  /// **'Собрать новый план'**
  String get planDoneCta;

  /// Кадр 21-7, режим чтения (plan.done.cta.readonly).
  ///
  /// In ru, this message translates to:
  /// **'Открыть коллекцию'**
  String get planDoneCtaReadonly;

  /// Кадр 21-9 (plan.menu.date).
  ///
  /// In ru, this message translates to:
  /// **'Изменить дату'**
  String get planMenuDate;

  /// Кадр 21-9 (plan.menu.new).
  ///
  /// In ru, this message translates to:
  /// **'Собрать новый план'**
  String get planMenuNew;

  /// Кадр 21-9 (plan.menu.collection).
  ///
  /// In ru, this message translates to:
  /// **'Открыть коллекцию'**
  String get planMenuCollection;

  /// Кадр 21-9, терракота (plan.menu.delete).
  ///
  /// In ru, this message translates to:
  /// **'Удалить план'**
  String get planMenuDelete;

  /// Подпись кнопки-меню в шапке таба для читалки экрана; кадра нет.
  ///
  /// In ru, this message translates to:
  /// **'Меню плана'**
  String get planMenuLabel;

  /// Кадр 21-10 (plan.date.title). По таблице строку склоняет сервер; в контракте её нет — собрана из event_native (вопрос архитектору).
  ///
  /// In ru, this message translates to:
  /// **'Когда {event}?'**
  String planDateTitle(String event);

  /// Кадр 21-10 у плана без даты и без названного события.
  ///
  /// In ru, this message translates to:
  /// **'Когда событие?'**
  String get planDateTitleNoEvent;

  /// Кадр 21-10 (plan.date.option.current); дата по локали.
  ///
  /// In ru, this message translates to:
  /// **'{date} · как сейчас'**
  String planDateOptionCurrent(String date);

  /// Кадры 21-10, 22-3b (plan.date.option.other, entry.date.option.other).
  ///
  /// In ru, this message translates to:
  /// **'Другая дата'**
  String get planDateOptionOther;

  /// Кадры 21-10, 22-3b (plan.date.option.other.sub, entry.date.option.other.sub).
  ///
  /// In ru, this message translates to:
  /// **'выбрать в календаре'**
  String get planDateOptionOtherSub;

  /// Кадр 21-10 (plan.date.cta).
  ///
  /// In ru, this message translates to:
  /// **'Применить'**
  String get planDateCta;

  /// Кадры 21-10, 21-12 (plan.date.cancel).
  ///
  /// In ru, this message translates to:
  /// **'Отменить'**
  String get planDateCancel;

  /// Кадр 21-10, план с датой: снять дату (event_date: null). Строки в таблице нет — добавлена нарядом PLAN-UI по контракту PATCH /schedule.
  ///
  /// In ru, this message translates to:
  /// **'Без даты'**
  String get planDateRemove;

  /// Кадр 21-11 (plan.new.title).
  ///
  /// In ru, this message translates to:
  /// **'Начать другой план?'**
  String get planNewTitle;

  /// Кадр 21-11 (plan.new.body); name — сервер.
  ///
  /// In ru, this message translates to:
  /// **'Этот план завершится на дне {n} из {total}. Всё, что уже в работе, останется в коллекции «{name}» и будет приходить на повторение'**
  String planNewBody(int n, int total, String name);

  /// Кадр 21-11 (plan.new.cta).
  ///
  /// In ru, this message translates to:
  /// **'Собрать новый'**
  String get planNewCta;

  /// Кадр 21-11 (plan.new.keep).
  ///
  /// In ru, this message translates to:
  /// **'Оставить этот'**
  String get planNewKeep;

  /// Кадр 21-12 (plan.delete.title).
  ///
  /// In ru, this message translates to:
  /// **'Удалить план?'**
  String get planDeleteTitle;

  /// Кадр 21-12 (plan.delete.body); name — сервер.
  ///
  /// In ru, this message translates to:
  /// **'План исчезнет из истории. Коллекция «{name}» и её слова останутся'**
  String planDeleteBody(String name);

  /// Кадр 21-12 у плана, у которого коллекции ещё нет (ни один день не закрыт).
  ///
  /// In ru, this message translates to:
  /// **'План исчезнет из истории'**
  String get planDeleteBodyNoCollection;

  /// Кадр 21-12, терракота (plan.delete.confirm).
  ///
  /// In ru, this message translates to:
  /// **'Удалить'**
  String get planDeleteConfirm;

  /// Кадр 21-14 (plan.overdue.meta): «Пройдено 4 дня из 7»; days — planDaysCount. Части «фраз и слов в работе» в контракте нет.
  ///
  /// In ru, this message translates to:
  /// **'Пройдено {days} из {total}'**
  String planOverdueMeta(String days, int total);

  /// Кадр 21-14 (plan.overdue.finish).
  ///
  /// In ru, this message translates to:
  /// **'Завершить план'**
  String get planOverdueFinish;

  /// Второй выход прошедшего события — вернуться к текущему дню (кадр 21-14, plan.overdue.continue).
  ///
  /// In ru, this message translates to:
  /// **'Дозаниматься · {days}'**
  String planOverdueContinue(String days);

  /// Кадр 21-2c (plan.hint.first.start). stage — имя первого ряда плиты дня из stages[] сервера: «Слова» у дня-сцены, «Повторение» у повторения, «Вспомнить» у репетиции (приёмка CLIENT-CONV-1c 22.09).
  ///
  /// In ru, this message translates to:
  /// **'Начни с этапа «{stage}». Остальные откроются по порядку'**
  String planHintFirstStart(String stage);

  /// Подсказка первого плана под заголовком маршрута, один раз (кадр 21-2c, plan.hint.first.route).
  ///
  /// In ru, this message translates to:
  /// **'День {n} открыт. Следующий откроется, когда пройдёшь этот'**
  String planHintFirstRoute(int n);

  /// Плашка пересборки над плитой — состояние плана, не сообщение (кадр 21-13, plan.rebuilt.title).
  ///
  /// In ru, this message translates to:
  /// **'Маршрут пересобран: было {from} дней, стало {to}'**
  String planRebuiltTitle(int from, int to);

  /// Кадр 21-4c (plan.hint.first.return).
  ///
  /// In ru, this message translates to:
  /// **'Эти карточки придут в следующий день ещё раз — так они и запоминаются'**
  String get planHintFirstReturn;

  /// Кадр 21-8 (plan.sheet.title).
  ///
  /// In ru, this message translates to:
  /// **'Как устроен план'**
  String get planSheetTitle;

  /// Кадр 21-8 (plan.sheet.cta).
  ///
  /// In ru, this message translates to:
  /// **'Понятно'**
  String get planSheetCta;

  /// Тихая строка на табе, когда показано последнее известное состояние из кэша (наряд PLAN-UI, §6).
  ///
  /// In ru, this message translates to:
  /// **'нет сети'**
  String get planTabOffline;

  /// Таб без кэша, когда сервер не ответил (§6: состояние с «Повторить»). Строки в таблице нет — добавлена нарядом PLAN-UI.
  ///
  /// In ru, this message translates to:
  /// **'Не получилось загрузить план'**
  String get planTabLoadFailedTitle;

  /// Кнопка «Повторить» на табе и на плите (entry.day.failed.retry).
  ///
  /// In ru, this message translates to:
  /// **'Повторить'**
  String get planTabRetry;

  /// Шапка входа, справа, 17/700 (entry.next).
  ///
  /// In ru, this message translates to:
  /// **'Далее'**
  String get planEntryNext;

  /// Вопрос шага цели, Literata 26 (кадр 22-1, entry.goal.title).
  ///
  /// In ru, this message translates to:
  /// **'К чему готовишься?'**
  String get planEntryGoalTitle;

  /// Подпись под вопросом цели (кадр 22-1, entry.goal.sub).
  ///
  /// In ru, this message translates to:
  /// **'Расскажи ситуацию своими словами: что будет, с кем говоришь, чего боишься'**
  String get planEntryGoalSub;

  /// Печатающийся плейсхолдер поля цели, кадр 1 — примеры НЕ те, что в списке историй (кадр 22-1, entry.goal.typing.1).
  ///
  /// In ru, this message translates to:
  /// **'Собеседование в пятницу, боюсь…'**
  String get planEntryGoalTyping1;

  /// Печатающийся плейсхолдер поля цели, кадр 2 (кадр 22-1, entry.goal.typing.2).
  ///
  /// In ru, this message translates to:
  /// **'Звоню в банк, не понимаю по телефону…'**
  String get planEntryGoalTyping2;

  /// Печатающийся плейсхолдер поля цели, кадр 3 (кадр 22-1, entry.goal.typing.3).
  ///
  /// In ru, this message translates to:
  /// **'Иду к врачу…'**
  String get planEntryGoalTyping3;

  /// Метка списка историй под полем цели (кадр 22-1, entry.goal.stories.title).
  ///
  /// In ru, this message translates to:
  /// **'Так пишут другие'**
  String get planEntryGoalStoriesTitle;

  /// История «так пишут другие» — тап подставляет её в поле (кадр 22-1, entry.goal.story.1).
  ///
  /// In ru, this message translates to:
  /// **'Собеседование в пятницу, боюсь вопросов про опыт'**
  String get planEntryGoalStory1;

  /// История «так пишут другие» (кадр 22-1, entry.goal.story.2).
  ///
  /// In ru, this message translates to:
  /// **'К врачу с ребёнком, первый раз в местной клинике'**
  String get planEntryGoalStory2;

  /// История «так пишут другие» (кадр 22-1, entry.goal.story.3).
  ///
  /// In ru, this message translates to:
  /// **'Звонок арендодателю про залог'**
  String get planEntryGoalStory3;

  /// Подсказка под коротким ответом; НЕ блокирует «Далее» (кадр 22-1c, entry.goal.short.hint).
  ///
  /// In ru, this message translates to:
  /// **'Добавь, с кем и что важно — план будет точнее'**
  String get planEntryGoalShortHint;

  /// Подпись у микрофона в покое (кадр 22-1, entry.goal.dictate).
  ///
  /// In ru, this message translates to:
  /// **'или надиктуй'**
  String get planEntryGoalDictate;

  /// Подпись у микрофона после распознавания (кадр 22-1, entry.goal.dictate.edit).
  ///
  /// In ru, this message translates to:
  /// **'можно поправить руками'**
  String get planEntryGoalDictateEdit;

  /// Таймер и приглашение над волной записи (кадр 22-1, entry.goal.listening).
  ///
  /// In ru, this message translates to:
  /// **'{time} · говори, я слушаю'**
  String planEntryGoalListening(String time);

  /// Лента ответов (entry.tape.goal).
  ///
  /// In ru, this message translates to:
  /// **'Цель'**
  String get planEntryTapeGoal;

  /// Лента ответов (entry.tape.language).
  ///
  /// In ru, this message translates to:
  /// **'Язык'**
  String get planEntryTapeLanguage;

  /// Лента ответов (entry.tape.days).
  ///
  /// In ru, this message translates to:
  /// **'Дни'**
  String get planEntryTapeDays;

  /// Лента ответов: «Английский · Средний».
  ///
  /// In ru, this message translates to:
  /// **'{language} · {level}'**
  String planEntryTapeLanguageValue(String language, String level);

  /// Вопрос шага языка (кадр 22-2, entry.language.title).
  ///
  /// In ru, this message translates to:
  /// **'На каком языке говорить?'**
  String get planEntryLanguageTitle;

  /// Метка зоны языков (кадр 22-2, entry.language.label).
  ///
  /// In ru, this message translates to:
  /// **'Язык'**
  String get planEntryLanguageLabel;

  /// Кадр 22-2 (entry.level.label).
  ///
  /// In ru, this message translates to:
  /// **'Уровень'**
  String get planEntryLevelLabel;

  /// Уровень — название (кадр 22-2, entry.level.beginner).
  ///
  /// In ru, this message translates to:
  /// **'Начальный'**
  String get planEntryLevelBeginner;

  /// Уровень описан тем, что человек умеет (кадр 22-2, entry.level.beginner.sub).
  ///
  /// In ru, this message translates to:
  /// **'знаю отдельные слова'**
  String get planEntryLevelBeginnerSub;

  /// Кадр 22-2 (entry.level.intermediate).
  ///
  /// In ru, this message translates to:
  /// **'Средний'**
  String get planEntryLevelIntermediate;

  /// Уровень описан тем, что человек умеет (кадр 22-2, entry.level.intermediate.sub).
  ///
  /// In ru, this message translates to:
  /// **'понимаю простую речь, говорю с ошибками'**
  String get planEntryLevelIntermediateSub;

  /// Вопрос шага длины плана (кадр 22-3a, entry.days.title).
  ///
  /// In ru, this message translates to:
  /// **'Сколько дней до разговора?'**
  String get planEntryDaysTitle;

  /// Состав длины плана — ситуации (кадр 22-3a, entry.days.scenes).
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} ситуация} few{{n} ситуации} many{{n} ситуаций} other{{n} ситуации}}'**
  String planEntryDaysScenes(int n);

  /// Состав длины плана — дни повторения (кадр 22-3a, entry.days.reviews).
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} повторение} few{{n} повторения} many{{n} повторений} other{{n} повторения}}'**
  String planEntryDaysReviews(int n);

  /// Состав длины плана — репетиция, она есть всегда (кадр 22-3a, entry.days.rehearsal).
  ///
  /// In ru, this message translates to:
  /// **'репетиция'**
  String get planEntryDaysRehearsal;

  /// Вопрос шага даты (кадр 22-3b, entry.date.title).
  ///
  /// In ru, this message translates to:
  /// **'Когда разговор?'**
  String get planEntryDateTitle;

  /// Метка зоны выбора даты (кадр 22-3b, entry.date.label).
  ///
  /// In ru, this message translates to:
  /// **'Дата'**
  String get planEntryDateLabel;

  /// Подпись ближней даты: день недели и сколько до неё (кадр 22-3b, entry.date.in).
  ///
  /// In ru, this message translates to:
  /// **'{weekday} · {n, plural, one{через {n} день} few{через {n} дня} many{через {n} дней} other{через {n} дня}}'**
  String planEntryDateIn(String weekday, int n);

  /// Равноправный вариант выбора даты (кадр 22-3b, entry.date.unknown).
  ///
  /// In ru, this message translates to:
  /// **'Дата пока неизвестна'**
  String get planEntryDateUnknown;

  /// Подпись варианта без даты (кадр 22-3b, entry.date.unknown.sub).
  ///
  /// In ru, this message translates to:
  /// **'план без даты, дни идут подряд'**
  String get planEntryDateUnknownSub;

  /// Третий вариант выбора даты (кадр 22-3b, entry.date.other).
  ///
  /// In ru, this message translates to:
  /// **'Другая дата'**
  String get planEntryDateOther;

  /// Подпись варианта «Другая дата» (кадр 22-3b, entry.date.other.sub).
  ///
  /// In ru, this message translates to:
  /// **'выбрать в календаре'**
  String get planEntryDateOtherSub;

  /// Строка следствия под выбором даты (кадр 22-3b, entry.date.rehearsal.on).
  ///
  /// In ru, this message translates to:
  /// **'Репетиция встанет на {date} — день перед разговором'**
  String planEntryDateRehearsalOn(String date);

  /// Кнопка шага даты — называет результат, а не «Готово» (кадр 22-3b, entry.date.cta).
  ///
  /// In ru, this message translates to:
  /// **'Собрать план'**
  String get planEntryDateCta;

  /// Заголовок готового превью (кадр 22-4b, entry.preview.title).
  ///
  /// In ru, this message translates to:
  /// **'Твой план готов'**
  String get planEntryPreviewTitle;

  /// Заголовок превью во время сборки (кадр 22-4a, entry.preview.loading.title).
  ///
  /// In ru, this message translates to:
  /// **'Собираю план'**
  String get planEntryPreviewLoadingTitle;

  /// Срок сборки человеческими словами, без процента (кадр 22-4a, entry.preview.about).
  ///
  /// In ru, this message translates to:
  /// **'Около 10 секунд'**
  String get planEntryPreviewAbout;

  /// Прелоадер 22-4a и 22-5a: первая строка статуса по кругу (om-pre-line1).
  ///
  /// In ru, this message translates to:
  /// **'Подбираю ситуации под твой разговор'**
  String get planEntryPreviewLine1;

  /// Прелоадер 22-4a и 22-5a: вторая строка статуса (om-pre-line2).
  ///
  /// In ru, this message translates to:
  /// **'Раскладываю их по дням'**
  String get planEntryPreviewLine2;

  /// Прелоадер 22-4a и 22-5a: третья строка статуса (om-pre-line3).
  ///
  /// In ru, this message translates to:
  /// **'Собираю слова и фразы'**
  String get planEntryPreviewLine3;

  /// Превью 22-4b: метка плиты с пересказом плана (summary сервера).
  ///
  /// In ru, this message translates to:
  /// **'Как это будет'**
  String get planEntryPreviewHowLabel;

  /// Превью 22-4b: текстовая ссылка над «Начать» — вернуться к ответам входа.
  ///
  /// In ru, this message translates to:
  /// **'Изменить'**
  String get planEntryPreviewEdit;

  /// Кадр 22-4 (entry.preview.cta).
  ///
  /// In ru, this message translates to:
  /// **'Начать'**
  String get planEntryPreviewCta;

  /// Заголовок неудачной сборки (кадр 22-4c, entry.preview.error.title).
  ///
  /// In ru, this message translates to:
  /// **'План не собрался'**
  String get planEntryPreviewErrorTitle;

  /// Что случилось при неудачной сборке (кадр 22-4c, entry.preview.error.what).
  ///
  /// In ru, this message translates to:
  /// **'Сеть пропала на середине'**
  String get planEntryPreviewErrorWhat;

  /// Что уцелело при неудачной сборке (кадр 22-4c, entry.preview.error.sub).
  ///
  /// In ru, this message translates to:
  /// **'Ответы сохранены — попробуй ещё раз, заново рассказывать не придётся'**
  String get planEntryPreviewErrorSub;

  /// Кнопка неудачной сборки; повтор не уводит на первый шаг (кадр 22-4c, entry.preview.error.retry).
  ///
  /// In ru, this message translates to:
  /// **'Попробовать ещё'**
  String get planEntryPreviewErrorRetry;

  /// Заголовок, когда цель непонятна — просьба, не упрёк (кадр 22-4d, entry.preview.unclear.title).
  ///
  /// In ru, this message translates to:
  /// **'Нужно чуть больше'**
  String get planEntryPreviewUnclearTitle;

  /// Цитата ответа человека в кадре «цель непонятна» (кадр 22-4d, entry.preview.unclear.quote).
  ///
  /// In ru, this message translates to:
  /// **'«{goal}» — это про что?'**
  String planEntryPreviewUnclearQuote(String goal);

  /// Три примера того, чего не хватает (кадр 22-4d, entry.preview.unclear.sub).
  ///
  /// In ru, this message translates to:
  /// **'Напиши, где будешь говорить и с кем: приём у врача, звонок в банк, разговор с соседом'**
  String get planEntryPreviewUnclearSub;

  /// Кнопка возврата к полю цели с сохранённым текстом (кадр 22-4d, entry.preview.unclear.cta).
  ///
  /// In ru, this message translates to:
  /// **'К цели'**
  String get planEntryPreviewUnclearCta;

  /// Кадр 22-6 (entry.push.title).
  ///
  /// In ru, this message translates to:
  /// **'План готов'**
  String get planEntryPushTitle;

  /// Тело уведомления «План готов»: срок до события и первый день (кадр 22-6, entry.push.body).
  ///
  /// In ru, this message translates to:
  /// **'{until}. День 1 — «{dayTitle}»'**
  String planEntryPushBody(String until, String dayTitle);

  /// Тело уведомления у плана без даты события (кадр 22-6, entry.push.body.nodate).
  ///
  /// In ru, this message translates to:
  /// **'{days}. День 1 — «{dayTitle}»'**
  String planEntryPushBodyNoDate(String days, String dayTitle);

  /// Уведомление «день собран» (кадр 22-6 — эталон вида); без push — баннер в приложении при возврате.
  ///
  /// In ru, this message translates to:
  /// **'День {n} собран'**
  String planNotifyDayReadyTitle(int n);

  /// Тело уведомления «день собран».
  ///
  /// In ru, this message translates to:
  /// **'«{title}» — можно начинать'**
  String planNotifyDayReadyBody(String title);

  /// Ежедневное напоминание в час обычного захода (локальное уведомление, не чаще раза в сутки).
  ///
  /// In ru, this message translates to:
  /// **'День {n} ждёт'**
  String planNotifyReminderTitle(int n);

  /// Тело ежедневного напоминания.
  ///
  /// In ru, this message translates to:
  /// **'«{title}» — начни с того места, где остановился'**
  String planNotifyReminderBody(String title);

  /// Уведомление в день события: «Сегодня приём»; event — слово события сервера строчными.
  ///
  /// In ru, this message translates to:
  /// **'Сегодня {event}'**
  String planNotifyEventTodayTitle(String event);

  /// Уведомление в день события, когда сервер не назвал событие.
  ///
  /// In ru, this message translates to:
  /// **'Сегодня разговор'**
  String get planNotifyEventTodayTitleNoName;

  /// Тело уведомления в день события.
  ///
  /// In ru, this message translates to:
  /// **'Скажи сам перед разговором — прогони его вслух'**
  String get planNotifyEventTodayBody;

  /// Локальное уведомление «дни пропущены»: наутро после даты дня, который не пройден.
  ///
  /// In ru, this message translates to:
  /// **'День {n} ждёт со вчера'**
  String planNotifySkippedTitle(int n);

  /// Тело уведомления «дни пропущены» — говорит, что сделала система, без укора.
  ///
  /// In ru, this message translates to:
  /// **'Маршрут сдвинулся: следующие дни пойдут от сегодня'**
  String get planNotifySkippedBody;

  /// Баннер уведомления в приложении (вид кадра 22-6): время справа от заголовка.
  ///
  /// In ru, this message translates to:
  /// **'сейчас'**
  String get planBannerNow;

  /// Баннер уведомления в приложении (кадр 22-6): буква-значок приложения «Слова» в квадрате 34.
  ///
  /// In ru, this message translates to:
  /// **'С'**
  String get planBannerAppMark;

  /// Вход офлайн (§6): нельзя начать сборку. Строки в таблице нет — добавлена нарядом PLAN-UI.
  ///
  /// In ru, this message translates to:
  /// **'Без сети план не собрать'**
  String get planEntryOffline;

  /// Окно дня (23-0a…0c): подпись стрелки назад на плите для читалки экрана.
  ///
  /// In ru, this message translates to:
  /// **'Назад'**
  String get planWindowBack;

  /// Окно дня, плита (23-0a): слово состояния дня под названием — «не начат · ≈ 20 минут».
  ///
  /// In ru, this message translates to:
  /// **'не начат'**
  String get planWindowStateNotStarted;

  /// Окно дня, плита (23-0b): слово состояния дня под названием — «идёт · ≈ 12 мин».
  ///
  /// In ru, this message translates to:
  /// **'идёт'**
  String get planWindowStateInProgress;

  /// Окно дня: оценка минут сервера (minutes_estimate, minutes_left) — «≈ 20 минут» на плите не начатого дня, «≈ 12 мин» у идущего, у текущего этапа и в компактной шапке; minutes — planMinutesCount или planMinutesShort.
  ///
  /// In ru, this message translates to:
  /// **'≈ {minutes}'**
  String planWindowApprox(String minutes);

  /// Окно дня: две части строки через точку — «не начат · ≈ 20 минут», «идёт · ≈ 8 мин» у текущего этапа, «СЛОВА · 8 · 5 ПРОЙДЕНО» в брови вкладки.
  ///
  /// In ru, this message translates to:
  /// **'{first} · {second}'**
  String planWindowJoin(String first, String second);

  /// Та же склейка через точку, когда вторая часть начинается с числа («пройдено · 3 минуты», «СЛОВА · 8», «Разговор целиком · 2 сцены»): неразрывный пробел держит число при точке (приёмка CLIENT-CONV-1c 22.09). Выбирает её код — planDot.
  ///
  /// In ru, this message translates to:
  /// **'{first} · {second}'**
  String planWindowJoinNumber(String first, String second);

  /// Окно дня, плита пройденного дня (23-0c): строка итога вместо цифр — «День пройден · 19 минут»; minutes — planMinutesCount(minutes_spent).
  ///
  /// In ru, this message translates to:
  /// **'День пройден · {minutes}'**
  String planWindowPassedLine(String minutes);

  /// Окно дня, бровь вкладки (23-0b…0d): часть «5 пройдено» — summary.done сервера; при нуле части нет.
  ///
  /// In ru, this message translates to:
  /// **'{n} пройдено'**
  String planWindowBrowDone(int n);

  /// Окно дня, бровь вкладки (23-0c, 23-0d): часть «2 вернутся завтра» — summary.returns сервера; при нуле части нет.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} вернётся завтра} few{{n} вернутся завтра} many{{n} вернутся завтра} other{{n} вернутся завтра}}'**
  String planWindowBrowReturns(int n);

  /// Окно дня и шит слова (23-0a…0e): подпись кружка «прослушать» — у слова, фразы, обеих реплик диалога, реплики «В разговоре» и слова в шите — для читалки экрана.
  ///
  /// In ru, this message translates to:
  /// **'Прослушать'**
  String get planWindowListen;

  /// Окно дня, плита не пройденного дня (23-0a, 23-0b): цели ОДНИМ предложением — «Научишься описать, где и как болит, ответить на вопросы врача и спросить про ограничения»; goals — цели сервера, склеенные planWindowGoalsJoin / planWindowGoalsJoinLast.
  ///
  /// In ru, this message translates to:
  /// **'Научишься {goals}'**
  String planWindowGoalsLearn(String goals);

  /// Окно дня, плита пройденного дня (23-0c): начало предложения целей шалфеем с галкой 14 — «Научился: описать, где и как болит, …».
  ///
  /// In ru, this message translates to:
  /// **'Научился:'**
  String get planWindowGoalsLearned;

  /// Окно дня, предложение целей (23-0a…0c): цель через запятую — «описать, где болит, ответить на вопросы врача».
  ///
  /// In ru, this message translates to:
  /// **'{head}, {next}'**
  String planWindowGoalsJoin(String head, String next);

  /// Окно дня, предложение целей (23-0a…0c): последняя цель через «и» — «… и спросить про ограничения».
  ///
  /// In ru, this message translates to:
  /// **'{head} и {last}'**
  String planWindowGoalsJoinLast(String head, String last);

  /// Шит слова (23-0e): лейбл caps над репликой дня, где звучит слово (usage сервера), слово в реплике латунью.
  ///
  /// In ru, this message translates to:
  /// **'В разговоре'**
  String get planWindowSheetTalk;

  /// Шит слова (23-0e): строка состояния слова, которое ещё не проходили (state = pending) — форма среднего рода от «не начат».
  ///
  /// In ru, this message translates to:
  /// **'не начато'**
  String get planWindowSheetNotStarted;

  /// Шит слова (23-0e): часть строки состояния слова, проваленного дважды — «пройдено · вернётся в день 3»; n — returns_day сервера.
  ///
  /// In ru, this message translates to:
  /// **'вернётся в день {n}'**
  String planWindowSheetReturnsOn(int n);

  /// Шит слова (23-0e): часть строки состояния, когда сервер не назвал день возврата (returns_day = null).
  ///
  /// In ru, this message translates to:
  /// **'вернётся завтра'**
  String get planWindowSheetReturnsTomorrow;

  /// Шит слова (23-0e): единственная кнопка — текстовая, латунью; шит закрывается и тягой вниз.
  ///
  /// In ru, this message translates to:
  /// **'Закрыть'**
  String get planWindowSheetClose;

  /// No description provided for @dayMinutes.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} минута} few{{n} минуты} other{{n} минут}}'**
  String dayMinutes(int n);

  /// Сессия, вход в этап (30-1): статус этапа в списке пяти — все карточки отвечены.
  ///
  /// In ru, this message translates to:
  /// **'пройден'**
  String get planSessionStateDone;

  /// Сессия, вход в этап (30-1) и «Нужен микрофон» (30-3): статус этапа, до которого ещё не дошли.
  ///
  /// In ru, this message translates to:
  /// **'впереди'**
  String get planSessionStateAhead;

  /// Сессия, вход в этап (30-1) и «Дальше» итога этапа (30-6): минуты этапа из окна дня (stage.minutes_left).
  ///
  /// In ru, this message translates to:
  /// **'≈ {n} мин'**
  String planSessionApproxMinutes(int n);

  /// Сессия, вход в этап «Слова» (30-1): описание под названием; число — единицы этапа.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} слово дня} few{{n} слова дня} many{{n} слов дня} other{{n} слова дня}} — посмотри, послушай и скажи вслух'**
  String planSessionDescWords(int n);

  /// Сессия, вход в этап «Фразы» (30-1): описание под названием; число — каркасы этапа.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} фраза дня} few{{n} фразы дня} many{{n} фраз дня} other{{n} фразы дня}} — одно окно меняется, фраза остаётся'**
  String planSessionDescPhrases(int n);

  /// Сессия, вход в этап «Диалог» (30-1): описание.
  ///
  /// In ru, this message translates to:
  /// **'Разговор по шагам: пойми реплику и ответь фразами дня'**
  String get planSessionDescDialogue;

  /// Сессия, вход в этап «Слушаю и отвечаю» (30-1): описание.
  ///
  /// In ru, this message translates to:
  /// **'Весь разговор на слух, потом вопросы о нём'**
  String get planSessionDescListen;

  /// Сессия, вход в этап «Говорю сам» (30-1): описание по кадру 30-1b; роль без склонения — «собеседник».
  ///
  /// In ru, this message translates to:
  /// **'Собеседник спрашивает — отвечай про себя, своими словами. Каркасы ты знаешь, окно — твоё'**
  String get planSessionDescSpeak;

  /// Сессия, вход в этап (30-1): переключатель; хранится на телефоне на план.
  ///
  /// In ru, this message translates to:
  /// **'Без подсказок'**
  String get planSessionNoHints;

  /// Сессия, вход в этап (30-1): подпись под переключателем «Без подсказок».
  ///
  /// In ru, this message translates to:
  /// **'Диалог и «Говорю сам» — сразу голосом'**
  String get planSessionNoHintsSub;

  /// Вход в день-репетицию (30-1, наряд FIX-3 §8): подпись тумблера — про разговор; «Диалог» и «Говорю сам» в этом дне не идут.
  ///
  /// In ru, this message translates to:
  /// **'В разговоре — без подсказок, текст собеседника закрыт'**
  String get planSessionNoHintsTalk;

  /// Сессия, вход в этап (30-1): кнопка.
  ///
  /// In ru, this message translates to:
  /// **'Начать'**
  String get planSessionStart;

  /// Сессия, вход в этап (30-1): версия сборки мелко серым в подвале — правило владельца.
  ///
  /// In ru, this message translates to:
  /// **'сборка {version}'**
  String planSessionBuild(String version);

  /// Сессия, шапка (30-2): справа словами — сколько единиц этапа ещё не закрыто.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{ещё {n} слово} few{ещё {n} слова} many{ещё {n} слов} other{ещё {n} слова}}'**
  String planSessionLeftWords(int n);

  /// Сессия, шапка (30-2) этапа «Фразы»: сколько каркасов ещё не закрыто.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{ещё {n} фраза} few{ещё {n} фразы} many{ещё {n} фраз} other{ещё {n} фразы}}'**
  String planSessionLeftPhrases(int n);

  /// Сессия, полоса сцены (30-2b): название сцены и роль собеседника в именительном, как отдал сервер.
  ///
  /// In ru, this message translates to:
  /// **'{scene} · {role}'**
  String planSessionSceneLine(String scene, String role);

  /// Сессия, крестик шапки (30-2) и итога этапа (30-6): подпись для читалки экрана.
  ///
  /// In ru, this message translates to:
  /// **'Закрыть'**
  String get planSessionClose;

  /// Сессия, знакомство со словом (31-1): задание над листом.
  ///
  /// In ru, this message translates to:
  /// **'Запомни слово'**
  String get planSessionTaskRememberWord;

  /// Сессия, «Повтори слово» (31-2): задание над листом.
  ///
  /// In ru, this message translates to:
  /// **'Скажи слово вслух'**
  String get planSessionTaskSayWord;

  /// Сессия, «Повтори слово» (31-2): голос спутника под заданием; роль собеседника в именительном со строчной.
  ///
  /// In ru, this message translates to:
  /// **'Скажи, как слышишь — {role} поймёт'**
  String planSessionCompanionSayWord(String role);

  /// Сессия, выбор слово → перевод (31-3) и обратный перевод фразы (32-3): задание.
  ///
  /// In ru, this message translates to:
  /// **'Выбери перевод'**
  String get planSessionTaskChooseTranslation;

  /// Сессия, выбор перевод → слово (31-4): задание.
  ///
  /// In ru, this message translates to:
  /// **'Выбери слово'**
  String get planSessionTaskChooseWord;

  /// Сессия, «На слух» (31-5) и «Окно на слух» (32-5): задание.
  ///
  /// In ru, this message translates to:
  /// **'Выбери, что услышал'**
  String get planSessionTaskChooseHeard;

  /// Сессия, сборка слова из плиток (31-6): задание.
  ///
  /// In ru, this message translates to:
  /// **'Собери из частей'**
  String get planSessionTaskAssembleParts;

  /// Сессия, слово в окне (31-7): задание.
  ///
  /// In ru, this message translates to:
  /// **'Вставь слово в окно'**
  String get planSessionTaskInsertWord;

  /// Сессия, знакомство с каркасом (32-1): задание (наряд SESSION-1b′).
  ///
  /// In ru, this message translates to:
  /// **'Посмотри и послушай'**
  String get planSessionTaskLookListen;

  /// Сессия, перевод → сборка (32-2): задание.
  ///
  /// In ru, this message translates to:
  /// **'Собери фразу'**
  String get planSessionTaskAssemblePhrase;

  /// Сессия, окно · вставь наполнение (32-4): задание.
  ///
  /// In ru, this message translates to:
  /// **'Вставь в окно'**
  String get planSessionTaskInsert;

  /// Сессия, «Повтори вслух» (32-6): задание.
  ///
  /// In ru, this message translates to:
  /// **'Скажи фразу вслух'**
  String get planSessionTaskSayPhrase;

  /// Сессия, «Скажи целиком» (32-7): задание — фраза говорится с каждым значением окна по очереди (наряд FIX-1 §6).
  ///
  /// In ru, this message translates to:
  /// **'Скажи фразу с каждым значением'**
  String get planSessionTaskSayEachMeaning;

  /// Сессия, «Скажи целиком» (32-7): подпись под заданием в последнем круге — значение в окне своё (наряд FIX-1 §6).
  ///
  /// In ru, this message translates to:
  /// **'а теперь со своим словом'**
  String get planSessionOwnWordNow;

  /// Сессия в режиме повтора («Ещё раз» с итога дня): состояние этапа в списке на входе (наряд FIX-1 §5).
  ///
  /// In ru, this message translates to:
  /// **'повтор'**
  String get planSessionStateReplay;

  /// Сессия, комбинация (32-8): задание над тремя целыми предложениями-ответами (наряд SESSION-1b′).
  ///
  /// In ru, this message translates to:
  /// **'Что ты ответишь?'**
  String get planSessionTaskWhatAnswer;

  /// Сессия, лист вопроса (30-9, 31-3): бровь над словом.
  ///
  /// In ru, this message translates to:
  /// **'Слово'**
  String get planSessionBrowWord;

  /// Сессия, лист вопроса (31-4, 32-2): бровь над переводом.
  ///
  /// In ru, this message translates to:
  /// **'Перевод'**
  String get planSessionBrowTranslation;

  /// Сессия, лист вопроса «На слух» (31-5, 32-5): бровь.
  ///
  /// In ru, this message translates to:
  /// **'На слух'**
  String get planSessionBrowByEar;

  /// Сессия, лист вопроса (31-7): бровь над строкой с окном.
  ///
  /// In ru, this message translates to:
  /// **'Окно'**
  String get planSessionBrowSlot;

  /// Сессия, выбор каркаса комбинации (32-8): бровь.
  ///
  /// In ru, this message translates to:
  /// **'Каркас'**
  String get planSessionBrowFrame;

  /// Сессия, обратный перевод (32-3), «Повтори вслух» (32-6) и лист «Скажи целиком» (32-7): бровь.
  ///
  /// In ru, this message translates to:
  /// **'Фраза'**
  String get planSessionBrowPhrase;

  /// Сессия, комбинация собрана (32-8): бровь.
  ///
  /// In ru, this message translates to:
  /// **'Собрано'**
  String get planSessionBrowAssembled;

  /// Сессия, комбинация (32-8): бровь над репликой собеседника; роль в именительном.
  ///
  /// In ru, this message translates to:
  /// **'{role} · спрашивает'**
  String planSessionBrowPartnerAsks(String role);

  /// Сессия, «На слух» (31-5): текст вопроса в листе.
  ///
  /// In ru, this message translates to:
  /// **'Что ты услышал?'**
  String get planSessionWhatHeard;

  /// Сессия, знакомство со словом (31-1) и каркасом (32-1): кнопка.
  ///
  /// In ru, this message translates to:
  /// **'Понятно'**
  String get planSessionUnderstood;

  /// Сессия, плитки (30-5, 31-6, 32-2): кнопка.
  ///
  /// In ru, this message translates to:
  /// **'Проверить'**
  String get planSessionCheck;

  /// Сессия: кнопка после неверного ответа (30-9), итога этапа (30-6), комбинации (32-8); бровь «Дальше» на итоге этапа.
  ///
  /// In ru, this message translates to:
  /// **'Дальше'**
  String get planSessionNext;

  /// Сессия, сборка слова (31-6): подпись справа от перевода.
  ///
  /// In ru, this message translates to:
  /// **'по частям'**
  String get planSessionByParts;

  /// Сессия, микрофон (30-3) в покое: подпись над кнопкой.
  ///
  /// In ru, this message translates to:
  /// **'тап — говорить'**
  String get planSessionMicTap;

  /// Сессия, микрофон (30-3) «слушаю · пусто»: подпись.
  ///
  /// In ru, this message translates to:
  /// **'говори, я слушаю'**
  String get planSessionMicListening;

  /// Сессия, микрофон (30-3) «услышал»: подпись над кнопкой-галкой.
  ///
  /// In ru, this message translates to:
  /// **'услышал'**
  String get planSessionMicHeard;

  /// Сессия, «Комбинация» (32-8) «собрано · играет»: подпись под волной, пока звучит собранная фраза.
  ///
  /// In ru, this message translates to:
  /// **'играет'**
  String get planSessionPlaying;

  /// Сессия, микрофон (30-3) «не расслышал»: подпись.
  ///
  /// In ru, this message translates to:
  /// **'не расслышал, ещё раз'**
  String get planSessionMicMissed;

  /// Сессия, «Повтори слово» (31-2c): подпись эха под услышанным словом.
  ///
  /// In ru, this message translates to:
  /// **'услышал — так же, как в записи'**
  String get planSessionEcho;

  /// Сессия, микрофон (30-3) и «Нужен микрофон»: текст над кнопкой — карточка закрывается «пропущено».
  ///
  /// In ru, this message translates to:
  /// **'Пропустить'**
  String get planSessionSkip;

  /// Сессия, голосовые карточки: поле debug-сборки на симуляторе — текст идёт как распознанный; в release поля нет.
  ///
  /// In ru, this message translates to:
  /// **'что услышал'**
  String get planSessionDebugHeard;

  /// Сессия, «нет разрешения · экран» (30-3): заголовок.
  ///
  /// In ru, this message translates to:
  /// **'Нужен микрофон'**
  String get planSessionNoMicTitle;

  /// Сессия, «нет разрешения · экран» (30-3): текст под заголовком.
  ///
  /// In ru, this message translates to:
  /// **'Без него этапы «Слушаю и отвечаю» и «Говорю сам» не пройти. Слова и фразы можно делать выбором и плитками.'**
  String get planSessionNoMicBody;

  /// Сессия, «нет разрешения · экран» (30-3): кнопка — спросить систему или открыть настройки.
  ///
  /// In ru, this message translates to:
  /// **'Разрешить'**
  String get planSessionNoMicAllow;

  /// Сессия, своё окно не зачтено (32-9): кнопка новой попытки под причиной отказа.
  ///
  /// In ru, this message translates to:
  /// **'Ещё раз'**
  String get planSessionTryAgain;

  /// Сессия, итог этапа (30-6): лейбл над единицами, которые вернутся на следующий день.
  ///
  /// In ru, this message translates to:
  /// **'Вернётся завтра'**
  String get planSessionReturnsTomorrow;

  /// Сессия, шит выхода (30-8): заголовок.
  ///
  /// In ru, this message translates to:
  /// **'Выйти? Прогресс сохранится'**
  String get planSessionExitTitle;

  /// Сессия, шит выхода (30-8): одно предложение; кадровое «вернёшься к пятой» требует порядкового числительного в падеже — заменено «с того же места».
  ///
  /// In ru, this message translates to:
  /// **'Сделанные карточки этапа «{stage}» останутся закрытыми — продолжишь с того же места.'**
  String planSessionExitBody(String stage);

  /// Сессия, шит выхода (30-8): остаться в этапе, текст латунью.
  ///
  /// In ru, this message translates to:
  /// **'Продолжить'**
  String get planSessionExitStay;

  /// Сессия, шит выхода (30-8): кнопка.
  ///
  /// In ru, this message translates to:
  /// **'Выйти'**
  String get planSessionExitLeave;

  /// Сессия: баннер под шапкой, пока ответ ждёт отправки (очередь отложенных ответов).
  ///
  /// In ru, this message translates to:
  /// **'нет связи — ответ отправится, как только сеть вернётся'**
  String get planSessionOffline;

  /// Сессия: вход, когда сервер не ответил.
  ///
  /// In ru, this message translates to:
  /// **'День не загрузился'**
  String get planSessionLoadFailed;

  /// Сессия, шапка (30-2) этапов «Диалог» и «Говорю сам»: сколько обменов ещё не закрыто.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{ещё {n} реплика} few{ещё {n} реплики} many{ещё {n} реплик} other{ещё {n} реплики}}'**
  String planSessionLeftExchanges(int n);

  /// Сессия, шапка (30-2) этапа «Слушаю и отвечаю» на вопросе: сколько вопросов слушания ещё без ответа, текущий тоже (34-2, 34-5).
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{ещё {n} вопрос} few{ещё {n} вопроса} many{ещё {n} вопросов} other{ещё {n} вопроса}}'**
  String planSessionLeftQuestions(int n);

  /// Сессия, шапка этапа «Слушаю и отвечаю»: вопрос слушания — последний (34-7).
  ///
  /// In ru, this message translates to:
  /// **'последний вопрос'**
  String get planSessionLastQuestion;

  /// Сессия, шапка этапа «Слушаю и отвечаю» на плеере визита (34-1).
  ///
  /// In ru, this message translates to:
  /// **'разговор целиком'**
  String get planSessionWholeTalk;

  /// Сессия, шапка этапа «Слушаю и отвечаю» на разборе (34-3).
  ///
  /// In ru, this message translates to:
  /// **'разбор'**
  String get planSessionReviewLabel;

  /// Сессия, шапка этапа «Слушаю и отвечаю» на карточке «быстро / медленно» (34-6).
  ///
  /// In ru, this message translates to:
  /// **'темп'**
  String get planSessionPaceLabel;

  /// Сессия, строка задания 33-2: ответ собеседнику чипами (beginner).
  ///
  /// In ru, this message translates to:
  /// **'Собери ответ'**
  String get planSessionTaskCollectAnswer;

  /// Сессия, строка задания 33-3 и 33-4: реплика ученика голосом — с репликой на экране и вслепую (наряд SESSION-2a §5).
  ///
  /// In ru, this message translates to:
  /// **'Скажи свою реплику'**
  String get planSessionTaskSayLine;

  /// Сессия, строка задания 33-6: «Не понял» в пузыре собеседника и медленный повтор. Канва — «Слушай назначение»: текста задания контракт не отдаёт.
  ///
  /// In ru, this message translates to:
  /// **'Не понял — переспроси'**
  String get planSessionTaskRescue;

  /// Сессия, 33-2: подпись под чипами наполнений — верен любой.
  ///
  /// In ru, this message translates to:
  /// **'любое — твой ответ'**
  String get planSessionAnyChip;

  /// Сессия, 33-6: кнопка 44 в пузыре собеседника — переспросить.
  ///
  /// In ru, this message translates to:
  /// **'Не понял'**
  String get planSessionNotUnderstood;

  /// Сессия: подпись волны медленного повтора (33-6) и кнопка «медленно» 44 в листе 34-6.
  ///
  /// In ru, this message translates to:
  /// **'медленно'**
  String get planSessionSlowly;

  /// Сессия, строка задания 34-1: плеер визита.
  ///
  /// In ru, this message translates to:
  /// **'Послушай разговор'**
  String get planSessionTaskListenTalk;

  /// Сессия, 34-1: текст над кнопкой — пауза после каждого обмена.
  ///
  /// In ru, this message translates to:
  /// **'По частям'**
  String get planSessionByPartsAction;

  /// Сессия, 34-1: кнопка на паузе «по частям» — следующий обмен.
  ///
  /// In ru, this message translates to:
  /// **'Продолжить'**
  String get planSessionContinue;

  /// Сессия: проиграть ещё раз — весь визит (34-1), реплику в темпе (34-6), реплику после зачёта (35-3, 35-4).
  ///
  /// In ru, this message translates to:
  /// **'Ещё раз'**
  String get planSessionReplay;

  /// Сессия, 34-1: сколько обменов в визите — справа под полосой плеера.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} обмен} few{{n} обмена} many{{n} обменов} other{{n} обмена}}'**
  String planSessionExchangesCount(int n);

  /// Сессия, 34-1: плеер стоит на паузе «по частям» после обмена N.
  ///
  /// In ru, this message translates to:
  /// **'пауза · обмен {n}'**
  String planSessionPlayerPaused(int n);

  /// Сессия, 34-1: главное действие плеера, пока разговор играет.
  ///
  /// In ru, this message translates to:
  /// **'Пауза'**
  String get planSessionPause;

  /// Сессия, 34-1: подпись пары кружков — кто говорит в разговоре.
  ///
  /// In ru, this message translates to:
  /// **'{role} и ты'**
  String planSessionPlayerRoles(String role);

  /// Сессия, 34-1: визит проигран до конца.
  ///
  /// In ru, this message translates to:
  /// **'дослушал'**
  String get planSessionPlayerDone;

  /// Сессия, 34-1: подпись пары кружков, когда роль собеседника не названа.
  ///
  /// In ru, this message translates to:
  /// **'ты'**
  String get planSessionYou;

  /// Сессия, строка задания 34-2: вопрос о визите по памяти.
  ///
  /// In ru, this message translates to:
  /// **'Что ты понял?'**
  String get planSessionTaskWhatUnderstood;

  /// Сессия, бровь листа вопроса (34-2, 34-7).
  ///
  /// In ru, this message translates to:
  /// **'Вопрос'**
  String get planSessionBrowQuestion;

  /// Сессия, 34-2: подпись под вопросом — переслушать нельзя.
  ///
  /// In ru, this message translates to:
  /// **'по памяти · звука нет'**
  String get planSessionFromMemory;

  /// Сессия, строка задания 34-3: разбор визита с подсветкой ответов.
  ///
  /// In ru, this message translates to:
  /// **'Где это прозвучало'**
  String get planSessionTaskWhereHeard;

  /// Сессия, строка задания 34-5: три озвучки без текста (наряд SESSION-2b §3).
  ///
  /// In ru, this message translates to:
  /// **'Послушай и выбери ответ'**
  String get planSessionTaskListenChoose;

  /// Сессия, 34-5: кнопка — отмеченная озвучка и есть ответ; активна только когда лист отмечен и прослушан.
  ///
  /// In ru, this message translates to:
  /// **'Это ответ'**
  String get planSessionThisIsAnswer;

  /// Сессия, строка задания 34-6.
  ///
  /// In ru, this message translates to:
  /// **'А теперь в обычном темпе'**
  String get planSessionTaskNormalPace;

  /// Сессия, 34-6: бровь листа на медленном темпе, rate — темп из карточки («0.75»).
  ///
  /// In ru, this message translates to:
  /// **'Медленно · {rate}×'**
  String planSessionBrowSlowRate(String rate);

  /// Сессия, 34-6: бровь листа на обычном темпе.
  ///
  /// In ru, this message translates to:
  /// **'В обычном темпе'**
  String get planSessionBrowNormalPace;

  /// Сессия, мета листа: текст реплики виден (34-6).
  ///
  /// In ru, this message translates to:
  /// **'текст открыт'**
  String get planSessionTextOpen;

  /// Сессия, мета листа: текст реплики скрыт (34-6, 35-3, 35-4).
  ///
  /// In ru, this message translates to:
  /// **'текст закрыт'**
  String get planSessionTextClosed;

  /// Сессия, 34-6: кнопка — реплика понята на обоих темпах.
  ///
  /// In ru, this message translates to:
  /// **'Понял'**
  String get planSessionUnderstoodAction;

  /// Сессия, строка задания 34-7: число в реплике на слух.
  ///
  /// In ru, this message translates to:
  /// **'Поймай число'**
  String get planSessionTaskCatchNumber;

  /// Сессия, «Поймай число» (34-7): текст вопроса в листе; контракт вопроса не отдаёт (наряд SESSION-2a §5).
  ///
  /// In ru, this message translates to:
  /// **'Какое число прозвучало?'**
  String get planSessionWhichNumber;

  /// Сессия, «Говорю сам» (35-2) на обмене ask (наряд FIX-2, п. 3): задание, когда ученик говорит первым — вопроса у такой карточки нет, отвечать не на что.
  ///
  /// In ru, this message translates to:
  /// **'Спроси сам'**
  String get planSessionTaskAskYourself;

  /// Сессия, «Говорю сам» (35-2) на обмене ask (наряд FIX-2, п. 3): намерение на родном в своём пузыре — своя реплика ученика в кавычках.
  ///
  /// In ru, this message translates to:
  /// **'Спроси: «{text}»'**
  String planSessionAskIntent(String text);

  /// Сессия, строка задания 35-2 и 35-5. Канва — «Ответь врачу»: склонения роли контракт не отдаёт.
  ///
  /// In ru, this message translates to:
  /// **'Ответь своими словами'**
  String get planSessionTaskAnswerOwnWords;

  /// Сессия, строка задания 35-3: эхо с задержкой.
  ///
  /// In ru, this message translates to:
  /// **'Повтори через паузу'**
  String get planSessionTaskRepeatPause;

  /// Сессия, строка задания 35-4: своя реплика звучит, текст закрыт, перевод — подсказка смысла (наряд SESSION-2b §4, контракт BACK-TAILS-1 §1.1).
  ///
  /// In ru, this message translates to:
  /// **'Повтори свою реплику'**
  String get planSessionTaskRetell;

  /// Сессия, бровь листа 35-3 / 35-4.
  ///
  /// In ru, this message translates to:
  /// **'Реплика'**
  String get planSessionBrowLine;

  /// Сессия, 35-3: подпись над микрофоном, пока звучит реплика.
  ///
  /// In ru, this message translates to:
  /// **'слушай'**
  String get planSessionListenCue;

  /// Сессия, 35-3: подпись над микрофоном в паузе 3 с (кольцо).
  ///
  /// In ru, this message translates to:
  /// **'жду'**
  String get planSessionWaiting;

  /// Сессия, 35-5: отказ судьи, когда сервер не прислал своей причины.
  ///
  /// In ru, this message translates to:
  /// **'не то — попробуем ещё'**
  String get planSessionNotThat;

  /// Сессия, 35-5: второй из трёх выходов — открыть каркас. Разговор (37-7c): кнопка справа от микрофона только в режиме «Без подсказок» — по тапу встаёт плашка подсказки на этот ход.
  ///
  /// In ru, this message translates to:
  /// **'Подсказать'**
  String get planSessionHintAction;

  /// Итог этапа (30-6, наряд CLIENT-CONV-1c): плашка «Дальше», когда следующего этапа нет, — итог дня, минуты дня справа.
  ///
  /// In ru, this message translates to:
  /// **'Итог дня'**
  String get planSessionDayTotal;

  /// Сессия, итог дня (30-7); minutes — planMinutesCount.
  ///
  /// In ru, this message translates to:
  /// **'День пройден · {minutes}'**
  String planSessionDayDoneTitle(String minutes);

  /// Сессия, 30-7: кнопка — POST …/days/{n}/close и назад в окно дня.
  ///
  /// In ru, this message translates to:
  /// **'Закрыть день'**
  String get planSessionCloseDay;

  /// Сессия, 30-7: сколько карточек вернётся завтра и из чего; cards — planCardsCount, parts — planSessionReturn* через planSessionAnd.
  ///
  /// In ru, this message translates to:
  /// **'{cards}: {parts}.'**
  String planSessionDayReturns(String cards, String parts);

  /// Сессия, 30-7: слова, которые вернутся завтра.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} слово} few{{n} слова} many{{n} слов} other{{n} слова}}'**
  String planSessionReturnWords(int n);

  /// Сессия, 30-7: фразы, которые вернутся завтра.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} фраза} few{{n} фразы} many{{n} фраз} other{{n} фразы}}'**
  String planSessionReturnPhrases(int n);

  /// Сессия, 30-7: реплики (обмены), которые вернутся завтра.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} реплика} few{{n} реплики} many{{n} реплик} other{{n} реплики}}'**
  String planSessionReturnExchanges(int n);

  /// Сессия, 30-7: последняя пара перечисления.
  ///
  /// In ru, this message translates to:
  /// **'{a} и {b}'**
  String planSessionAnd(String a, String b);

  /// Сессия, 30-7: следующий день плана, урок которого ещё пишется (или встанет в очередь при закрытии).
  ///
  /// In ru, this message translates to:
  /// **'День {n} — собираю'**
  String planSessionNextDayBuilding(int n);

  /// Сессия, 30-7: следующий день плана, урок которого уже написан.
  ///
  /// In ru, this message translates to:
  /// **'День {n} — готов'**
  String planSessionNextDayReady(int n);

  /// Сессия, 30-7: POST закрытия дня не дошёл; кнопка остаётся.
  ///
  /// In ru, this message translates to:
  /// **'День не закрылся — нет связи'**
  String get planSessionCloseFailed;

  /// Имя шестого этапа дня (наряд CONV-1): плита таба 21-2, ряд окна 23-0a, узел маршрута, вход 30-1, итог дня 30-7, шапка экрана разговора 37-6.
  ///
  /// In ru, this message translates to:
  /// **'Разговор'**
  String get planPlateStageTalk;

  /// Имя этапа репетиции (кадры 37-1, 37-3): свои реплики плана, прочитанные и сказанные вслух.
  ///
  /// In ru, this message translates to:
  /// **'Вспомнить'**
  String get planPlateStageRecall;

  /// Вход в разговор (37-5): оценка сервера minutes_estimate — «около 3 минут». После «около» — родительный падеж, поэтому своя форма, а не planMinutesCount (живой прогон поймал «около 3 минуты»).
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{около {n} минуты} few{около {n} минут} many{около {n} минут} other{около {n} минуты}}'**
  String planTalkEntryMinutes(int n);

  /// Вход в разговор репетиции (37-5): бровь вместо названия сцены — разговор идёт по всем сценам плана.
  ///
  /// In ru, this message translates to:
  /// **'Разговор целиком'**
  String get planTalkEntryWhole;

  /// Вход в разговор репетиции (37-5): сколько сцен в разговоре — вторая часть брови «Разговор целиком · 3 сцены».
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} сцена} few{{n} сцены} many{{n} сцен} other{{n} сцены}}'**
  String planTalkEntryScenes(int n);

  /// Вход в разговор (37-5): первая строка правил. Роль без склонения — «собеседник».
  ///
  /// In ru, this message translates to:
  /// **'Собеседник начнёт первым. Отвечай и спрашивай сам.'**
  String get planTalkEntryRuleStart;

  /// Вход в разговор (37-5): вторая строка правил. Пример «скажи „Sorry?“» из кадра снят: он на английском, а язык цели бывает любым.
  ///
  /// In ru, this message translates to:
  /// **'Не понял — нажми «Не понял», и он повторит проще.'**
  String get planTalkEntryRuleRescue;

  /// Вход в разговор (37-5): третья строка правил.
  ///
  /// In ru, this message translates to:
  /// **'Считается: сказал сам, фразы дня, понял вопросы.'**
  String get planTalkEntryRuleCounts;

  /// Вход в разговор (37-5): кнопка — POST …/days/{n}/conversation.
  ///
  /// In ru, this message translates to:
  /// **'Начать разговор'**
  String get planTalkStart;

  /// Экран разговора (37-6…37-10): кнопка слева от микрофона, во всех состояниях твоей очереди — ход kind=rescue, ход сцены не тратит.
  ///
  /// In ru, this message translates to:
  /// **'Не понял'**
  String get planTalkRescueAction;

  /// «Ответь своими словами» (35-2): чип подсказки — префикс печатает клиент; task_native карточки — предложение, его в придаточное переводит зеркало IntentClause. В разговоре рамки больше нет: подсказка — целая фраза hints.sentence (наряд CLIENT-FIX-4 §3).
  ///
  /// In ru, this message translates to:
  /// **'Скажи, что {intent}'**
  String planTalkHintChip(String intent);

  /// Экран разговора в «Без подсказок» (наряд CLIENT-CONV-1c §5, приёмка 22.09): чип рядом с «прослушать» у закрытой реплики собеседника — тап открывает текст этой реплики.
  ///
  /// In ru, this message translates to:
  /// **'текст'**
  String get planTalkOpenText;

  /// Экран разговора (37-9): пометка у реплики собеседника, которую прервали тапом по микрофону.
  ///
  /// In ru, this message translates to:
  /// **'прервано'**
  String get planTalkInterrupted;

  /// Экран разговора (37-7, 37-9): подпись под живой строкой, пока идёт запись.
  ///
  /// In ru, this message translates to:
  /// **'тишина — конец'**
  String get planTalkSilenceEnds;

  /// Экран разговора (37-10): запись вышла пустой. Ход на сервер не уходит.
  ///
  /// In ru, this message translates to:
  /// **'не расслышал — скажи ещё раз'**
  String get planTalkUnheard;

  /// Экран разговора (37-10): ход не дошёл. «Повторить» перечитывает разговор и повторяет ход, если он всё ещё твой.
  ///
  /// In ru, this message translates to:
  /// **'Связь пропала — разговор продолжится отсюда'**
  String get planTalkOffline;

  /// Экран разговора (37-10): 503 plan_conversation_unavailable — в журнале не осталось ничего, ход повторяется один в один. Роль без склонения.
  ///
  /// In ru, this message translates to:
  /// **'Собеседник не отвечает — попробуй ещё раз'**
  String get planTalkSilent;

  /// Экран разговора: старт не прошёл (нет связи, роль не ответила).
  ///
  /// In ru, this message translates to:
  /// **'Разговор не начался — попробуй ещё раз'**
  String get planTalkOpenFailed;

  /// Экран разговора: 422 plan_conversation_not_in_day — день роздан без шестого этапа.
  ///
  /// In ru, this message translates to:
  /// **'В этом дне разговора нет'**
  String get planTalkNotInDay;

  /// Конец разговора (37-11): заголовок листа.
  ///
  /// In ru, this message translates to:
  /// **'Разговор окончен'**
  String get planTalkEnded;

  /// Конец разговора (37-11): кнопка листа — ведёт на итог 37-12.
  ///
  /// In ru, this message translates to:
  /// **'Итог'**
  String get planTalkSummaryAction;

  /// Итог разговора (37-12): счёт сервера said_count.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{Сказал сам {n} реплику} few{Сказал сам {n} реплики} many{Сказал сам {n} реплик} other{Сказал сам {n} реплики}}'**
  String planTalkSaidLines(int n);

  /// Итог разговора (37-12): understood_all = true.
  ///
  /// In ru, this message translates to:
  /// **'Понял все вопросы'**
  String get planTalkUnderstoodAll;

  /// Итог разговора (37-12): not_understood > 0.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{Понял вопросы, кроме одного — вернётся} other{Понял вопросы, кроме {n} — вернутся}}'**
  String planTalkUnderstoodExcept(int n);

  /// Итог разговора (37-12): вторая часть строки понимания, всегда нейтральная.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{переспросил {n} раз} few{переспросил {n} раза} many{переспросил {n} раз} other{переспросил {n} раза}}'**
  String planTalkRescues(int n);

  /// Итог разговора репетиции (37-12b): заголовок вместо счёта реплик. Канва — «Ты готов к приёму», но событие плана в дательном сервер не шлёт (есть только event_native в именительном), а склонять клиент не вправе, поэтому готов — к разговору, для любого плана (решение архитектора при приёмке CLIENT-FIX-4 25.09; «к событию» снято). Сборка (20) печатала «к приёму» любому плану, в том числе залу.
  ///
  /// In ru, this message translates to:
  /// **'Ты готов к разговору'**
  String get planTalkReady;

  /// Итог дня (30-7): бровь блока готовых строк сервера (window.highlights).
  ///
  /// In ru, this message translates to:
  /// **'Что было хорошо'**
  String get planTalkHighlights;

  /// «Что прозвучит в ответ?» (34-5): подпись задания под шапкой.
  ///
  /// In ru, this message translates to:
  /// **'Слушай целиком — ответ один'**
  String get planSessionListenWholeOne;

  /// Знакомство с каркасом (32-1), третье состояние: у фразы одно значение, окна нет.
  ///
  /// In ru, this message translates to:
  /// **'Эту фразу говорят целиком — в ней ничего не меняется'**
  String get planSessionFrameWhole;

  /// Знакомство с каркасом (32-1): подпись над плашками значений.
  ///
  /// In ru, this message translates to:
  /// **'эту часть можно менять'**
  String get planSessionChangeable;

  /// Знакомство с каркасом (32-1), третье состояние: бровь блока с репликой дня, в которой фраза звучит.
  ///
  /// In ru, this message translates to:
  /// **'В разговоре'**
  String get planSessionInTalk;

  /// «Скажи целиком» (32-7): последняя плашка ряда значений — круг своего слова.
  ///
  /// In ru, this message translates to:
  /// **'своё слово'**
  String get planSessionOwnWordChip;

  /// Экран разговора: микрофон не пишет (нет разрешения или распознавания на устройстве). Рядом «Разрешить» — спросить систему ещё раз или открыть настройки, как на «Нужен микрофон» (30-3). Вместо «тап — говорить» над кнопкой, которая ничего не делает (пойман живым прогоном).
  ///
  /// In ru, this message translates to:
  /// **'Нужен микрофон — без него разговор не пройти'**
  String get planTalkNoMic;

  /// Вход в этап «Вспомнить» (30-1, день репетиции): описание этапа — обзор своих реплик по сценам (37-3), потом пять-шесть из них вслух (35-4).
  ///
  /// In ru, this message translates to:
  /// **'Свои реплики всех сцен — посмотри, послушай и скажи вслух'**
  String get planSessionDescRecall;

  /// Обзор «Вспомнить» (37-3): строка задания над репликами сцены.
  ///
  /// In ru, this message translates to:
  /// **'Вспомни свои реплики'**
  String get planSessionRecallTask;

  /// Обзор «Вспомнить» (37-3): кнопка на последней сцене — за ней пять-шесть своих реплик вслух (35-4).
  ///
  /// In ru, this message translates to:
  /// **'Дальше — повтори вслух'**
  String get planSessionRecallLast;

  /// Окно дня репетиции (37-1): начало строки статуса — «перед событием · в четверг · не начат».
  ///
  /// In ru, this message translates to:
  /// **'перед событием'**
  String get planWindowRehearsalBefore;

  /// Окно дня репетиции (37-1): в какой день недели стоит этот день плана — «в четверг». «Сегодня» и «завтра» приходят с сервера готовыми (slot.label_native).
  ///
  /// In ru, this message translates to:
  /// **'{weekday, select, mon{в понедельник} tue{во вторник} wed{в среду} thu{в четверг} fri{в пятницу} sat{в субботу} sun{в воскресенье} other{}}'**
  String planWindowOnWeekday(String weekday);

  /// Окно дня повторения (37-2): название на плите.
  ///
  /// In ru, this message translates to:
  /// **'Что уже было'**
  String get planWindowReviewTitle;

  /// Окно дня репетиции (37-1): строка под статусом. Роли в творительном контракт не отдаёт — «с собеседником», как «Поговори с собеседником» на 37-5.
  ///
  /// In ru, this message translates to:
  /// **'Проговоришь весь разговор с собеседником'**
  String get planWindowRehearsalLead;

  /// Окно дня повторения (37-2): строка под статусом; роль — «с собеседником», как на 37-5.
  ///
  /// In ru, this message translates to:
  /// **'Вернёшь фразы прошлых дней и поговоришь с собеседником'**
  String get planWindowReviewLead;

  /// Окно дня репетиции (37-1): бровь списка сцен под плитой.
  ///
  /// In ru, this message translates to:
  /// **'Из каких сцен'**
  String get planWindowFromScenes;

  /// Окно дня повторения (37-2): бровь списка дней под плитой.
  ///
  /// In ru, this message translates to:
  /// **'Из каких дней'**
  String get planWindowFromDays;

  /// Проверка понимания (33-1, 33-5): подпись над репликой — одна и та же на обоих кадрах (наряд FIX-3 §1).
  ///
  /// In ru, this message translates to:
  /// **'Проверь, что понял'**
  String get planSessionTaskUnderstood;

  /// «Ответь своими словами» (35-2) и круг своего слова «Скажи целиком» (32-7): отказ судьи — что он судил (heard ответа судьи, CONV-2 п. 8), строкой под причиной; без поля — что распознал телефон.
  ///
  /// In ru, this message translates to:
  /// **'услышал: {text}'**
  String planSessionHeardLine(String text);

  /// Имя этапа дня повторения (кадр 37-2, наряд CLIENT-CONV-1c): id repetition, который BACK-TAILS-2 даёт этапу карточек повторения вместо speak.
  ///
  /// In ru, this message translates to:
  /// **'Повторение'**
  String get planPlateStageRepetition;

  /// Итог этапа (30-6): заголовок «Слова» — к нему через « · » минуты этапа сервера (решение архитектора 22.09).
  ///
  /// In ru, this message translates to:
  /// **'Слова пройдены'**
  String get planSessionPassedWords;

  /// Итог этапа (30-6): заголовок «Фразы» — к нему через « · » минуты этапа.
  ///
  /// In ru, this message translates to:
  /// **'Фразы пройдены'**
  String get planSessionPassedPhrases;

  /// Итог этапа (30-6, кадр SESSION-DES-4 «Диалог пройден · 3 минуты»): заголовок «Диалог» — к нему через « · » минуты этапа.
  ///
  /// In ru, this message translates to:
  /// **'Диалог пройден'**
  String get planSessionPassedDialogue;

  /// Итог этапа (30-6): заголовок «Слушаю и отвечаю» — к нему через « · » минуты этапа (решение архитектора 22.09).
  ///
  /// In ru, this message translates to:
  /// **'Слушаю и отвечаю — пройдено'**
  String get planSessionPassedListen;

  /// Итог этапа (30-6): заголовок «Говорю сам» — к нему через « · » минуты этапа (решение архитектора 22.09).
  ///
  /// In ru, this message translates to:
  /// **'Говорю сам — пройдено'**
  String get planSessionPassedSpeak;

  /// Итог этапа (30-6, день репетиции): заголовок «Вспомнить» — к нему через « · » минуты этапа (решение архитектора 22.09).
  ///
  /// In ru, this message translates to:
  /// **'Вспомнить — пройдено'**
  String get planSessionPassedRecall;

  /// Итог этапа (30-6, день повторения): заголовок «Повторение» — к нему через « · » минуты этапа (решение архитектора 22.09).
  ///
  /// In ru, this message translates to:
  /// **'Повторение пройдено'**
  String get planSessionPassedRepetition;

  /// Итог этапа (30-6): заголовок этапа и его минуты сервера — «Говорю сам — пройдено · 6 минут». Хвост «пройдено · N минут» неразрывен целиком: неразрывные пробелы вокруг «·», внутри «N минут» — planMinutesCount, а перед тире — сами заголовки; строка переносится только после тире (приёмка CLIENT-CONV-1c 22.09, третий заход).
  ///
  /// In ru, this message translates to:
  /// **'{title} · {minutes}'**
  String planSessionPassedMinutes(String title, String minutes);

  /// Итог этапа «Слова» (30-6), первая строка: сколько слов в этапе и сколько из них зачтено с первой попытки без подсказки — по карточкам сервера.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} слово, {first} с первого раза} few{{n} слова, {first} с первого раза} many{{n} слов, {first} с первого раза} other{{n} слова, {first} с первого раза}}'**
  String planSessionFirstTryWords(int n, int first);

  /// Итог этапа «Фразы» (30-6), первая строка: фразы этапа и зачтённые с первой попытки без подсказки.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} фраза, {first} с первого раза} few{{n} фразы, {first} с первого раза} many{{n} фраз, {first} с первого раза} other{{n} фразы, {first} с первого раза}}'**
  String planSessionFirstTryPhrases(int n, int first);

  /// Итог этапа «Диалог» и «Говорю сам» (30-6, кадр «8 реплик, 6 с первого раза»), первая строка: реплики этапа и зачтённые с первой попытки без подсказки.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} реплика, {first} с первого раза} few{{n} реплики, {first} с первого раза} many{{n} реплик, {first} с первого раза} other{{n} реплики, {first} с первого раза}}'**
  String planSessionFirstTryLines(int n, int first);

  /// Итог этапа «Слушаю и отвечаю» (30-6), первая строка: вопросы слушания и отвеченные верно с первого раза.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} вопрос, {first} с первого раза} few{{n} вопроса, {first} с первого раза} many{{n} вопросов, {first} с первого раза} other{{n} вопроса, {first} с первого раза}}'**
  String planSessionFirstTryQuestions(int n, int first);

  /// Итог этапа «Повторение» (30-6), первая строка: карточки этапа и зачтённые с первой попытки без подсказки.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} карточка, {first} с первого раза} few{{n} карточки, {first} с первого раза} many{{n} карточек, {first} с первого раза} other{{n} карточки, {first} с первого раза}}'**
  String planSessionFirstTryCards(int n, int first);

  /// Итог этапа «Вспомнить» (30-6), первая строка: «6 реплик из 2 сцен» — свои реплики, сказанные вслух, и из скольких сцен.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} реплика} few{{n} реплики} many{{n} реплик} other{{n} реплики}} из {scenes, plural, one{{scenes} сцены} few{{scenes} сцен} many{{scenes} сцен} other{{scenes} сцены}}'**
  String planSessionRecallLinesOfScenes(int n, int scenes);

  /// Итог этапа (30-6), вторая строка: сколько единиц этапа вернётся завтра — по возвратам сервера.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} вернётся завтра} few{{n} вернутся завтра} many{{n} вернутся завтра} other{{n} вернутся завтра}}'**
  String planSessionStageReturns(int n);

  /// Итог этапа (30-6), вторая строка, когда у этапа возвратов нет.
  ///
  /// In ru, this message translates to:
  /// **'завтра ничего не вернётся'**
  String get planSessionStageNoReturns;

  /// Итог этапа «Диалог» (30-6, кадр «сказал вслух 5 своих реплик»), вторая строка: свои реплики диалога, зачтённые голосом.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{сказал вслух {n} свою реплику} few{сказал вслух {n} свои реплики} many{сказал вслух {n} своих реплик} other{сказал вслух {n} своей реплики}}'**
  String planSessionSaidAloud(int n);

  /// Итог этапа «Диалог» (30-6, кадр «дважды переспросил — врач повторил медленнее»), третья строка: пройденные обмены «Не понял» диалога; role — роль собеседника сцены строчной.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, =1{переспросил — {role} повторил медленнее} =2{дважды переспросил — {role} повторил медленнее} few{{n} раза переспросил — {role} повторил медленнее} many{{n} раз переспросил — {role} повторил медленнее} other{{n} раза переспросил — {role} повторил медленнее}}'**
  String planSessionRescuedSlower(int n, String role);

  /// Итог этапа «Слова» (30-6), третья строка — тёплая, одна на этап (решение архитектора 22.09).
  ///
  /// In ru, this message translates to:
  /// **'Эти слова ты теперь узнаёшь — дальше они встретятся во фразах'**
  String get planSessionWarmWords;

  /// Итог этапа «Фразы» (30-6), третья строка — тёплая, одна на этап.
  ///
  /// In ru, this message translates to:
  /// **'Фразы собраны и сказаны вслух — в диалоге они пригодятся'**
  String get planSessionWarmPhrases;

  /// Итог этапа «Слушаю и отвечаю» (30-6), третья строка — тёплая, одна на этап.
  ///
  /// In ru, this message translates to:
  /// **'Реплики собеседника ты понимаешь на слух'**
  String get planSessionWarmListen;

  /// Итог этапа «Говорю сам» (30-6), третья строка — тёплая, одна на этап.
  ///
  /// In ru, this message translates to:
  /// **'Свои реплики ты сказал сам — дальше живой разговор'**
  String get planSessionWarmSpeak;

  /// Итог этапа «Вспомнить» (30-6), третья строка — тёплая, одна на этап.
  ///
  /// In ru, this message translates to:
  /// **'Реплики на месте — дальше разговор целиком'**
  String get planSessionWarmRecall;

  /// Итог этапа «Повторение» (30-6), третья строка — тёплая, одна на этап.
  ///
  /// In ru, this message translates to:
  /// **'Всё, что возвращалось, сказано ещё раз'**
  String get planSessionWarmRepetition;

  /// Вход в разговор (37-5, SESSION-DES-4): бровь списка фраз, ради которых разговор (targets ряда разговора окна).
  ///
  /// In ru, this message translates to:
  /// **'Скажи в разговоре'**
  String get planTalkEntrySay;

  /// Лист голоса (38-1, наряд FIX-3 §6): спрашивается один раз перед первым планом, пока пол в профиле не сказан.
  ///
  /// In ru, this message translates to:
  /// **'Каким голосом озвучивать твои реплики?'**
  String get planVoiceTitle;

  /// Лист голоса (38-1): плашка мужского голоса.
  ///
  /// In ru, this message translates to:
  /// **'Мужской'**
  String get planVoiceMale;

  /// Лист голоса (38-1): подпись мужской плашки.
  ///
  /// In ru, this message translates to:
  /// **'ниже и спокойнее'**
  String get planVoiceMaleHint;

  /// Лист голоса (38-1): плашка женского голоса.
  ///
  /// In ru, this message translates to:
  /// **'Женский'**
  String get planVoiceFemale;

  /// Лист голоса (38-1): подпись женской плашки.
  ///
  /// In ru, this message translates to:
  /// **'выше и мягче'**
  String get planVoiceFemaleHint;

  /// Лист голоса (38-1): строка под плашками — выбор не навсегда.
  ///
  /// In ru, this message translates to:
  /// **'Можно поменять в профиле'**
  String get planVoiceInProfile;

  /// Итог разговора (37-12, 37-12b): надпись над карточками конструкций; лист по тапу на ряд над микрофоном (37-8d): заголовок листа. Счётчика рядом нет — наряд FIX-3 §3 снял «N из M».
  ///
  /// In ru, this message translates to:
  /// **'Конструкции в разговоре'**
  String get planTalkConstructions;

  /// Итог разговора (37-12) и лист конструкций (37-8d): серая строка под закрашенной карточкой — каркас со значением ученика (value_target).
  ///
  /// In ru, this message translates to:
  /// **'ты сказал: {said}'**
  String planTalkYouSaid(String said);

  /// Итог репетиции и повтора (37-12b): серая строка под незакрашенной карточкой — завтра событие, а не новый день. «Разговором» — то же слово, что в заголовке planTalkReady: склонённой формы события сервер не шлёт (приёмка CLIENT-FIX-4 25.09, отчёт §7).
  ///
  /// In ru, this message translates to:
  /// **'повтори перед разговором'**
  String get planTalkRepeatBefore;

  /// Лист конструкций (37-8d): серая строка под плашкой, сказанной «почти» (targets[].state = almost) — строка урока на языке цели (каркас со значением урока).
  ///
  /// In ru, this message translates to:
  /// **'почти — скажи целиком: {line}'**
  String planTalkAlmostLine(String line);

  /// Лист конструкций (37-8d): серая строка под несказанной плашкой — строка урока на языке цели (каркас со значением урока).
  ///
  /// In ru, this message translates to:
  /// **'из урока: {line}'**
  String planTalkFromLessonLine(String line);

  /// Лента разговора (37-8e): строка судьи чернилами под своим пузырём — ход сказал цель «почти» (одно слово мимо), цель ещё ждёт.
  ///
  /// In ru, this message translates to:
  /// **'Почти — скажи целиком'**
  String get planTalkAlmostJudge;

  /// Итог разговора (37-12, 37-12b): бровь группы каркасов сцены, сказанных сверх целей (summary.extra_said). Нет таких — группы нет.
  ///
  /// In ru, this message translates to:
  /// **'Ещё вспомнил'**
  String get planTalkExtraSaid;

  /// Итог разговора (37-12): серая строка над плашками, когда разговор кончил лимит (summary.ended_by_limit) и несказанное вернётся завтра (returns_tomorrow).
  ///
  /// In ru, this message translates to:
  /// **'Разговор закончился по времени — несказанное вернётся'**
  String get planTalkEndedByTime;

  /// Итог разговора репетиции и повтора (37-12b): та же строка про лимит, когда завтра ничего не вернётся (returns_tomorrow = false) — обещания «вернётся» нет. Строки нет в канве, её вариант назван в отчёте CLIENT-FIX-4.
  ///
  /// In ru, this message translates to:
  /// **'Разговор закончился по времени'**
  String get planTalkEndedByTimeOnly;

  /// Разговор по нескольким сценам: первая часть разделителя сцен в ленте (39-1b, «Сцена 2 · Приём у врача · врач») и заголовка группы на входе (37-5b).
  ///
  /// In ru, this message translates to:
  /// **'Сцена {n}'**
  String planTalkSceneNumber(int n);

  /// Карточка перехода между сценами (39-1): капитель над названием следующей сцены.
  ///
  /// In ru, this message translates to:
  /// **'Сцена {n} из {total}'**
  String planTalkSceneOf(int n, int total);

  /// Карточка перехода между сценами (39-1): единственная кнопка — карточка сжимается в разделитель, здоровается роль следующей сцены.
  ///
  /// In ru, this message translates to:
  /// **'Продолжить'**
  String get planTalkSceneContinue;

  /// Вход в разговор по нескольким сценам (37-5b): первая строка правил с ролью первой сцены в именительном, как её назвал план («Регистратор»); род — window.sources[].partner_gender первой сцены (FIX-4c §3): «Медсестра начнёт первой».
  ///
  /// In ru, this message translates to:
  /// **'{gender, select, female{{role} начнёт первой.} other{{role} начнёт первым.}} Отвечай и спрашивай сам.'**
  String planTalkEntryRuleStartRole(String role, String gender);

  /// Вход в разговор по нескольким сценам (37-5b): серая строка между группами конструкций сцен.
  ///
  /// In ru, this message translates to:
  /// **'Разговор идёт сцена за сценой'**
  String get planTalkEntrySceneByScene;

  /// Окно дня, вкладка программы (23-0d · вернулось): заголовок группы единиц, вернувшихся из прошлого дня — items[].source = returned, номер дня из items[].scene.day_number.
  ///
  /// In ru, this message translates to:
  /// **'Вернулось из дня {n}'**
  String planWindowReturnedFromDay(int n);

  /// То же, когда у сцены-источника нет своего дня (items[].scene.day_number = null).
  ///
  /// In ru, this message translates to:
  /// **'Вернулось'**
  String get planWindowReturnedFrom;

  /// Окно дня и вход в этап (23-0c, 30-1): вторичное действие пройденного ряда — пройти этап ещё раз; по stages[].again сервера.
  ///
  /// In ru, this message translates to:
  /// **'ещё раз'**
  String get planWindowStageAgain;

  /// Ряд разговора пройденного дня, когда повторы суток исчерпаны (30-1e): stages[].again = false.
  ///
  /// In ru, this message translates to:
  /// **'лимит на сегодня'**
  String get planWindowTalkLimitToday;

  /// Кнопка пройденного дня (23-0c): ведёт на итог дня 30-7; повтора дня целиком нет.
  ///
  /// In ru, this message translates to:
  /// **'Итог дня'**
  String get planWindowDaySummary;

  /// Окно пройденного дня-системы (37-1c, 37-2): кнопка внизу — итог репетиции или повторения; «Итог дня» остаётся дню плана (23-0c).
  ///
  /// In ru, this message translates to:
  /// **'Итог'**
  String get planWindowSummary;

  /// Окно пройденного дня: шит на 409 plan_conversation_replay_limit — повтор разговора на сегодня исчерпан.
  ///
  /// In ru, this message translates to:
  /// **'Разговор сегодня уже повторяли — вернись завтра'**
  String get planWindowTalkReplayLimit;

  /// Заставка (41-1c) и вход на ней (41-4): слоган под словомарком Ritora. Inter 15 серым.
  ///
  /// In ru, this message translates to:
  /// **'Готов говорить.'**
  String get startSlogan;

  /// Вход на заставке (41-4): угольная кнопка с логотипом Apple. Формулировка из вариантов Apple HIG (sign in with Apple).
  ///
  /// In ru, this message translates to:
  /// **'Войти с Apple'**
  String get startSignInApple;

  /// Вход на заставке (41-4): карточка с контуром чернил и цветной «G».
  ///
  /// In ru, this message translates to:
  /// **'Войти с Google'**
  String get startSignInGoogle;

  /// Вход (41-4b): строка чернилами над кнопками, когда вход не удался (сеть, сервер, провайдер). Отмена своего окна Apple/Google — не ошибка, строки нет.
  ///
  /// In ru, this message translates to:
  /// **'Не удалось войти. Попробуй ещё раз'**
  String get startSignInFailed;

  /// Вход (41-4): юрстрока под кнопками, 13 серым; {terms} и {privacy} — ссылки латунью (startLegalTerms, startLegalPrivacy).
  ///
  /// In ru, this message translates to:
  /// **'Продолжая, ты принимаешь {terms} и {privacy}'**
  String startLegal(String terms, String privacy);

  /// Ссылка «Правила» в юрстроке входа (41-4) и строка профиля (42-1).
  ///
  /// In ru, this message translates to:
  /// **'Правила'**
  String get startLegalTerms;

  /// Ссылка «Конфиденциальность» в юрстроке входа (41-4); в профиле (42-1) — строка «Конфиденциальность» (en: Privacy).
  ///
  /// In ru, this message translates to:
  /// **'Конфиденциальность'**
  String get startLegalPrivacy;

  /// Листы «зачем» (41-2 a–d): справа сверху, на фото — на плашке бумаги 60 %; ведёт на вкладку «План».
  ///
  /// In ru, this message translates to:
  /// **'Пропустить'**
  String get introSkip;

  /// Последний лист «зачем» (41-2e): угольная кнопка → вкладка «План».
  ///
  /// In ru, this message translates to:
  /// **'Начать'**
  String get introStart;

  /// Лист a (41-2a): заголовок Literata 30, три строки ручным переносом; точки латунью.
  ///
  /// In ru, this message translates to:
  /// **'Скоро важный\nразговор.\nТы будешь готов.'**
  String get introATitle;

  /// Лист a (41-2a): мысль под заголовком, Inter 15 серым.
  ///
  /// In ru, this message translates to:
  /// **'Врач, аренда, собеседование, банк. Назови событие — план соберётся именно под него.'**
  String get introAThought;

  /// Лист a (41-2a): перевод реплики «I’d like to make an appointment.» под ней, 13 серым. В английском интерфейсе пусто — строки нет.
  ///
  /// In ru, this message translates to:
  /// **'Я хочу записаться на приём.'**
  String get introALineNative;

  /// Лист a (41-2a): капитель на карточке сцены (печатается прописными).
  ///
  /// In ru, this message translates to:
  /// **'аэропорт'**
  String get introSceneAirport;

  /// Лист a (41-2a): капитель на карточке сцены.
  ///
  /// In ru, this message translates to:
  /// **'банк'**
  String get introSceneBank;

  /// Лист a (41-2a): капитель на карточке сцены «собеседование».
  ///
  /// In ru, this message translates to:
  /// **'работа'**
  String get introSceneInterview;

  /// Лист a (41-2a): капитель на карточке сцены.
  ///
  /// In ru, this message translates to:
  /// **'аренда'**
  String get introSceneRent;

  /// Лист a (41-2a): капитель на верхней карточке сцены.
  ///
  /// In ru, this message translates to:
  /// **'врач'**
  String get introSceneDoctor;

  /// Лист b (41-2b): заголовок, две строки; точка латунью.
  ///
  /// In ru, this message translates to:
  /// **'Язык —\nпод каждый разговор.'**
  String get introBTitle;

  /// Лист b (41-2b): мысль — семь языков дословно по реестру канвы.
  ///
  /// In ru, this message translates to:
  /// **'Английский, немецкий, румынский, польский, испанский, итальянский, французский. Выбираешь в каждом плане, подсказки — на родном.'**
  String get introBThought;

  /// Лист c (41-2c): бровь капителью над маршрутом (печатается прописными).
  ///
  /// In ru, this message translates to:
  /// **'6 дней · 20 минут в день'**
  String get introCBrow;

  /// Лист c (41-2c): подпись под узлом дня 2 маршрута.
  ///
  /// In ru, this message translates to:
  /// **'сегодня'**
  String get introCToday;

  /// Лист c (41-2c): капитель даты события над латунной точкой (печатается прописными).
  ///
  /// In ru, this message translates to:
  /// **'2 октября'**
  String get introCDate;

  /// Лист c (41-2c): название события под латунной точкой, 13 чернилами.
  ///
  /// In ru, this message translates to:
  /// **'приём у врача'**
  String get introCEvent;

  /// Лист c (41-2c): подпись иконки этапа в ряду шести этапов.
  ///
  /// In ru, this message translates to:
  /// **'Слова'**
  String get introStageWords;

  /// Лист c (41-2c): подпись иконки этапа.
  ///
  /// In ru, this message translates to:
  /// **'Фразы'**
  String get introStagePhrases;

  /// Лист c (41-2c): подпись иконки этапа.
  ///
  /// In ru, this message translates to:
  /// **'Диалог'**
  String get introStageDialogue;

  /// Лист c (41-2c): подпись иконки этапа «Слушаю и отвечаю» (коротко).
  ///
  /// In ru, this message translates to:
  /// **'Слушаю'**
  String get introStageListen;

  /// Лист c (41-2c): подпись иконки этапа «Говорю сам» (коротко).
  ///
  /// In ru, this message translates to:
  /// **'Говорю'**
  String get introStageSpeak;

  /// Лист c (41-2c): подпись иконки этапа «Разговор».
  ///
  /// In ru, this message translates to:
  /// **'Разговор'**
  String get introStageTalk;

  /// Лист c (41-2c): заголовок, две строки; точка латунью.
  ///
  /// In ru, this message translates to:
  /// **'Двадцать минут\nв день — вслух.'**
  String get introCTitle;

  /// Лист c (41-2c): мысль под заголовком.
  ///
  /// In ru, this message translates to:
  /// **'Слова, фразы, диалог: слушаешь и говоришь, а не печатаешь. Ровно на те дни, что остались до события.'**
  String get introCThought;

  /// Лист d (41-2d): перевод реплики роли в её пузыре, 15 серым. В английском интерфейсе пусто — строки нет.
  ///
  /// In ru, this message translates to:
  /// **'Доброе утро. Что вас беспокоит?'**
  String get introDLineNative;

  /// Лист d (41-2d): подпись у микрофона 30-3 в покое.
  ///
  /// In ru, this message translates to:
  /// **'тап — говорить'**
  String get introDTap;

  /// Лист d (41-2d): заголовок, две строки; точка латунью.
  ///
  /// In ru, this message translates to:
  /// **'Живой разговор с ИИ\n— не по сценарию.'**
  String get introDTitle;

  /// Лист d (41-2d): мысль под заголовком.
  ///
  /// In ru, this message translates to:
  /// **'Собеседник слышит, что ты сказал, и отвечает именно на это. Переспроси, отойди от темы — он подхватит.'**
  String get introDThought;

  /// Лист e (41-2e): подпись 13 серым над заголовком.
  ///
  /// In ru, this message translates to:
  /// **'три подборки из стора · свои слова из планов'**
  String get introECaption;

  /// Лист e (41-2e): название обложки подборки стора, Literata 17.
  ///
  /// In ru, this message translates to:
  /// **'Быт и город'**
  String get introECoverCity;

  /// Лист e (41-2e): название средней обложки.
  ///
  /// In ru, this message translates to:
  /// **'Здоровье'**
  String get introECoverHealth;

  /// Лист e (41-2e): название обложки.
  ///
  /// In ru, this message translates to:
  /// **'Работа'**
  String get introECoverWork;

  /// Лист e (41-2e): число слов под названием обложки, 13 серым.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}}'**
  String introEWordCount(int count);

  /// Лист e (41-2e): заголовок, две строки; точка латунью.
  ///
  /// In ru, this message translates to:
  /// **'Слова остаются\nс тобой.'**
  String get introETitle;

  /// Лист e (41-2e): мысль под заголовком.
  ///
  /// In ru, this message translates to:
  /// **'Свои коллекции и готовые подборки. Повторяй между планами — ничего не пропадёт.'**
  String get introEThought;

  /// Профиль (42-1): строка провайдера под именем, с галкой шалфея, без действия.
  ///
  /// In ru, this message translates to:
  /// **'Вход через Apple'**
  String get accountSignedInApple;

  /// Профиль (42-1b): строка провайдера под именем.
  ///
  /// In ru, this message translates to:
  /// **'Вход через Google'**
  String get accountSignedInGoogle;

  /// Профиль (42-1): капитель группы подписки.
  ///
  /// In ru, this message translates to:
  /// **'Подписка'**
  String get accountGroupSubscription;

  /// Профиль (42-1a): тариф без подписки (access.plan = free).
  ///
  /// In ru, this message translates to:
  /// **'Бесплатно'**
  String get accountFree;

  /// Профиль (42-1a): что даёт бесплатный тариф — справа серым.
  ///
  /// In ru, this message translates to:
  /// **'один план, день 1'**
  String get accountFreeValue;

  /// Профиль (42-1b): тариф при access.plan = premium.
  ///
  /// In ru, this message translates to:
  /// **'Premium'**
  String get accountPremium;

  /// Профиль (42-1b): когда продлится подписка — access.expires_at, дата «25 октября».
  ///
  /// In ru, this message translates to:
  /// **'продлится {date}'**
  String accountPremiumUntil(String date);

  /// Профиль (42-1b): право без срока (access.expires_at = null — выдано владельцем, lifetime).
  ///
  /// In ru, this message translates to:
  /// **'бессрочно'**
  String get accountPremiumForever;

  /// Профиль (42-1b): ведёт в подписки App Store.
  ///
  /// In ru, this message translates to:
  /// **'Управлять подпиской'**
  String get accountManageSubscription;

  /// Профиль (42-1b): перечитывает права аккаунта, без перехода (покупок в API нет до PAY-1).
  ///
  /// In ru, this message translates to:
  /// **'Восстановить покупки'**
  String get accountRestorePurchases;

  /// Профиль (42-1): капитель группы обучения.
  ///
  /// In ru, this message translates to:
  /// **'Обучение'**
  String get accountGroupLearning;

  /// Профиль (42-1): голос своих реплик (мужской/женский → сервер, лист 38-1).
  ///
  /// In ru, this message translates to:
  /// **'Голос ученика'**
  String get accountVoice;

  /// Профиль (42-1): значение строки «Голос ученика».
  ///
  /// In ru, this message translates to:
  /// **'мужской'**
  String get accountVoiceMale;

  /// Профиль (42-1): значение строки «Голос ученика».
  ///
  /// In ru, this message translates to:
  /// **'женский'**
  String get accountVoiceFemale;

  /// Профиль (42-1): тумблер шести звуков сессии дня.
  ///
  /// In ru, this message translates to:
  /// **'Звуки в сессии'**
  String get accountSessionSounds;

  /// Профиль (42-1): язык интерфейса — шит выбора «русский / English».
  ///
  /// In ru, this message translates to:
  /// **'Язык интерфейса'**
  String get accountUiLanguage;

  /// Профиль (42-1): русский язык интерфейса — своим именем в любом интерфейсе.
  ///
  /// In ru, this message translates to:
  /// **'русский'**
  String get accountUiRussian;

  /// Профиль (42-1): английский язык интерфейса — своим именем.
  ///
  /// In ru, this message translates to:
  /// **'English'**
  String get accountUiEnglish;

  /// Профиль (42-1): родной язык аккаунта — список родных сервера минус цель (LANG-1).
  ///
  /// In ru, this message translates to:
  /// **'Родной язык'**
  String get accountNativeLanguage;

  /// Профиль (42-1): капитель группы напоминаний.
  ///
  /// In ru, this message translates to:
  /// **'Напоминания'**
  String get accountGroupReminders;

  /// Профиль (42-1) и шит 42-4: тумблер напоминаний.
  ///
  /// In ru, this message translates to:
  /// **'Напоминать о дне'**
  String get accountRemind;

  /// Профиль (42-1): время напоминания → шит 42-4.
  ///
  /// In ru, this message translates to:
  /// **'Время'**
  String get accountTime;

  /// Профиль (42-1): капитель группы приложения.
  ///
  /// In ru, this message translates to:
  /// **'Приложение'**
  String get accountGroupApp;

  /// Профиль (42-1): документ «Правила».
  ///
  /// In ru, this message translates to:
  /// **'Правила'**
  String get accountTerms;

  /// Профиль (42-1): документ «Конфиденциальность».
  ///
  /// In ru, this message translates to:
  /// **'Конфиденциальность'**
  String get accountPrivacy;

  /// Профиль (42-1): письмо в поддержку.
  ///
  /// In ru, this message translates to:
  /// **'Поддержка'**
  String get accountSupport;

  /// Профиль (42-1): значение строки «Поддержка».
  ///
  /// In ru, this message translates to:
  /// **'письмо'**
  String get accountSupportValue;

  /// Профиль (42-1): системное окно оценки App Store.
  ///
  /// In ru, this message translates to:
  /// **'Оценить Ritora'**
  String get accountRate;

  /// Профиль (42-1): текстом чернилами над «Удалить аккаунт»; POST /auth/logout → заставка 41-4a.
  ///
  /// In ru, this message translates to:
  /// **'Выйти'**
  String get accountSignOut;

  /// Профиль (42-1) и шит 42-3: текст терракотой / кнопка контуром терракоты.
  ///
  /// In ru, this message translates to:
  /// **'Удалить аккаунт'**
  String get accountDelete;

  /// Шит имени (42-2): заголовок.
  ///
  /// In ru, this message translates to:
  /// **'Имя'**
  String get accountNameTitle;

  /// Шит имени (42-2): плейсхолдер пустого поля. Подписи над полем нет — заголовок листа уже «Имя».
  ///
  /// In ru, this message translates to:
  /// **'Как тебя зовут'**
  String get accountNameHint;

  /// Шиты 42-2 и 42-4: угольная кнопка.
  ///
  /// In ru, this message translates to:
  /// **'Готово'**
  String get accountDone;

  /// Шит удаления (42-3): заголовок.
  ///
  /// In ru, this message translates to:
  /// **'Удалить аккаунт?'**
  String get accountDeleteTitle;

  /// Шит удаления (42-3): что исчезнет, с названием текущего плана.
  ///
  /// In ru, this message translates to:
  /// **'Исчезнут план «{plan}», пройденные дни и настройки. Восстановить их будет нельзя.'**
  String accountDeleteBodyPlan(String plan);

  /// Шит удаления (42-3): то же, когда плана нет.
  ///
  /// In ru, this message translates to:
  /// **'Исчезнут пройденные дни и настройки. Восстановить их будет нельзя.'**
  String get accountDeleteBody;

  /// Шит удаления (42-3): строка 13 серым под текстом.
  ///
  /// In ru, this message translates to:
  /// **'Подписку отмени в App Store'**
  String get accountDeleteNote;

  /// Шит удаления (42-3): DELETE /auth/me не прошёл (нет сети, 5xx) — ничего не удалено, кнопка снова живая.
  ///
  /// In ru, this message translates to:
  /// **'Не удалось удалить. Попробуй ещё раз'**
  String get accountDeleteFailed;

  /// Шит удаления (42-3b): кнопка, пока сервер удаляет; «Отмена» погашена, шит не закрывается.
  ///
  /// In ru, this message translates to:
  /// **'Удаляем…'**
  String get accountDeleting;

  /// Шит напоминаний (42-4): заголовок.
  ///
  /// In ru, this message translates to:
  /// **'Напоминания'**
  String get accountRemindersTitle;

  /// Шит напоминаний (42-4): строка под колесом.
  ///
  /// In ru, this message translates to:
  /// **'Мы напоминаем раз в день, когда ждёт следующий день плана'**
  String get accountRemindersNote;

  /// Шит напоминаний (42-4): вместо колеса, когда iOS запретила уведомления; тап — Настройки.
  ///
  /// In ru, this message translates to:
  /// **'Уведомления выключены в Настройках iOS — включить там'**
  String get accountRemindersDenied;

  /// Пред-запрос уведомлений (43-1): после итога дня 1 (и дня 2 после «Не сейчас»); день — следующий, время — своё из профиля или час сервера.
  ///
  /// In ru, this message translates to:
  /// **'Напомнить про день {day} завтра в {time}?'**
  String notifyAskTitle(int day, String time);

  /// Пред-запрос уведомлений (43-1): строка под вопросом.
  ///
  /// In ru, this message translates to:
  /// **'Раз в день, без лишнего'**
  String get notifyAskBody;

  /// Пред-запрос уведомлений (43-1): латунью; после дня 1 — ещё раз после дня 2, потом не спрашивать.
  ///
  /// In ru, this message translates to:
  /// **'Не сейчас'**
  String get notifyAskLater;

  /// Пред-запрос уведомлений (43-1): угольная кнопка → системный запрос iOS.
  ///
  /// In ru, this message translates to:
  /// **'Напоминать'**
  String get notifyAskYes;

  /// Пред-запрос микрофона (41-3): на первой карточке с микрофоном.
  ///
  /// In ru, this message translates to:
  /// **'Ritora слушает, как ты говоришь'**
  String get micAskTitle;

  /// Пред-запрос микрофона (41-3): строка без обещаний.
  ///
  /// In ru, this message translates to:
  /// **'Микрофон нужен, чтобы говорить: в «Диалоге», «Говорю сам» и в разговоре'**
  String get micAskBody;

  /// Пред-запрос микрофона (41-3): латунью; карточку можно пропустить, шит вернётся на следующей карточке с микрофоном.
  ///
  /// In ru, this message translates to:
  /// **'Позже'**
  String get micAskLater;

  /// Пред-запрос микрофона (41-3): угольная кнопка → системные окна iOS (распознавание и микрофон).
  ///
  /// In ru, this message translates to:
  /// **'Разрешить микрофон'**
  String get micAskAllow;

  /// Плита упавшего дня (22-5c): вторая строка, только когда «Повторить» не ушёл — сети действительно нет (CLIENT-START §6).
  ///
  /// In ru, this message translates to:
  /// **'Нет сети'**
  String get planPlateNoNetwork;

  /// Плита дня, запертого подпиской (21-3 / 23-0a «по подписке»): начало строки под названием — «по подписке · 75 карточек».
  ///
  /// In ru, this message translates to:
  /// **'по подписке'**
  String get planPlateBySubscription;

  /// Плита дня, запертого подпиской (21-3 / 23-0a): 13 серым вместо кнопки «Начать».
  ///
  /// In ru, this message translates to:
  /// **'Откроется с подпиской'**
  String get planPlateOpensWithSubscription;

  /// Плита дня, запертого подпиской (21-3 / 23-0a), и экран «Второй план — по подписке»: кнопка контуром латуни → профиль, группа подписки (до PAY-1).
  ///
  /// In ru, this message translates to:
  /// **'Подписка'**
  String get planPlateSubscription;

  /// Маршрут (22-5a / 21-3 «по подписке»): мета первого дня, запертого подпиской.
  ///
  /// In ru, this message translates to:
  /// **'откроется с подпиской'**
  String get planRouteMetaOpensWithSubscription;

  /// Маршрут (22-5a / 21-3 «по подписке»): мета следующих дней, запертых подпиской.
  ///
  /// In ru, this message translates to:
  /// **'по подписке'**
  String get planRouteMetaBySubscription;

  /// Таб «План» (21-2b): карточка спасательного набора под маршрутом — rescue_kit плана (LANG-1b §2).
  ///
  /// In ru, this message translates to:
  /// **'Спасательный набор'**
  String get planKitLabel;

  /// Карточка набора (21-2b): строка под названием — сколько фраз.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} фраза на любой случай} few{{n} фразы на любой случай} many{{n} фраз на любой случай} other{{n} фразы на любой случай}}'**
  String planKitSub(int n);

  /// Карточка набора (21-2b): раскрыть все фразы на месте.
  ///
  /// In ru, this message translates to:
  /// **'все {n} →'**
  String planKitAll(int n);

  /// Карточка набора (21-2b): свернуть до первой фразы.
  ///
  /// In ru, this message translates to:
  /// **'свернуть'**
  String get planKitCollapse;

  /// Вход в план: POST /plans ответил 402 plan_subscription_required — экран-заглушка до пейволла PAY-1, кнопка «Подписка» → профиль.
  ///
  /// In ru, this message translates to:
  /// **'Второй план — по подписке'**
  String get planEntrySubscriptionTitle;

  /// Вход в план: POST /plans ответил 409 plan_active_limit — три плана уже в работе.
  ///
  /// In ru, this message translates to:
  /// **'Не больше трёх планов сразу'**
  String get planEntryActiveLimitTitle;

  /// Вход в план, заглушка «Не больше трёх планов сразу»: вернуться на таб «План».
  ///
  /// In ru, this message translates to:
  /// **'К плану'**
  String get planEntryToTab;
}

class _AppLocalizationsDelegate extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) => <String>['en', 'ru'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'en':
      return AppLocalizationsEn();
    case 'ru':
      return AppLocalizationsRu();
  }

  throw FlutterError(
    'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}

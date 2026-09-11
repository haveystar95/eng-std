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
  /// **'{current} из {total}'**
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
  /// **'Ещё {count} после синхронизации — зайдите снова, когда будет сеть.'**
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
  /// **'{count, plural, one{Ещё {count} слово после синхронизации} few{Ещё {count} слова после синхронизации} many{Ещё {count} слов после синхронизации} other{Ещё {count} слов после синхронизации}}'**
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
  /// **'У врача'**
  String get homeGenerateChipDoctor;

  /// Чип-пример темы.
  ///
  /// In ru, this message translates to:
  /// **'Аренда'**
  String get homeGenerateChipRent;

  /// Чип-пример темы.
  ///
  /// In ru, this message translates to:
  /// **'Собеседование'**
  String get homeGenerateChipInterview;

  /// Прогресс коллекции на карточке ленты, «18 из 24 слов» (ICU plural). Предлог «из» требует родительного падежа, поэтому формы здесь НЕ счётные: 1 → «из 1 слова», 2 → «из 2 слов», 5 → «из 5 слов» (QA-OBS-5).
  ///
  /// In ru, this message translates to:
  /// **'{total, plural, one{{done} из {total} слова} few{{done} из {total} слов} many{{done} из {total} слов} other{{done} из {total} слов}}'**
  String homeCollectionProgress(int done, int total);

  /// Таб-бар: главная.
  ///
  /// In ru, this message translates to:
  /// **'Главная'**
  String get tabHome;

  /// Таб-бар: коллекции.
  ///
  /// In ru, this message translates to:
  /// **'Коллекции'**
  String get tabCollections;

  /// Таб-бар: профиль.
  ///
  /// In ru, this message translates to:
  /// **'Профиль'**
  String get tabProfile;

  /// Заголовок экрана сессии, запущенной с главной (повторение / свободная тренировка).
  ///
  /// In ru, this message translates to:
  /// **'Занятие'**
  String get homeSessionTitle;

  /// Количество слов в коллекции, «24 слова» (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}}'**
  String collectionWordsCount(int count);

  /// Хвост подзаголовка коллекции про due-слова.
  ///
  /// In ru, this message translates to:
  /// **'{count} к повторению сегодня'**
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

  /// Подстрока кнопки «Учить».
  ///
  /// In ru, this message translates to:
  /// **'Новые слова — выучить'**
  String get collectionLearnSubtitle;

  /// Главная кнопка коллекции: подошёл срок повторения.
  ///
  /// In ru, this message translates to:
  /// **'Повторить {count}'**
  String collectionReviewButton(int count);

  /// Подстрока кнопки «Повторить».
  ///
  /// In ru, this message translates to:
  /// **'Срок повторения подошёл'**
  String get collectionReviewSubtitle;

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

  /// Почему у коллекции по умолчанию нет удаления.
  ///
  /// In ru, this message translates to:
  /// **'«{title}» — коллекция для сохранённых слов, её нельзя удалить. Переименовать можно.'**
  String collectionDefaultUndeletable(String title);

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
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}}'**
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
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}} · освоено {mastered}'**
  String collectionsTileMastered(int count, int mastered);

  /// Заголовок карточки идущей генерации (кадр 2.5).
  ///
  /// In ru, this message translates to:
  /// **'Собираем коллекцию…'**
  String get generationGeneratingTitle;

  /// Мета-строка генерации: тема · уровни · размер («Аренда жилья · A2–B1 · ≈15 слов»).
  ///
  /// In ru, this message translates to:
  /// **'{topic} · {levels} · {size}'**
  String generationGeneratingMeta(String topic, String levels, String size);

  /// Пояснение под индикатором идущей генерации (кадр 2.5).
  ///
  /// In ru, this message translates to:
  /// **'Подбираем слова и фотографии · обычно 20–30 секунд'**
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
  /// **'{delivered} из {requested}'**
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
  /// **'{count, plural, one{Осталось {count} генерация сегодня} few{Осталось {count} генерации сегодня} many{Осталось {count} генераций сегодня} other{Осталось {count} генераций сегодня}}'**
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
  /// **'Нужно больше? Premium — до 20 в день'**
  String get generatePremiumUpsell;

  /// Ссылка на ручное создание коллекции (кадр 6b).
  ///
  /// In ru, this message translates to:
  /// **'Собрать коллекцию вручную'**
  String get generateManual;

  /// Индикатор идущей записи с таймером (кадр 6c).
  ///
  /// In ru, this message translates to:
  /// **'Слушаю · {time}'**
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

  /// Подсказка на месте клавиатуры во время записи (кадр 6c).
  ///
  /// In ru, this message translates to:
  /// **'Говори — клавиатура вернётся, когда остановишь запись'**
  String get generateVoiceRecordingNote;

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

  /// Таб-бар: поиск слова (между Коллекциями и Прогрессом).
  ///
  /// In ru, this message translates to:
  /// **'Поиск'**
  String get tabSearch;

  /// Заголовок экрана поиска.
  ///
  /// In ru, this message translates to:
  /// **'Поиск слова'**
  String get searchTitle;

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
  /// **'{used} из {cap} на сегодня'**
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
  /// **'{step} из {total}'**
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

  /// Таб-бар: прогресс (между Коллекциями и Профилем).
  ///
  /// In ru, this message translates to:
  /// **'Прогресс'**
  String get tabProgress;

  /// Заголовок экрана прогресса (кадр 2.6).
  ///
  /// In ru, this message translates to:
  /// **'Прогресс'**
  String get progressTitle;

  /// Крупная антиква-строка стрика на экране прогресса.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} день подряд} few{{count} дня подряд} many{{count} дней подряд} other{{count} дня подряд}}'**
  String progressStreakDays(int count);

  /// Строка «Лучший результат» под стриком.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Лучший результат — {count} день} few{Лучший результат — {count} дня} many{Лучший результат — {count} дней} other{Лучший результат — {count} дня}}'**
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
  /// **'{count, plural, one{Все {count} слово} few{Все {count} слова} many{Все {count} слов} other{Все {count} слов}}'**
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

  /// Пояснение на пунктирной карточке генерации в офлайне (кадр 9c).
  ///
  /// In ru, this message translates to:
  /// **'Генерация недоступна без сети. Тема сохранится и уйдёт в работу, когда связь вернётся.'**
  String get homeGenerateOfflineNote;

  /// Словесный знак приложения на экране входа (бренд, не переводится).
  ///
  /// In ru, this message translates to:
  /// **'Слова'**
  String get appWordmark;

  /// Подзаголовок на экране входа (кадр 10a).
  ///
  /// In ru, this message translates to:
  /// **'Слова для реальных ситуаций — от банка до собеседования.'**
  String get authTagline;

  /// Кнопка входа через Google (кадр 10a).
  ///
  /// In ru, this message translates to:
  /// **'Продолжить с Google'**
  String get authContinueGoogle;

  /// Подпись кнопки Apple. SignInWithAppleButton рисуется Flutter'ом и по умолчанию несёт зашитое английское «Sign in with Apple» — локаль устройства тут ни при чём (QA-OBS-31). Формулировка обязана оставаться одним из вариантов, разрешённых Apple HIG: sign in / sign up / continue with Apple.
  ///
  /// In ru, this message translates to:
  /// **'Продолжить с Apple'**
  String get authContinueApple;

  /// Ссылка на условия (кадр 10a).
  ///
  /// In ru, this message translates to:
  /// **'Условия'**
  String get authTerms;

  /// Ссылка на политику конфиденциальности (кадр 10a).
  ///
  /// In ru, this message translates to:
  /// **'Конфиденциальность'**
  String get authPrivacy;

  /// Подсказка на экране входа в офлайне.
  ///
  /// In ru, this message translates to:
  /// **'Нет сети. Для первого входа нужно подключение.'**
  String get authOfflineHint;

  /// Ошибка, если Apple-вход не настроен (нет бэкенда/entitlement).
  ///
  /// In ru, this message translates to:
  /// **'Вход через Apple пока недоступен.'**
  String get authAppleUnavailable;

  /// Онбординг, шаг 1 — заголовок (кадр 10b).
  ///
  /// In ru, this message translates to:
  /// **'Какой язык учим?'**
  String get onbLangTitle;

  /// Онбординг, шаг 1 — подзаголовок.
  ///
  /// In ru, this message translates to:
  /// **'Можно поменять в профиле в любой момент.'**
  String get onbLangSubtitle;

  /// Онбординг, шаг 2 — заголовок (кадр 10c).
  ///
  /// In ru, this message translates to:
  /// **'Насколько уверенно читаешь?'**
  String get onbLevelTitle;

  /// Онбординг, шаг 2 — подзаголовок.
  ///
  /// In ru, this message translates to:
  /// **'Примерно — потом уточним по твоим ответам в разборе.'**
  String get onbLevelSubtitle;

  /// Онбординг, шаг 2 — пример слов для уровня.
  ///
  /// In ru, this message translates to:
  /// **'На {level} в коллекции попадают слова вроде «wire transfer» и «make ends meet».'**
  String onbLevelExample(String level);

  /// Онбординг, шаг 3 — заголовок (кадр 10d).
  ///
  /// In ru, this message translates to:
  /// **'Сколько слов в день?'**
  String get onbGoalTitle;

  /// Онбординг, шаг 3 — подзаголовок.
  ///
  /// In ru, this message translates to:
  /// **'Цель влияет только на напоминания и прогресс.'**
  String get onbGoalSubtitle;

  /// Онбординг, шаг 3 — оценка времени.
  ///
  /// In ru, this message translates to:
  /// **'≈ {count} минут в день'**
  String onbGoalMinutes(int count);

  /// Метка рекомендованной дневной цели.
  ///
  /// In ru, this message translates to:
  /// **'рекомендуем'**
  String get onbGoalRecommended;

  /// Онбординг — сноска.
  ///
  /// In ru, this message translates to:
  /// **'Всё это меняется в профиле — уровень, цель и язык не заперты за онбордингом.'**
  String get onbFooterNote;

  /// Кнопка перехода к следующему шагу онбординга.
  ///
  /// In ru, this message translates to:
  /// **'Далее'**
  String get onbNext;

  /// Кнопка завершения онбординга.
  ///
  /// In ru, this message translates to:
  /// **'Начать'**
  String get onbStart;

  /// Подпись уровня A1.
  ///
  /// In ru, this message translates to:
  /// **'начало'**
  String get cefrHintA1;

  /// Подпись уровня A2.
  ///
  /// In ru, this message translates to:
  /// **'базовый'**
  String get cefrHintA2;

  /// Подпись уровня B1.
  ///
  /// In ru, this message translates to:
  /// **'средний'**
  String get cefrHintB1;

  /// Подпись уровня B2.
  ///
  /// In ru, this message translates to:
  /// **'уверенный'**
  String get cefrHintB2;

  /// Подпись уровня C1.
  ///
  /// In ru, this message translates to:
  /// **'свободный'**
  String get cefrHintC1;

  /// Подпись уровня C2.
  ///
  /// In ru, this message translates to:
  /// **'почти носитель'**
  String get cefrHintC2;

  /// Заголовок экрана профиля (кадр 11a).
  ///
  /// In ru, this message translates to:
  /// **'Профиль'**
  String get profileTitle;

  /// Секция профиля: обучение.
  ///
  /// In ru, this message translates to:
  /// **'Обучение'**
  String get profileSectionLearning;

  /// Секция профиля: приложение.
  ///
  /// In ru, this message translates to:
  /// **'Приложение'**
  String get profileSectionApp;

  /// Секция профиля: подписка.
  ///
  /// In ru, this message translates to:
  /// **'Подписка'**
  String get profileSectionSubscription;

  /// Секция профиля: аккаунт.
  ///
  /// In ru, this message translates to:
  /// **'Аккаунт'**
  String get profileSectionAccount;

  /// Строка профиля: уровень.
  ///
  /// In ru, this message translates to:
  /// **'Уровень'**
  String get profileRowLevel;

  /// Строка профиля: дневная цель.
  ///
  /// In ru, this message translates to:
  /// **'Дневная цель'**
  String get profileRowGoal;

  /// Строка профиля: язык изучения.
  ///
  /// In ru, this message translates to:
  /// **'Язык изучения'**
  String get profileRowTargetLang;

  /// Строка профиля: язык интерфейса.
  ///
  /// In ru, this message translates to:
  /// **'Язык интерфейса'**
  String get profileRowUiLang;

  /// Строка профиля: автопроизношение.
  ///
  /// In ru, this message translates to:
  /// **'Автопроизношение'**
  String get profileRowAutoPronounce;

  /// Подпись автопроизношения.
  ///
  /// In ru, this message translates to:
  /// **'Озвучивать слово при показе карточки'**
  String get profileAutoPronounceHint;

  /// Строка профиля: показывать чтение слова своими буквами на карточке.
  ///
  /// In ru, this message translates to:
  /// **'Подсказка произношения'**
  String get profileRowTransliteration;

  /// Подпись подсказки произношения.
  ///
  /// In ru, this message translates to:
  /// **'Показывать, как читается слово, вашими буквами'**
  String get profileTransliterationHint;

  /// Строка профиля: напоминания.
  ///
  /// In ru, this message translates to:
  /// **'Напоминания'**
  String get profileRowReminders;

  /// Подпись напоминаний.
  ///
  /// In ru, this message translates to:
  /// **'Одно в день, если есть что повторить'**
  String get profileRemindersHint;

  /// Строка профиля: время напоминания.
  ///
  /// In ru, this message translates to:
  /// **'Время'**
  String get profileRowReminderTime;

  /// Строка подписки: бесплатный тариф.
  ///
  /// In ru, this message translates to:
  /// **'Бесплатный тариф'**
  String get profileFreeTier;

  /// Подпись бесплатного тарифа.
  ///
  /// In ru, this message translates to:
  /// **'3 генерации в день'**
  String get profileFreeTierHint;

  /// Метка «скоро» у подписки.
  ///
  /// In ru, this message translates to:
  /// **'Скоро'**
  String get profileSoon;

  /// Строка профиля: выйти.
  ///
  /// In ru, this message translates to:
  /// **'Выйти'**
  String get profileSignOut;

  /// Строка профиля: удалить аккаунт.
  ///
  /// In ru, this message translates to:
  /// **'Удалить аккаунт'**
  String get profileDeleteAccount;

  /// Значение дневной цели, «N слов».
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слов}}'**
  String profileGoalValue(int count);

  /// Язык интерфейса: системный.
  ///
  /// In ru, this message translates to:
  /// **'Системный'**
  String get uiLangSystem;

  /// Язык интерфейса: русский.
  ///
  /// In ru, this message translates to:
  /// **'Русский'**
  String get uiLangRussian;

  /// Язык интерфейса: английский.
  ///
  /// In ru, this message translates to:
  /// **'English'**
  String get uiLangEnglish;

  /// Заголовок шита выбора языка интерфейса.
  ///
  /// In ru, this message translates to:
  /// **'Язык интерфейса'**
  String get profileUiLangSheet;

  /// Заголовок шита выбора уровня.
  ///
  /// In ru, this message translates to:
  /// **'Уровень'**
  String get profileLevelSheet;

  /// Заголовок шита выбора дневной цели.
  ///
  /// In ru, this message translates to:
  /// **'Дневная цель'**
  String get profileGoalSheet;

  /// Заголовок шита выбора времени напоминания (кадр 13b).
  ///
  /// In ru, this message translates to:
  /// **'Когда напомнить'**
  String get reminderSheetTitle;

  /// Подзаголовок шита времени напоминания.
  ///
  /// In ru, this message translates to:
  /// **'Лучше всего работает время, когда у тебя обычно есть пять свободных минут.'**
  String get reminderSheetSubtitle;

  /// Кнопка сохранения.
  ///
  /// In ru, this message translates to:
  /// **'Сохранить'**
  String get commonSave;

  /// Заголовок подтверждения удаления аккаунта (кадр 11b).
  ///
  /// In ru, this message translates to:
  /// **'Удалить аккаунт?'**
  String get deleteAccountTitle;

  /// Тело подтверждения удаления аккаунта с персональными числами.
  ///
  /// In ru, this message translates to:
  /// **'Все данные и прогресс будут удалены безвозвратно: {words}, {streak} и все коллекции.'**
  String deleteAccountBody(String words, String streak);

  /// Часть «N слов» в подтверждении удаления.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слов}}'**
  String deleteAccountWords(int count);

  /// Часть «N дней стрика» в подтверждении удаления.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} день стрика} few{{count} дня стрика} many{{count} дней стрика} other{{count} дней стрика}}'**
  String deleteAccountStreak(int count);

  /// Кнопка подтверждения удаления аккаунта.
  ///
  /// In ru, this message translates to:
  /// **'Удалить'**
  String get deleteAccountConfirm;

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

  /// Кнопка записи на карточке говорения — до начала прослушивания.
  ///
  /// In ru, this message translates to:
  /// **'Сказать'**
  String get sessionSpeakStart;

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

  /// Семантика неактивного микрофончика на карточке знакомства: пермишена ещё нет, тап его запрашивает. Подписи на экране нет — только глиф (наряд A-4.1 Ч.5).
  ///
  /// In ru, this message translates to:
  /// **'Включить микрофон'**
  String get sessionEchoEnable;

  /// Шапка сессии на карточке знакомства (кадры 16a–16b).
  ///
  /// In ru, this message translates to:
  /// **'Знакомство'**
  String get sessionHeaderIntro;

  /// Шапка сессии на ступенях узнавания 1–2 (кадры 16c-1, 16c-2).
  ///
  /// In ru, this message translates to:
  /// **'Узнавание'**
  String get sessionHeaderRecognition;

  /// Инструкция ступени 1: показан термин, выбрать перевод (кадр 16c-1).
  ///
  /// In ru, this message translates to:
  /// **'выбери перевод'**
  String get sessionInstrRecogniseTranslation;

  /// Подпись на узнавании: слово встречалось в этой же сессии (кадр 16c-2).
  ///
  /// In ru, this message translates to:
  /// **'вы только что познакомились с этим словом'**
  String get sessionRecogniseJustMet;

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
  /// **'Ступень {step} из {total}: {rung}'**
  String statusLadderStep(int step, int total, String rung);

  /// «Разобрать N слов» — единственная форма, в которой разбор называется в интерфейсе. Слова «триаж»/«стряж» в UI не живут.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Разобрать {count} слово} few{Разобрать {count} слова} many{Разобрать {count} слов} other{Разобрать {count} слова}}'**
  String statusCountToSort(int count);

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

  /// Заголовок блока лестницы в развёрнутой карточке слова (кадр 16e).
  ///
  /// In ru, this message translates to:
  /// **'ЛЕСТНИЦА СЛОВА'**
  String get ladderTitle;

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
  /// **'{days, plural, one{через {days} день} few{через {days} дня} many{через {days} дней} other{через {days} дней}}'**
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
  /// **'{days, plural, one{Стрик — {days} день} few{Стрик — {days} дня} many{Стрик — {days} дней} other{Стрик — {days} дней}}'**
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

  /// Инструкция «Тебе скажут» на ситуационной карточке (SIT-1): реплика звучит, варианты — смыслы на языке поддержки.
  ///
  /// In ru, this message translates to:
  /// **'выбери, что он сказал'**
  String get sessionInstrSituationalHear;

  /// Инструкция «Ты ответишь» на ситуационной карточке (SIT-1, канон §4).
  ///
  /// In ru, this message translates to:
  /// **'выбери, что ответишь'**
  String get sessionInstrSituationalSay;

  /// Инструкция уровня B+ — сборка ответа из блоков вместо выбора из вариантов (SCENE-RUN, Ч.1).
  ///
  /// In ru, this message translates to:
  /// **'собери свой ответ из блоков'**
  String get sessionInstrAssembleTurn;

  /// Инструкция «Ты спросишь» на ситуационной карточке (SIT-1, канон §4).
  ///
  /// In ru, this message translates to:
  /// **'выбери, что спросишь'**
  String get sessionInstrSituationalAsk;

  /// Подпись над положением на ситуационной карточке (кадр D-04).
  ///
  /// In ru, this message translates to:
  /// **'Ситуация'**
  String get sessionSituationLabel;

  /// Подпись над названием сцены на карточке «Тебе скажут» (кадр D-03).
  ///
  /// In ru, this message translates to:
  /// **'Сейчас услышите'**
  String get sessionSituationHearLabel;

  /// Кнопка под карточкой «Тебе скажут»: показывает реплику после ответа.
  ///
  /// In ru, this message translates to:
  /// **'Показать текст'**
  String get sessionSituationRevealText;

  /// Задача карточки, когда у дня нет парной реплики роли: умение сцены (канон §8).
  ///
  /// In ru, this message translates to:
  /// **'Твоя задача — {outcome}.'**
  String sessionSituationTask(String outcome);

  /// Главное действие экрана между присестами.
  ///
  /// In ru, this message translates to:
  /// **'Продолжить'**
  String get planSittingContinue;

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

  /// Ошибка загрузки сессии (сессии строятся на сервере, офлайн недоступны).
  ///
  /// In ru, this message translates to:
  /// **'Не удалось загрузить сессию: {error}'**
  String sessionLoadError(String error);

  /// Ошибка входа: нет сети (кадр 10a).
  ///
  /// In ru, this message translates to:
  /// **'Нет подключения к интернету. Для входа нужна сеть.'**
  String get authErrorOffline;

  /// Ошибка входа: Google Sign-In недоступен на платформе.
  ///
  /// In ru, this message translates to:
  /// **'Вход через Google не поддерживается на этой платформе.'**
  String get authErrorGoogleUnsupported;

  /// Ошибка входа: пользователь отменил вход.
  ///
  /// In ru, this message translates to:
  /// **'Вход отменён.'**
  String get authErrorCancelled;

  /// Ошибка входа: сбой Google Sign-In.
  ///
  /// In ru, this message translates to:
  /// **'Не удалось войти через Google. Попробуй ещё раз.'**
  String get authErrorGoogle;

  /// Ошибка входа: нет ID-токена от Google.
  ///
  /// In ru, this message translates to:
  /// **'Не удалось получить токен Google.'**
  String get authErrorGoogleToken;

  /// Ошибка входа: бэкенд отклонил обмен токена.
  ///
  /// In ru, this message translates to:
  /// **'Не удалось войти. Попробуй ещё раз.'**
  String get authErrorLoginFailed;

  /// Ошибка входа: Apple-вход недоступен (нет бэкенда/платной команды).
  ///
  /// In ru, this message translates to:
  /// **'Вход через Apple пока недоступен.'**
  String get authErrorApple;

  /// Ошибка входа: нет identity-токена от Apple.
  ///
  /// In ru, this message translates to:
  /// **'Не удалось получить токен Apple.'**
  String get authErrorAppleToken;

  /// Кнопка входа в голосовой разговор на экране коллекции (только Premium).
  ///
  /// In ru, this message translates to:
  /// **'Разговор · 3 мин'**
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
  /// **'Слов прозвучало: {used} из {total}'**
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
  /// **'слов: {used} из {total}'**
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

  /// Заголовок секции стора для наборов без темы.
  ///
  /// In ru, this message translates to:
  /// **'Разное'**
  String get storeSectionOther;

  /// Размер набора в сторе, «16 слов».
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}}'**
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
  /// **'Открываются все {count} наборов сразу'**
  String storeAllSetsUnlock(int count);

  /// Заголовок списка терминов в превью-шите набора (кадр 8c).
  ///
  /// In ru, this message translates to:
  /// **'Что внутри'**
  String get storeInsideLabel;

  /// Строка под превью-списком: сколько слов ещё в наборе (кадр 8c).
  ///
  /// In ru, this message translates to:
  /// **'и ещё {count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}}'**
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
  /// **'{title} и ещё {count} наборов'**
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
  /// **'До 20 генераций в день'**
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
  /// **'\$2.50 в месяц'**
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
  /// **'Подписка продлевается автоматически. {price} за год списываются с Apple ID; отменить можно в настройках App Store не позднее чем за 24 часа до конца периода.'**
  String paywallLegalYear(String price);

  /// Юридическая строка авто-продления для месячного периода (кадр 14b).
  ///
  /// In ru, this message translates to:
  /// **'Подписка продлевается автоматически. {price} в месяц списываются с Apple ID; отменить можно в настройках App Store не позднее чем за 24 часа до конца периода.'**
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

  /// Сообщение, когда сервер отклоняет подписку без реального premium.
  ///
  /// In ru, this message translates to:
  /// **'Нужен настоящий Premium (StoreKit — отдельный блок)'**
  String get paywallNeedsRealPremium;

  /// Строка профиля (free): переход на пейволл (кадр 15a).
  ///
  /// In ru, this message translates to:
  /// **'Попробовать Premium'**
  String get profileTryPremium;

  /// Подпись бесплатного тарифа с временем сброса (кадр 15a).
  ///
  /// In ru, this message translates to:
  /// **'3 генерации в день · сбрасываются в {time}'**
  String profileFreeTierReset(String time);

  /// Строка профиля (premium): название тарифа (кадр 15b).
  ///
  /// In ru, this message translates to:
  /// **'Premium'**
  String get profilePremiumActive;

  /// Бейдж «активна» у строки Premium (кадр 15b).
  ///
  /// In ru, this message translates to:
  /// **'активна'**
  String get profilePremiumBadge;

  /// Подпись активной подписки Premium (кадр 15b).
  ///
  /// In ru, this message translates to:
  /// **'Подписка активна'**
  String get profilePremiumHint;

  /// Строка профиля (premium): управление в App Store (кадр 15b).
  ///
  /// In ru, this message translates to:
  /// **'Управлять подпиской'**
  String get profileManageSubscription;

  /// Строка профиля (premium): восстановление покупок (кадр 15b).
  ///
  /// In ru, this message translates to:
  /// **'Восстановить покупки'**
  String get profileRestorePurchases;

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

  /// Развёрнутая карточка слова (16e) для слова вне пула: вместо лестницы, которой ещё нет. «На полке», а не «в каталоге»: слово ждёт разбора, и словарь статусов зовёт это состояние «Разобрать» (Ч.4).
  ///
  /// In ru, this message translates to:
  /// **'Слово на полке — ты его пока не учишь.'**
  String get poolNotStudyingNote;

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
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}}'**
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

  /// Вход на главном экране: учебная сессия, суженная до одной коллекции-источника.
  ///
  /// In ru, this message translates to:
  /// **'Тренировка по теме'**
  String get topicSessionAction;

  /// Заголовок шита выбора коллекции для тематической тренировки.
  ///
  /// In ru, this message translates to:
  /// **'Выбери тему'**
  String get topicSessionTitle;

  /// Бейдж стрика в шапке главной (кадр 17a).
  ///
  /// In ru, this message translates to:
  /// **'Стрик {count}'**
  String homeStreakBadge(int count);

  /// Заголовок тёмной карточки дня (кадр 17a).
  ///
  /// In ru, this message translates to:
  /// **'Сессия на сегодня'**
  String get homeSessionCardTitle;

  /// Лейбл карточки слова-вызова (кадр 19-4), брасовая мишень слева от него.
  ///
  /// In ru, this message translates to:
  /// **'Слово-вызов'**
  String get challengeLabel;

  /// Счётчик серии справа в шапке карточки (кадр 19-4). При нуле не рисуется — «угадано 0 подряд» не предложение.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{угадано {count} подряд} few{угадано {count} подряд} many{угадано {count} подряд} other{угадано {count} подряд}}'**
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
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}}'**
  String homeSessionCardWords(int count);

  /// Оценка времени сессии, «≈ 9 минут» (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{≈ {count} минута} few{≈ {count} минуты} many{≈ {count} минут} other{≈ {count} минуты}}'**
  String homeSessionCardMinutes(int count);

  /// Вторая половина честной подписи сессии: сколько КАРТОЧЕК получится из обещанных слов. Тильда неспроста — состав дня может дойти до тренажёра слегка другим. Склеивается со счётом слов через « · » (см. sessionSizeLabel), одной строкой на главной и на кнопках коллекции (Ч.3).
  ///
  /// In ru, this message translates to:
  /// **'~{count, plural, one{{count} карточка} few{{count} карточки} many{{count} карточек} other{{count} карточки}}'**
  String sessionSizeCards(int count);

  /// Состав сессии: повторения. Форма не склоняется — считается глагол, а не слова.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} повторить} few{{count} повторить} many{{count} повторить} other{{count} повторить}}'**
  String homeSessionPartRepeat(int count);

  /// Состав сессии: новые слова (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} новое} few{{count} новых} many{{count} новых} other{{count} новых}}'**
  String homeSessionPartNew(int count);

  /// Состав сессии: свайпы разбора.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} разобрать} few{{count} разобрать} many{{count} разобрать} other{{count} разобрать}}'**
  String homeSessionPartTriage(int count);

  /// Кнопка тёмной карточки дня (кадр 17a).
  ///
  /// In ru, this message translates to:
  /// **'Начать'**
  String get homeSessionStart;

  /// Заголовок блока «В работе» — размер пула (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{В работе — {count} слово} few{В работе — {count} слова} many{В работе — {count} слов} other{В работе — {count} слова}}'**
  String homeInWorkTitle(int count);

  /// Сколько слов пула ещё ни разу не показывали (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} ждёт очереди} few{{count} ждут очереди} many{{count} ждут очереди} other{{count} ждут очереди}}'**
  String homeInWorkWaiting(int count);

  /// Когда очередь дойдёт до последнего ждущего слова (ICU plural по дням).
  ///
  /// In ru, this message translates to:
  /// **'{days, plural, one{при {perDay} в день новым до очереди ~{days} день} few{при {perDay} в день новым до очереди ~{days} дня} many{при {perDay} в день новым до очереди ~{days} дней} other{при {perDay} в день новым до очереди ~{days} дня}}'**
  String homeInWorkPace(int perDay, int days);

  /// Строка «В работе» в состоянии Б (кадр 17d): квота дня цела, очередь стоит.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{возьмёте {count} сейчас — очередь двинется сегодня} few{возьмёте {count} сейчас — очередь двинется сегодня} many{возьмёте {count} сейчас — очередь двинется сегодня} other{возьмёте {count} сейчас — очередь двинется сегодня}}'**
  String homeInWorkQueueStands(int count);

  /// Заголовок секции слов с ближайшей датой повтора (кадр 17a).
  ///
  /// In ru, this message translates to:
  /// **'На грани забывания'**
  String get homeEdgeTitle;

  /// Дата выпадения слова: завтра.
  ///
  /// In ru, this message translates to:
  /// **'выпадет завтра'**
  String get homeEdgeTomorrow;

  /// Дата выпадения слова, «через 2 дня» (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{через {count} день} few{через {count} дня} many{через {count} дней} other{через {count} дня}}'**
  String homeEdgeInDays(int count);

  /// Заголовок вечерней секции ошибок сессии (кадр 17b).
  ///
  /// In ru, this message translates to:
  /// **'Далось труднее всего'**
  String get homeHardestTitle;

  /// Сколько раз слово ответили неверно в последней сессии (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} ошибка} few{{count} ошибки} many{{count} ошибок} other{{count} ошибки}}'**
  String homeHardestErrors(int count);

  /// Счётчик справа от заголовка секции, «2 слова» (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} слово} few{{count} слова} many{{count} слов} other{{count} слова}}'**
  String homeSectionCount(int count);

  /// Заголовок вечерней карточки (кадр 17b).
  ///
  /// In ru, this message translates to:
  /// **'Сегодня закрыто'**
  String get homeDoneTitle;

  /// Итог дня в СЛОВАХ (кадр 19-2). Единица обязательна: «52 из 52» без неё — счёт карточек в форме счёта слов, и 52 не было числом слов ни у кого. Карточки и минуты идут подписью следом.
  ///
  /// In ru, this message translates to:
  /// **'{total, plural, one{{done} из {total} слова} few{{done} из {total} слов} many{{done} из {total} слов} other{{done} из {total} слов}}'**
  String homeDoneOfWords(int done, int total);

  /// Вторая единица итога дня (кадр 19-2) — сколько карточек стоили эти слова.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} карточка} few{{count} карточки} many{{count} карточек} other{{count} карточки}}'**
  String homeDoneCards(int count);

  /// Третья единица итога дня (кадр 19-2). Сокращённо: это хвост строки, а не самостоятельное утверждение.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} мин} few{{count} мин} many{{count} мин} other{{count} мин}}'**
  String homeDoneMinutes(int count);

  /// Внутренний прогресс одного слова, когда день дошёл до него одного: «1 слово · карточка 2 из 3». Пока слово в цепочке — строка живёт; цепочка кончилась — слово ушло из плана, и строки нет.
  ///
  /// In ru, this message translates to:
  /// **'{total, plural, one{карточка {position} из {total}} few{карточка {position} из {total}} many{карточка {position} из {total}} other{карточка {position} из {total}}}'**
  String homeChainProgress(int position, int total);

  /// Прогресс дня: отвеченные карточки из запланированных, «32 из 32».
  ///
  /// In ru, this message translates to:
  /// **'{done} из {total}'**
  String homeDoneOf(int done, int total);

  /// Сколько заняла сессия, «6 мин 40 с».
  ///
  /// In ru, this message translates to:
  /// **'{minutes} мин {seconds} с'**
  String homeDoneDuration(int minutes, int seconds);

  /// Длительность сессии короче минуты (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} секунда} few{{count} секунды} many{{count} секунд} other{{count} секунды}}'**
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
  /// **'{count, plural, one{Следующий повтор — {when}, {count} слово.} few{Следующий повтор — {when}, {count} слова.} many{Следующий повтор — {when}, {count} слов.} other{Следующий повтор — {when}, {count} слова.}}'**
  String homeNextReviewLine(String when, int count);

  /// Подстановка «когда» для следующего повтора — завтра.
  ///
  /// In ru, this message translates to:
  /// **'завтра'**
  String get homeWhenTomorrow;

  /// Предложение добрать слова из недоразобранной коллекции (кадр 17b).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Можно добить {count} слово из «{title}» сверх плана.} few{Можно добить {count} слова из «{title}» сверх плана.} many{Можно добить {count} слов из «{title}» сверх плана.} other{Можно добить {count} слова из «{title}» сверх плана.}}'**
  String homeExtraFromCollection(int count, String title);

  /// Предложение на закрытом дне (кадр 19-2): слова, УЖЕ взятые в очередь, которые сегодняшняя квота ещё позволяет раздать. Идут прежде свайп-прохода — эти уже выбраны, а разбор это предложение выбрать ещё.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Можно взять {count} слово, которое уже ждёт очереди.} few{Можно взять {count} слова, которые уже ждут очереди.} many{Можно взять {count} слов, которые уже ждут очереди.} other{Можно взять {count} слова, которые уже ждут очереди.}}'**
  String homeExtraNew(int count);

  /// Кнопка «Ещё N слов» на вечерней карточке (кадр 17b).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Ещё {count} слово} few{Ещё {count} слова} many{Ещё {count} слов} other{Ещё {count} слова}}'**
  String homeExtraButton(int count);

  /// Лейбл карточки брошенной коллекции (кадр 17a).
  ///
  /// In ru, this message translates to:
  /// **'Продолжить'**
  String get homeContinueLabel;

  /// Давность последнего касания коллекции (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{брошено {count} день назад} few{брошено {count} дня назад} many{брошено {count} дней назад} other{брошено {count} дня назад}}'**
  String homeContinueAbandoned(int count);

  /// Строка входа в генерацию на главной (кадр 17a).
  ///
  /// In ru, this message translates to:
  /// **'Собрать коллекцию по теме'**
  String get homeGenerateRow;

  /// Тихая строка входа в магазин под генерацией (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{или взять из {count} готового} few{или взять из {count} готовых} many{или взять из {count} готовых} other{или взять из {count} готовых}}'**
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
  /// **'{count, plural, one{Завтра выпадет {count} слово} few{Завтра выпадет {count} слова} many{Завтра выпадет {count} слов} other{Завтра выпадет {count} слова}}'**
  String homeTomorrowRow(int count);

  /// Первая половина строки-награды (кадр 19-2). Со знаком плюс: это прибавление, а не счёт.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{+{count} слово продвинулось} few{+{count} слова продвинулись} many{+{count} слов продвинулись} other{+{count} слова продвинулись}}'**
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
  /// **'5 минут в день — 20 слов в неделю'**
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

  /// Первая карточка первого дня (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Взять готовый набор ({count} тема)} few{Взять готовый набор ({count} темы)} many{Взять готовый набор ({count} тем)} other{Взять готовый набор ({count} темы)}}'**
  String homeFirstDayReadyTitle(int count);

  /// Подпись карточки готовых наборов (кадр 17c).
  ///
  /// In ru, this message translates to:
  /// **'Слова уже отобраны, озвучены и размечены по уровню'**
  String get homeFirstDayReadyHint;

  /// Вторая карточка первого дня (кадр 17c).
  ///
  /// In ru, this message translates to:
  /// **'Собрать свою по описанию'**
  String get homeFirstDayOwnTitle;

  /// Подпись карточки генерации (кадр 17c).
  ///
  /// In ru, this message translates to:
  /// **'Опишите ситуацию — ИИ подберёт слова и фразы под неё'**
  String get homeFirstDayOwnHint;

  /// Предложение свайп-прохода, когда день закрыт (кадры 17b/17d).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Можно разобрать ещё {count} слово из «{title}»} few{Можно разобрать ещё {count} слова из «{title}»} many{Можно разобрать ещё {count} слов из «{title}»} other{Можно разобрать ещё {count} слова из «{title}»}}'**
  String homeSortOffer(int count, String title);

  /// Кнопка свайп-прохода в состоянии Б (кадр 17d).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Разобрать {count} слово} few{Разобрать {count} слова} many{Разобрать {count} слов} other{Разобрать {count} слова}}'**
  String homeTriageAction(int count);

  /// Заголовок вместо «Всё повторено», когда в работе ещё ничего нет.
  ///
  /// In ru, this message translates to:
  /// **'Пора разобрать слова'**
  String get homeSortFirstTitle;

  /// Онбординг, шаг 1 (ONB-1) — родной язык. Спрашивается один раз за всё время.
  ///
  /// In ru, this message translates to:
  /// **'На каком языке показывать переводы?'**
  String get onbNativeTitle;

  /// Онбординг, шаг 1 — что покупает ответ.
  ///
  /// In ru, this message translates to:
  /// **'На нём будут переводы, объяснения и планы подготовки. Можно поменять в профиле.'**
  String get onbNativeSubtitle;

  /// Строка профиля: родной язык (язык переводов).
  ///
  /// In ru, this message translates to:
  /// **'Родной язык'**
  String get profileRowNativeLang;

  /// Подпись под строкой родного языка: смена языка не переписывает уже собранный материал.
  ///
  /// In ru, this message translates to:
  /// **'Существующие коллекции останутся как есть'**
  String get profileNativeLangHint;

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

  /// Ссылка «изменить» в свёрнутой строке шага (кадр Б-02).
  ///
  /// In ru, this message translates to:
  /// **'Изм.'**
  String get planStepEdit;

  /// Уровень плана `zero`.
  ///
  /// In ru, this message translates to:
  /// **'С нуля'**
  String get planLevelZero;

  /// Уровень плана `basic`.
  ///
  /// In ru, this message translates to:
  /// **'Понимаю простое'**
  String get planLevelBasic;

  /// Уровень плана `conversational`.
  ///
  /// In ru, this message translates to:
  /// **'Объясняюсь'**
  String get planLevelConversational;

  /// Уровень плана `fluent`.
  ///
  /// In ru, this message translates to:
  /// **'Свободно'**
  String get planLevelFluent;

  /// Заголовок шита выбора даты.
  ///
  /// In ru, this message translates to:
  /// **'Когда это случится?'**
  String get planWhenSheetTitle;

  /// Пульсирующая строка под кнопкой, пока модель пишет каркас или день. Заменяет ориентир/плейсхолдер цены на время ожидания.
  ///
  /// In ru, this message translates to:
  /// **'Разбираю цель — обычно 15–30 секунд'**
  String get planBuilderBusyLine;

  /// Ошибка сети на экранах плана.
  ///
  /// In ru, this message translates to:
  /// **'Нет соединения. План собирается на сервере — попробуй, когда появится сеть.'**
  String get planErrorOffline;

  /// Ошибка сборки каркаса плана.
  ///
  /// In ru, this message translates to:
  /// **'Не получилось собрать план. Попробуй ещё раз.'**
  String get planErrorBuildFailed;

  /// Ошибка старта плана.
  ///
  /// In ru, this message translates to:
  /// **'Не получилось начать план. Попробуй ещё раз.'**
  String get planErrorStartFailed;

  /// Ошибка чтения плана.
  ///
  /// In ru, this message translates to:
  /// **'Не удалось загрузить план.'**
  String get planErrorLoadFailed;

  /// Латунная метка дня.
  ///
  /// In ru, this message translates to:
  /// **'День {index}'**
  String planDayNumber(int index);

  /// Длительность плана в архиве.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} день} few{{count} дня} many{{count} дней} other{{count} дня}}'**
  String planDaysCount(int count);

  /// Тихая ссылка на превью.
  ///
  /// In ru, this message translates to:
  /// **'Убрать день'**
  String get planPreviewDropDay;

  /// Заглушка под кнопкой «Начать». Paywall в этом наряде не трогается.
  ///
  /// In ru, this message translates to:
  /// **'[цена / условия — placeholder]'**
  String get planPricePlaceholder;

  /// Заголовок шита выбора дня.
  ///
  /// In ru, this message translates to:
  /// **'Какой день убрать?'**
  String get planDropDaySheet;

  /// Отказ, когда в плане остался один день знакомства.
  ///
  /// In ru, this message translates to:
  /// **'Последний день подготовки убрать нельзя.'**
  String get planDropLastDay;

  /// Заголовок карточки «срок мал под цель» (кадр Б-05).
  ///
  /// In ru, this message translates to:
  /// **'{days, plural, one{За {days} день по {minutes} минут закроем половину: вот эти умения.} few{За {days} дня по {minutes} минут закроем половину: вот эти умения.} many{За {days} дней по {minutes} минут закроем половину: вот эти умения.} other{За {days} дня по {minutes} минут закроем половину: вот эти умения.}}'**
  String planTightTitle(int days, int minutes);

  /// Рекомендованное действие на карточке «срок мал».
  ///
  /// In ru, this message translates to:
  /// **'Добавить {minutes} минут в день'**
  String planTightAddMinutes(int minutes);

  /// Отклонить рекомендацию «срок мал».
  ///
  /// In ru, this message translates to:
  /// **'Оставить'**
  String get planTightKeep;

  /// Заголовок экрана сборки дня (кадр Б-06).
  ///
  /// In ru, this message translates to:
  /// **'Собираю день {index}'**
  String planBuildingTitle(int index);

  /// Пояснение на экране сборки.
  ///
  /// In ru, this message translates to:
  /// **'Подбираю реплики события и слова, которые в них подставляются.'**
  String get planBuildingBody;

  /// Шаг статуса сборки.
  ///
  /// In ru, this message translates to:
  /// **'Цель разобрана'**
  String get planBuildingStep1;

  /// Шаг статуса сборки.
  ///
  /// In ru, this message translates to:
  /// **'Реплики подобраны'**
  String get planBuildingStep2;

  /// Шаг статуса сборки.
  ///
  /// In ru, this message translates to:
  /// **'Слова и примеры'**
  String get planBuildingStep3;

  /// Сборка не дошла до конца.
  ///
  /// In ru, this message translates to:
  /// **'День собирается дольше обычного. План уже создан — его можно открыть и вернуться к дню позже.'**
  String get planBuildingFailed;

  /// Выход из зависшей сборки.
  ///
  /// In ru, this message translates to:
  /// **'Открыть план'**
  String get planBuildingOpenAnyway;

  /// Служебная строка под шкалой готовности — на каком дне юзер.
  ///
  /// In ru, this message translates to:
  /// **'День {index} из {total}'**
  String planDayOfTotal(int index, int total);

  /// Лейбл блока умений плана.
  ///
  /// In ru, this message translates to:
  /// **'Ты уже можешь · {hit} из {total}'**
  String planCanAlready(int hit, int total);

  /// Лейбл блока дней плана.
  ///
  /// In ru, this message translates to:
  /// **'Дни · {index} из {total}'**
  String planDaysHeader(int index, int total);

  /// Латунная метка в шапке экрана дня.
  ///
  /// In ru, this message translates to:
  /// **'День {index} из {total}'**
  String planDayOfPlan(int index, int total);

  /// Подпись под «На всякий случай» (DAY-GATE-1, доработка Ч.2.7): прежнее имя «Спасатели» объясняло себя само, новое — нет.
  ///
  /// In ru, this message translates to:
  /// **'если не понял или не расслышал'**
  String get planRescueHint;

  /// Подтверждение перед отказом от плана — с экрана плана и с несобираемого дня. Одна формулировка на оба входа: последствие одно.
  ///
  /// In ru, this message translates to:
  /// **'Отказаться от этого плана?'**
  String get planAbandonTitle;

  /// Что произойдёт при отказе от плана.
  ///
  /// In ru, this message translates to:
  /// **'План уйдёт в архив, а его слова — в общее повторение. Собранные дни останутся обычными коллекциями.'**
  String get planAbandonBody;

  /// Кнопка подтверждения отказа.
  ///
  /// In ru, this message translates to:
  /// **'Отказаться'**
  String get planAbandonConfirm;

  /// Сервер ОТКАЗАЛ, а не задержался. Причину НЕ выдумываем: этот экран видит только код ответа, а честную причину по fail_code называет экран дня (Д-19).
  ///
  /// In ru, this message translates to:
  /// **'Сервер отказался собирать этот день. Ждать дальше нечего — открой план: на экране дня написано, что именно случилось.'**
  String get planBuildingRefused;

  /// Действие сегодняшнего дня, в его же строке (кадр D-07).
  ///
  /// In ru, this message translates to:
  /// **'Начать день'**
  String get planRowStartDay;

  /// Зрелость плана словами — первое состояние (канон §2: познакомился → применяю → говорю сам). Заменило «ступень A · знакомство с материалом».
  ///
  /// In ru, this message translates to:
  /// **'Знакомишься со словами и фразами'**
  String get planMaturityMeeting;

  /// Второе состояние зрелости плана.
  ///
  /// In ru, this message translates to:
  /// **'Применяешь в разговоре'**
  String get planMaturityApplying;

  /// Третье состояние зрелости (канон §2). Наступает по переписи ступени C: каждый ход сцены прозвучал голосом в прогоне (SCENE-RUN, Ч.3).
  ///
  /// In ru, this message translates to:
  /// **'Говоришь сам'**
  String get planMaturitySpeaking;

  /// Пустой таб «План» (кадр 1c-10).
  ///
  /// In ru, this message translates to:
  /// **'Подготовиться к чему-то конкретному'**
  String get planEmptyTitle;

  /// Разница коллекции и плана — тремя строками, без обещаний.
  ///
  /// In ru, this message translates to:
  /// **'Коллекции — про темы, которые хочется знать. План — про день, когда придётся говорить: приём, собеседование, подпись договора.'**
  String get planEmptyBody;

  /// Пустой таб «План», строка 01.
  ///
  /// In ru, this message translates to:
  /// **'Говоришь цель и дату'**
  String get planEmptyStep1;

  /// Пустой таб «План», строка 02.
  ///
  /// In ru, this message translates to:
  /// **'Каждый день — фразы, которые реально скажешь, и слова из них'**
  String get planEmptyStep2;

  /// Пустой таб «План», строка 03.
  ///
  /// In ru, this message translates to:
  /// **'В конце — разговор в роли и вся ситуация вслух'**
  String get planEmptyStep3;

  /// Кнопка пустого таба.
  ///
  /// In ru, this message translates to:
  /// **'Составить план'**
  String get planEmptyCta;

  /// Латунная метка завершённого плана (кадр 1c-11).
  ///
  /// In ru, this message translates to:
  /// **'Подготовка завершена'**
  String get planFinishedBadge;

  /// Итог завершённого плана.
  ///
  /// In ru, this message translates to:
  /// **'{days, plural, one{За {days} день подготовки. Событие было {date}.} few{За {days} дня подготовки. Событие было {date}.} many{За {days} дней подготовки. Событие было {date}.} other{За {days} дня подготовки. Событие было {date}.}}'**
  String planFinishedSummary(int days, String date);

  /// Карточка о судьбе слов после плана. Правда: архив, а не общее повторение (Д-30).
  ///
  /// In ru, this message translates to:
  /// **'Слова плана остались в архиве'**
  String get planWordsReleasedTitle;

  /// Пояснение к судьбе слов: завершённый план — архив, слова в ежедневные занятия сами не приходят.
  ///
  /// In ru, this message translates to:
  /// **'Они никуда не делись — прогресс, расписание и вся история на месте. Сами в ежедневные занятия они не придут: чтобы вернуть слово в работу, открой его карточку и нажми «Учить это слово».'**
  String get planWordsReleasedBody;

  /// Лейбл списка прошлых планов.
  ///
  /// In ru, this message translates to:
  /// **'Архив'**
  String get planArchive;

  /// Кнопка внизу завершённого плана — под результатом, а не поверх него.
  ///
  /// In ru, this message translates to:
  /// **'Составить новый'**
  String get planFinishedNewPlan;

  /// Латунная метка карточки плана на главной (кадр 08).
  ///
  /// In ru, this message translates to:
  /// **'План · день {index} из {total}'**
  String homePlanCardBadge(int index, int total);

  /// Правая метка карточки плана на главной.
  ///
  /// In ru, this message translates to:
  /// **'{days, plural, one{Событие через {days} день} few{Событие через {days} дня} many{Событие через {days} дней} other{Событие через {days} дня}}'**
  String homePlanCardEventIn(int days);

  /// Правая метка карточки плана на главной.
  ///
  /// In ru, this message translates to:
  /// **'Событие сегодня'**
  String get homePlanCardEventToday;

  /// Подпись рядом с процентом на карточке плана.
  ///
  /// In ru, this message translates to:
  /// **'готовности к событию · {hit} из {total}'**
  String homePlanCardReadiness(int hit, int total);

  /// Кнопка карточки плана на главной.
  ///
  /// In ru, this message translates to:
  /// **'Продолжить'**
  String get homePlanCardContinue;

  /// Приглашение на месте карточки плана (кадр 09).
  ///
  /// In ru, this message translates to:
  /// **'Есть дата и цель?'**
  String get homePlanInviteTitle;

  /// Пояснение приглашения составить план.
  ///
  /// In ru, this message translates to:
  /// **'Соберём дни подготовки — от приёма у врача до собеседования.'**
  String get homePlanInviteBody;

  /// Кнопка приглашения составить план.
  ///
  /// In ru, this message translates to:
  /// **'Составить'**
  String get homePlanInviteCta;

  /// Имя канала уведомлений (видно в системных настройках Android).
  ///
  /// In ru, this message translates to:
  /// **'План подготовки'**
  String get planNotifyChannelName;

  /// Описание канала уведомлений.
  ///
  /// In ru, this message translates to:
  /// **'Напоминания перед событием, к которому идёт подготовка'**
  String get planNotifyChannelBody;

  /// Уведомление накануне события (кадр 12). Причина — дата события, не серия дней.
  ///
  /// In ru, this message translates to:
  /// **'{days, plural, one{До события {days} день. День {index} ждёт} few{До события {days} дня. День {index} ждёт} many{До события {days} дней. День {index} ждёт} other{До события {days} дня. День {index} ждёт}}'**
  String planNotifyBeforeTitle(int days, int index);

  /// Тело уведомления накануне: готовность и чему учит сегодняшний день.
  ///
  /// In ru, this message translates to:
  /// **'Готовность {percent}%. Сегодня — {title}.'**
  String planNotifyBeforeBody(int percent, String title);

  /// Уведомление утром дня события (кадр 13) — ведёт в быструю репетицию.
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Сегодня событие. {count} фраза за 3 минуты} few{Сегодня событие. {count} фразы за 3 минуты} many{Сегодня событие. {count} фраз за 3 минуты} other{Сегодня событие. {count} фразы за 3 минуты}}'**
  String planNotifyMorningTitle(int count);

  /// Тело утреннего уведомления.
  ///
  /// In ru, this message translates to:
  /// **'Быстрая репетиция перед выходом — только то, что скажешь.'**
  String get planNotifyMorningBody;

  /// Уведомление после события (кадр 14).
  ///
  /// In ru, this message translates to:
  /// **'Как прошло? Отметь, что сказал'**
  String get planNotifyEveningTitle;

  /// Тело вечернего уведомления.
  ///
  /// In ru, this message translates to:
  /// **'Отметь умения, которые пригодились — план закроется этим.'**
  String get planNotifyEveningBody;

  /// Итог завершённого плана по отметкам после события (кадр 11).
  ///
  /// In ru, this message translates to:
  /// **'На событии сказал {used} из {total}.'**
  String planFinishedAtEvent(int used, int total);

  /// План без даты события — везде, где обычно стоит дата (кадры V4·04б, 06б).
  ///
  /// In ru, this message translates to:
  /// **'Без даты'**
  String get planNoDate;

  /// Надзаголовок шагов входа (кадры V4·01…04б).
  ///
  /// In ru, this message translates to:
  /// **'План подготовки'**
  String get planEntryKicker;

  /// Вопрос шага 1 (кадр V4·01).
  ///
  /// In ru, this message translates to:
  /// **'К чему готовишься?'**
  String get planEntryGoalTitle;

  /// Подзаголовок на пустом поле (кадр V4·01).
  ///
  /// In ru, this message translates to:
  /// **'Расскажи своими словами: где будешь, с кем, что нужно сказать и понять. Подробности — это те самые фразы, которые пригодятся.'**
  String get planEntryGoalSubtitleLong;

  /// Короткий подзаголовок, когда в поле уже что-то есть (кадр V4·01б).
  ///
  /// In ru, this message translates to:
  /// **'Расскажи своими словами: где будешь, с кем, что нужно сказать и понять.'**
  String get planEntryGoalSubtitle;

  /// Подсказка в поле — написана как чужой ответ, а не как инструкция.
  ///
  /// In ru, this message translates to:
  /// **'Например: иду к врачу с ребёнком, надо объяснить симптомы и понять назначение'**
  String get planEntryGoalPlaceholder;

  /// Счётчик строк, пока цели не хватает.
  ///
  /// In ru, this message translates to:
  /// **'можно 4–6 строк'**
  String get planEntryLinesHint;

  /// Латунная замена счётчика, когда цели достаточно (кадр V4·01б).
  ///
  /// In ru, this message translates to:
  /// **'хватит для плана'**
  String get planEntryEnough;

  /// Счётчик на длинной цели (кадр V4·01г).
  ///
  /// In ru, this message translates to:
  /// **'подробно — это хорошо'**
  String get planEntryDetailed;

  /// Подсказка под короткой целью — без красного и без блокировки (кадр V4·01в).
  ///
  /// In ru, this message translates to:
  /// **'Пары слов мало. Добавь: к какому врачу, с кем идёшь, что нужно понять.'**
  String get planEntryTooShort;

  /// Надзаголовок примеров на пустом поле.
  ///
  /// In ru, this message translates to:
  /// **'Так тоже подходит'**
  String get planEntryExamplesTitle;

  /// Надзаголовок дополнений, когда цели уже хватает.
  ///
  /// In ru, this message translates to:
  /// **'Можно добавить'**
  String get planEntryAdditionsTitle;

  /// Надзаголовок готовых продолжений под короткой целью.
  ///
  /// In ru, this message translates to:
  /// **'Дописать за тебя'**
  String get planEntryFinishTitle;

  /// Пример цели 1 (кадр V4·01).
  ///
  /// In ru, this message translates to:
  /// **'Иду к врачу, болит спина, надо объяснить и понять назначение'**
  String get planEntryExample1;

  /// Пример цели 2.
  ///
  /// In ru, this message translates to:
  /// **'Онлайн-собеседование PHP-разработчика, удалённо, английская команда'**
  String get planEntryExample2;

  /// Пример цели 3.
  ///
  /// In ru, this message translates to:
  /// **'Летим в отпуск с ребёнком, аэропорт, отель, аптека'**
  String get planEntryExample3;

  /// Кнопка перехода к следующему шагу входа.
  ///
  /// In ru, this message translates to:
  /// **'Дальше'**
  String get planEntryNext;

  /// Подпись кнопки диктовки внутри поля цели (для скринридера).
  ///
  /// In ru, this message translates to:
  /// **'Продиктовать'**
  String get planEntryDictate;

  /// Вопрос шага 2 (кадр V4·02).
  ///
  /// In ru, this message translates to:
  /// **'Какой язык учишь?'**
  String get planEntryLangTitle;

  /// Родной язык не спрашиваем — только сообщаем (кадр V4·02).
  ///
  /// In ru, this message translates to:
  /// **'Переводы на {language}'**
  String planEntryTranslationsInto(String language);

  /// Ссылка рядом со строкой о языке переводов.
  ///
  /// In ru, this message translates to:
  /// **'изменить в настройках'**
  String get planEntrySettingsLink;

  /// Второй вопрос шага 2 (кадр V4·02).
  ///
  /// In ru, this message translates to:
  /// **'Как сейчас говоришь?'**
  String get planEntryLevelTitle;

  /// Человеческое описание уровня `zero`.
  ///
  /// In ru, this message translates to:
  /// **'Знаю отдельные слова, фразу не соберу'**
  String get planLevelZeroHint;

  /// Человеческое описание уровня `basic`.
  ///
  /// In ru, this message translates to:
  /// **'Читаю переписку, но говорю с паузами'**
  String get planLevelBasicHint;

  /// Человеческое описание уровня `conversational`.
  ///
  /// In ru, this message translates to:
  /// **'Договорюсь о бытовом, сложное — подбираю слова'**
  String get planLevelConversationalHint;

  /// Человеческое описание уровня `fluent`.
  ///
  /// In ru, this message translates to:
  /// **'Говорю без подготовки, шлифую точность'**
  String get planLevelFluentHint;

  /// Надзаголовок шага слуха (кадр V4·03).
  ///
  /// In ru, this message translates to:
  /// **'Необязательный шаг'**
  String get planListenKicker;

  /// Заголовок предложения послушать.
  ///
  /// In ru, this message translates to:
  /// **'Хочешь, настрою точнее?'**
  String get planListenOfferTitle;

  /// Текст предложения послушать.
  ///
  /// In ru, this message translates to:
  /// **'Послушай три реплики из твоей ситуации — как они прозвучат на самом деле. Минута.'**
  String get planListenOfferBody;

  /// Кнопка согласия на шаг слуха.
  ///
  /// In ru, this message translates to:
  /// **'Послушать'**
  String get planListenListen;

  /// Кнопка пропуска шага слуха — того же веса, что и «Послушать».
  ///
  /// In ru, this message translates to:
  /// **'Пропустить'**
  String get planListenSkip;

  /// Подпись под кнопками предложения.
  ///
  /// In ru, this message translates to:
  /// **'Это не тест. Ответы никто не увидит, план соберётся и без этого шага.'**
  String get planListenReassure;

  /// Выход из шага слуха в любой момент (шапка плеера).
  ///
  /// In ru, this message translates to:
  /// **'Хватит'**
  String get planListenEnough;

  /// Надзаголовок плеера, когда модель не назвала место.
  ///
  /// In ru, this message translates to:
  /// **'Реплика {index}'**
  String planListenLine(int index);

  /// Надзаголовок плеера с местом от модели (кадр V4·03б).
  ///
  /// In ru, this message translates to:
  /// **'Реплика {index} · {place}'**
  String planListenLineAt(int index, String place);

  /// Подпись под волной в плеере.
  ///
  /// In ru, this message translates to:
  /// **'Слушай столько раз, сколько нужно.'**
  String get planListenReplayHint;

  /// Ссылка, открывающая текст реплики и перевод.
  ///
  /// In ru, this message translates to:
  /// **'Показать текст'**
  String get planListenShowText;

  /// Надзаголовок над двумя самооценками.
  ///
  /// In ru, this message translates to:
  /// **'Как ощущается'**
  String get planListenFeelLabel;

  /// Самооценка «понял» — не правильный ответ, просто ответ.
  ///
  /// In ru, this message translates to:
  /// **'Понял'**
  String get planListenGot;

  /// Самооценка «не совсем».
  ///
  /// In ru, this message translates to:
  /// **'Не совсем'**
  String get planListenNotQuite;

  /// Подпись под самооценками.
  ///
  /// In ru, this message translates to:
  /// **'Правильного ответа нет — это про то, что подобрать в план.'**
  String get planListenNoRightAnswer;

  /// Итог слуха, если хоть одна реплика далась не полностью (кадр V4·03в).
  ///
  /// In ru, this message translates to:
  /// **'Понял: сделаю упор на понимание на слух'**
  String get planListenResultUnderstanding;

  /// Подпись под итогом «упор на понимание».
  ///
  /// In ru, this message translates to:
  /// **'Речь идёт быстрее, чем удобно. В плане будет больше прослушивания и меньше зубрёжки слов.'**
  String get planListenResultUnderstandingBody;

  /// Итог слуха, если все три реплики поняты (кадр V4·03г).
  ///
  /// In ru, this message translates to:
  /// **'Понимаешь на слух уверенно: сделаю упор на говорение'**
  String get planListenResultSpeaking;

  /// Подпись под итогом «упор на говорение».
  ///
  /// In ru, this message translates to:
  /// **'Реплики тебе даются — в плане будет больше твоих ответов вслух и меньше зубрёжки слов.'**
  String get planListenResultSpeakingBody;

  /// Строка в рамке под итогом слуха.
  ///
  /// In ru, this message translates to:
  /// **'Настройку можно поменять в плане в любой день.'**
  String get planListenResultFootnote;

  /// Строка ленты о пройденном шаге слуха (кадр V4·04).
  ///
  /// In ru, this message translates to:
  /// **'Слух · упор на понимание'**
  String get planEntryRibbonListenUnderstanding;

  /// Строка ленты о пройденном шаге слуха.
  ///
  /// In ru, this message translates to:
  /// **'Слух · упор на говорение'**
  String get planEntryRibbonListenSpeaking;

  /// Строка ленты о пропущенном шаге слуха (кадр V4·04б).
  ///
  /// In ru, this message translates to:
  /// **'Слух · шаг пропущен'**
  String get planEntryRibbonListenSkipped;

  /// Приглашение пройти пропущенный шаг — без укора.
  ///
  /// In ru, this message translates to:
  /// **'Пройти'**
  String get planEntryPass;

  /// Строка ленты о языке и уровне.
  ///
  /// In ru, this message translates to:
  /// **'{language} · {level}'**
  String planEntryRibbonLangLevel(String language, String level);

  /// Вопрос шага даты (кадр V4·04).
  ///
  /// In ru, this message translates to:
  /// **'Когда это случится?'**
  String get planEntryWhenTitle;

  /// Кнопка выбора даты, когда даты ещё нет (кадр V4·04б).
  ///
  /// In ru, this message translates to:
  /// **'Выбрать дату'**
  String get planEntryPickDate;

  /// Строка-ориентир под датой.
  ///
  /// In ru, this message translates to:
  /// **'{days, plural, one{Через {days} день · сервер разложит подготовку по этим дням} few{Через {days} дня · сервер разложит подготовку по этим дням} many{Через {days} дней · сервер разложит подготовку по этим дням} other{Через {days} дня · сервер разложит подготовку по этим дням}}'**
  String planEntryHintDays(int days);

  /// Строка-ориентир, когда событие сегодня.
  ///
  /// In ru, this message translates to:
  /// **'Сегодня · вся подготовка уместится в один подход'**
  String get planEntryHintToday;

  /// Честная строка-ориентир без даты (решение владельца 03.09).
  ///
  /// In ru, this message translates to:
  /// **'Даты нет · идём в своём темпе, по одной сцене за подход'**
  String get planEntryHintNoDate;

  /// Второй вопрос шага даты.
  ///
  /// In ru, this message translates to:
  /// **'Сколько минут в день?'**
  String get planEntryMinutesTitle;

  /// Подпись под числом минут на карточке.
  ///
  /// In ru, this message translates to:
  /// **'минут'**
  String get planEntryMinutesUnit;

  /// Кнопка запуска сборки (кадры V4·04, 04б).
  ///
  /// In ru, this message translates to:
  /// **'Собрать план'**
  String get planEntryBuild;

  /// Надзаголовок экрана сборки (кадр V4·05).
  ///
  /// In ru, this message translates to:
  /// **'Собираю твой план'**
  String get planBuildKicker;

  /// Шаг сборки 1.
  ///
  /// In ru, this message translates to:
  /// **'Разбираю цель'**
  String get planBuildStep1;

  /// Шаг сборки 2.
  ///
  /// In ru, this message translates to:
  /// **'Подбираю реплики'**
  String get planBuildStep2;

  /// Шаг сборки 3.
  ///
  /// In ru, this message translates to:
  /// **'Собираю слова'**
  String get planBuildStep3;

  /// Строка под шагами сборки.
  ///
  /// In ru, this message translates to:
  /// **'Реплики берём из живой речи, не из учебника. Это занимает несколько секунд.'**
  String get planBuildFootnote;

  /// Надзаголовок первой неудачи (кадр V4·05в).
  ///
  /// In ru, this message translates to:
  /// **'Сборка идёт'**
  String get planBuildRetryKicker;

  /// Единственная изменившаяся строка при первой неудаче. Причин не называем.
  ///
  /// In ru, this message translates to:
  /// **'Не получилось собрать с первого раза — пробую ещё.'**
  String get planBuildRetryBody;

  /// Строка под шагами при первой неудаче.
  ///
  /// In ru, this message translates to:
  /// **'Ответы на месте. Вторая попытка идёт с того же шага.'**
  String get planBuildRetryFootnote;

  /// Надзаголовок при потере сети (кадр V4·05б).
  ///
  /// In ru, this message translates to:
  /// **'Сборка приостановлена'**
  String get planBuildOfflineKicker;

  /// Заголовок при потере сети.
  ///
  /// In ru, this message translates to:
  /// **'Пропала связь на середине'**
  String get planBuildOfflineTitle;

  /// Текст при потере сети.
  ///
  /// In ru, this message translates to:
  /// **'Ответы сохранены — ничего вводить заново не придётся. Продолжим, как только сеть вернётся.'**
  String get planBuildOfflineBody;

  /// Кнопка повтора сборки при потере сети.
  ///
  /// In ru, this message translates to:
  /// **'Попробовать снова'**
  String get planBuildRetryButton;

  /// Вторая кнопка кадра V4·05б. Механики уведомления нет — кнопка честно неактивна.
  ///
  /// In ru, this message translates to:
  /// **'Сообщить, когда будет готов'**
  String get planBuildNotifyButton;

  /// Подпись под неактивной кнопкой уведомления — вместо выдуманной механики.
  ///
  /// In ru, this message translates to:
  /// **'Уведомления ещё не подключены'**
  String get planBuildNotifyUnavailable;

  /// Надзаголовок второй неудачи (кадр V4·05г).
  ///
  /// In ru, this message translates to:
  /// **'Не собралось'**
  String get planBuildFailedKicker;

  /// Заголовок второй неудачи.
  ///
  /// In ru, this message translates to:
  /// **'Не собралось. Твои ответы сохранены'**
  String get planBuildFailedTitle;

  /// Текст второй неудачи.
  ///
  /// In ru, this message translates to:
  /// **'Ничего вводить заново не придётся. Можно вернуться к ответам и запустить сборку снова — или написать нам.'**
  String get planBuildFailedBody;

  /// Главная кнопка второй неудачи.
  ///
  /// In ru, this message translates to:
  /// **'Вернуться к ответам'**
  String get planBuildBackToAnswers;

  /// Вторая кнопка второй неудачи — открывает почту.
  ///
  /// In ru, this message translates to:
  /// **'Написать нам'**
  String get planBuildWriteUs;

  /// Тема письма в поддержку.
  ///
  /// In ru, this message translates to:
  /// **'Не собрался план'**
  String get planBuildMailSubject;

  /// Надзаголовок превью (кадры V4·06, 06б).
  ///
  /// In ru, this message translates to:
  /// **'Твой план готов'**
  String get planPreviewKicker;

  /// Подзаголовок превью — ТЕМЫ, а не пересказ цели: число сцен и названия первых трёх с маленькой буквы. Цели в нём нет вовсе: заголовок экрана и есть цель, и повторять её строкой ниже — говорить одно и то же дважды (живой прогон 03.09). `goal_summary` от P1 остаётся в данных для модели и админки и на экран не едет.
  ///
  /// In ru, this message translates to:
  /// **'По твоим словам — {scenes, plural, one{{scenes} сцена} few{{scenes} сцены} many{{scenes} сцен} other{{scenes} сцены}}: {topics}.'**
  String planPreviewSubtitle(int scenes, String topics);

  /// То же без даты (кадр V4·06б).
  ///
  /// In ru, this message translates to:
  /// **'По твоим словам — {scenes, plural, one{{scenes} сцена} few{{scenes} сцены} many{{scenes} сцен} other{{scenes} сцены}}: {topics}. Даты нет, идём в своём темпе.'**
  String planPreviewSubtitleNoDate(int scenes, String topics);

  /// Моноширинная строка-ориентир превью с датой.
  ///
  /// In ru, this message translates to:
  /// **'{days, plural, one{{days} ДЕНЬ ПОДГОТОВКИ} few{{days} ДНЯ ПОДГОТОВКИ} many{{days} ДНЕЙ ПОДГОТОВКИ} other{{days} ДНЯ ПОДГОТОВКИ}} · {minutes} МИНУТ В ДЕНЬ'**
  String planPreviewOrientation(int days, int minutes);

  /// Моноширинная строка-ориентир превью без даты.
  ///
  /// In ru, this message translates to:
  /// **'{scenes, plural, one{{scenes} СЦЕНА} few{{scenes} СЦЕНЫ} many{{scenes} СЦЕН} other{{scenes} СЦЕНЫ}} · {minutes} МИНУТ В ДЕНЬ · ПО ОДНОЙ ЗА ПОДХОД'**
  String planPreviewOrientationNoDate(int scenes, int minutes);

  /// Надзаголовок лестницы сцен, когда дата есть.
  ///
  /// In ru, this message translates to:
  /// **'Твои дни'**
  String get planPreviewDaysTitle;

  /// Надзаголовок лестницы сцен без даты.
  ///
  /// In ru, this message translates to:
  /// **'Твои сцены'**
  String get planPreviewScenesTitle;

  /// Метка карточки сцены, когда дата есть.
  ///
  /// In ru, this message translates to:
  /// **'ДЕНЬ {index}'**
  String planPreviewDayLabel(int index);

  /// Метка карточки сцены без даты.
  ///
  /// In ru, this message translates to:
  /// **'СЦЕНА {index}'**
  String planPreviewSceneLabel(int index);

  /// Плашка спасательного набора в превью.
  ///
  /// In ru, this message translates to:
  /// **'Пять фраз на всякий случай — с первого дня, если не понял или не расслышал.'**
  String get planPreviewRescueBody;

  /// Живая фраза набора курсивом — обещание должно быть осязаемым.
  ///
  /// In ru, this message translates to:
  /// **'«Помедленнее, пожалуйста» — и ещё четыре таких, с первого дня.'**
  String get planPreviewRescueQuote;

  /// Метка прогона, когда дата есть.
  ///
  /// In ru, this message translates to:
  /// **'НАКАНУНЕ'**
  String get planPreviewRehearsalEveLabel;

  /// Метка прогона без даты.
  ///
  /// In ru, this message translates to:
  /// **'В КОНЦЕ'**
  String get planPreviewRehearsalEndLabel;

  /// Название пунктирной карточки прогона.
  ///
  /// In ru, this message translates to:
  /// **'Скажи сам перед событием'**
  String get planPreviewRehearsalTitle;

  /// Текст прогона, когда дата есть.
  ///
  /// In ru, this message translates to:
  /// **'{scenes, plural, one{Одна сцена} few{Все {scenes} сцены} many{Все {scenes} сцен} other{Все {scenes} сцены}} подряд, вслух, за один присест.'**
  String planPreviewRehearsalEveBody(int scenes);

  /// Текст прогона без даты.
  ///
  /// In ru, this message translates to:
  /// **'Откроется, когда пройдёшь все сцены. Дату можно поставить в любой день.'**
  String get planPreviewRehearsalEndBody;

  /// Главная кнопка превью с датой.
  ///
  /// In ru, this message translates to:
  /// **'Начать первый день'**
  String get planPreviewStartDay;

  /// Главная кнопка превью без даты.
  ///
  /// In ru, this message translates to:
  /// **'Начать первую сцену'**
  String get planPreviewStartScene;

  /// Вторая кнопка превью с датой.
  ///
  /// In ru, this message translates to:
  /// **'Изменить ответы'**
  String get planPreviewEditAnswers;

  /// Вторая кнопка превью без даты — единственное, чего плану не хватает.
  ///
  /// In ru, this message translates to:
  /// **'Поставить дату'**
  String get planPreviewSetDate;

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
  /// **'≈ {price} за план'**
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

  /// Дев-бейдж сломанной трубы озвучки (наряд TTS-1). Показывается только при DEV_MENU: тихий фолбэк на системный голос при живом audio_url — дефект, и в дев-сборке он должен быть виден.
  ///
  /// In ru, this message translates to:
  /// **'Озвучка: {silent} реплик системным голосом, {failed} не скачалось'**
  String devVoiceTrouble(int silent, int failed);

  /// Слово состояния дня (DAY-FIX-2, Ч.3): серверное day_state = not_started. Вкладка «План», экран дня, шапка присеста, карточка на главной.
  ///
  /// In ru, this message translates to:
  /// **'не начат'**
  String get planStateNotStarted;

  /// Слово состояния дня: day_state = in_progress. Рядом — минуты (planStateMinutes).
  ///
  /// In ru, this message translates to:
  /// **'идёт'**
  String get planStateInProgress;

  /// Слово состояния дня: day_state = done. Единственное слово о пройденном дне на всех экранах плана.
  ///
  /// In ru, this message translates to:
  /// **'пройден'**
  String get planStateDone;

  /// Слово состояния дня (DAY-FIX-3, Ч.4.3): day_state = material_done — присест «Материал» пройден, впереди «Разговор». Рядом — planStateConversationAbout.
  ///
  /// In ru, this message translates to:
  /// **'слова и фразы пройдены'**
  String get planStateMaterialDone;

  /// Минуты присеста «Материал» (DAY-FIX-3, Ч.5.1): серверное material_minutes. Экран дня, рядом с planStateConversationAbout.
  ///
  /// In ru, this message translates to:
  /// **'{minutes, plural, one{слова и фразы около {minutes} минуты} few{слова и фразы около {minutes} минут} many{слова и фразы около {minutes} минут} other{слова и фразы около {minutes} минуты}}'**
  String planStateMaterialAbout(int minutes);

  /// Минуты присеста «Разговор» (DAY-FIX-3, Ч.4.3 / Ч.5.1): серверное conversation_minutes. Вкладка «План» и экран дня при «материал пройден»; экран дня рядом с planStateMaterialAbout.
  ///
  /// In ru, this message translates to:
  /// **'{minutes, plural, one{разговор около {minutes} минуты} few{разговор около {minutes} минут} many{разговор около {minutes} минут} other{разговор около {minutes} минуты}}'**
  String planStateConversationAbout(int minutes);

  /// Кнопка дня в состоянии material_done (DAY-FIX-3, Ч.4.3): вкладка «План», экран дня, итог материала между присестами.
  ///
  /// In ru, this message translates to:
  /// **'К разговору'**
  String get planSittingToConversation;

  /// Минуты дня (DAY-FIX-2, Ч.3): серверное minutes_left, карточки × 16 с, округлённые до минуты. Единственная цифра на экранах плана.
  ///
  /// In ru, this message translates to:
  /// **'{minutes, plural, one{около {minutes} минуты} few{около {minutes} минут} many{около {minutes} минут} other{около {minutes} минуты}}'**
  String planStateMinutes(int minutes);

  /// Кнопка пройденного дня (DAY-FIX-2, Ч.4.1): вкладка «План» и экран дня. Говорит то же, что слово «пройден».
  ///
  /// In ru, this message translates to:
  /// **'Пройти ещё раз'**
  String get planDayRepeat;

  /// Карточка плана на главной (DAY-FIX-2, Ч.3): день фокуса и его слово состояния с минутами.
  ///
  /// In ru, this message translates to:
  /// **'День {index} · {state}'**
  String planHomeDayState(int index, String state);

  /// Дев-меню (DAY-FIX-2, Ч.7): сдвиг «сегодня» плана на QA-аккаунте за dev-дверью.
  ///
  /// In ru, this message translates to:
  /// **'QA · «сегодня» плана'**
  String get devQaClockTitle;

  /// Текущий сдвиг под заголовком строки.
  ///
  /// In ru, this message translates to:
  /// **'{days, plural, =0{без сдвига} one{сдвиг: {days} день} few{сдвиг: {days} дня} other{сдвиг: {days} дней}}'**
  String devQaClockShift(int days);

  /// Кнопка сдвига «сегодня» плана на день вперёд.
  ///
  /// In ru, this message translates to:
  /// **'+1 день'**
  String get devQaClockPlus;

  /// Кнопка сброса сдвига «сегодня» плана.
  ///
  /// In ru, this message translates to:
  /// **'Сбросить'**
  String get devQaClockReset;

  /// Тумблер четырёх звуков дня (4к-3).
  ///
  /// In ru, this message translates to:
  /// **'Звуки'**
  String get profileRowSounds;

  /// No description provided for @profileSoundsHint.
  ///
  /// In ru, this message translates to:
  /// **'Верно · неверно · этап закрыт · день закрыт'**
  String get profileSoundsHint;

  /// Латунный лейбл плиты дня.
  ///
  /// In ru, this message translates to:
  /// **'День {n}'**
  String dayLabel(int n);

  /// No description provided for @dayCards.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} карточка} few{{n} карточки} other{{n} карточек}}'**
  String dayCards(int n);

  /// No description provided for @dayMinutes.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} минута} few{{n} минуты} other{{n} минут}}'**
  String dayMinutes(int n);

  /// No description provided for @dayApproxMinutes.
  ///
  /// In ru, this message translates to:
  /// **'≈ {n, plural, one{{n} минута} few{{n} минуты} other{{n} минут}}'**
  String dayApproxMinutes(int n);

  /// No description provided for @dayApproxMin.
  ///
  /// In ru, this message translates to:
  /// **'≈ {n} мин'**
  String dayApproxMin(int n);

  /// No description provided for @dayLevelBeginner.
  ///
  /// In ru, this message translates to:
  /// **'Начинающий'**
  String get dayLevelBeginner;

  /// No description provided for @dayLevelIntermediate.
  ///
  /// In ru, this message translates to:
  /// **'Средний'**
  String get dayLevelIntermediate;

  /// No description provided for @dayGoalLabel.
  ///
  /// In ru, this message translates to:
  /// **'Научишься'**
  String get dayGoalLabel;

  /// No description provided for @dayStageWords.
  ///
  /// In ru, this message translates to:
  /// **'Слова'**
  String get dayStageWords;

  /// No description provided for @dayStagePhrases.
  ///
  /// In ru, this message translates to:
  /// **'Фразы'**
  String get dayStagePhrases;

  /// No description provided for @dayStageDialogue.
  ///
  /// In ru, this message translates to:
  /// **'Диалог'**
  String get dayStageDialogue;

  /// No description provided for @dayStageListen.
  ///
  /// In ru, this message translates to:
  /// **'Слушаю и отвечаю'**
  String get dayStageListen;

  /// No description provided for @dayStageSpeak.
  ///
  /// In ru, this message translates to:
  /// **'Говорю сам'**
  String get dayStageSpeak;

  /// No description provided for @dayStageCount.
  ///
  /// In ru, this message translates to:
  /// **'{done} / {total}'**
  String dayStageCount(int done, int total);

  /// No description provided for @dayNewWords.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} новое слово} few{{n} новых слова} other{{n} новых слов}}'**
  String dayNewWords(int n);

  /// No description provided for @dayStageSubStart.
  ///
  /// In ru, this message translates to:
  /// **'начни отсюда · {what}'**
  String dayStageSubStart(String what);

  /// No description provided for @dayStageSubUnfinished.
  ///
  /// In ru, this message translates to:
  /// **'не закончен · {cards} · {minutes}'**
  String dayStageSubUnfinished(String cards, String minutes);

  /// No description provided for @dayStageSubHinted.
  ///
  /// In ru, this message translates to:
  /// **'{n} с подсказкой'**
  String dayStageSubHinted(int n);

  /// No description provided for @dayCtaStart.
  ///
  /// In ru, this message translates to:
  /// **'Начать'**
  String get dayCtaStart;

  /// No description provided for @dayCtaContinue.
  ///
  /// In ru, this message translates to:
  /// **'Продолжить · осталось {n}'**
  String dayCtaContinue(int n);

  /// No description provided for @dayCtaPlan.
  ///
  /// In ru, this message translates to:
  /// **'К плану'**
  String get dayCtaPlan;

  /// No description provided for @dayClosedTitle.
  ///
  /// In ru, this message translates to:
  /// **'День {n} закрыт'**
  String dayClosedTitle(int n);

  /// No description provided for @dayNumCards.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{карточка} few{карточки} other{карточек}}'**
  String dayNumCards(int n);

  /// No description provided for @dayNumMinutes.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{минута} few{минуты} other{минут}}'**
  String dayNumMinutes(int n);

  /// No description provided for @dayNumFirstTry.
  ///
  /// In ru, this message translates to:
  /// **'с первого раза'**
  String get dayNumFirstTry;

  /// No description provided for @daySectionWords.
  ///
  /// In ru, this message translates to:
  /// **'Слова · {n}'**
  String daySectionWords(int n);

  /// No description provided for @daySectionPhrases.
  ///
  /// In ru, this message translates to:
  /// **'Фразы · {n}'**
  String daySectionPhrases(int n);

  /// No description provided for @daySectionTalk.
  ///
  /// In ru, this message translates to:
  /// **'Разговор · {n, plural, one{{n} обмен} few{{n} обмена} other{{n} обменов}}'**
  String daySectionTalk(int n);

  /// No description provided for @dayFromDay.
  ///
  /// In ru, this message translates to:
  /// **'из дня {n}'**
  String dayFromDay(int n);

  /// No description provided for @dayWordsExtra.
  ///
  /// In ru, this message translates to:
  /// **'+ {n} из дня {d}'**
  String dayWordsExtra(int n, int d);

  /// No description provided for @dayHardest.
  ///
  /// In ru, this message translates to:
  /// **'Далось труднее всего'**
  String get dayHardest;

  /// No description provided for @dayTries.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} попытка} few{{n} попытки} other{{n} попыток}}'**
  String dayTries(int n);

  /// No description provided for @dayInWork.
  ///
  /// In ru, this message translates to:
  /// **'В работе'**
  String get dayInWork;

  /// No description provided for @dayWordsCount.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} слово} few{{n} слова} other{{n} слов}}'**
  String dayWordsCount(int n);

  /// No description provided for @dayPhrasesCount.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} фраза} few{{n} фразы} other{{n} фраз}}'**
  String dayPhrasesCount(int n);

  /// No description provided for @dayExchangesCount.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} обмен} few{{n} обмена} other{{n} обменов}}'**
  String dayExchangesCount(int n);

  /// No description provided for @dayWillReturn.
  ///
  /// In ru, this message translates to:
  /// **'{k, plural, one{Вернётся в день {n} · {k} карточка} few{Вернутся в день {n} · {k} карточки} other{Вернутся в день {n} · {k} карточек}}'**
  String dayWillReturn(int n, int k);

  /// No description provided for @dayShellCounter.
  ///
  /// In ru, this message translates to:
  /// **'{stage} · {done} из {total}'**
  String dayShellCounter(String stage, int done, int total);

  /// No description provided for @dayEntryLabel.
  ///
  /// In ru, this message translates to:
  /// **'Этап {n} из 5'**
  String dayEntryLabel(int n);

  /// No description provided for @dayEntryLine.
  ///
  /// In ru, this message translates to:
  /// **'{units} · {cards} · {minutes}'**
  String dayEntryLine(String units, String cards, String minutes);

  /// No description provided for @dayEntryWordsSteps.
  ///
  /// In ru, this message translates to:
  /// **'познакомься · произнеси · выбери перевод · вставь в пример'**
  String get dayEntryWordsSteps;

  /// No description provided for @dayEntryPhrasesSteps.
  ///
  /// In ru, this message translates to:
  /// **'познакомься · повтори вслух · собери'**
  String get dayEntryPhrasesSteps;

  /// No description provided for @dayEntryDialogueSteps.
  ///
  /// In ru, this message translates to:
  /// **'прочитай и послушай весь разговор'**
  String get dayEntryDialogueSteps;

  /// No description provided for @dayEntryListenSteps.
  ///
  /// In ru, this message translates to:
  /// **'послушай · выбери, что он спросил · ответь'**
  String get dayEntryListenSteps;

  /// No description provided for @dayEntrySpeakSteps.
  ///
  /// In ru, this message translates to:
  /// **'скажи свою реплику в каждом обмене — текста не будет'**
  String get dayEntrySpeakSteps;

  /// No description provided for @dayEntryNew.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} новое} few{{n} новых} other{{n} новых}}'**
  String dayEntryNew(int n);

  /// No description provided for @dayEntryReturned.
  ///
  /// In ru, this message translates to:
  /// **'{r, plural, one{{r} вернулось из дня {d}} few{{r} вернулись из дня {d}} other{{r} вернулись из дня {d}}}'**
  String dayEntryReturned(int r, int d);

  /// No description provided for @dayEntryResume.
  ///
  /// In ru, this message translates to:
  /// **'продолжаем · осталось {n} из {total} · {minutes}'**
  String dayEntryResume(int n, int total, String minutes);

  /// No description provided for @dayEntryCta.
  ///
  /// In ru, this message translates to:
  /// **'Начать'**
  String get dayEntryCta;

  /// No description provided for @dayEntryResumeCta.
  ///
  /// In ru, this message translates to:
  /// **'Продолжить'**
  String get dayEntryResumeCta;

  /// No description provided for @dayIntroBadgeNew.
  ///
  /// In ru, this message translates to:
  /// **'новое слово'**
  String get dayIntroBadgeNew;

  /// No description provided for @dayIntroBadgeRepeat.
  ///
  /// In ru, this message translates to:
  /// **'повторение'**
  String get dayIntroBadgeRepeat;

  /// No description provided for @dayIntroCta.
  ///
  /// In ru, this message translates to:
  /// **'Понятно'**
  String get dayIntroCta;

  /// No description provided for @dayReturnedBadge.
  ///
  /// In ru, this message translates to:
  /// **'Вернулось из дня {n}'**
  String dayReturnedBadge(int n);

  /// No description provided for @dayPhraseBadgeNew.
  ///
  /// In ru, this message translates to:
  /// **'новая фраза'**
  String get dayPhraseBadgeNew;

  /// No description provided for @dayPhraseInTalk.
  ///
  /// In ru, this message translates to:
  /// **'в разговоре'**
  String get dayPhraseInTalk;

  /// No description provided for @dayTaskPronounce.
  ///
  /// In ru, this message translates to:
  /// **'Произнеси'**
  String get dayTaskPronounce;

  /// No description provided for @dayTaskRepeat.
  ///
  /// In ru, this message translates to:
  /// **'Повтори вслух'**
  String get dayTaskRepeat;

  /// No description provided for @dayTaskAsked.
  ///
  /// In ru, this message translates to:
  /// **'Что он спросил'**
  String get dayTaskAsked;

  /// «Ответь по-английски» — наречие языка карточки.
  ///
  /// In ru, this message translates to:
  /// **'Ответь {lang}'**
  String dayTaskAnswer(String lang);

  /// No description provided for @dayTaskSay.
  ///
  /// In ru, this message translates to:
  /// **'Скажи {lang}'**
  String dayTaskSay(String lang);

  /// No description provided for @dayTaskAssemble.
  ///
  /// In ru, this message translates to:
  /// **'Собери, что он сказал'**
  String get dayTaskAssemble;

  /// No description provided for @dayTaskChoose.
  ///
  /// In ru, this message translates to:
  /// **'Выбери перевод'**
  String get dayTaskChoose;

  /// No description provided for @dayTaskChooseWord.
  ///
  /// In ru, this message translates to:
  /// **'Выбери слово'**
  String get dayTaskChooseWord;

  /// No description provided for @dayTaskCloze.
  ///
  /// In ru, this message translates to:
  /// **'Вставь слово'**
  String get dayTaskCloze;

  /// No description provided for @dayTaskAssemblePhrase.
  ///
  /// In ru, this message translates to:
  /// **'Собери {lang}'**
  String dayTaskAssemblePhrase(String lang);

  /// No description provided for @daySayMic.
  ///
  /// In ru, this message translates to:
  /// **'Скажи вслух'**
  String get daySayMic;

  /// No description provided for @daySayListening.
  ///
  /// In ru, this message translates to:
  /// **'Слушаем…'**
  String get daySayListening;

  /// No description provided for @daySayThinking.
  ///
  /// In ru, this message translates to:
  /// **'…'**
  String get daySayThinking;

  /// No description provided for @daySayHeard.
  ///
  /// In ru, this message translates to:
  /// **'Услышали: {word}'**
  String daySayHeard(String word);

  /// No description provided for @daySayHeardShort.
  ///
  /// In ru, this message translates to:
  /// **'Услышали'**
  String get daySayHeardShort;

  /// No description provided for @daySayRetry.
  ///
  /// In ru, this message translates to:
  /// **'Не расслышали. Ещё раз'**
  String get daySayRetry;

  /// No description provided for @daySaySkip.
  ///
  /// In ru, this message translates to:
  /// **'Пропустить'**
  String get daySaySkip;

  /// No description provided for @dayReturnDay.
  ///
  /// In ru, this message translates to:
  /// **'Вернётся в день {n}'**
  String dayReturnDay(int n);

  /// No description provided for @dayReturnStage.
  ///
  /// In ru, this message translates to:
  /// **'Вернётся в конце этапа'**
  String get dayReturnStage;

  /// No description provided for @dayNext.
  ///
  /// In ru, this message translates to:
  /// **'Дальше'**
  String get dayNext;

  /// No description provided for @dayDialogTitle.
  ///
  /// In ru, this message translates to:
  /// **'Весь разговор'**
  String get dayDialogTitle;

  /// No description provided for @dayDialogSub.
  ///
  /// In ru, this message translates to:
  /// **'Прочитай и послушай — потом будешь отвечать сам'**
  String get dayDialogSub;

  /// No description provided for @dayDialogRoleYou.
  ///
  /// In ru, this message translates to:
  /// **'Ты'**
  String get dayDialogRoleYou;

  /// No description provided for @dayDialogTranslate.
  ///
  /// In ru, this message translates to:
  /// **'перевод'**
  String get dayDialogTranslate;

  /// No description provided for @daySpeakerSays.
  ///
  /// In ru, this message translates to:
  /// **'{role} говорит'**
  String daySpeakerSays(String role);

  /// No description provided for @daySpeakerAnswers.
  ///
  /// In ru, this message translates to:
  /// **'{role} отвечает'**
  String daySpeakerAnswers(String role);

  /// No description provided for @daySpeakHint.
  ///
  /// In ru, this message translates to:
  /// **'Подсказка'**
  String get daySpeakHint;

  /// No description provided for @daySpeakHinted.
  ///
  /// In ru, this message translates to:
  /// **'Засчитано с подсказкой · вернётся в день {n}'**
  String daySpeakHinted(int n);

  /// No description provided for @dayStageDoneWords.
  ///
  /// In ru, this message translates to:
  /// **'Слова закрыты'**
  String get dayStageDoneWords;

  /// No description provided for @dayStageDonePhrases.
  ///
  /// In ru, this message translates to:
  /// **'Фразы закрыты'**
  String get dayStageDonePhrases;

  /// No description provided for @dayStageDoneDialogue.
  ///
  /// In ru, this message translates to:
  /// **'Диалог закрыт'**
  String get dayStageDoneDialogue;

  /// No description provided for @dayStageDoneListen.
  ///
  /// In ru, this message translates to:
  /// **'«Слушаю и отвечаю» закрыт'**
  String get dayStageDoneListen;

  /// No description provided for @dayStageDoneSpeak.
  ///
  /// In ru, this message translates to:
  /// **'«Говорю сам» закрыт'**
  String get dayStageDoneSpeak;

  /// No description provided for @dayStageDoneMeta.
  ///
  /// In ru, this message translates to:
  /// **'{cards} · {minutes}'**
  String dayStageDoneMeta(String cards, String minutes);

  /// No description provided for @dayStageFactsInWork.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} слово в работе} few{{n} слова в работе} other{{n} слов в работе}}'**
  String dayStageFactsInWork(int n);

  /// No description provided for @dayStageFactsPhrasesInWork.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} фраза в работе} few{{n} фразы в работе} other{{n} фраз в работе}}'**
  String dayStageFactsPhrasesInWork(int n);

  /// No description provided for @dayStageFactsExchangesInWork.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} обмен в работе} few{{n} обмена в работе} other{{n} обменов в работе}}'**
  String dayStageFactsExchangesInWork(int n);

  /// No description provided for @dayStageFactsHinted.
  ///
  /// In ru, this message translates to:
  /// **'{n} с подсказкой'**
  String dayStageFactsHinted(int n);

  /// No description provided for @dayStageFactsReturn.
  ///
  /// In ru, this message translates to:
  /// **'{n, plural, one{{n} вернётся} other{{n} вернутся}}'**
  String dayStageFactsReturn(int n);

  /// No description provided for @dayStageNext.
  ///
  /// In ru, this message translates to:
  /// **'Дальше: {stage} · {cards} · {minutes}'**
  String dayStageNext(String stage, String cards, String minutes);

  /// No description provided for @dayExitTitle.
  ///
  /// In ru, this message translates to:
  /// **'Продолжить позже?'**
  String get dayExitTitle;

  /// No description provided for @dayExitBody.
  ///
  /// In ru, this message translates to:
  /// **'Ты на этапе {stage} · {done} из {total}. Прогресс сохранится'**
  String dayExitBody(String stage, int done, int total);

  /// No description provided for @dayExitStay.
  ///
  /// In ru, this message translates to:
  /// **'Остаться'**
  String get dayExitStay;

  /// No description provided for @dayExitLeave.
  ///
  /// In ru, this message translates to:
  /// **'Выйти'**
  String get dayExitLeave;

  /// No description provided for @daySheetStatePassed.
  ///
  /// In ru, this message translates to:
  /// **'сдал · день {n}'**
  String daySheetStatePassed(int n);

  /// No description provided for @daySheetStateHinted.
  ///
  /// In ru, this message translates to:
  /// **'с подсказкой · день {n}'**
  String daySheetStateHinted(int n);

  /// No description provided for @daySheetStateReturns.
  ///
  /// In ru, this message translates to:
  /// **'вернётся в день {n}'**
  String daySheetStateReturns(int n);

  /// No description provided for @daySheetInTalk.
  ///
  /// In ru, this message translates to:
  /// **'В разговоре'**
  String get daySheetInTalk;

  /// No description provided for @daySheetRoleYou.
  ///
  /// In ru, this message translates to:
  /// **'Ты:'**
  String get daySheetRoleYou;

  /// No description provided for @dayNoVoice.
  ///
  /// In ru, this message translates to:
  /// **'без озвучки — читает телефон'**
  String get dayNoVoice;

  /// No description provided for @dayLockedByDay.
  ///
  /// In ru, this message translates to:
  /// **'Сначала закончи день {n}'**
  String dayLockedByDay(int n);

  /// No description provided for @dayLockedUntil.
  ///
  /// In ru, this message translates to:
  /// **'Откроется {date}'**
  String dayLockedUntil(String date);

  /// No description provided for @dayLessonBuilding.
  ///
  /// In ru, this message translates to:
  /// **'Собираем день · около минуты'**
  String get dayLessonBuilding;

  /// No description provided for @dayLessonFailed.
  ///
  /// In ru, this message translates to:
  /// **'День не собрался'**
  String get dayLessonFailed;

  /// No description provided for @dayLessonRetry.
  ///
  /// In ru, this message translates to:
  /// **'Повторить'**
  String get dayLessonRetry;

  /// No description provided for @dayBack.
  ///
  /// In ru, this message translates to:
  /// **'Назад'**
  String get dayBack;

  /// No description provided for @dayTabTitle.
  ///
  /// In ru, this message translates to:
  /// **'План'**
  String get dayTabTitle;

  /// No description provided for @dayTabRoute.
  ///
  /// In ru, this message translates to:
  /// **'Маршрут'**
  String get dayTabRoute;
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

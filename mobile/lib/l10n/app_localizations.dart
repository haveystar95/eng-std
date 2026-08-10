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
  AppLocalizations(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

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
  static const List<Locale> supportedLocales = <Locale>[
    Locale('en'),
    Locale('ru'),
  ];

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

  /// Третичная кнопка отмены последнего вердикта (кадр 2.2).
  ///
  /// In ru, this message translates to:
  /// **'Отменить последний'**
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

  /// Итог сессии: лейбл счётчика «Знаю».
  ///
  /// In ru, this message translates to:
  /// **'Знакомо'**
  String get triageTallyKnown;

  /// Итог сессии: лейбл счётчика «Не знаю».
  ///
  /// In ru, this message translates to:
  /// **'Учим'**
  String get triageTallyLearning;

  /// Итог сессии: лейбл счётчика «Не уверен».
  ///
  /// In ru, this message translates to:
  /// **'Не уверены'**
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

  /// Лейбл блока дневной цели (кадр 2.1).
  ///
  /// In ru, this message translates to:
  /// **'Дневная цель'**
  String get homeDailyGoal;

  /// Прогресс дневной цели, «8 / 20 слов» (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{total, plural, one{{done} / {total} слово} few{{done} / {total} слова} many{{done} / {total} слов} other{{done} / {total} слова}}'**
  String homeGoalCount(int done, int total);

  /// Строка стрика, «Стрик — 5 дней» (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Стрик — {count} день} few{Стрик — {count} дня} many{Стрик — {count} дней} other{Стрик — {count} дня}}'**
  String homeStreakActive(int count);

  /// Строка стрика для нового пользователя (кадр 2b).
  ///
  /// In ru, this message translates to:
  /// **'Стрик начнётся сегодня'**
  String get homeStreakStartToday;

  /// Главная кнопка: есть due-повторения (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Повторить {count} слово} few{Повторить {count} слова} many{Повторить {count} слов} other{Повторить {count} слова}}'**
  String homeReviewButton(int count);

  /// Главная кнопка: есть отриаженные «не знаю» новые слова к изучению (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Учить {count} слово} few{Учить {count} слова} many{Учить {count} слов} other{Учить {count} слова}}'**
  String homeLearnButton(int count);

  /// Подстрока кнопки «Учить N».
  ///
  /// In ru, this message translates to:
  /// **'Новые слова — первый разбор'**
  String get homeLearnSubtitle;

  /// Главная кнопка: есть новые неразобранные термины (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{Разобрать {count} слово} few{Разобрать {count} слова} many{Разобрать {count} слов} other{Разобрать {count} слова}}'**
  String homeTriageButton(int count);

  /// Главная кнопка: свободная тренировка (нет due и разбора).
  ///
  /// In ru, this message translates to:
  /// **'Повторить'**
  String get homePracticeButton;

  /// Подстрока кнопки свободной тренировки.
  ///
  /// In ru, this message translates to:
  /// **'Свободная тренировка'**
  String get homePracticeSubtitle;

  /// Антиква-заголовок карточки генерации.
  ///
  /// In ru, this message translates to:
  /// **'Опиши тему — соберём коллекцию'**
  String get homeGenerateTitle;

  /// Подзаголовок карточки генерации.
  ///
  /// In ru, this message translates to:
  /// **'ИИ подберёт слова и фразы, которые реально нужны'**
  String get homeGenerateSubtitle;

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

  /// Заметка о бесплатной квоте (кадр 2b, новый пользователь).
  ///
  /// In ru, this message translates to:
  /// **'3 генерации в день на бесплатном тарифе'**
  String get homeGenerateFreeTier;

  /// Лейбл блока «Слово дня».
  ///
  /// In ru, this message translates to:
  /// **'Слово дня'**
  String get homeWordOfDay;

  /// Лейбл ленты коллекций.
  ///
  /// In ru, this message translates to:
  /// **'Мои коллекции'**
  String get homeMyCollections;

  /// Ссылка «Все» рядом с лейблом ленты коллекций.
  ///
  /// In ru, this message translates to:
  /// **'Все'**
  String get homeSeeAll;

  /// Прогресс коллекции на карточке ленты, «18 из 24 слов» (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{total, plural, one{{done} из {total} слово} few{{done} из {total} слова} many{{done} из {total} слов} other{{done} из {total} слов}}'**
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

  /// Легенда плотности: подтверждено упражнениями.
  ///
  /// In ru, this message translates to:
  /// **'Подтверждено {count}'**
  String collectionDensityConfirmed(int count);

  /// Легенда плотности: знакомое, не освоено.
  ///
  /// In ru, this message translates to:
  /// **'Знакомое {count}'**
  String collectionDensityFamiliar(int count);

  /// Легенда плотности: новые / не разобрано.
  ///
  /// In ru, this message translates to:
  /// **'В работе {count}'**
  String collectionDensityInProgress(int count);

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
  /// **'Коллекция удалится. Прогресс по словам сохранится.'**
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

  /// Приблизительный размер набора, «≈15 слов» (ICU plural).
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{≈{count} слово} few{≈{count} слова} many{≈{count} слов} other{≈{count} слова}}'**
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

  /// Заголовок карточки «всё повторено» (кадр 9b).
  ///
  /// In ru, this message translates to:
  /// **'На сегодня всё'**
  String get homeAllDoneTitle;

  /// Подзаголовок карточки «всё повторено».
  ///
  /// In ru, this message translates to:
  /// **'{count, plural, one{{count} повторение сделано, новых слов в очереди нет.} few{{count} повторения сделано, новых слов в очереди нет.} many{{count} повторений сделано, новых слов в очереди нет.} other{{count} повторений сделано, новых слов в очереди нет.}}'**
  String homeAllDoneSubtitle(int count);

  /// Кнопка свободной тренировки в карточке «всё повторено».
  ///
  /// In ru, this message translates to:
  /// **'Свободная тренировка'**
  String get homeAllDonePractice;

  /// Ссылка на генерацию в карточке «всё повторено».
  ///
  /// In ru, this message translates to:
  /// **'Собрать новую коллекцию'**
  String get homeAllDoneGenerate;

  /// Тихий офлайн-баннер на главной (кадр 9c).
  ///
  /// In ru, this message translates to:
  /// **'Нет сети. Повторения идут как обычно — синхронизируем, когда связь вернётся.'**
  String get homeOfflineBanner;

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

  /// Инструкция под промптом, выбор из четырёх (кадр 12a).
  ///
  /// In ru, this message translates to:
  /// **'выбери английский эквивалент'**
  String get sessionInstrChoose;

  /// Инструкция под промптом, сборка фразы (кадр 12b).
  ///
  /// In ru, this message translates to:
  /// **'собери из слов'**
  String get sessionInstrAssemble;

  /// Инструкция под промптом, ввод с клавиатуры (кадр 12c).
  ///
  /// In ru, this message translates to:
  /// **'напиши по-английски'**
  String get sessionInstrType;

  /// Инструкция аудирования-узнавания (кадр 12g).
  ///
  /// In ru, this message translates to:
  /// **'прослушай и выбери перевод · можно повторить'**
  String get sessionInstrListenChoose;

  /// Инструкция аудирования-воспроизведения (кадр 12h).
  ///
  /// In ru, this message translates to:
  /// **'прослушай и напиши по-английски'**
  String get sessionInstrListenType;

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

  /// Кнопка проверки собранного ответа (word_bank) — сабмит, до фидбека.
  ///
  /// In ru, this message translates to:
  /// **'Проверить'**
  String get sessionCheck;

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

  /// Вердикт фидбека — ошибка (кадр 12d).
  ///
  /// In ru, this message translates to:
  /// **'Не то — правильная форма ниже'**
  String get sessionFeedbackWrong;

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

  /// Счётчик итога — сколько карточек пройдено.
  ///
  /// In ru, this message translates to:
  /// **'Повторено'**
  String get sessionStatReviewed;

  /// Счётчик итога — сколько новых слов.
  ///
  /// In ru, this message translates to:
  /// **'Новых'**
  String get sessionStatNew;

  /// Счётчик итога — сколько ошибок.
  ///
  /// In ru, this message translates to:
  /// **'Ошибки'**
  String get sessionStatErrors;

  /// Счётчик компактного итога свободной тренировки — сколько карточек пройдено (F17).
  ///
  /// In ru, this message translates to:
  /// **'Пройдено'**
  String get sessionPracticeStatDone;

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
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['en', 'ru'].contains(locale.languageCode);

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

import 'package:flutter/painting.dart';

import 'colors.dart';

/// Typography tokens — roles from `tokens.html` §2 (base) and §2б (exercises).
///
/// Rule 04: Literata (antiqua) is used ONLY for the target language, collection
/// names and the streak line. Everything else — Russian UI, numbers, buttons —
/// is Inter.
///
/// Figure features (§4г):
/// * Literata display numbers and prose → oldstyle (`onum`), see [_oldstyle];
/// * aligned columns, goal counters, timers → tabular (`tnum`), see [_tabular].
///
/// ⚠ Transcriptions are **Inter**, never Literata: Literata ships no IPA glyphs
/// (verified — ɪ ɔ ː are absent), Inter covers the full IPA. Keep [transcription]
/// and [feedbackTranscription] on Inter.
abstract final class AppFonts {
  static const literata = 'Literata';
  static const inter = 'Inter';
}

const List<FontFeature> _oldstyle = [FontFeature.oldstyleFigures()];
const List<FontFeature> _tabular = [FontFeature.tabularFigures()];

/// Named text roles. Colours default to the spec's fixed value where the spec
/// fixes one; where a role's colour varies (e.g. a term), it defaults to ink
/// and callers `copyWith(color: …)`.
abstract final class AppText {
  // ── Literata — целевой язык, названия коллекций, стрик (§2) ──

  /// Термин на флип-карточке. 46 / 500 / 1.05 · −.02em.
  static const termFlip = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 46,
    height: 1.05,
    letterSpacing: -0.92,
    color: AppColors.ink,
    fontFeatures: _oldstyle,
  );

  /// Стрик (2.6). 40 / 500 / 1.05 · −.02em.
  static const streak = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 40,
    height: 1.05,
    letterSpacing: -0.8,
    color: AppColors.ink,
    fontFeatures: _oldstyle,
  );

  /// Заголовок карточки генерации. 22–24 / 500 / 1.18.
  static const generationCardTitle = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 23,
    height: 1.18,
    color: AppColors.ink,
    fontFeatures: _oldstyle,
  );

  /// «Слово дня» / термин в шите. 25–28 / 500 / 1.05.
  static const displayTerm = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 26,
    height: 1.05,
    color: AppColors.ink,
    fontFeatures: _oldstyle,
  );

  /// Термин в списке слов. 17 / 500 / 1.2.
  static const termInList = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 17,
    height: 1.2,
    color: AppColors.ink,
  );

  /// Название коллекции — экран. 30 / 500 / 1.1.
  static const collectionNameScreen = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 30,
    height: 1.1,
    color: AppColors.ink,
    fontFeatures: _oldstyle,
  );

  /// Название коллекции — карточка. 17 или 14.5 / 500 / 1.15.
  static const collectionNameCard = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 17,
    height: 1.15,
    color: AppColors.ink,
  );

  /// Пример употребления. Literata italic 14–15.5 / 400 / 1.4 · ink-body.
  static const usageExample = TextStyle(
    fontFamily: AppFonts.literata,
    fontStyle: FontStyle.italic,
    fontWeight: FontWeight.w400,
    fontSize: 15,
    height: 1.4,
    color: AppColors.inkBody,
  );

  // ── Inter — весь UI (§2) ──

  /// Заголовок экрана. 28 / 800 / 1.1 · −.02em.
  static const screenTitle = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w800,
    fontSize: 28,
    height: 1.1,
    letterSpacing: -0.56,
    color: AppColors.ink,
  );

  /// Заголовок пустого состояния / шага. 26–30 / 800 / 1.15.
  static const stepTitle = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w800,
    fontSize: 28,
    height: 1.15,
    color: AppColors.ink,
  );

  /// Главная кнопка. 19–20 / 700.
  static const primaryButton = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w700,
    fontSize: 19,
    color: AppColors.paper,
  );

  /// Подстрока главной кнопки. 12.5 / 400 · .66 alpha (paper).
  static const primaryButtonSub = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 12.5,
    color: Color.fromARGB(168, 246, 243, 236), // paper @ .66
  );

  /// Кнопка в шите. 15.5 / 700.
  static const sheetButton = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w700,
    fontSize: 15.5,
    color: AppColors.ink,
  );

  /// Подпись вердикта. 13.5 / 600.
  static const verdictLabel = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 13.5,
  );

  /// Лейбл секции. 11–11.5 / 700 · .09–.1em · caps · secondary.
  static const sectionLabel = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w700,
    fontSize: 11.5,
    letterSpacing: 1.15, // ~.1em
    color: AppColors.secondary,
  );

  /// Перевод. 13–16 / 400 · secondary · одна строка на всю ширину.
  static const translation = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 15,
    color: AppColors.secondary,
  );

  /// Транскрипция. Inter 11.5–12.5 / 400 · secondary. Всегда в слэшах (правило 06).
  static const transcription = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 12.5,
    color: AppColors.secondary,
  );

  /// ЛЕЙБЛ БЛОКА ГЛАВНОЙ — теснее общего лейбла секции (кадры 19-1…19-4, токен-лист).
  ///
  /// 10.5 / 700 / caps / tertiary против 11.5 / secondary у [sectionLabel]. Полкегля и одна ступень
  /// тона — мелочь порознь, но на главной таких лейблов четыре, и вместе они решают, читается блок
  /// как подпись или как заголовок. Трекинг задаётся на месте: .1em у плиты статистики, .14em у
  /// слова-вызова — так их и называют ряды.
  static const blockLabel = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w700,
    fontSize: 10.5,
    letterSpacing: 1.05, // .1em
    color: AppColors.tertiary,
  );

  /// ДИСПЛЕЙНОЕ ЧИСЛО — антиква, табличные цифры (кадры 19-1, 19-2).
  ///
  /// Не [counterLarge]: тот — гротеск 700 для счётчиков внутри строк, а это цифра, НА КОТОРУЮ
  /// смотрят: «32» над сессией, «146» на плите статистики. Кадры набирают их Literata светлым
  /// начертанием, и разница читается сразу — гротеск 700 в 56 кеглей кричит, антиква 400 говорит.
  ///
  /// Размер задаётся на месте: 56 над сессией, 26 на плите. Кегль тут не токен, потому что это
  /// одна роль в двух масштабах, а не два разных элемента.
  static const displayNumber = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w400,
    fontSize: 26,
    height: 1,
    color: AppColors.ink,
    fontFeatures: _tabular,
  );

  /// Крупный счётчик. 26 / 700 tabular.
  static const counterLarge = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w700,
    fontSize: 26,
    color: AppColors.ink,
    fontFeatures: _tabular,
  );

  /// Счётчик в шапке. 15 / 700 tabular.
  static const counterHeader = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w700,
    fontSize: 15,
    color: AppColors.ink,
    fontFeatures: _tabular,
  );

  /// Мелкий счётчик. 11–12.5 / 400 tabular.
  static const counterSmall = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 12,
    color: AppColors.secondary,
    fontFeatures: _tabular,
  );

  /// Бейдж типа / недобора. 9.5–10 / 700 · .05–.06em · caps · контур hairline.
  static const badge = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w700,
    fontSize: 10,
    letterSpacing: 0.55, // ~.055em
    color: AppColors.secondary,
  );

  // ── лестница слова (кадры 16d/16e) ────────────────────────────────────────

  /// Подпись ступени в развёрнутой карточке. Гротеск 11 / 400 tertiary.
  static const ladderLabel = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 11,
    color: AppColors.tertiary,
  );

  /// Текущая ступень — та же строка полужирным и чернилами: единственное выделение в блоке.
  static const ladderLabelCurrent = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 11,
    color: AppColors.ink,
  );

  /// «— знаю» вместо точек: слово вне лестницы. Тише подписи ступени, но читаемо.
  static const ladderDash = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 11,
    color: AppColors.tertiary,
  );

  /// Пояснение под неактивным действием развёрнутой карточки — целое предложение, поэтому крупнее
  /// подписи ступени (11 — размер ярлыка, не текста) и с интерлиньяжем на две строки.
  static const ladderLockedNote = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 13,
    height: 1.35,
    color: AppColors.tertiary,
  );

  /// Заголовок блока лестницы («ЛЕСТНИЦА СЛОВА»). Гротеск 10 / 700, разрядка — как у бейджа.
  static const ladderTitle = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w700,
    fontSize: 10,
    letterSpacing: 0.55,
    color: AppColors.tertiary,
  );

  // ── поиск и карточка слова (макет «Слова · фаза 3») ───────────────────────
  //
  // Направление 1a («Словарная статья») для поиска, 1b («Фото-герой») для карточки. Мокап набран
  // IBM Plex Mono там, где стоят транскрипция, уровень и счётчики; в приложении моноширинного
  // шрифта нет и не будет (правило 04 + IPA живёт только в Inter), поэтому эти роли —
  // Inter с табличными цифрами. Всё англоязычное, как и везде, — Literata.

  /// Поле поиска. Literata 20 — строка словаря, а не поле формы.
  static const searchInput = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w400,
    fontSize: 20,
    color: AppColors.ink,
  );

  /// Эхо ввода СПРАВА ВНУТРИ поля («holl → холл»). Курсив и тише всего на экране: это обратная
  /// связь набора, а не строка результата, и спорить с полем ей нельзя.
  static const searchEcho = TextStyle(
    fontFamily: AppFonts.inter,
    fontStyle: FontStyle.italic,
    fontWeight: FontWeight.w400,
    fontSize: 13.5,
    color: AppColors.tertiary,
  );

  /// Термин в строке списка поиска. Literata 21; кадр 02 даёт 23, вторичные списки — 19.
  static const searchRowTerm = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w400,
    fontSize: 21,
    height: 1.2,
    color: AppColors.ink,
  );

  /// Перевод в той же строке. Inter 14.5 secondary.
  static const searchRowTranslation = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14.5,
    color: AppColors.secondary,
  );

  /// Уровень (A1…C2) в строке списка. Табличные цифры, tertiary, без рамки.
  static const levelMark = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 11,
    color: AppColors.tertiary,
    fontFeatures: _tabular,
  );

  /// Заголовок «„слово“ ещё нет в базе». Literata 22 / 1.35.
  static const searchMissTitle = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w400,
    fontSize: 22,
    height: 1.35,
    color: AppColors.ink,
  );

  /// Пояснение абзацем под заголовком или в плашке лимита. Inter 14.5 / 1.55 ink-body.
  static const searchMissBody = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14.5,
    height: 1.55,
    color: AppColors.inkBody,
  );

  /// Служебная строка под кнопкой или под списком. Inter 12.5 / 1.5 tertiary.
  static const searchFootnote = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 12.5,
    height: 1.5,
    color: AppColors.tertiary,
  );

  /// Строка «В базе N слов…» и «Нажмите Enter…». Inter 13.5 / 1.55 secondary.
  static const searchNote = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 13.5,
    height: 1.55,
    color: AppColors.secondary,
  );

  /// Термин на карточке слова. Literata 42 / 500 / 1.02 · −.025em — первое, что видит глаз.
  static const cardTerm = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 42,
    height: 1.02,
    letterSpacing: -1.05,
    color: AppColors.ink,
  );

  /// Транскрипция на карточке. Inter 14.5 secondary (IPA — только Inter).
  static const cardTranscription = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14.5,
    color: AppColors.secondary,
  );

  /// Чтение термина буквами родного алфавита («knife» → «найф») — на карточке, рядом с IPA.
  /// Inter 14.5 tertiary: тише транскрипции ровно на одну ступень, потому что это подсказка, а не
  /// нотация. Словарный набор — в квадратных скобках, как в бумажном словаре (слэши заняты IPA).
  static const cardTransliteration = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14.5,
    color: AppColors.tertiary,
  );

  /// То же чтение в мини-карточке результата поиска. Inter 12.5 tertiary — на ступень тише
  /// [transcription], которая стоит там же.
  static const transliteration = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 12.5,
    color: AppColors.tertiary,
  );

  /// Строка «также: …» на карточке — синонимы термина. Literata 15.5 / 1.4 secondary: словарная
  /// врезка под переводом, не самостоятельный блок.
  static const cardSynonyms = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w400,
    fontSize: 15.5,
    height: 1.4,
    color: AppColors.secondary,
  );

  /// Уровень на карточке — плашка с заливкой ink-body и бумажной подписью.
  static const cardLevelBadge = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w500,
    fontSize: 11.5,
    color: AppColors.paper,
  );

  /// Перевод на карточке. Literata 26 / 1.2 — вторая по величине строка после термина.
  static const cardTranslation = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w400,
    fontSize: 26,
    height: 1.2,
    color: AppColors.ink,
  );

  /// Значение — по-английски, курсивом, на отдельном поднятом листе.
  static const cardDefinition = TextStyle(
    fontFamily: AppFonts.literata,
    fontStyle: FontStyle.italic,
    fontWeight: FontWeight.w400,
    fontSize: 16.5,
    height: 1.55,
    color: AppColors.inkBody,
  );

  /// Пример употребления на карточке. Literata 19 / 1.45 ink (сам термин внутри — полужирным).
  static const cardExample = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w400,
    fontSize: 19,
    height: 1.45,
    color: AppColors.ink,
  );

  /// Перевод примера — под ним, тише. Inter 14.5 / 1.5 secondary.
  static const cardExampleTranslation = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14.5,
    height: 1.5,
    color: AppColors.secondary,
  );

  /// Атрибуция фотографа поверх фото-героя. Мельче всего, на [AppColors.plateLabel].
  static const photoCredit = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 10.5,
    color: AppColors.plateLabel,
  );

  /// Таб-бар — активный. 9.5 / 700.
  static const tabActive = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w700,
    fontSize: 9.5,
    color: AppColors.ink,
  );

  /// Таб-бар — остальные. 9.5 / 600.
  static const tabInactive = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 9.5,
    color: AppColors.secondary,
  );
}

/// Exercise-session roles (`tokens.html` §2б). Промпт по-русски — Inter 800;
/// всё англоязычное — Literata.
abstract final class AppTextExercise {
  /// Шапка сессии. Inter 13 / 400 secondary · счётчик tabular.
  static const sessionHeader = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 13,
    color: AppColors.secondary,
    fontFeatures: _tabular,
  );

  /// Промпт задания (RU). Inter 22–23 / 800 / 1.2–1.25 · −.02em · ink.
  static const taskPromptRu = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w800,
    fontSize: 22,
    height: 1.22,
    letterSpacing: -0.44,
    color: AppColors.ink,
  );

  /// Инструкция под промптом. Inter 12.5 / 400 tertiary.
  static const taskInstruction = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 12.5,
    color: AppColors.tertiary,
  );

  /// Вариант ответа (выбор из четырёх). Literata 19 / 500.
  static const answerOption = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 19,
    color: AppColors.ink,
  );

  /// Чип словаря (сборка фразы). Literata 18 / 500.
  static const dictionaryChip = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 18,
    color: AppColors.ink,
  );

  /// Строка сборки. Literata 22 / 500.
  static const assemblyLine = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 22,
    color: AppColors.ink,
  );

  /// Поле ввода (набор с клавиатуры). Literata 26 / 500.
  static const typingInput = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 26,
    color: AppColors.ink,
  );

  // ── знакомство со словом (нулевая ступень, кадр 16b) ──────────────────────
  //
  // Читающая карточка, а не упражнение: термин встречает читателя ПЕРВЫМ и набран засечками
  // крупно; всё остальное — тише его. Ни одного цвета вердикта здесь нет и быть не может.

  /// Термин на карточке знакомства. Literata 34 / 500 ink.
  static const introTerm = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 34,
    height: 1.1,
    color: AppColors.ink,
  );

  /// Перевод под термином. Inter 16 / 400 inkBody — тише термина, но не подпись.
  static const introTranslation = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 16,
    color: AppColors.inkBody,
  );

  /// Пример-предложение. Literata 15.5 / 400 курсивом — цитата, а не задание; сам термин внутри
  /// набирается полужирным прямым (см. _ExampleLine).
  static const introExample = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w400,
    fontStyle: FontStyle.italic,
    fontSize: 15.5,
    height: 1.35,
    color: AppColors.inkBody,
  );

  /// «также: fill in · complete». Inter 12 / 400 tertiary.
  static const introAlso = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 12,
    color: AppColors.tertiary,
  );

  /// Вспомогательные кнопки ответа. Inter 13.5 / 600 secondary.
  static const answerAuxButton = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 13.5,
    color: AppColors.secondary,
  );

  /// Строка вердикта в фидбеке. Inter 14.5 / 600 (в цвете вердикта).
  static const feedbackVerdict = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 14.5,
  );

  /// Верная форма в фидбеке. Literata 17 / 500 ink.
  static const feedbackCorrectForm = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 17,
    color: AppColors.ink,
  );

  /// Термин в разборе. Literata 28 / 500.
  static const feedbackTerm = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 28,
    color: AppColors.ink,
  );

  /// Транскрипция в разборе. Inter 12.5 secondary (IPA — только Inter).
  static const feedbackTranscription = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 12.5,
    color: AppColors.secondary,
  );

  /// «Увидишь снова через N дней». Inter 12.5 tertiary.
  static const feedbackNextDue = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 12.5,
    color: AppColors.tertiary,
  );

  /// Пропуск в примере (cloze). Literata italic 18–19 / 400 / 1.5.
  static const clozeExample = TextStyle(
    fontFamily: AppFonts.literata,
    fontStyle: FontStyle.italic,
    fontWeight: FontWeight.w400,
    fontSize: 18,
    height: 1.5,
    color: AppColors.inkBody,
  );

  /// Итог сессии — заголовок. Inter 26 / 800.
  static const summaryTitle = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w800,
    fontSize: 26,
    color: AppColors.ink,
  );

  /// Итог сессии — число. Inter 26 / 700 tabular.
  static const summaryNumber = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w700,
    fontSize: 26,
    color: AppColors.ink,
    fontFeatures: _tabular,
  );

  /// Итог сессии — лейбл. Inter 11 / 700 · .07em · caps · tertiary.
  static const summaryLabel = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w700,
    fontSize: 11,
    letterSpacing: 0.77, // ~.07em
    color: AppColors.tertiary,
  );
}

/// РОЛИ ДНЯ ПЛАНА — кадры 23-x, токен-лист 2б/4к–4н. Правило кегля: основной ≥ 15, вторичный
/// ≥ 14, лейблы caps ≥ 11; ничего меньше 11 не существует.
///
/// Literata — всё на изучаемом языке и названия дней; Inter — русский UI, числа и лейблы.
/// Табличные цифры там, где числа стоят колонкой (счётчики этапов, числа плиты).
abstract final class AppTextDay {

  /// Лейбл секции на бумаге — 11/700/.14em tertiary caps («СЛОВА · 8», «НАУЧИШЬСЯ»).
  static const sectionLabel = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w700,
    fontSize: 11,
    letterSpacing: 1.54,
    color: AppColors.tertiary,
  );

  /// «+ 1 из дня 1» — латунь на бумаге, 14.
  static const brassNote = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14,
    color: AppColors.brassInk,
  );

  /// Латунная пилюля «ИЗ ДНЯ 1» / «ВЕРНУЛОСЬ ИЗ ДНЯ 1» — 11/700/.1em paper на #8C6A3A.
  static const brassPill = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w700,
    fontSize: 11,
    letterSpacing: 1.1,
    color: AppColors.paper,
  );

  /// Перевод строкой под словом во входе в этап — 15 secondary.
  static const rowTranslation = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 15,
    color: AppColors.secondary,
  );

  /// Реплики обмена в программе — Literata 15/1.35: врач secondary, ты ink.
  static const exchangePartner = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w400,
    fontSize: 15,
    height: 1.35,
    color: AppColors.secondary,
  );
  static const exchangeOwn = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w400,
    fontSize: 15,
    height: 1.35,
    color: AppColors.ink,
  );

  /// Лейбл блока задания (4м) — 11/700/.14em tertiary caps.
  static const taskLabel = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w700,
    fontSize: 11,
    letterSpacing: 1.54,
    color: AppColors.tertiary,
  );

  /// Латунный лейбл собеседника «ВРАЧ ГОВОРИТ» — 11/700/.14em #8C6A3A.
  static const speakerLabel = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w700,
    fontSize: 11,
    letterSpacing: 1.54,
    color: AppColors.brassInk,
  );

  /// Текст задания (4м) — Inter 22/800/1.2 · −.02em.
  static const taskText = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w800,
    fontSize: 22,
    height: 1.2,
    letterSpacing: -0.44,
    color: AppColors.ink,
  );

  /// Вариант ответа (4л): NATIVE — Inter 17/500, TARGET — Literata 18/500.
  static const optionNative = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w500,
    fontSize: 17,
    color: AppColors.ink,
  );
  static const optionTarget = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 18,
    color: AppColors.ink,
  );

  /// Плитка сборки — Literata 18/500 (2б); высота 44 задаёт виджет.
  static const tile = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 18,
    color: AppColors.ink,
  );

  /// Слово на знакомстве и «произнеси» — Literata 46/500/1.05 · −.02em.
  static const wordBig = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 46,
    height: 1.05,
    letterSpacing: -0.92,
    color: AppColors.ink,
  );

  /// Перевод под словом на знакомстве — 17 ink-body; в блоке задания — 16 secondary.
  static const introTranslation = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 17,
    color: AppColors.inkBody,
  );
  static const taskTranslation = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 16,
    color: AppColors.secondary,
  );

  /// Чтение кириллицей в слэшах — 14 tertiary на знакомстве, 15 secondary в блоке задания.
  static const introReading = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14,
    color: AppColors.tertiary,
  );
  static const taskReading = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 15,
    color: AppColors.secondary,
  );

  /// Пример курсивом — Literata italic 16/1.5 ink-body; перевод примера — 14 secondary.
  static const example = TextStyle(
    fontFamily: AppFonts.literata,
    fontStyle: FontStyle.italic,
    fontWeight: FontWeight.w400,
    fontSize: 16,
    height: 1.5,
    color: AppColors.inkBody,
  );
  static const exampleTranslation = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14,
    height: 1.4,
    color: AppColors.secondary,
  );

  /// Фраза на знакомстве — Literata 30/500/1.25; на «повтори» и в шите — 26/500/1.3.
  static const phraseIntro = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 30,
    height: 1.25,
    letterSpacing: -0.6,
    color: AppColors.ink,
  );
  static const phrase = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 26,
    height: 1.3,
    letterSpacing: -0.39,
    color: AppColors.ink,
  );

  /// Реплика собеседника в карточке — Literata 19/1.35; перевод — 14 secondary; объяснение —
  /// 14 ink-body/1.4.
  static const partnerLine = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w400,
    fontSize: 19,
    height: 1.35,
    color: AppColors.ink,
  );
  static const partnerTranslation = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14,
    height: 1.4,
    color: AppColors.secondary,
  );
  static const explanation = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14,
    height: 1.4,
    color: AppColors.inkBody,
  );

  /// Пузырь ленты — Literata 17/1.35 (свой — 16 на плите); перевод — 14.
  static const bubble = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w400,
    fontSize: 17,
    height: 1.35,
    color: AppColors.ink,
  );
  static const bubbleOwn = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w400,
    fontSize: 17,
    height: 1.35,
    color: AppColors.paper,
  );
  static const bubbleTranslation = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14,
    color: AppColors.secondary,
  );

  /// Строка вердикта речи «Услышали: …» — 14.5/600 в цвете вердикта; «Не расслышали» — secondary.
  static const heard = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 14.5,
    color: AppColors.verdictKnown,
  );
  static const retry = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14.5,
    color: AppColors.secondary,
  );

  /// Подпись под микрофоном, «Подсказка», «Пропустить», «Вернётся в конце этапа» — 14 tertiary.
  static const quiet = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14,
    color: AppColors.tertiary,
  );

  /// «Вернётся в день N» — 14 терракота; «Засчитано с подсказкой» — 14 охра.
  static const returnNote = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14,
    color: AppColors.destructiveText,
  );
  static const hintedNote = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14,
    color: AppColors.verdictUnsure,
  );

  /// Подсказка-ключ — Literata 19/500 с подчёркиванием ink; весь текст — Literata 22/500.
  static const hintKey = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 19,
    color: AppColors.ink,
    decoration: TextDecoration.underline,
    decorationColor: AppColors.ink,
    decorationThickness: 2,
  );
  static const hintText = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 22,
    height: 1.3,
    color: AppColors.ink,
  );

  /// Шапка сессии «Слова · 12 из 32» — 14 secondary tabular.
  static const shellHeader = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14,
    color: AppColors.secondary,
    fontFeatures: [FontFeature.tabularFigures()],
  );

  /// Вход в этап и итог: заголовок Literata 30/500; факты 14 secondary; шаги 14.5 ink-body/1.6;
  /// «Слова закрыты» Literata 23/500.
  static const entryTitle = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 30,
    height: 1.1,
    letterSpacing: -0.6,
    color: AppColors.ink,
  );
  static const facts = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14,
    color: AppColors.secondary,
    fontFeatures: [FontFeature.tabularFigures()],
  );
  static const steps = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 14.5,
    height: 1.6,
    color: AppColors.inkBody,
  );
  static const stageDoneTitle = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 23,
    height: 1.1,
    letterSpacing: -0.46,
    color: AppColors.ink,
  );
  static const stageFacts = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w400,
    fontSize: 16,
    color: AppColors.ink,
    fontFeatures: [FontFeature.tabularFigures()],
  );

  /// Строка списка (вход в этап, итог): слово Literata 17/500/1.2; перевод 14 secondary.
  static const listWord = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 17,
    height: 1.2,
    color: AppColors.ink,
  );

  /// Заголовок диалога «Весь разговор» — Literata 26/500; подзаголовок 14 secondary.
  static const dialogueTitle = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 26,
    height: 1.1,
    letterSpacing: -0.52,
    color: AppColors.ink,
  );

}

/// ОКНО ДНЯ — кадры 23-0a … 23-0e канвы plan-canvas (наряды DAY-UI-2, DAY-UI-3). Кегли только из
/// кадров: 11 лейблы caps, 13 статусы и чтение, 15 текст, вкладки и перевод, 17 итог дня, 22 слова,
/// фразы и реплики на изучаемом языке, 30 название дня (26 при переносе) и слово шита. Литеры —
/// Literata у изучаемого языка и названия, Inter у всего остального.
abstract final class AppTextWindow {
  /// Бровь «ДЕНЬ 2» — 13/600, .08em, строка 18, светлая латунь.
  static const brow = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 13,
    height: 18 / 13,
    letterSpacing: 1.04,
    color: AppColors.windowBrow,
  );

  /// Название дня — Literata 30/500, строка 36, −.01em, в одну строку.
  static const title = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 30,
    height: 36 / 30,
    letterSpacing: -0.3,
    color: AppColors.paper,
  );

  /// Название, которое в одну строку не встало, — 26, строка 32, с переносом.
  static const titleWrapped = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 26,
    height: 32 / 26,
    letterSpacing: -0.26,
    color: AppColors.paper,
  );

  /// «не начат · ≈ 20 минут» — 13, строка 18.
  static const status = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 13,
    height: 18 / 13,
    color: AppColors.windowStatus,
  );

  /// Цели одним предложением — 15, строка 20, бумага .78; «Научился:» у пройденного — шалфеем.
  static const goals = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 15,
    height: 20 / 15,
    color: AppColors.windowGoals,
  );

  /// Имя этапа — 15/500 (текущий 600), строка 20.
  static const stage = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w500,
    fontSize: 15,
    height: 20 / 15,
    color: AppColors.paper,
  );

  /// Слово состояния этапа — 13.
  static const stageState = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 13,
    height: 20 / 13,
    color: AppColors.windowAhead,
  );

  /// «6 / 16» у текущего этапа — 15/600, табличные цифры.
  static const stageCount = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 15,
    height: 20 / 15,
    color: AppColors.paper,
    fontFeatures: _tabular,
  );

  /// «День пройден · 19 минут» на месте статуса — 17/600, строка 20.
  static const passed = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 17,
    height: 20 / 17,
    color: AppColors.paper,
  );

  /// Компактная шапка: «День 2 · Приём у врача» 15/600 и «≈ 20 мин» 13 tertiary.
  static const compactTitle = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 15,
    height: 20 / 15,
    color: AppColors.ink,
  );
  static const compactMinutes = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 13,
    color: AppColors.tertiary,
  );

  /// Сегмент пилюли — 15/600: активный бумагой на чипе чернила, остальные secondary.
  static const tab = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 15,
    color: AppColors.secondary,
  );

  /// Бровь вкладки «СЛОВА · 8 · 5 ПРОЙДЕНО» и лейбл «В РАЗГОВОРЕ» — 11/600, .08em, строка 14, tertiary.
  static const tabBrow = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 11,
    height: 14 / 11,
    letterSpacing: 0.88,
    color: AppColors.tertiary,
  );

  /// Слово, фраза, реплика — Literata 22/500, строка 28.
  static const target = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 22,
    height: 28 / 22,
    color: AppColors.ink,
  );

  /// Чтение кириллицей — 13, строка 18, tertiary.
  static const reading = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 13,
    height: 18 / 13,
    color: AppColors.tertiary,
  );

  /// Перевод — 15, строка 20, secondary.
  static const translation = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 15,
    height: 20 / 15,
    color: AppColors.secondary,
  );

  /// Шит 23-0e: слово — Literata 30/500, строка 36.
  static const sheetWord = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 30,
    height: 36 / 30,
    color: AppColors.ink,
  );

  /// Шит: чтение — 15, строка 20, tertiary.
  static const sheetReading = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 15,
    height: 20 / 15,
    color: AppColors.tertiary,
  );

  /// Шит: перевод — 17, строка 24, ink.
  static const sheetTranslation = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 17,
    height: 24 / 17,
    color: AppColors.ink,
  );

  /// Шит: строка состояния словами — 13, строка 18, tertiary.
  static const sheetState = TextStyle(
    fontFamily: AppFonts.inter,
    fontSize: 13,
    height: 18 / 13,
    color: AppColors.tertiary,
  );

  /// Шит: «Закрыть» — 15/500 латунью.
  static const sheetClose = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w500,
    fontSize: 15,
    color: AppColors.brassInk,
  );

  /// Кнопка главного действия — 17/600 бумагой.
  static const action = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 17,
    color: AppColors.paper,
  );
}

/// DAY SESSION — canvases of series 30–32 in `session-canvas.dc.html` (work order SESSION-1b). The app's fonts:
/// Literata — the target language and headings, Inter — everything else. Size / line height — as on the canvases.
abstract final class AppTextSession {
  /// Stage name on the entry (30-1) and the stage summary (30-6) — Literata 26/500, line 32, −.01em.
  static const stageTitle = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 26,
    height: 32 / 26,
    letterSpacing: -0.26,
    color: AppColors.ink,
  );

  /// Stage description, the body of the «Microphone needed» sheet — 15, line 20, secondary.
  static const body = TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 20 / 15, color: AppColors.secondary);

  /// Small caption 13, line 18, tertiary — «≈ 6 min», the reading, «tap to speak», the stage status.
  static const meta = TextStyle(fontFamily: AppFonts.inter, fontSize: 13, height: 18 / 13, color: AppColors.tertiary);

  /// Stage row in the list — 15, line 20; the current one 600 in ink, the others secondary.
  static const stageRow = TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 20 / 15, color: AppColors.secondary);
  static const stageRowCurrent = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 15,
    height: 20 / 15,
    color: AppColors.ink,
  );

  /// Text 15/20 in ink — «No hints», the word's definition, the «Next: Phrases» line.
  static const text15 = TextStyle(fontFamily: AppFonts.inter, fontSize: 15, height: 20 / 15, color: AppColors.ink);

  /// Action button 56 — 17/600 in paper.
  static const dock = TextStyle(fontFamily: AppFonts.inter, fontWeight: FontWeight.w600, fontSize: 17, color: AppColors.paper);

  /// Header: the stage name — 15/600.
  static const headerStage = TextStyle(fontFamily: AppFonts.inter, fontWeight: FontWeight.w600, fontSize: 15, color: AppColors.ink);

  /// Scene strip: «Doctor's appointment · Receptionist» — 13, line 18, secondary.
  static const sceneLine = TextStyle(fontFamily: AppFonts.inter, fontSize: 13, height: 18 / 13, color: AppColors.secondary);

  /// Task line above the sheet — 17/600, line 22.
  static const task = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 17,
    height: 22 / 17,
    color: AppColors.ink,
  );

  /// Sheet eyebrow «WORD» — 11/600, .08em, small caps, tertiary.
  static const eyebrow = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 11,
    letterSpacing: 0.88,
    color: AppColors.tertiary,
  );

  /// Question text in the 30-9 sheet — Literata 26/500, line 34.
  static const question = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 26,
    height: 34 / 26,
    color: AppColors.ink,
  );

  /// Option in the native language — 17, line 22.
  static const option = TextStyle(fontFamily: AppFonts.inter, fontSize: 17, height: 22 / 17, color: AppColors.ink);

  /// Option in the target language, the live line, the assembled row, the «In the conversation» line — Literata
  /// 22/500, 28.
  static const target22 = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 22,
    height: 28 / 22,
    color: AppColors.ink,
  );

  /// The word on the intro and on «Repeat» — Literata 30/500, line 36.
  static const term = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 30,
    height: 36 / 30,
    color: AppColors.ink,
  );

  /// The phrase's frame on its plate — Literata 30/500, line 38, −.01em.
  static const frame = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 30,
    height: 38 / 30,
    letterSpacing: -0.3,
    color: AppColors.ink,
  );

  /// The frame on the lesson card 32-1 — Literata 28/500, line 36 («размер вне списка Части 0, он назван в наряде и
  /// живёт только на этом кадре»).
  static const frameLesson = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 28,
    height: 36 / 28,
    letterSpacing: -0.28,
    color: AppColors.ink,
  );

  /// A phrase of the talk in a list — «Скажи в разговоре» on the entry (37-5) and a row of the phrase sheet (37-8d):
  /// Literata 17/500, line 23 (SESSION-DES-4).
  static const phrase17 = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 17,
    height: 23 / 17,
    color: AppColors.ink,
  );

  /// A meaning on its neutral plate (32-1) — Literata 17/500, 1.2: the token list's «термин в списке слов».
  static const meaning = TextStyle(
    fontFamily: AppFonts.literata,
    fontWeight: FontWeight.w500,
    fontSize: 17,
    height: 1.2,
    color: AppColors.ink,
  );

  /// Tray tile and filler chip — Literata 15.
  static const tile = TextStyle(fontFamily: AppFonts.literata, fontSize: 15, color: AppColors.ink);

  /// «Skip» — 15/500 secondary.
  static const skip = TextStyle(fontFamily: AppFonts.inter, fontWeight: FontWeight.w500, fontSize: 15, color: AppColors.secondary);

  /// Exit sheet: the title — 17/600, line 22.
  static const sheetTitle = TextStyle(
    fontFamily: AppFonts.inter,
    fontWeight: FontWeight.w600,
    fontSize: 17,
    height: 22 / 17,
    color: AppColors.ink,
  );

  /// Exit sheet: «Continue» — 15/500 in brass.
  static const sheetStay = TextStyle(fontFamily: AppFonts.inter, fontWeight: FontWeight.w500, fontSize: 15, color: AppColors.brassInk);

  /// The build version in the entry's footer — small, in gray.
  static const buildStamp = TextStyle(fontFamily: AppFonts.inter, fontSize: 11, height: 14 / 11, color: AppColors.tertiary);
}

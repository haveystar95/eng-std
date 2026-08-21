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

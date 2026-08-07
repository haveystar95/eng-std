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

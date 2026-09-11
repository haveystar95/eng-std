import 'package:eng_std/l10n/app_localizations.dart';

import '../../../data/languages.dart' show languageAdverbFor;
import '../../../data/plan/plan_contract.dart';

/// СЛОВА ДНЯ — имена этапов, уровней и шагов по словарю 4к-4. Все из ARB; здесь только выбор.
abstract final class DayTexts {
  static String stage(AppLocalizations l, DayStage s) => switch (s) {
    DayStage.words => l.dayStageWords,
    DayStage.phrases => l.dayStagePhrases,
    DayStage.dialogue => l.dayStageDialogue,
    DayStage.listen => l.dayStageListen,
    DayStage.speak => l.dayStageSpeak,
  };

  static String stageDone(AppLocalizations l, DayStage s) => switch (s) {
    DayStage.words => l.dayStageDoneWords,
    DayStage.phrases => l.dayStageDonePhrases,
    DayStage.dialogue => l.dayStageDoneDialogue,
    DayStage.listen => l.dayStageDoneListen,
    DayStage.speak => l.dayStageDoneSpeak,
  };

  static String stageSteps(AppLocalizations l, DayStage s) => switch (s) {
    DayStage.words => l.dayEntryWordsSteps,
    DayStage.phrases => l.dayEntryPhrasesSteps,
    DayStage.dialogue => l.dayEntryDialogueSteps,
    DayStage.listen => l.dayEntryListenSteps,
    DayStage.speak => l.dayEntrySpeakSteps,
  };

  static String level(AppLocalizations l, PlanLevel2 level) => switch (level) {
    PlanLevel2.beginner => l.dayLevelBeginner,
    PlanLevel2.intermediate => l.dayLevelIntermediate,
  };

  /// «по-английски» — наречие языка плана для «Скажи …» / «Ответь …» / «Собери …».
  static String adverb(AppLocalizations l, String targetLang) =>
      languageAdverbFor(targetLang, l.localeName);

  /// «{units} в работе» по виду единицы этапа.
  static String unitsInWork(AppLocalizations l, DayUnitKind kind, int n) => switch (kind) {
    DayUnitKind.word => l.dayStageFactsInWork(n),
    DayUnitKind.phrase => l.dayStageFactsPhrasesInWork(n),
    DayUnitKind.exchange => l.dayStageFactsExchangesInWork(n),
  };

  /// «{n} слов» / «{n} фраз» / «{n} обменов».
  static String unitsCount(AppLocalizations l, DayUnitKind kind, int n) => switch (kind) {
    DayUnitKind.word => l.dayWordsCount(n),
    DayUnitKind.phrase => l.dayPhrasesCount(n),
    DayUnitKind.exchange => l.dayExchangesCount(n),
  };

  /// Чтение в слэшах (правило 06) — или null, когда чтения нет.
  static String? reading(String? pronunciation) {
    final p = pronunciation?.trim() ?? '';
    if (p.isEmpty) return null;
    return p.startsWith('/') ? p : '/$p/';
  }
}

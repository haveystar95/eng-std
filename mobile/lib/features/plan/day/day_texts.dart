import 'package:eng_std/l10n/app_localizations.dart';

import '../../../data/languages.dart' show languageAdverbFor;
import '../../../data/plan/plan_models.dart';

/// СЛОВА ДНЯ — имена этапов, уровней и шагов по словарю 4к-4. Все из ARB; здесь только выбор.
abstract final class DayTexts {
  static String stage(AppLocalizations l, PlanStage s) => switch (s) {
    PlanStage.words => l.dayStageWords,
    PlanStage.phrases => l.dayStagePhrases,
    PlanStage.dialogue => l.dayStageDialogue,
    PlanStage.listen => l.dayStageListen,
    // `unknown` сюда не доезжает: этапы приходят из контракта, и день рисует только их пять.
    PlanStage.speak || PlanStage.unknown => l.dayStageSpeak,
  };

  static String stageDone(AppLocalizations l, PlanStage s) => switch (s) {
    PlanStage.words => l.dayStageDoneWords,
    PlanStage.phrases => l.dayStageDonePhrases,
    PlanStage.dialogue => l.dayStageDoneDialogue,
    PlanStage.listen => l.dayStageDoneListen,
    PlanStage.speak || PlanStage.unknown => l.dayStageDoneSpeak,
  };

  static String stageSteps(AppLocalizations l, PlanStage s) => switch (s) {
    PlanStage.words => l.dayEntryWordsSteps,
    PlanStage.phrases => l.dayEntryPhrasesSteps,
    PlanStage.dialogue => l.dayEntryDialogueSteps,
    PlanStage.listen => l.dayEntryListenSteps,
    PlanStage.speak || PlanStage.unknown => l.dayEntrySpeakSteps,
  };

  /// «по-английски» — наречие языка плана для «Скажи …» / «Ответь …» / «Собери …».
  static String adverb(AppLocalizations l, String targetLang) =>
      languageAdverbFor(targetLang, l.localeName);

  /// «{units} в работе» по виду единицы этапа.
  static String unitsInWork(AppLocalizations l, PlanUnitKind kind, int n) => switch (kind) {
    PlanUnitKind.word => l.dayStageFactsInWork(n),
    PlanUnitKind.phrase => l.dayStageFactsPhrasesInWork(n),
    PlanUnitKind.exchange || PlanUnitKind.unknown => l.dayStageFactsExchangesInWork(n),
  };

  /// «{n} слов» / «{n} фраз» / «{n} обменов».
  static String unitsCount(AppLocalizations l, PlanUnitKind kind, int n) => switch (kind) {
    PlanUnitKind.word => l.dayWordsCount(n),
    PlanUnitKind.phrase => l.dayPhrasesCount(n),
    PlanUnitKind.exchange || PlanUnitKind.unknown => l.dayExchangesCount(n),
  };

  /// Чтение в слэшах (правило 06) — или null, когда чтения нет.
  static String? reading(String? pronunciation) {
    final p = pronunciation?.trim() ?? '';
    if (p.isEmpty) return null;
    return p.startsWith('/') ? p : '/$p/';
  }
}

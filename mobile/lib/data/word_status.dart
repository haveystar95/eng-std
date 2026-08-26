import 'package:eng_std/l10n/app_localizations.dart';

import 'practice/learning_ladder.dart';

/// THE STATUS VOCABULARY — the five things this product may say about where a word stands, and the
/// only words it may say them in.
///
/// There used to be four vocabularies for one set of facts. A collection called them
/// «Подтверждено / Знакомое / В работе», «Мои слова» drew five unnamed dots, the session header
/// named a phase, and a word row said «в каталоге». Every one of them was locally sensible and no
/// two of them agreed, so a learner reading two screens about the same word could not tell whether
/// they were reading about one state or two. That is a vocabulary problem, and the fix is a
/// vocabulary and not a redesign: the five states below, said the same way everywhere.
///
///  * **Разобрать** — the word is on a shelf and waiting for a decision. It is in a collection, and
///    the learner has not said whether they want to study it.
///  * **В работе** — the word is in the trainer's queue. It comes back on its own.
///  * **Ступень X из 5** — where in the queue: the rung, named
///    ({@link LadderRung}). Only a word in the queue has one.
///  * **Освоено** — it walked the whole ladder.
///  * **Отложено** — it was in the queue and was taken out. A PAUSE, never a delete: the word keeps
///    its rung and its due date and can come back.
///
/// The jargon this replaces does not live in the UI at all — «триаж» is a word for the code, and
/// what the learner reads is «Разобрать N слов».
enum WordStatus {
  /// On the shelf, undecided. The swipe pass is what it is waiting for.
  toSort,

  /// In the trainer's queue.
  inWork,

  /// Through every rung.
  mastered,

  /// Taken out of the queue — paused, not deleted.
  paused,
}

/// The five NAMED rungs, in order. Rungs 1 and 2 (recognition forward and reverse) are one rung to
/// a learner: the list is about how far the word has come, and the direction a recognition was
/// asked in is not that. This is why the ladder is «из 5» and the enum has six steps.
enum LadderRung { meeting, recognition, assembly, writing, dictation }

/// Which named rung a raw ladder step belongs to, or null when the word is off the ladder entirely
/// (a `known` claim). Mirrors [LadderDots.indexFor], which lights the dot for the same rung.
LadderRung? ladderRungFor(int? step) => switch (step) {
  LearningLadder.stepIntro => LadderRung.meeting,
  LearningLadder.stepRecognitionForward || LearningLadder.stepRecognitionReverse =>
    LadderRung.recognition,
  LearningLadder.stepAssembly => LadderRung.assembly,
  LearningLadder.stepTyping => LadderRung.writing,
  LearningLadder.stepDictation => LadderRung.dictation,
  _ => null,
};

/// The word for one status.
String wordStatusLabel(AppLocalizations l, WordStatus status) => switch (status) {
  WordStatus.toSort => l.statusToSort,
  WordStatus.inWork => l.statusInWork,
  WordStatus.mastered => l.statusMastered,
  WordStatus.paused => l.statusPaused,
};

/// The name of one rung, on its own — «узнавание».
String ladderRungLabel(AppLocalizations l, LadderRung rung) => switch (rung) {
  LadderRung.meeting => l.ladderStep0,
  LadderRung.recognition => l.ladderStep1,
  LadderRung.assembly => l.ladderStep3,
  LadderRung.writing => l.ladderStep4,
  LadderRung.dictation => l.ladderStep5,
};

/// «Ступень 2 из 5: узнавание» — the full progress phrase, for a word in the queue.
///
/// Null for a word that has no rung: one outside the ladder («знаю»), or one that is not in the
/// queue at all. Those have a STATUS and no position, and inventing «Ступень 1 из 5» for them would
/// claim the word had started something it has not.
String? ladderPositionLabel(AppLocalizations l, int? step) {
  final rung = ladderRungFor(step);
  if (rung == null) return null;

  return l.statusLadderStep(rung.index + 1, LadderRung.values.length, ladderRungLabel(l, rung));
}

/// Where a word stands, from the two facts that decide it.
///
/// The POOL comes first and the ladder second, deliberately: whether a word comes back at all is a
/// different question from how far up it is, and a word can be enrolled with no rung (just taken
/// in) or hold a rung while paused. Reading the rung first is how a paused word would report itself
/// as «в работе».
///
/// [everStudied] separates the two out-of-queue states. A word that has walked at least one rung and
/// is now outside the queue was PAUSED; one that never walked anywhere is still waiting to be
/// sorted. Where the caller cannot tell (a shelf row, which knows membership and not history), pass
/// false — «Разобрать» is what the shelf is asking for either way.
WordStatus wordStatusOf({
  required bool enrolled,
  required bool mastered,
  bool everStudied = false,
}) {
  if (mastered) return WordStatus.mastered;
  if (enrolled) return WordStatus.inWork;

  return everStudied ? WordStatus.paused : WordStatus.toSort;
}

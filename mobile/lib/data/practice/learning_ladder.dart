import '../models.dart';

/// How far a (user, term) pair has come along the ACQUISITION LADDER — a second dimension,
/// deliberately orthogonal to the SRS state.
///
///   state (`TermProgress.state`)  answers WHEN the pair comes back  → the scheduler owns it
///   acquisition                   answers WHAT it comes back as     → the admission matrix owns it
///
/// Client port of the server's `Learning\Domain\ValueObject\Acquisition`. Mirrored down by `/sync`
/// per term, because the device builds sessions offline and «when is it due» alone cannot say what
/// card to deal.
enum Acquisition {
  /// Never shown. Its first card is the intro.
  isNew('new'),

  /// Introduced, working through the two recognition steps. Answers here are real retrievals — they
  /// are logged — but they schedule nothing.
  learning('learning'),

  /// Off the recognition rungs. From here ordinary SRS runs and the rung widens with `reps`.
  graduated('graduated');

  const Acquisition(this.wire);
  final String wire;

  /// A row written before the ladder existed, or by a newer server, degrades to `graduated` — the
  /// safe direction: it can never push a word the learner already knows back to an intro card.
  static Acquisition fromWire(String? v) =>
      Acquisition.values.firstWhere((a) => a.wire == v, orElse: () => Acquisition.graduated);

  /// Is the pair still on the recognition rungs, where an answer must not schedule?
  bool get isOnLadder => this != Acquisition.graduated;
}

/// Client port of the server's `LearningLadder::stepFor` — THE function that says which rung of the
/// acquisition ladder a pair stands on.
///
/// It exists twice, here and in PHP, because the device builds sessions offline; and because it
/// exists twice, it is pinned by a parity test that walks the whole table
/// (`test/data/practice/ladder_parity_test.dart`). A ladder derived one way on the server and
/// another way here is silent: the phone simply deals a card the server would not have dealt.
///
/// The rungs:
///
///   0  intro                 shown, not asked — no grade, no review row (writes an exposure)
///   1  recognition forward   term → translation, tap an option (graded by IDENTITY, not text)
///   2  recognition reverse   translation → term, tap an option
///   3  assembly / choice     word_bank, cloze, scramble, pick_correct, ordinary multiple_choice
///   4  + typed production    typing, listening
///   5  + dictation           the whole sentence from hearing it alone
///
/// Rungs 0–2 live in `learningStep`, not in `reps`, and that is not redundancy: a FAILED recognition
/// step is re-queued as the same step but is still a real retrieval, so it is logged, and anything
/// derived from the log alone would push the pair up a rung it has not earned.
abstract final class LearningLadder {
  static const int stepIntro = 0;
  static const int stepRecognitionForward = 1;
  static const int stepRecognitionReverse = 2;
  static const int stepAssembly = 3;
  static const int stepTyping = 4;
  static const int stepDictation = 5;

  /// `reps` thresholds for the graduated rungs. Counted from GRADUATION, not from the first answer:
  /// the recognition steps never reach the scheduler, so `reps` is still 0 the moment a pair
  /// graduates, and these mean "successful SRS reviews since".
  static const int typingMinReps = 4;
  static const int dictationMinReps = 6;

  /// The first `learningStep` a pair holds once it has been introduced.
  static const int firstLadderStep = stepRecognitionForward;

  /// The rung this pair stands on, or null when it is outside the ladder.
  ///
  /// Null is returned for a `known` pair — a triage self-assessment awaiting its verification check,
  /// which is always typing whatever the matrix says. Callers must treat null as "the matrix does
  /// not apply", never as step 0.
  static int? stepFor({
    required Acquisition acquisition,
    required int reps,
    required int learningStep,
    bool isKnown = false,
  }) {
    if (isKnown) return null;

    return switch (acquisition) {
      // A pair that has never been shown starts at the intro whatever its counters say. The one way
      // to get here with reps > 0 is a `known` mark being undone, which resets the ladder on
      // purpose: the pair was never actually taught, only claimed.
      Acquisition.isNew => stepIntro,
      // Clamped, so a row written by a newer build can never point at a rung this one cannot deal.
      Acquisition.learning =>
        learningStep.clamp(stepRecognitionForward, stepRecognitionReverse),
      Acquisition.graduated => switch (reps) {
          >= dictationMinReps => stepDictation,
          >= typingMinReps => stepTyping,
          _ => stepAssembly,
        },
    };
  }

  /// Is this rung one of the two recognition steps, where an answer must not schedule?
  static bool isRecognitionStep(int? step) =>
      step == stepRecognitionForward || step == stepRecognitionReverse;
}

/// Where one (user, term) pair stands, as mirrored locally — the inputs [LearningLadder.stepFor]
/// needs, gathered so a caller reads a rung instead of assembling one.
class LadderPosition {
  const LadderPosition({
    this.acquisition = Acquisition.isNew,
    this.learningStep = 0,
    this.reps = 0,
    this.isKnown = false,
  });

  /// A term with NO progress row: never scheduled, never shown, standing at the intro.
  static const LadderPosition untouched = LadderPosition();

  final Acquisition acquisition;
  final int learningStep;
  final int reps;

  /// `state == 'known'` — a triage self-assessment, outside the ladder entirely.
  final bool isKnown;

  /// The rung, or null when the pair is outside the ladder.
  int? get step => LearningLadder.stepFor(
        acquisition: acquisition,
        reps: reps,
        learningStep: learningStep,
        isKnown: isKnown,
      );

  /// The rung to filter modes by. A pair outside the ladder is not held back by it — its
  /// verification is decided elsewhere — so it reads as the top rung rather than as rung 0.
  int get admissionStep => step ?? LearningLadder.stepDictation;
}

/// Where a choice card's WRONG options come from. Client port of the server's `OptionsPolicy`.
///
///   distant   the other terms in THIS session — unmistakably different, so the card is answerable
///             by knowing the word and by nothing else. What a first meeting needs.
///   standard  the enrichment distractors, deliberately nearly right. That IS the exercise once the
///             word is known.
enum OptionsPolicy {
  distant('distant'),
  standard('standard');

  const OptionsPolicy(this.wire);
  final String wire;

  static OptionsPolicy fromWire(String? v) =>
      OptionsPolicy.values.firstWhere((p) => p.wire == v, orElse: () => OptionsPolicy.standard);
}

/// One row of the admission matrix: the earliest rung at which a trainer may be dealt, plus where
/// its options come from. Client port of the server's `ModeRule`.
class ModeRule {
  const ModeRule({
    required this.mode,
    required this.minStep,
    this.optionsPolicy = OptionsPolicy.standard,
  });

  /// From `/sync`'s `settings.mode_admission`. `min_step` is the server's own resolution of the
  /// three thresholds — taken as sent rather than re-derived here, so the two runtimes cannot
  /// disagree about a rung even if the thresholds gain a dimension later.
  static ModeRule? fromWire(Map<String, dynamic> j) {
    final mode = ExerciseMode.values.where((m) => m.wire == j['mode']).firstOrNull;
    if (mode == null) return null; // a trainer this build does not have
    return ModeRule(
      mode: mode,
      minStep: (j['min_step'] as num?)?.toInt() ?? LearningLadder.stepAssembly,
      optionsPolicy: OptionsPolicy.fromWire(j['options_policy'] as String?),
    );
  }

  final ExerciseMode mode;
  final int minStep;
  final OptionsPolicy optionsPolicy;
}

/// THE ADMISSION MATRIX: which trainers a pair is allowed to be shown in at a given rung, and where
/// their options come from. Client port of the server's `ModeAdmission`.
///
/// Three questions are kept apart, exactly as on the server, and this is the third:
///
///   PracticeModes    is this trainer switched on for this user?      (settings)
///   TermPlayability  can this trainer be built for this term's data? (content)
///   ModeAdmission    has this PAIR earned this trainer yet?          (ladder)
class ModeAdmission {
  const ModeAdmission(this.rules);

  /// Parse `settings.mode_admission` as stored by the sync service. A missing or unreadable value
  /// falls back to [shipped] — what a device that has not synced yet must assume.
  static ModeAdmission fromWire(List<dynamic>? rows) {
    if (rows == null || rows.isEmpty) return shipped;
    final rules = <ExerciseMode, ModeRule>{};
    for (final row in rows) {
      if (row is! Map<String, dynamic>) continue;
      final rule = ModeRule.fromWire(row);
      if (rule != null) rules[rule.mode] = rule;
    }
    return rules.isEmpty ? shipped : ModeAdmission(rules);
  }

  /// The matrix the server seeds `learning_mode_settings` with — the fallback before a first sync,
  /// and the value the parity test asserts against the server's own.
  static const ModeAdmission shipped = ModeAdmission({
    ExerciseMode.intro: ModeRule(mode: ExerciseMode.intro, minStep: LearningLadder.stepIntro),
    ExerciseMode.multipleChoice: ModeRule(
      mode: ExerciseMode.multipleChoice,
      minStep: LearningLadder.stepRecognitionForward,
      optionsPolicy: OptionsPolicy.distant,
    ),
    ExerciseMode.wordBank: ModeRule(mode: ExerciseMode.wordBank, minStep: LearningLadder.stepAssembly),
    ExerciseMode.cloze: ModeRule(mode: ExerciseMode.cloze, minStep: LearningLadder.stepAssembly),
    ExerciseMode.pickCorrect: ModeRule(mode: ExerciseMode.pickCorrect, minStep: LearningLadder.stepAssembly),
    ExerciseMode.scramble: ModeRule(mode: ExerciseMode.scramble, minStep: LearningLadder.stepAssembly),
    ExerciseMode.typing: ModeRule(mode: ExerciseMode.typing, minStep: LearningLadder.stepTyping),
    ExerciseMode.listening: ModeRule(mode: ExerciseMode.listening, minStep: LearningLadder.stepTyping),
    ExerciseMode.dictation: ModeRule(mode: ExerciseMode.dictation, minStep: LearningLadder.stepDictation),
  });

  final Map<ExerciseMode, ModeRule> rules;

  /// Is this mode admitted at this rung?
  ///
  /// A mode with no rule is NOT admitted anywhere — fail-closed, matching the server: a trainer
  /// nobody has placed on the ladder should be undealable rather than universally available.
  ///
  /// A mode that produces no grade (`intro`) is admitted at its rung and NOWHERE ABOVE it. That
  /// ceiling is a rule, not a stored value: an intro after the learner has already answered the word
  /// is not a gentler card, it is a wasted one.
  bool allows(ExerciseMode mode, int step) {
    final rule = rules[mode];
    if (rule == null) return false;
    return mode.isGraded ? step >= rule.minStep : step == rule.minStep;
  }

  /// Where this card's wrong options come from. `distant` is a ladder concession — it exists to make
  /// a first meeting winnable — so a pair off the ladder always gets `standard`, whatever is stored.
  OptionsPolicy optionsPolicyFor(ExerciseMode mode, Acquisition acquisition) =>
      acquisition == Acquisition.graduated
          ? OptionsPolicy.standard
          : (rules[mode]?.optionsPolicy ?? OptionsPolicy.standard);

  /// The given modes admitted at this rung, order preserved — the third filter a ladder passes
  /// through, alongside the enabled set and [TermPlayability.only].
  List<ExerciseMode> only(List<ExerciseMode> modes, int step) => [
        for (final mode in modes)
          if (allows(mode, step)) mode,
      ];
}

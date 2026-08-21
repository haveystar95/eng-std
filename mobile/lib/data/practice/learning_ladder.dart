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

  /// Off the recognition rungs. From here ordinary SRS runs and the rung widens with each
  /// SUCCESSFUL review.
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
/// Rungs 0–2 live in `learningStep`, not in a counter, and that is not redundancy: a FAILED
/// recognition step is re-queued as the same step but is still a real retrieval, so it is logged,
/// and anything derived from the log alone would push the pair up a rung it has not earned.
///
/// Rungs 3–5 come from `successfulReviews`, for the same reason. They used to come from the
/// server's `reps`, which counts how many times SM-2 has been CALLED — every grade, `again`
/// included — so four misses and two hits read as six, and because a miss re-schedules the pair
/// immediately a word nobody could remember rode its own failures up to dictation (QA-18).
abstract final class LearningLadder {
  static const int stepIntro = 0;
  static const int stepRecognitionForward = 1;
  static const int stepRecognitionReverse = 2;
  static const int stepAssembly = 3;
  static const int stepTyping = 4;
  static const int stepDictation = 5;

  /// Thresholds for the graduated rungs, counted in SUCCESSFUL reviews since graduation. Counted
  /// from graduation without any arithmetic to arrange it: the recognition rungs are not graduated,
  /// so nothing on them increments the counter.
  static const int typingMinSuccesses = 4;
  static const int dictationMinSuccesses = 6;

  /// The first `learningStep` a pair holds once it has been introduced.
  static const int firstLadderStep = stepRecognitionForward;

  /// The rung a word OUTSIDE the pool is dealt at in a collection's free practice.
  ///
  /// Such a word has no rung of its own — nobody has decided to study it, so nothing about it has
  /// been earned — and it must not be dealt the trainers a rung is for. It is dealt as a first
  /// meeting is: the easy half of the matrix, choice and assembly, and never typed production
  /// («написание», «аудирование») or dictation, which ask the learner to reproduce a word they may
  /// be seeing for the first time.
  ///
  /// The assembly rung and not a recognition one, because recognition admits multiple_choice and
  /// nothing else, and «зашёл в кафе, открыл тему» deserves the assembly trainers too. Mirrored on
  /// the server as `LearningLadder::STEP_UNENROLLED_PRACTICE`.
  static const int stepUnenrolledPractice = stepAssembly;

  /// The rung this pair stands on, or null when it is outside the ladder.
  ///
  /// Null is returned for a `known` pair — a triage self-assessment awaiting its verification check,
  /// which is always typing whatever the matrix says. Callers must treat null as "the matrix does
  /// not apply", never as step 0.
  static int? stepFor({
    required Acquisition acquisition,
    required int successfulReviews,
    required int learningStep,
    bool isKnown = false,
  }) {
    if (isKnown) return null;

    return switch (acquisition) {
      // A pair that has never been shown starts at the intro whatever its counters say. The one way
      // to get here with a counter above 0 is a `known` mark being undone, which resets the ladder
      // on purpose: the pair was never actually taught, only claimed.
      Acquisition.isNew => stepIntro,
      // Clamped, so a row written by a newer build can never point at a rung this one cannot deal.
      Acquisition.learning =>
        learningStep.clamp(stepRecognitionForward, stepRecognitionReverse),
      Acquisition.graduated => switch (successfulReviews) {
          >= dictationMinSuccesses => stepDictation,
          >= typingMinSuccesses => stepTyping,
          _ => stepAssembly,
        },
    };
  }

  /// Is this rung one of the two recognition steps, where an answer must not schedule?
  static bool isRecognitionStep(int? step) =>
      step == stepRecognitionForward || step == stepRecognitionReverse;

  /// May a pair standing on this rung be dealt a FREE PRACTICE card at all?
  ///
  /// Rung 0 may not, and this is the owner's call rather than a technical limit. Practice
  /// introduces nothing — it writes no exposure and spends no daily quota — so a word that has
  /// never been introduced has nothing for practice to drill. Practice used to substitute the
  /// rung-1 card for it, which meant the one rung the admission matrix places at 0 was the one
  /// rung practice ignored: an exception dressed as a fallback. A first meeting happens in a study
  /// session, or it does not happen.
  ///
  /// `null` — a `known` pair — is admitted BY THIS RULE: it is outside the ladder, not at the
  /// bottom of it. Whether it is drilled at all is then decided by the pool, which a `known` pair
  /// is not in — see [LadderPosition.admitsPractice], where the two filters meet.
  ///
  /// This does not strand a word when the intro trainer is switched off globally: a study session
  /// then deals rung 0 its recognition card directly and passing it moves the pair up, so the word
  /// still leaves rung 0 by studying it.
  static bool admitsPractice(int? step) => step != stepIntro;
}

/// Where one (user, term) pair stands, as mirrored locally — the inputs [LearningLadder.stepFor]
/// needs, gathered so a caller reads a rung instead of assembling one.
class LadderPosition {
  const LadderPosition({
    this.acquisition = Acquisition.isNew,
    this.learningStep = 0,
    this.successfulReviews = 0,
    this.isKnown = false,
    this.enrolled = false,
  });

  /// A term with NO progress row: never scheduled, never shown, and NOT in the pool — which is
  /// what a word sitting in a collection nobody has triaged looks like.
  static const LadderPosition untouched = LadderPosition();

  final Acquisition acquisition;
  final int learningStep;

  /// Correct non-practice reviews since graduation — the ladder's own counter, NOT the scheduler's
  /// `reps`. See [LearningLadder].
  final int successfulReviews;

  /// `state == 'known'` — a triage self-assessment, outside the ladder entirely.
  final bool isKnown;

  /// Is this pair in the learner's POOL (`enrolled_at` non-null)? A third dimension, independent of
  /// the rung: not when the word comes back, nor as what, but whether it comes back at all. A word
  /// enters the pool only by a deliberate act — a «не знаю»/«не уверен» swipe, or «Учить это
  /// слово» — and leaves it only by «Убрать из изучения», which is a pause.
  final bool enrolled;

  /// The rung, or null when the pair is outside the ladder.
  int? get step => LearningLadder.stepFor(
        acquisition: acquisition,
        successfulReviews: successfulReviews,
        learningStep: learningStep,
        isKnown: isKnown,
      );

  /// The rung to filter modes by. A pair outside the ladder is not held back by it — its
  /// verification is decided elsewhere — so it reads as the top rung rather than as rung 0.
  int get admissionStep => step ?? LearningLadder.stepDictation;

  /// May this pair be dealt a free-practice card?
  ///
  /// The two halves of the pair are asked DIFFERENT questions, because they are in different
  /// situations:
  ///
  ///  * IN the pool — the rung decides, as it always has. A pair standing at rung 0 is enrolled and
  ///    waiting for its first meeting, and that meeting belongs to a study session: practice
  ///    introduces nothing (no exposure, no quota), so it has nothing to do with such a pair and
  ///    would only spend it. See [LearningLadder.admitsPractice].
  ///  * OUTSIDE the pool — admitted. «Тренировка по теме» is a drill over the COLLECTION, and a
  ///    collection that nobody has triaged is exactly the case the owner asked for: «зашёл в кафе,
  ///    открыл тему, прошёл маленькую тренировку без разбора коллекции». There is no study session
  ///    coming for these words, so practice is not spending a first meeting that was owed
  ///    elsewhere — it is the only meeting on offer.
  ///
  /// Admitting them changes NOTHING about progress: practice enrols nothing, writes no exposure,
  /// moves no rung and spends no quota, so a word outside the pool is still outside it when the
  /// session ends and «Мои слова» does not grow. What it does change is the CARD such a word gets
  /// — see [LearningLadder.stepUnenrolledPractice].
  ///
  /// Study sessions, «Учить N» and due repeats are untouched by any of this: they are still read
  /// strictly from the pool, on both sides.
  bool get admitsPractice =>
      enrolled ? LearningLadder.admitsPractice(step) : true;

  /// The rung a free-practice card for this pair is DEALT at — what the card reports back with the
  /// answer, and what narrows the trainers for a word outside the pool.
  ///
  /// For a pool pair it is the pair's own rung, folded the way a practice card has always folded it:
  /// never rung 1, because practice does not deal the identity-graded direction. For a pair outside
  /// the pool it is the fixed [LearningLadder.stepUnenrolledPractice].
  int? get practiceCardStep => enrolled
      ? (LearningLadder.isRecognitionStep(step) ? LearningLadder.stepRecognitionReverse : step)
      : LearningLadder.stepUnenrolledPractice;
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
  /// disagree about a rung even if the thresholds gain a dimension later. (The wire still calls the
  /// graduated threshold `min_reps`; the number it holds is a count of SUCCESSFUL reviews, and this
  /// side never reads it — only the `min_step` the server resolved it into.)
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
    // Speaking opens with the assembly trainers. Its two forms separate themselves by rung INSIDE
    // the mode ([ExerciseMode.asksForExample]), so there is one row here and not two — the same
    // shape the server's `ModeAdmission::shipped()` has.
    ExerciseMode.speaking: ModeRule(mode: ExerciseMode.speaking, minStep: LearningLadder.stepAssembly),
    // description_match opens with the assembly trainers too. `standard` options, not `distant`:
    // distant exists for the recognition rungs, and this card's wrong options come from the pool
    // through the ordinary picker — which already refuses a candidate sharing the target's meaning,
    // the failure that actually matters when the prompt is a definition rather than a gloss.
    ExerciseMode.descriptionMatch:
        ModeRule(mode: ExerciseMode.descriptionMatch, minStep: LearningLadder.stepAssembly),
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

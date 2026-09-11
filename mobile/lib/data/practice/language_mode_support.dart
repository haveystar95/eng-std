import '../models.dart';

/// WHICH TRAINERS A LANGUAGE CAN CARRY — client port of the server's
/// `App\Modules\Shared\Domain\Service\LanguageModeSupport`, **table for table, verbatim**.
///
/// The admin matrix (`learning_mode_settings`, mirrored here as `ModeAdmission`) is a PRODUCT
/// judgement: which trainers are switched on, and from which rung. This is a CAPABILITY fact:
/// whether a trainer's question is honest in this language at all. The effective availability of a
/// mode is the intersection of the two, and it points one way only — the panel can CLOSE a trainer,
/// and can never open one for a language that cannot carry it (DECISIONS п. 130). Which is why this
/// is code on both sides and not a column, and why it is not on the `/sync` wire: a capability an
/// admin screen can toggle is not a capability.
///
/// WHY IT EXISTS HERE AT ALL. Free practice is built on the device — it has to work in airplane
/// mode from start to summary — so the device deals the cards, and until now it dealt them without
/// this gate. A Polish word was therefore offered `pick_correct` (a trainer whose distractor
/// taxonomy is «типичные ошибки русскоязычного в английском» and has no judge for Polish), and a
/// Chinese or Japanese term — reference-only in v1 — was offered the full set. The server has
/// refused both since A-2; the phone did not, and an offline card the server would never have dealt
/// is exactly the divergence the practice port exists to prevent (BUGFIX-2 Ч.2б D3/D4).
///
/// THE SEMANTICS ARE NOT TOUCHED BY A BIT. This is a port, not a decision: the rows below are the
/// server's rows, and which language loses which trainer is settled there. `test/data/practice/
/// language_gate_parity_test.dart` pins the two tables against each other.
///
/// The language of a card here is the STUDIED side of its pair, resolved from the local mirror
/// through `AppDatabase.pairByTerms` (there is no `lang` column on `terms`, and deliberately none:
/// the pair resolver is the one place that answers «which pair is this term being studied in»).
abstract final class LanguageModeSupport {
  /// Every trainer the app has, in the server registry's own order. The taught languages are
  /// described as «all of these, minus …», because that is how the capability matrix reads and how a
  /// new trainer should arrive: available everywhere unless a language cannot carry it.
  static const List<ExerciseMode> _allModes = [
    ExerciseMode.intro,
    ExerciseMode.multipleChoice,
    ExerciseMode.wordBank,
    ExerciseMode.cloze,
    ExerciseMode.typing,
    ExerciseMode.listening,
    ExerciseMode.scramble,
    ExerciseMode.dictation,
    ExerciseMode.pickCorrect,
    ExerciseMode.speaking,
    ExerciseMode.descriptionMatch,
  ];

  /// Language → what it cannot carry, and what it carries only with a network. A language ABSENT
  /// from this table carries nothing (see the class doc); present with an empty `closed` means the
  /// full set.
  static const Map<String, ({List<ExerciseMode> closed, List<ExerciseMode> onlineOnly})> _support = {
    // The language every gate, grader and distractor taxonomy in this app was written for.
    'en': (closed: [], onlineOnly: []),
    // Vendors are complete (DeepL, TTS, on-device STT); only the distractor judge is missing.
    'de': (closed: [ExerciseMode.pickCorrect], onlineOnly: []),
    'es': (closed: [ExerciseMode.pickCorrect], onlineOnly: []),
    'it': (closed: [ExerciseMode.pickCorrect], onlineOnly: []),
    'fr': (closed: [ExerciseMode.pickCorrect], onlineOnly: []),
    // …plus no on-device recognition, so the two trainers that listen need a network.
    'pl': (
      closed: [ExerciseMode.pickCorrect],
      onlineOnly: [ExerciseMode.speaking, ExerciseMode.dictation],
    ),
    'ro': (
      closed: [ExerciseMode.pickCorrect],
      onlineOnly: [ExerciseMode.speaking, ExerciseMode.dictation],
    ),
    // Reference-only in v1: a collection, an audio and a translation — no training at all.
    'zh': (closed: _allModes, onlineOnly: []),
    'ja': (closed: _allModes, onlineOnly: []),
  };

  /// The modes this language can carry, in registry order. EMPTY means «none», which is a different
  /// answer from «not in the enabled set» and the caller must treat it as one: there is no honest
  /// trainer to fall back to, so there is no card.
  static List<ExerciseMode> modesFor(String lang) {
    final row = _support[lang];
    if (row == null) return const [];

    return [
      for (final mode in _allModes)
        if (!row.closed.contains(mode)) mode,
    ];
  }

  static bool supports(String lang, ExerciseMode mode) => modesFor(lang).contains(mode);

  /// Does this trainer need a network in this language? True only for a mode the language DOES
  /// carry — «closed» and «online-only» are different answers and must not collapse into one.
  static bool isOnlineOnly(String lang, ExerciseMode mode) {
    final row = _support[lang];

    return row != null && row.onlineOnly.contains(mode) && supports(lang, mode);
  }

  /// Every language with an entry here — the taught seven plus the two reference ones.
  static List<String> get languages => _support.keys.toList(growable: false);

  /// Every trainer, in registry order — what the parity test walks.
  static List<ExerciseMode> get allModes => _allModes;
}

/// THE intersection: the product matrix ∧ what this language can carry. Client port of the server's
/// `EnabledModes::forLanguage()`, including both of its empty cases and the difference between them.
///
///  * the language carries NOTHING (`zh`, `ja`) → **null**, and the caller deals no card;
///  * the language carries trainers but none that are switched on → the FLOOR
///    (`multiple_choice`, even when it is switched off), because an empty session is a worse answer
///    to a misconfigured toggle than an unexpected exercise.
///
/// The direction is one-way and cannot be inverted here: this can only REMOVE modes.
List<ExerciseMode>? modesForLanguage(List<ExerciseMode> enabled, String lang) {
  final supported = LanguageModeSupport.modesFor(lang);
  final kept = [
    for (final mode in enabled)
      if (supported.contains(mode)) mode,
  ];
  if (kept.isNotEmpty) return kept;
  if (supported.isEmpty) return null;

  return [
    supported.contains(ExerciseMode.multipleChoice) ? ExerciseMode.multipleChoice : supported.first,
  ];
}

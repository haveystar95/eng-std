import 'dart:math';

import '../api_client.dart';
import '../local/app_database.dart';
import '../models.dart';
import 'practice_distractors.dart';
import 'practice_mode_selector.dart';

/// Builds a free-practice session on the device, from the local term mirror.
///
/// Practice is built here rather than by `POST /study/sessions` for one reason: it has to work in
/// airplane mode from start to summary. Everything it needs is already mirrored — text, translation,
/// example, transcription, type — and its pool rule is the simple one: the whole collection,
/// shuffled, no `due_at`, no daily quota, no scheduling. The session id is a client ULID; the
/// server adopts it when the answers arrive (see SubmitReviewsHandler).
///
/// The exercise ladder is [PracticeModeSelector], which is pinned to the server's by a fixture the
/// server generates. What is NOT pinned, deliberately: which distractors and which chip order —
/// those are shuffled per session by design, on both sides.
abstract final class LocalPracticeSessionBuilder {
  /// Options on a multiple-choice card: the answer plus up to three distractors, as the server does.
  static const int optionCount = 4;

  /// Common phrasal-verb particles used as decoy chips, so assembling "give up" is a real choice
  /// and not just re-ordering the only two tiles on screen. Mirrors the server's ChipShuffler.
  static const List<String> _particles = [
    'up', 'on', 'in', 'off', 'out', 'down', 'over', 'away', 'back',
  ];

  /// Build a session from [terms] (the collection's whole mirror). Terms with no text are dropped —
  /// nothing can be asked about them. Returns a session with no cards when there is nothing
  /// playable, which the screen renders as its empty state.
  static StudySession build({
    required List<Term> terms,
    required int limit,
    required Random random,
    String? sessionId,
    PracticeModes enabled = PracticeModes.serverDefault,
  }) {
    final playable = [
      for (final term in terms)
        if ((term.termText ?? '').trim().isNotEmpty) term,
    ];
    final pool = [...playable]..shuffle(random);
    final chosen = pool.take(limit).toList();

    final cards = <SessionCard>[];
    for (var index = 0; index < chosen.length; index++) {
      cards.add(_card(
        term: chosen[index],
        cardIndex: index,
        pool: playable,
        enabled: enabled,
        random: random,
      ));
    }

    return StudySession(
      sessionId: sessionId ?? ApiClient.ulid(),
      cards: cards,
      builtLocally: true,
    );
  }

  static SessionCard _card({
    required Term term,
    required int cardIndex,
    required List<Term> pool,
    required PracticeModes enabled,
    required Random random,
  }) {
    var answer = (term.termText ?? '').trim();
    final example = term.example;
    final mode = PracticeModeSelector.select(
      enabled: enabled,
      rotation: PracticeModeSelector.rotationFor(term.id, cardIndex),
      playable: TermPlayability.of(
        answer: answer,
        example: example,
        exampleTranslation: term.exampleTranslation,
      ),
    );

    List<String>? options;
    List<String>? chips;
    // A sentence-level mode asks for the EXAMPLE, so the card's answer — what the grading compares
    // against — is that sentence, and the prompt is its translation. Same swap the server's
    // StudyCardAssembler makes, so an offline card and an online one are the same card.
    var prompt = term.translation;

    if (mode == ExerciseMode.multipleChoice) {
      final candidates = [...pool]..shuffle(random);
      final distractors = PracticeDistractors.forTarget(
        target: term,
        pool: candidates,
        count: optionCount - 1,
      );
      options = [answer, ...distractors]..shuffle(random);
    } else if (mode == ExerciseMode.wordBank) {
      chips = _chips(answer, phrasalVerb: term.type == 'phrasal_verb', random: random);
    } else if (mode == ExerciseMode.scramble) {
      // The gate only lets scramble through when the example and its translation are both there.
      answer = example!;
      prompt = term.exampleTranslation;
      chips = _sentenceChips(answer, random: random);
    } else if (mode == ExerciseMode.dictation) {
      // The task is the audio: no written cue at all, or it becomes a translation exercise.
      answer = example!;
      prompt = null;
    }

    return SessionCard(
      termId: term.id,
      mode: mode,
      type: term.type,
      prompt: prompt,
      answer: answer,
      transcription: term.transcription,
      example: example,
      exampleTranslation: term.exampleTranslation,
      options: options,
      chips: chips,
    );
  }

  /// Word chips for a phrase, letter chips for a single word — so word_bank never degenerates into
  /// a one-chip card. The shuffle is retried until it differs from the answer's own order (with two
  /// chips a plain shuffle is the original half the time). Mirrors the server's ChipShuffler.
  static List<String> _chips(String answer, {required bool phrasalVerb, required Random random}) {
    final words = answer.trim().split(RegExp(r'\s+')).where((w) => w.isNotEmpty).toList();
    var tokens = words.length > 1 ? words : answer.trim().split('');

    if (phrasalVerb && tokens.length >= 2) {
      tokens = [...tokens, ..._particleDecoys(tokens, random)];
    }
    if (tokens.length < 2) return tokens;

    final shuffled = [...tokens];
    for (var attempt = 0; attempt < 10; attempt++) {
      shuffled.shuffle(random);
      if (!_sameOrder(shuffled, tokens)) break;
    }
    return shuffled;
  }

  /// Chips for a scramble card: the example sentence's own tokens, shuffled — no decoys (on a
  /// sentence an extra tile turns "recall the order" into "spot the intruder"). Mirrors the
  /// server's `ChipShuffler::sentenceChips`.
  static List<String> _sentenceChips(String sentence, {required Random random}) {
    final tokens = SentenceTokenizer.tokenize(sentence);
    if (tokens.length < 2) return tokens;

    final shuffled = [...tokens];
    for (var attempt = 0; attempt < 10; attempt++) {
      shuffled.shuffle(random);
      if (!_sameOrder(shuffled, tokens)) break;
    }
    return shuffled;
  }

  /// One or two particles that aren't already in the answer, so the decoy is never the real one.
  static List<String> _particleDecoys(List<String> tokens, Random random) {
    final present = tokens.map((t) => t.toLowerCase()).toSet();
    final candidates = [for (final p in _particles) if (!present.contains(p)) p]..shuffle(random);
    if (candidates.isEmpty) return const [];
    return candidates.take(1 + random.nextInt(2)).toList();
  }

  static bool _sameOrder(List<String> a, List<String> b) {
    if (a.length != b.length) return false;
    for (var i = 0; i < a.length; i++) {
      if (a[i] != b[i]) return false;
    }
    return true;
  }
}

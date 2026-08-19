import 'dart:convert';
import 'dart:math';

import '../api_client.dart';
import '../local/app_database.dart';
import '../models.dart';
import 'learning_ladder.dart';
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
///
/// Practice fans across EVERY switched-on trainer the term's data can furnish — two filters, not
/// three: is the trainer on ([PracticeModes]) and can this term's data build it
/// ([TermPlayability]). The acquisition ladder does NOT narrow the mode set here, which is what the
/// server's own `ExerciseSelector::selectForPractice` has always done and what this port had
/// diverged from (QA-26): the admission matrix opens dictation at 6 successful reviews and typed
/// production at 4, so on a real pool — where nothing had passed 3 — free practice could only ever
/// deal recognition and the assembly trainers, and the dictation card never appeared once. Free
/// practice is a DRILL, not a rung: it schedules nothing, so nothing has to be earned to enter it.
///
/// [ModeAdmission] is still read, for the one thing that is about the card and not about the gate:
/// where a choice card's wrong options come from ([OptionsPolicy]), so a pair still on the
/// recognition rungs keeps its far options and its first meetings stay winnable.
///
/// Three things practice deliberately does NOT do, all because it moves nothing:
///
///  * it never deals an INTRO card. An intro is an introduction — it writes an exposure and spends
///    the daily new-term quota — and practice introduces nothing.
///  * and so it does not deal a word standing at RUNG 0 at all: such a word is dropped from the
///    pool, not handed the rung-1 card as a substitute. Substituting was a hole in the very gate
///    this class applies — rung 0 is the one rung the matrix places a trainer at, and it was the one
///    rung practice overrode. A first meeting belongs to a study session. See
///    [LearningLadder.admitsPractice] for why that does not strand the word.
///  * it never deals the IDENTITY-graded direction (term → translation, tap an option id). The
///    server refuses identity grading for practice answers, so a card built that way here would be
///    graded as text against the term's forms and marked wrong. Recognition in practice is always
///    the text-graded direction, with the same far options.
abstract final class LocalPracticeSessionBuilder {
  /// pick_correct shows the right sentence plus two wrong ones — three options, mirroring the
  /// server's StudyCardAssembler. A fourth whole sentence turns the card into a reading test.
  static const int pickCorrectWrongOptions = 2;

  /// Options on a multiple-choice card: the answer plus up to three distractors, as the server does.
  static const int optionCount = 4;

  /// Below this, a choice card is not a card: one option is not a question — the learner taps the
  /// only thing on screen and the queue records a correct answer nobody gave. See [_card].
  static const int minOptions = 2;

  /// Common phrasal-verb particles used as decoy chips, so assembling "give up" is a real choice
  /// and not just re-ordering the only two tiles on screen. Mirrors the server's ChipShuffler.
  static const List<String> _particles = [
    'up', 'on', 'in', 'off', 'out', 'down', 'over', 'away', 'back',
  ];

  /// Build a session from [terms] (the collection's whole mirror). Terms with no text are dropped —
  /// nothing can be asked about them. Returns a session with no cards when there is nothing
  /// playable, which the screen renders as its empty state.
  /// [ladder] is where each term stands on the acquisition ladder, keyed by term id, as mirrored by
  /// `/sync`. A term missing from it has no progress row — never scheduled, never shown.
  /// [admission] is the matrix the same feed carried.
  static StudySession build({
    required List<Term> terms,
    required int limit,
    required Random random,
    String? sessionId,
    PracticeModes enabled = PracticeModes.serverDefault,
    Map<String, LadderPosition> ladder = const {},
    ModeAdmission admission = ModeAdmission.shipped,
  }) {
    // Every term that could appear ON a card — as the question or as a wrong option.
    final playable = [
      for (final term in terms)
        if ((term.termText ?? '').trim().isNotEmpty) term,
    ];
    // …and the subset practice may actually QUESTION. A rung-0 word is only dropped as a question,
    // not as a decoy: appearing among someone else's wrong options claims nothing about it, and
    // dropping it there too would starve the far options in a collection full of new words.
    final drillable = [
      for (final term in playable)
        if ((ladder[term.id] ?? LadderPosition.untouched).admitsPractice) term,
    ]..shuffle(random);
    final chosen = drillable.take(limit).toList();

    // «Тренировать слово» — one word in the pool — FANS: a card per applicable mode instead of the
    // single card the round-robin would deal. The button promises the word is run through the
    // trainers, and «1 of 1» was not that (QA-14). Over many words the round-robin already shows
    // every mode across the session, so a fan there would only make one word crowd out the others.
    if (chosen.length == 1) {
      return StudySession(
        sessionId: sessionId ?? ApiClient.ulid(),
        cards: _fan(
          term: chosen.single,
          pool: playable,
          enabled: enabled,
          random: random,
          position: ladder[chosen.single.id] ?? LadderPosition.untouched,
          admission: admission,
        ),
        builtLocally: true,
      );
    }

    // A refused card is skipped, not replaced: see the option floor in [_card].
    final cards = <SessionCard>[
      for (var index = 0; index < chosen.length; index++)
        ?_card(
          term: chosen[index],
          cardIndex: index,
          pool: playable,
          enabled: enabled,
          random: random,
          position: ladder[chosen[index].id] ?? LadderPosition.untouched,
          admission: admission,
        ),
    ];

    return StudySession(
      sessionId: sessionId ?? ApiClient.ulid(),
      cards: cards,
      builtLocally: true,
    );
  }

  /// One card per mode this pair may be drilled in right now, in the enabled set's own order.
  ///
  /// The order is the enabled set's — which the server sorts by the `position` column of
  /// `learning_mode_settings` and the sync feed hands over unchanged — so the fan walks the trainers
  /// in the order the product puts them in, not in whatever order the rotation seed happened to land
  /// on. Same two filters as every other practice card and in the same order: switched on, buildable
  /// from this term's data.
  ///
  /// A pair whose applicable set comes out empty still gets exactly ONE card — [PracticeModeSelector]'s
  /// floor. «Nothing applies» must not become «nothing to train».
  static List<SessionCard> _fan({
    required Term term,
    required List<Term> pool,
    required PracticeModes enabled,
    required Random random,
    required LadderPosition position,
    required ModeAdmission admission,
  }) {
    final answer = (term.termText ?? '').trim();
    final playable = TermPlayability.of(
      answer: answer,
      example: term.example,
      exampleTranslation: term.exampleTranslation,
      distractorCount: _spanDistinct(_distractorsOf(term)).length,
    );
    final modes = playable.only(_drillable(enabled));

    if (modes.isEmpty) {
      final floor = _card(
        term: term, cardIndex: 0, pool: pool, enabled: enabled,
        random: random, position: position, admission: admission,
      );
      return floor == null ? const [] : [floor];
    }

    // A mode the term's data cannot furnish with enough options drops out of the fan rather than
    // shortening it to a card nobody can answer. A fan of nothing is an empty session, which the
    // screen already renders as its empty state.
    return [
      for (var i = 0; i < modes.length; i++)
        ?_card(
          term: term, cardIndex: i, pool: pool, enabled: enabled,
          random: random, position: position, admission: admission,
          forcedMode: modes[i],
        ),
    ];
  }

  /// [forcedMode] is the fan's doing ([_fan]): the mode has already been chosen from the same three
  /// filters, so the round-robin is skipped rather than re-run. Null everywhere else.
  static SessionCard? _card({
    required Term term,
    required int cardIndex,
    required List<Term> pool,
    required PracticeModes enabled,
    required Random random,
    required LadderPosition position,
    required ModeAdmission admission,
    ExerciseMode? forcedMode,
  }) {
    var answer = (term.termText ?? '').trim();
    final example = term.example;
    // Span-distinct, because that is what a card can actually use — see _spanDistinct.
    final usableDistractors = _spanDistinct(_distractorsOf(term));
    final step = position.admissionStep;
    final mode = forcedMode ?? PracticeModeSelector.select(
      // The switched-on trainers, minus `intro` — practice introduces nothing. The ladder is NOT a
      // filter here (QA-26): free practice drills every trainer the term's data can furnish, which
      // is what the server's `selectForPractice` does. What the rung still decides is the card's
      // SHAPE, below: where a choice card's options come from, and which form `speaking` asks in.
      // Rung 0 never reaches this method at all — `build` leaves it out of the pool, because
      // practice writes no exposure and a first meeting belongs to a study session.
      enabled: PracticeModes(_drillable(enabled)),
      rotation: PracticeModeSelector.rotationFor(term.id, cardIndex),
      playable: TermPlayability.of(
        answer: answer,
        example: example,
        exampleTranslation: term.exampleTranslation,
        distractorCount: usableDistractors.length,
      ),
    );

    // Far options: on the recognition rungs the wrong answers are the session's own NEIGHBOURS,
    // not the enrichment distractors. Those exist to be nearly right, which is the exercise once
    // the word is known and an unpassable card before it.
    final farOptions = mode == ExerciseMode.multipleChoice &&
        admission.optionsPolicyFor(mode, position.acquisition) == OptionsPolicy.distant;

    List<String>? options;
    List<String>? chips;
    List<OptionFeedback> optionFeedback = const [];
    // A sentence-level mode asks for the EXAMPLE, so the card's answer — what the grading compares
    // against — is that sentence, and the prompt is its translation. Same swap the server's
    // StudyCardAssembler makes, so an offline card and an online one are the same card.
    var prompt = term.translation;

    if (mode == ExerciseMode.multipleChoice) {
      final candidates = [...pool]..shuffle(random);
      final distractors = farOptions
          // Neighbours, deliberately NOT filtered for MEANING — that is the whole point: at a first
          // meeting the card must be answerable by knowing the word and by nothing else. They are
          // filtered for SHAPE, for exactly the same reason: an option of another type is discarded
          // on sight, without knowing anything, and a word offered beside whole questions is a card
          // with two real options pretending to have four (QA-6). Too few same-shape neighbours
          // makes the card SHORTER, never mixed — mirroring the server's StudyCardAssembler.
          ? [
              for (final t in candidates)
                if (t.id != term.id && t.type == term.type && (t.termText ?? '').trim().isNotEmpty)
                  (t.termText ?? '').trim(),
            ].take(optionCount - 1).toList()
          : PracticeDistractors.forTarget(
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
    } else if (mode == ExerciseMode.pickCorrect) {
      // Same swap the server's StudyCardAssembler makes: the answer is the example, the prompt is
      // its translation, and the options are three sentences.
      answer = example!;
      prompt = term.exampleTranslation;
      final wrong = usableDistractors.take(pickCorrectWrongOptions).toList();
      options = [answer, ...wrong.map((d) => d.sentence)]..shuffle(random);
      optionFeedback = wrong;
    } else if (mode == ExerciseMode.dictation) {
      // The task is the audio: no written cue at all, or it becomes a translation exercise.
      answer = example!;
      prompt = null;
    }

    // The rung this card is dealt at, echoed back with the answer like a server-built card's.
    // Never rung 1: practice does not deal the identity-graded direction (see the class doc), so a
    // recognition card here reports the direction it actually asked.
    final cardStep =
        LearningLadder.isRecognitionStep(step) ? LearningLadder.stepRecognitionReverse : position.step;

    if (mode == ExerciseMode.speaking && ExerciseMode.speaking.asksForExample(cardStep) && example != null) {
      // The late form: the pinned example IS the task, read aloud. Decided from `cardStep` — the
      // very value this card UPLOADS — so the form the learner was shown and the key the server
      // grades against cannot come apart. (An offline practice card carries its real rung while a
      // server-built practice card carries none, so the two may deal different forms of this
      // trainer; each is graded against what it actually asked, which is the part that matters.)
      answer = example;
      prompt = term.exampleTranslation;
    }

    // THE OPTION FLOOR (QA-15), deliberately here — after every fallback branch above, not inside
    // one of them. The far-option branch shortens the card when there are too few same-shape
    // neighbours, and the ordinary branch takes however many distractors it is given; for a phrase
    // whose only neighbours share its translation that is none, and the card came out with the
    // answer alone on screen. Mirrors the server's StudyCardAssembler, and it has to: a card the
    // server would refuse must not appear just because the phone built the session offline.
    if (options != null && options.length < minOptions) return null;

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
      // Same rule as the server's StudyCardAssembler: variants belong to the TERM, so they apply
      // only while the answer is the term. On scramble/dictation the answer is the sentence.
      acceptedVariants: mode.asksForExample(cardStep) && example != null ? const [] : _variantsOf(term),
      optionFeedback: optionFeedback,
      ladderStep: cardStep,
    );
  }

  /// The switched-on trainers that actually ASK the learner something — mirror of the server's
  /// `ExerciseSelector::drillable()`. `intro` is toggled like a trainer but is not one: it can never
  /// be an answer to «what can this term be drilled in», so it is dropped here, once.
  static List<ExerciseMode> _drillable(PracticeModes enabled) =>
      [for (final mode in enabled.modes) if (mode.isGraded) mode];

  /// The term's example distractors, as mirrored by `/sync` (a JSON array in one column). Malformed
  /// or absent JSON degrades to "none", which simply gates pick_correct out for that term — the safe
  /// direction, since the alternative is a card with too few options.
  static List<OptionFeedback> _distractorsOf(Term term) {
    final raw = term.exampleDistractors;
    if (raw == null || raw.isEmpty) return const [];
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return const [];
      return [
        for (final row in decoded)
          if (row is Map<String, dynamic>) OptionFeedback.fromJson(row),
      ];
    } on FormatException {
      return const [];
    }
  }

  /// The distractors a card can actually use: one per `errorSpan`, first occurrence winning.
  ///
  /// Two distractors sharing a span put two options on screen that differ from the example in the
  /// same place — «Could you explain the fees?» beside «Could you explain fees?» — and the card stops
  /// asking which sentence is right. It is also what the playability gate must count: two same-span
  /// rows would pass the ≥2 check and then yield one usable option, i.e. a two-option coin flip.
  ///
  /// Mirrors the server's StudyCardAssembler.spanDistinct() exactly — same order, same first-wins
  /// rule, same trim + lowercase comparison — so an offline card and an online one drop the same row.
  static List<OptionFeedback> _spanDistinct(List<OptionFeedback> distractors) {
    final kept = <OptionFeedback>[];
    final spans = <String>{};
    for (final d in distractors) {
      final span = d.errorSpan.trim().toLowerCase();
      if (span.isEmpty || !spans.add(span)) continue;
      kept.add(d);
    }
    return kept;
  }

  /// The term's accepted variants, as mirrored by `/sync` (a JSON array in one column). Malformed
  /// or absent JSON degrades to "no variants": that only makes the check stricter than the server,
  /// never looser, so a bad row can never let a wrong answer pass.
  static List<String> _variantsOf(Term term) {
    final raw = term.acceptedVariants;
    if (raw == null || raw.isEmpty) return const [];
    try {
      final decoded = jsonDecode(raw);
      if (decoded is! List) return const [];
      return decoded.whereType<String>().toList(growable: false);
    } on FormatException {
      return const [];
    }
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

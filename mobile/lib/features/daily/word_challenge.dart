/// СЛОВО-ВЫЗОВ (кадр 19-4) — one word a day the learner is not studying, and three options.
///
/// The card is the only thing on the home screen a learner can touch when they do not want a
/// session, and the counter «угадано N подряд» is the only mechanic that brings them back at noon.
///
/// THIS FILE IS THE STUB'S BRAIN, and it is deliberately the only part that knows the word came
/// from the device. DAILY-1 replaces the SOURCE — the server will pick by level and send the social
/// line — and the widget must not notice: it is handed a [WordChallenge] and renders it, whoever
/// built it. So everything here is pure and takes its world as arguments; nothing reaches for a
/// clock, a database or a random number generator.
library;

/// One row of the local mirror as the challenge sees it.
///
/// [inPool] is the whole reason the mirror is read rather than the pool: the challenge offers a word
/// the learner is NOT studying — that is what makes «Учить» a real act rather than a no-op — while
/// the WRONG options may come from anywhere, including words already in the queue.
class ChallengeTerm {
  const ChallengeTerm({
    required this.termId,
    required this.text,
    required this.translation,
    required this.learned,
    required this.support,
    required this.inPool,
    this.example,
    this.exampleTranslation,
  });

  final String termId, text, translation;

  /// The pair this term is studied through — its collection's `target_lang` / `source_lang`.
  ///
  /// Load-bearing: a card whose word is Italian and whose options are Polish asks nothing, because
  /// every option is right in its own language (MIX-1a, BUGFIX-2 Ч.1). The options are drawn from
  /// this pair and no other.
  final String learned, support;

  final bool inPool;
  final String? example, exampleTranslation;
}

/// The card, whole — content and state together, because the widget renders one thing.
class WordChallenge {
  const WordChallenge({
    required this.termId,
    required this.text,
    required this.translation,
    required this.options,
    required this.streak,
    this.example,
    this.exampleTranslation,
    this.chosen,
    this.collapsed = false,
    this.optionOwners = const {},
  });

  final String termId, text, translation;

  /// Three options in a stable order, one of them [translation]. Stable because a card that
  /// reshuffles itself between two builds of the same screen is a card the learner cannot answer.
  final List<String> options;

  /// THE RUN AS IT STANDS NOW — before the answer while the question is open, and including it once
  /// it has been given, because the store advances the number the moment the learner taps.
  ///
  /// One number and not a before-and-after pair: the card showed «угадано 7 подряд» off a stored 6
  /// and a `+1`, and reading the same state back after a relaunch counted the day twice. 0 means
  /// «no run» and the card says nothing rather than «угадано 0 подряд».
  final int streak;

  final String? example, exampleTranslation;

  /// What the learner tapped, or null while the question stands.
  final String? chosen;

  /// «Завтра новое» was pressed: the card is one line until tomorrow.
  final bool collapsed;

  /// Which TERM each wrong option is the translation of — «Вы выбрали „надёжный" — это reliable».
  ///
  /// The frame's line ends «…слова похожи только на вид», which is an editor's judgement about two
  /// particular words; this half is derivable and true for every pair, so it is the half that ships.
  final Map<String, String> optionOwners;

  bool get answered => chosen != null;
  bool get isCorrect => chosen == translation;

  WordChallenge copyWith({String? chosen, bool? collapsed, int? streak}) => WordChallenge(
    termId: termId,
    text: text,
    translation: translation,
    options: options,
    streak: streak ?? this.streak,
    example: example,
    exampleTranslation: exampleTranslation,
    chosen: chosen ?? this.chosen,
    collapsed: collapsed ?? this.collapsed,
    optionOwners: optionOwners,
  );
}

/// How many options a card carries. Three, as the frame draws them — one right, two wrong.
const int kChallengeOptions = 3;

/// Today's word, or null when the mirror cannot honestly produce one.
///
/// Null is a real answer and the caller must draw nothing: a learner whose mirror is empty, or whose
/// words carry no examples, or whose pair holds fewer than three translations, has no card — and an
/// empty card is exactly the «блок без данных» this screen refuses.
///
/// [pinnedTermId] is today's word once it has been chosen. It wins over the seeded pick and it is
/// looked up across the WHOLE mirror rather than among the candidates: pressing «Учить» puts the
/// word in the pool, and the day's word must not change under the learner's hand the moment they
/// take it.
WordChallenge? pickWordChallenge({
  required List<ChallengeTerm> mirror,
  required String seed,
  int streak = 0,
  String? pinnedTermId,
  String? chosen,
  bool collapsed = false,
}) {
  final byId = {for (final t in mirror) t.termId: t};

  ChallengeTerm? subject;
  if (pinnedTermId != null) {
    subject = byId[pinnedTermId];
  }
  if (subject == null) {
    // Sorted by id, so the pick depends on the seed and on nothing else — two devices with the same
    // mirror and the same day land on the same word, and so does the same device twice.
    final candidates = mirror.where(_isCandidate).toList()
      ..sort((a, b) => a.termId.compareTo(b.termId));
    if (candidates.isEmpty) return null;
    subject = candidates[_seededIndex(seed, candidates.length)];
  }

  final wrong = _wrongOptions(mirror, subject, seed);
  if (wrong.length < kChallengeOptions - 1) return null;

  return WordChallenge(
    termId: subject.termId,
    text: subject.text,
    translation: subject.translation,
    options: _arrange(subject.translation, wrong.map((t) => t.translation).toList(), seed),
    streak: streak,
    example: subject.example,
    exampleTranslation: subject.exampleTranslation,
    chosen: chosen,
    collapsed: collapsed,
    optionOwners: {for (final t in wrong) t.translation: t.text},
  );
}

/// A word the challenge may ASK about: not in the queue, and rich enough to explain itself.
///
/// The example is required because the answer state shows one — a card that reveals «reluctant —
/// неохотный» and nothing else teaches less than the word card the learner could have opened.
bool _isCandidate(ChallengeTerm t) =>
    !t.inPool &&
    t.text.trim().isNotEmpty &&
    t.translation.trim().isNotEmpty &&
    (t.example ?? '').trim().isNotEmpty;

/// Two wrong options: translations of OTHER terms of the SAME pair.
///
/// Other terms' translations rather than the term's own distractor list, because the mirror has no
/// distractor list for a word-choice card — `example_distractors` are wrong SENTENCES, which is a
/// different trainer's material and would put a sentence under a one-word prompt.
///
/// Excluded, beyond the subject itself: anything that reads the same as the right answer. A pair of
/// synonyms among the options makes two answers true and one of them graded wrong, which is the one
/// way a quiz can be worse than no quiz.
List<ChallengeTerm> _wrongOptions(List<ChallengeTerm> mirror, ChallengeTerm subject, String seed) {
  final taken = <String>{_key(subject.translation)};
  final pool =
      mirror
          .where(
            (t) =>
                t.termId != subject.termId &&
                t.learned == subject.learned &&
                t.support == subject.support &&
                t.translation.trim().isNotEmpty &&
                _key(t.translation) != _key(subject.translation),
          )
          .toList()
        ..sort((a, b) => a.termId.compareTo(b.termId));

  if (pool.isEmpty) return const [];

  // A seeded WALK rather than a shuffle: it visits every candidate exactly once, so «two distinct
  // options» either succeeds or the pair genuinely has fewer than two, and the answer is the same
  // for the same seed.
  final out = <ChallengeTerm>[];
  final start = _seededIndex('$seed:wrong', pool.length);
  for (var i = 0; i < pool.length && out.length < kChallengeOptions - 1; i++) {
    final candidate = pool[(start + i) % pool.length];
    if (taken.add(_key(candidate.translation))) out.add(candidate);
  }

  return out;
}

/// The three options in a stable, seeded order — the right one is not always in the same slot.
List<String> _arrange(String right, List<String> wrong, String seed) {
  final options = [right, ...wrong];
  final at = _seededIndex('$seed:slot', options.length);
  options.removeAt(0);
  options.insert(at, right);

  return options;
}

String _key(String s) => s.trim().toLowerCase();

/// FNV-1a over the seed, folded into `[0, length)`.
///
/// Written out rather than taken from `Random(seed)`: this number decides which word the learner
/// meets today, and it must not change because a Dart release changed how its generator is seeded.
int _seededIndex(String seed, int length) {
  if (length <= 1) return 0;
  var hash = 0x811c9dc5; // hex-ok — FNV offset basis, not a colour
  for (final unit in seed.codeUnits) {
    hash = ((hash ^ unit) * 0x01000193) & 0x7fffffff; // hex-ok — FNV prime
  }

  return hash % length;
}

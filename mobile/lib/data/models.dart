// The acquisition ladder's rung numbers. Imported rather than restated because `speaking` asks a
// different question at a different rung, and a second copy of «which rung is the dictation rung»
// is exactly the kind of duplication the ladder's parity test exists to prevent. (The import is
// mutual — `learning_ladder.dart` needs [ExerciseMode] — which Dart resolves without trouble.)
import 'dart:convert';

import 'practice/learning_ladder.dart';

/// Grade sent to the SM-2 scheduler on backend2 (`again|hard|good|easy`).
enum Rating {
  again('again'),
  hard('hard'),
  good('good'),
  easy('easy');

  const Rating(this.grade);
  final String grade;
}

class WordCollection {
  final String id;
  final String title;
  final String? emoji; // backend2 has no emoji; kept optional for the UI
  final String? description;
  final String source; // curated | ai | user
  final String type; // system | shared | custom
  final int wordsCount;
  final String sourceLang;
  final String targetLang;
  final String? imageUrl; // Pexels cover; null → gradient placeholder
  final String? imageAuthor;
  final String? imageAuthorUrl;

  /// True when the collection is one the user subscribed to from the store (not their own). Parsed
  /// additively from the `/sync` feed's `is_subscribed` flag when present; otherwise derived from
  /// [type] (system/shared are store sets), which the feed already carries — so no drift migration is
  /// needed. Drives [readOnly].
  final bool isSubscribed;

  WordCollection({
    required this.id,
    required this.title,
    this.emoji,
    this.description,
    required this.source,
    required this.type,
    required this.wordsCount,
    required this.sourceLang,
    required this.targetLang,
    this.imageUrl,
    this.imageAuthor,
    this.imageAuthorUrl,
    this.isSubscribed = false,
    this.isDefault = false,
    this.isReference = false,
  });

  bool get isAi => source == 'ai';

  /// The user's own, fully-editable collection (created or generated). Store sets are `system`/`shared`.
  bool get isOwned => type == 'custom';

  /// A store set in «Мои»: full learning cycle (triage/session/progress) but no editing — no
  /// rename, no add/edit/delete words; removing it means unsubscribe, not delete.
  bool get readOnly => isSubscribed || !isOwned;

  /// «Сохранённые»: the folder a one-tap save from search lands in. Exactly one per owner. It is an
  /// ordinary custom collection in every other respect — practisable, renameable — and the only two
  /// things this flag changes are that the shelf greys its delete action out and that the save
  /// confirmation can name it. Read from the flag rather than from the TITLE, which the owner may
  /// have changed.
  final bool isDefault;

  /// A PHRASEBOOK, not a course: the studied language carries no trainers at all (zh, ja in v1;
  /// DECISIONS пп. 84, 136). The screen shows term — translation — audio and offers neither
  /// training nor enrolment, because the server refuses both anyway (422 `reference_language_term`).
  ///
  /// Read from `/sync` and never derived here: which languages this deployment can teach is a
  /// server capability that changes without a client release.
  final bool isReference;

  factory WordCollection.fromJson(Map<String, dynamic> j) => WordCollection(
    id: j['id'] as String,
    title: j['title'] as String,
    emoji: j['emoji'] as String?,
    description: j['description'] as String?,
    source: (j['source'] as String?) ?? 'user',
    type: (j['type'] as String?) ?? 'custom',
    wordsCount: (j['items_count'] as int?) ?? 0,
    sourceLang: (j['source_lang'] as String?) ?? 'ru',
    targetLang: (j['target_lang'] as String?) ?? 'en',
    imageUrl: j['image_url'] as String?,
    imageAuthor: j['image_author'] as String?,
    imageAuthorUrl: j['image_author_url'] as String?,
    isSubscribed: (j['is_subscribed'] as bool?) ?? false,
    isDefault: (j['is_default'] as bool?) ?? false,
    isReference: (j['is_reference'] as bool?) ?? false,
  );
}

/// A collection item / study card. Reads both the collection-item shape and the
/// study-card shape from backend2 (both carry `term_id`, `text`, `translation`, …).
class Word {
  final String termId;
  final String term;
  final String translation;
  final String? transcription;
  final String? example;

  /// The example's translation, and what the word MEANS in the language being learned. Both are
  /// mirrored on the local term row, and both are what the word CARD (кадр 06/09) is made of — the
  /// list rows never showed them, which is why they were not carried here before.
  final String? exampleTranslation;
  final String? description;

  /// How the term READS in the letters of the support language («knife» → «найф»). Beside
  /// [transcription] (IPA), never instead of it. Null is ordinary — most pairs have no hint, and
  /// a pair whose two alphabets are the same deliberately never gets one.
  final String? transliteration;

  /// Every reading in the support language, the PINNED one first — `translations.first` is
  /// [translation]. Empty when the server sent nothing, which reads as «only the pinned one»;
  /// [readings] is what the card should draw.
  final List<String> translations;

  /// Near-synonyms in the language being LEARNED. Card material only: the local grader keeps
  /// using `accepted_variants`, so the device is never looser than the server.
  final List<String> synonyms;

  final String type; // word | phrase | idiom | phrasal_verb
  final String? audioUrl; // optional override; null → system TTS
  final String? ttsHint; // reading fix for system TTS, e.g. "ATM" → "A T M"
  final String?
  status; // learning state from local progress (new|learning|review|relearning|known); null → not started
  final String? imageUrl; // Pexels photo; null → type-badge placeholder
  final String? imageAuthor;
  final String? imageAuthorUrl;

  /// Where the word stands on the ACQUISITION LADDER, 0–5, or null when it is outside it (a triage
  /// «знаю»). Derived locally by the shared ladder function from the mirrored progress row, so the
  /// list renders it offline like everything else on that screen.
  final int? ladderStep;

  /// True when the word left the deck as «знаю» — the row then reads as a dash rather than as five
  /// pale dots, which would have said «at the very beginning».
  final bool isKnown;

  /// Is the word in the learner's POOL — is the trainer working on it at all?
  ///
  /// Independent of [ladderStep], which says how far along a word is IF it is being studied. A
  /// collection is a catalogue now, so most of its words may well be false here, and the card's
  /// action changes accordingly: «Учить это слово» for one outside the pool, «Убрать из изучения»
  /// for one inside it.
  final bool enrolled;

  Word({
    required this.termId,
    required this.term,
    required this.translation,
    this.transcription,
    this.example,
    this.exampleTranslation,
    this.description,
    this.transliteration,
    this.translations = const [],
    this.synonyms = const [],
    required this.type,
    this.audioUrl,
    this.ttsHint,
    this.status,
    this.imageUrl,
    this.imageAuthor,
    this.imageAuthorUrl,
    this.ladderStep,
    this.isKnown = false,
    this.enrolled = false,
  });

  /// Convenience so existing screens that used `word.id` keep working.
  String get id => termId;

  /// Phrase-like = anything the server didn't explicitly tag `word`. Keeps the client
  /// forward-compatible: new/unknown types (idiom, phrasal_verb, …) fall back to phrase
  /// behaviour rather than being mis-treated as single words.
  bool get isPhrase => type != 'word';

  /// What the card prints where the translation goes: the pinned reading first, the alternatives
  /// after it. Falls back to the single [translation] whenever the list is missing — which is the
  /// state every term was in before the станок started writing the list.
  List<String> get readings => joinedReadings(translation, translations);

  factory Word.fromJson(Map<String, dynamic> j) => Word(
    termId: (j['term_id'] ?? j['id']) as String,
    term: ((j['text'] ?? j['term']) as String?) ?? '',
    translation: (j['translation'] as String?) ?? '',
    transcription: j['transcription'] as String?,
    example: j['example'] as String?,
    exampleTranslation: j['example_translation'] as String?,
    description: j['description'] as String?,
    // Additive and defensive: the study-card and collection-item shapes carry none of the three,
    // and an absent key must read as «none», never as a parse failure.
    transliteration: j['transliteration'] is String ? j['transliteration'] as String : null,
    translations: stringList(j['translations']),
    synonyms: stringList(j['synonyms']),
    type: (j['type'] as String?) ?? 'word',
    audioUrl: j['audio_url'] as String?,
    ttsHint: j['tts_hint'] as String?,
  );
}

/// A list of strings out of anything the wire or the local mirror might hold — a real list, a
/// list with the odd non-string in it, a null, or junk. Never throws: all three v15 fields are
/// ADDITIVE, so «this build has never seen that shape» has to degrade to «none».
List<String> stringList(Object? v) {
  if (v is! List) return const [];
  return [
    for (final e in v)
      if (e is String && e.trim().isNotEmpty) e.trim(),
  ];
}

/// The same, from the JSON TEXT the local `terms` mirror stores.
List<String> decodeStringList(String? raw) {
  if (raw == null || raw.isEmpty) return const [];
  try {
    return stringList(jsonDecode(raw));
  } on FormatException {
    return const [];
  }
}

/// [pinned] first, then every other reading, de-duplicated and with the blanks dropped. An empty
/// result means there is nothing to print, which is a legal state on a term with no translation.
List<String> joinedReadings(String? pinned, List<String> all) {
  final out = <String>[];
  void add(String? s) {
    final v = (s ?? '').trim();
    if (v.isNotEmpty && !out.contains(v)) out.add(v);
  }

  add(pinned);
  for (final s in all) {
    add(s);
  }
  return out;
}

class ReviewCard {
  final Word word;
  ReviewCard({required this.word});

  /// backend2 `/study/due` returns flat cards (no `word` wrapper).
  factory ReviewCard.fromJson(Map<String, dynamic> j) => ReviewCard(word: Word.fromJson(j));
}

/// How a session card is presented (backend2 `SessionCard.exercise_mode`). The mode decides
/// which exercise body renders and how the client's INSTANT feedback checks an answer; it never
/// decides scheduling — the server grades every answer and the client check is never stricter
/// than the server's (invariant). Unknown values fall back to [typing] so a future server mode
/// still plays as free recall instead of crashing.
enum ExerciseMode {
  multipleChoice('multiple_choice'),
  wordBank('word_bank'),
  typing('typing'),
  listening('listening'),
  cloze('cloze'),
  scramble('scramble'),
  dictation('dictation'),
  pickCorrect('pick_correct'),

  /// DESCRIPTION → WORD: the card shows what a word MEANS, in the language being learned, and asks
  /// which of four words it is describing.
  ///
  /// The one card in the app that never shows the learner's own language: the prompt is the
  /// description, the options are words, and the whole question is answered inside English. That is
  /// also the only way to separate two words the learner has collapsed onto one Russian gloss.
  ///
  /// Tapped, so it grades like an ordinary multiple_choice — by TEXT against the term's own forms,
  /// not by option id: its correct option is the WORD, so nothing here needs identity grading (that
  /// exists for the rung-1 card, whose correct option is a translation).
  descriptionMatch('description_match'),

  /// SPEAKING RECALL: the card is read, the answer is SAID OUT LOUD, and the device recognises the
  /// speech on-device (nothing is uploaded but the recognised text).
  ///
  /// It checks that the word can be retrieved and produced — **not** that it is pronounced well.
  /// There is no accent scoring here and there must never be one: a recogniser disagreeing is a
  /// fact about a noisy room at least as often as it is a fact about the learner.
  ///
  /// Which is why this trainer forgives the CHANNEL and not the knowledge — the exact inversion of
  /// [forgivesTypos]. A card the recogniser could not hear is retried and then SKIPPED, and a skip
  /// uploads nothing at all: no review, no verdict, no schedule. Only «не помню» sends an answer,
  /// and that one is an honest lapse. See the speaking card in `session_exercise.dart`.
  speaking('speaking'),

  /// The zeroth rung of the acquisition ladder: the word is SHOWN, not asked. Term, translation,
  /// transcription, example — the learner reads it and taps «Понятно».
  ///
  /// It is a mode rather than a screen of its own because that is what makes it toggleable like
  /// every other trainer and what keeps «which card next» a single question. What makes it different
  /// is one thing, [isGraded]: it produces no answer, so no verdict, and nothing reaches the review
  /// queue — it writes an EXPOSURE instead.
  intro('intro');

  const ExerciseMode(this.wire);
  final String wire;

  static ExerciseMode fromWire(String? v) =>
      ExerciseMode.values.firstWhere((m) => m.wire == v, orElse: () => ExerciseMode.typing);

  /// Does this card produce an answer to grade at all? Everything downstream of an answer — the
  /// local check, the verdict, the review queue — is unreachable for `intro`, and this predicate is
  /// the single guard that keeps it that way.
  bool get isGraded => this != intro;

  /// A production mode reproduces from memory (free recall / assembly), so the answer is TYPED or
  /// assembled rather than picked. Recognition modes (pick one of four) are handled inline.
  /// `scramble` is assembled from chips, not typed — it shares word_bank's affordances.
  /// `dictation` IS typed, which is what gives it the hint row and «Не помню» for free.
  bool get isTyped => this == typing || this == cloze || this == listening || this == dictation;

  /// Modes that assemble the answer from given tiles rather than picking or typing it.
  bool get isAssembled => this == wordBank || this == scramble;

  /// Modes whose content is heard, not read: the card plays on appearance and offers a replay.
  bool get isHeard => this == listening || this == dictation;

  /// Does this card ask for the term's EXAMPLE SENTENCE rather than the term itself? Mirrors the
  /// server's `ExerciseMode::gradesAgainstExample()` — on these cards [SessionCard.answer] is the
  /// sentence, so the feedback must not also print it as "the example".
  ///
  /// [speaking] is the one mode whose question changes with the RUNG, so the rung is a required
  /// argument here as it is on the server: early it asks for the word (free recall out loud), from
  /// the dictation rung it asks the learner to read the pinned example aloud. A null rung — free
  /// practice, a `known` verification — is the word form, because nothing off the ladder has earned
  /// the later one.
  ///
  /// Content is not consulted here, only the question: use [SessionCard.asksForExample], which also
  /// checks the term actually HAS the sentence, exactly as the server's assembler does.
  bool asksForExample(int? ladderStep) => switch (this) {
    scramble || dictation || pickCorrect => true,
    speaking => ladderStep != null && ladderStep >= LearningLadder.stepDictation,
    _ => false,
  };

  /// Does this card ask the learner to TAP one of several given sentences? The body renders options
  /// like multiple_choice, but each option is a whole sentence and a wrong tap gets an explanation.
  bool get isSentenceChoice => this == pickCorrect;

  /// Mirror of the server's `ExerciseMode::forgivesTypos()`. Only a TYPED answer has a slipped key to
  /// forgive; a tapped or assembled one does not, and on pick_correct a one-character difference is
  /// usually the very distinction being tested.
  bool get forgivesTypos => isTyped;
}

/// Why one option of a `pick_correct` card is wrong: the broken fragment and what it should have
/// been. Produced by the enrichment станок, validated server-side (the span is guaranteed to occur
/// in [sentence]), and shipped with the card so the explanation works offline.
class OptionFeedback {
  final String sentence;
  final String errorSpan;
  final String correction;

  const OptionFeedback({required this.sentence, required this.errorSpan, required this.correction});

  factory OptionFeedback.fromJson(Map<String, dynamic> j) => OptionFeedback(
    sentence: (j['sentence'] as String?) ?? '',
    errorSpan: (j['error_span'] as String?) ?? '',
    correction: (j['correction'] as String?) ?? '',
  );

  Map<String, dynamic> toJson() => {
    'sentence': sentence,
    'error_span': errorSpan,
    'correction': correction,
  };
}

/// One self-contained card in a study session (`POST /study/sessions`). Carries the prompt (user's
/// language), the target answer (for grading feedback), and the mode-specific extras the client
/// needs to play it offline — shuffled [options] for multiple_choice, shuffled [chips] for
/// word_bank. The photo is NOT on the wire: the feedback pulls it from the local term mirror.
class SessionCard {
  final String termId;
  final ExerciseMode mode;
  final String type; // word | phrase | idiom | phrasal_verb
  final String? prompt; // the cue, in the user's language
  final String answer; // the correct target-language answer
  final String? transcription;
  final String? example;
  final String? exampleTranslation;
  final List<String>? options; // multiple_choice — answer + distractors, shuffled
  final List<String>? chips; // word_bank — the answer's tokens, shuffled

  /// Other answers that also count as correct for [answer], so the instant check matches the
  /// server's. Always empty on the sentence modes (scramble, dictation), where [answer] is the
  /// example sentence — a variant of the term is not a variant of the sentence.
  final List<String> acceptedVariants;

  /// pick_correct: per WRONG option, which fragment is broken and what it should have been. The
  /// reason this mode beats multiple_choice — a wrong tap is explained, not merely marked. Keyed by
  /// the option's own sentence so the feedback survives the shuffle.
  final List<OptionFeedback> optionFeedback;

  /// Which rung of the acquisition ladder this card was dealt at, echoed back with the answer: the
  /// pair's rung MOVES the moment that answer is folded, so without it the server could not tell
  /// afterwards what the card had asked. Null for a `known` verification, which is off the ladder.
  final int? ladderStep;

  /// Present ONLY on the forward-recognition card (rung 1), aligned index-for-index with [options]:
  /// the term each option's translation belongs to. That card is graded by IDENTITY — the learner
  /// taps, the client uploads the tapped id, and [answer] is this card's own term id. It is the one
  /// card whose correct option is a translation, and it is exactly why no translation ever enters a
  /// text answer key.
  final List<String>? optionIds;

  /// Is this a tapped recognition card whose correct option is identified by id rather than by text?
  bool get isIdentityGraded => optionIds != null && optionIds!.isNotEmpty;

  /// Is the answer TAPPED from options printed above the feedback (multiple_choice, pick_correct,
  /// listening-recognition), rather than typed or assembled?
  ///
  /// The feedback's wrong-verdict line points somewhere, and on these cards it must point UP: the
  /// correct option is marked in the list the learner just read, while what sits below is a reminder
  /// of the term. «Правильная форма ниже» sent them past the answer to the reminder (QA-8).
  bool get answeredByTapping => options != null && options!.isNotEmpty;

  /// The term's translation as HUMAN TEXT — the counterpart of [answerText].
  ///
  /// On every ordinary card the prompt IS the translation. On the identity-graded rung-1 card the
  /// prompt is the term (that card asks term→translation), and the translation is the text of the
  /// correct OPTION — which is why this has to be looked up through the answer key rather than read
  /// off a field. Without it the session summary printed «cold / cold»: headline and caption both
  /// resolving to the same prompt.
  String get translationText {
    if (!isIdentityGraded) return prompt ?? '';
    final ids = optionIds!;
    final opts = options ?? const <String>[];
    final at = ids.indexOf(answer);
    return at >= 0 && at < opts.length ? opts[at] : '';
  }

  /// Does THIS card ask for the example sentence — the mode's question at this card's rung, AND a
  /// term that actually has the sentence to ask for.
  ///
  /// Both halves, in one place, mirroring `StudyCardAssembler`'s own `$asksExample`: a speaking
  /// card dealt at the late rung to a term with no example degrades to the word form, and the
  /// prompt, the verdict and the feedback all have to agree about which form that was. Asking the
  /// mode alone would leave the feedback printing an example the card never showed.
  bool get asksForExample =>
      mode.asksForExample(ladderStep) && example != null && example!.trim().isNotEmpty;

  /// The term id behind the option at [index] — the ANSWER KEY for an identity-graded card, which
  /// is what the client both grades against and uploads. Null on every other card, where the
  /// option's own text is the key. Index-aligned with [options] by contract, so the pair can never
  /// be shuffled apart.
  String? optionIdAt(int index) {
    final ids = optionIds;
    if (ids == null || index < 0 || index >= ids.length) return null;
    return ids[index];
  }

  /// The correct answer as HUMAN TEXT — the only thing the UI may print or speak.
  ///
  /// [answer] is the GRADING KEY, and on the identity-graded forward-recognition card that key is a
  /// term id (a ULID), not words. Printing the key was the bug: the verdict card's headline read
  /// «01M00WHZFYJSYW76Z4B4BBASXC» instead of «over the counter», and the speak button read the ULID
  /// out loud. The transcription and the example looked right in the same card precisely because
  /// they come from their own fields and never went through the key.
  ///
  /// On that card the term is the PROMPT: rung 1 asks term→translation, so the English term is the
  /// cue and the options are translations (see the server's StudyCardAssembler.recognitionCard and
  /// `option_ids` in the OpenAPI contract). Every other card — including rung 2's reversed
  /// translation→term, whose key really is the term's text — returns [answer] unchanged.
  ///
  /// Falls back to the empty string rather than to [answer]: showing nothing is a blemish, showing
  /// an id is the bug this getter exists to make unrepresentable.
  String get answerText => isIdentityGraded ? (prompt ?? '') : answer;

  /// The explanation for a wrong pick, or null when the pick was right (nothing to underline).
  OptionFeedback? feedbackFor(String option) {
    for (final f in optionFeedback) {
      if (f.sentence == option) return f;
    }
    return null;
  }

  SessionCard({
    required this.termId,
    required this.mode,
    required this.type,
    this.prompt,
    required this.answer,
    this.transcription,
    this.example,
    this.exampleTranslation,
    this.options,
    this.chips,
    this.acceptedVariants = const [],
    this.optionFeedback = const [],
    this.ladderStep,
    this.optionIds,
  });

  bool get isPhrase => type != 'word';

  factory SessionCard.fromJson(Map<String, dynamic> j) => SessionCard(
    termId: j['term_id'] as String,
    mode: ExerciseMode.fromWire(j['exercise_mode'] as String?),
    type: (j['type'] as String?) ?? 'word',
    prompt: j['prompt'] as String?,
    answer: (j['answer'] as String?) ?? '',
    transcription: j['transcription'] as String?,
    example: j['example'] as String?,
    exampleTranslation: j['example_translation'] as String?,
    options: (j['options'] as List?)?.map((e) => e as String).toList(),
    chips: (j['chips'] as List?)?.map((e) => e as String).toList(),
    acceptedVariants:
        (j['accepted_variants'] as List?)?.map((e) => e as String).toList() ?? const [],
    optionFeedback:
        (j['option_feedback'] as List?)
            ?.map((e) => OptionFeedback.fromJson(e as Map<String, dynamic>))
            .toList() ??
        const [],
    ladderStep: (j['ladder_step'] as num?)?.toInt(),
    optionIds: (j['option_ids'] as List?)?.map((e) => e as String).toList(),
  );
}

/// A ready-to-play session: the server-fixed composition (one card per exercise) under a
/// client-generated [sessionId] (idempotent). `POST /study/sessions`.
class StudySession {
  final String sessionId;
  final List<SessionCard> cards;

  /// Built on the device rather than by `POST /study/sessions` — free practice, which must work
  /// offline end to end. The server has never seen this [sessionId] and adopts it when the answers
  /// arrive; nothing else about the session behaves differently.
  final bool builtLocally;

  const StudySession({required this.sessionId, required this.cards, this.builtLocally = false});
}

class Profile {
  final String nativeLanguage;
  final String targetLanguage;
  final String cefrLevel;
  final int dailyGoal;

  /// Subscription tier from `/me` (B5): free | premium. Server-derived, so it is NOT written back
  /// in [toJson] (staleness-safe, like the generation quota). Gates the practice-dialog entry.
  final String tier;

  /// ISO-8601 instant the user finished first-run onboarding, or null if never — the server-side
  /// onboarding gate (device-batch F1). Replaces the old per-device keychain flag: it's tied to the
  /// account, so a relogin never re-onboards and a new account always does. Persisted in [toJson]
  /// so the offline-restored user still gates correctly on a cold start.
  final String? onboardedAt;

  /// The user's IANA timezone as the server knows it (F19). UTC until the client has sent a real
  /// zone. Informational on the client (the client sends the *device* zone; the server does the due
  /// rounding), kept for round-tripping the cached user.
  final String timezone;

  Profile({
    required this.nativeLanguage,
    required this.targetLanguage,
    required this.cefrLevel,
    required this.dailyGoal,
    this.tier = 'free',
    this.onboardedAt,
    this.timezone = 'UTC',
  });

  bool get isPremium => tier == 'premium';

  /// True once the account has completed onboarding (server truth).
  bool get isOnboarded => onboardedAt != null;

  factory Profile.fromJson(Map<String, dynamic> j) => Profile(
    nativeLanguage: (j['native_language'] as String?) ?? 'ru',
    targetLanguage: (j['target_language'] as String?) ?? 'en',
    cefrLevel: (j['cefr_level'] as String?) ?? 'B1',
    dailyGoal: (j['daily_goal'] as int?) ?? 20,
    tier: (j['tier'] as String?) ?? 'free',
    onboardedAt: j['onboarded_at'] as String?,
    timezone: (j['timezone'] as String?) ?? 'UTC',
  );

  Map<String, dynamic> toJson() => {
    'native_language': nativeLanguage,
    'target_language': targetLanguage,
    'cefr_level': cefrLevel,
    'daily_goal': dailyGoal,
    'timezone': timezone,
    // Persist tier so the cached user keeps premium across restart/refresh — otherwise the
    // restored user defaults to free and the premium-gated dialog button vanishes until a
    // re-login. The server still enforces the real gate (403), so mild staleness is safe.
    'tier': tier,
    'onboarded_at': onboardedAt, // keep the onboarding gate correct on offline cold start
  };
}

/// One subscribable store collection (`GET /store/collections`, B5). Public/system sets for a
/// language pair, grouped client-side by [topic] into sections. [isPremium] draws the lock badge
/// and gates the subscribe (free tier → paywall); [isSubscribed] swaps the CTA to «В моих». The
/// contract carries no term list — only [itemsCount] — so the preview shows the count, not the words.
class StoreCollection {
  final String id;
  final String title;
  final String? description;
  final String? topic;
  final String sourceLang;
  final String targetLang;
  final bool isPremium;
  final bool isSubscribed;
  final int itemsCount;

  /// CEFR level (or range, e.g. "A2–B1") for the card's «N слов · A2–B1» line. Nullable — shown only
  /// when present. Read from `cefr`, falling back to `level`, so it renders whichever key the store
  /// feed ships (the field was added after the frozen contract; openapi not yet updated).
  final String? cefr;
  final String? imageUrl;
  final String? imageAuthor;
  final String? imageAuthorUrl;

  /// A PHRASEBOOK — a collection whose language carries no trainers at all (DECISIONS пп. 84, 136).
  ///
  /// NULLABLE on purpose, and the null is the point: `null` means the feed did not state it, not
  /// «no». `/sync` has carried the flag since A-4 and `/store/collections` does not yet, so a build
  /// of this app can meet either server, and reading a missing field as `false` would print a pair
  /// of flags on a Chinese deck — the exact lie the flag exists to prevent. The card therefore shows
  /// the pair only when the answer is a stated `false`.
  final bool? isReference;

  const StoreCollection({
    required this.id,
    required this.title,
    this.description,
    this.topic,
    required this.sourceLang,
    required this.targetLang,
    required this.isPremium,
    required this.isSubscribed,
    required this.itemsCount,
    this.cefr,
    this.imageUrl,
    this.imageAuthor,
    this.imageAuthorUrl,
    this.isReference,
  });

  StoreCollection copyWith({bool? isSubscribed}) => StoreCollection(
    id: id,
    title: title,
    description: description,
    topic: topic,
    sourceLang: sourceLang,
    targetLang: targetLang,
    isPremium: isPremium,
    isSubscribed: isSubscribed ?? this.isSubscribed,
    itemsCount: itemsCount,
    cefr: cefr,
    imageUrl: imageUrl,
    imageAuthor: imageAuthor,
    imageAuthorUrl: imageAuthorUrl,
    isReference: isReference,
  );

  factory StoreCollection.fromJson(Map<String, dynamic> j) => StoreCollection(
    id: j['id'] as String,
    title: (j['title'] as String?) ?? '',
    description: j['description'] as String?,
    topic: j['topic'] as String?,
    sourceLang: (j['source_lang'] as String?) ?? 'ru',
    targetLang: (j['target_lang'] as String?) ?? 'en',
    isPremium: (j['is_premium'] as bool?) ?? false,
    isSubscribed: (j['is_subscribed'] as bool?) ?? false,
    itemsCount: (j['items_count'] as int?) ?? 0,
    cefr: (j['cefr'] ?? j['level']) as String?,
    imageUrl: j['image_url'] as String?,
    imageAuthor: j['image_author'] as String?,
    imageAuthorUrl: j['image_author_url'] as String?,
    // Read with `containsKey`, not with a `?? false`: the absence of the key and a stated `false`
    // are different answers here, and the parse must survive a server that has neither.
    isReference: j.containsKey('is_reference') ? (j['is_reference'] as bool?) ?? false : null,
  );
}

/// One preview row in the store sheet (кадры 8c/8d): «term — translation».
class StorePreviewItem {
  final String term;
  final String translation;
  const StorePreviewItem({required this.term, required this.translation});

  factory StorePreviewItem.fromJson(Map<String, dynamic> j) => StorePreviewItem(
    term: ((j['text'] ?? j['term']) as String?) ?? '',
    translation: (j['translation'] as String?) ?? '',
  );
}

/// The store preview (`GET /store/collections/{id}/preview`): the first few terms + the full count,
/// so the sheet can show «что внутри» + «и ещё N слов» before subscribing. Keys parsed defensively
/// (`items`/`preview`/`terms`; `total`/`items_count`) — the endpoint landed after the frozen contract
/// and isn't in openapi yet.
class StorePreview {
  final List<StorePreviewItem> items;
  final int total;
  const StorePreview({required this.items, required this.total});

  /// Words beyond the shown preview rows (the «и ещё N слов» line); 0 hides it.
  int get more => (total - items.length).clamp(0, total);

  factory StorePreview.fromJson(Map<String, dynamic> j) {
    final raw = (j['items'] ?? j['preview'] ?? j['terms']) as List? ?? const [];
    final items = raw.map((e) => StorePreviewItem.fromJson(e as Map<String, dynamic>)).toList();
    return StorePreview(
      items: items,
      total: (j['total'] ?? j['items_count'] ?? items.length) as int,
    );
  }
}

class Stats {
  final int totalWords;
  final int learned;
  final int mastered;
  final int dueToday;
  final int reviewsTotal;
  final int streakDays;

  /// Local (user-tz) calendar dates `YYYY-MM-DD` with any activity — the server-truth activity
  /// calendar (F18). Merged into the local `daily_activity` map so a relogin restores it.
  final List<String> activeDays;

  /// Daily NEW-term quota state (F13). [newRemaining] = how many new terms a "Learn N" home CTA
  /// may still introduce today (0 = new-term limit reached; reviews are never gated by it).
  final int newGoal;
  final int newRemaining;

  Stats({
    required this.totalWords,
    required this.learned,
    required this.mastered,
    required this.dueToday,
    required this.reviewsTotal,
    required this.streakDays,
    this.activeDays = const [],
    this.newGoal = 0,
    this.newRemaining = 0,
  });

  factory Stats.fromJson(Map<String, dynamic> j) => Stats(
    totalWords: (j['total_terms'] as int?) ?? 0,
    learned: (j['learned'] as int?) ?? 0,
    mastered: (j['mastered'] as int?) ?? 0,
    dueToday: (j['due_today'] as int?) ?? 0,
    reviewsTotal: (j['reviews_today'] as int?) ?? 0,
    streakDays: (j['streak_days'] as int?) ?? 0,
    activeDays: (j['active_days'] as List?)?.map((e) => e as String).toList() ?? const [],
    newGoal: (j['new_goal'] as int?) ?? 0,
    newRemaining: (j['new_remaining'] as int?) ?? 0,
  );
}

/// Derived learning progress for one collection (from `GET /study/progress`).
class CollectionProgress {
  final String collectionId;
  final int total;
  final int learned;
  final int mastered;
  final int due;

  CollectionProgress({
    required this.collectionId,
    required this.total,
    required this.learned,
    required this.mastered,
    required this.due,
  });

  /// Share of the collection's terms that are learned (state = review), 0..1.
  double get ratio => total == 0 ? 0 : (learned / total).clamp(0, 1);

  factory CollectionProgress.fromJson(Map<String, dynamic> j) => CollectionProgress(
    collectionId: j['collection_id'] as String,
    total: (j['total'] as int?) ?? 0,
    learned: (j['learned'] as int?) ?? 0,
    mastered: (j['mastered'] as int?) ?? 0,
    due: (j['due'] as int?) ?? 0,
  );
}

/// Daily AI-generation allowance, from `/auth/me`'s `generation` block. `resetsAt` is an absolute
/// UTC instant (the quota's next-day boundary) — render it in device-local time.
class GenerationQuota {
  final int limit;
  final int used;
  final int remaining;
  final DateTime resetsAt;

  const GenerationQuota({
    required this.limit,
    required this.used,
    required this.remaining,
    required this.resetsAt,
  });

  bool get exhausted => remaining <= 0;

  factory GenerationQuota.fromJson(Map<String, dynamic> j) => GenerationQuota(
    limit: (j['limit'] as int?) ?? 0,
    used: (j['used'] as int?) ?? 0,
    remaining: (j['remaining'] as int?) ?? 0,
    resetsAt: DateTime.tryParse((j['resets_at'] as String?) ?? '')?.toLocal() ?? DateTime.now(),
  );
}

class AppUser {
  final String id;
  final String name;
  final String? email;
  final String? avatar;
  final Profile? profile;

  /// The generation allowance, present on `/auth/me` responses. Transient (NOT persisted in
  /// [toJson]) so a cached user never carries a stale quota — the create screen fetches it fresh.
  final GenerationQuota? quota;

  AppUser({
    required this.id,
    required this.name,
    this.email,
    this.avatar,
    this.profile,
    this.quota,
  });

  factory AppUser.fromJson(Map<String, dynamic> j) => AppUser(
    id: j['id'] as String,
    name: (j['name'] as String?) ?? 'Learner',
    email: j['email'] as String?,
    avatar: j['avatar'] as String?,
    profile: j['profile'] != null ? Profile.fromJson(j['profile'] as Map<String, dynamic>) : null,
    quota: j['generation'] != null
        ? GenerationQuota.fromJson(j['generation'] as Map<String, dynamic>)
        : null,
  );

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'email': ?email,
    'avatar': ?avatar,
    'profile': ?profile?.toJson(),
  };
}

/// The pollable state of a generation request (`GET /generations/{id}`).
class GenerationStatusView {
  final String status; // pending | running | succeeded | failed
  final String? collectionId;
  final String? error;
  final int? requested;
  final int? delivered;

  const GenerationStatusView({
    required this.status,
    this.collectionId,
    this.error,
    this.requested,
    this.delivered,
  });

  bool get isSucceeded => status == 'succeeded';
  bool get isFailed => status == 'failed';
  bool get isTerminal => isSucceeded || isFailed;
}

/// A triage swipe verdict. Three, not two — a binary choice makes people lie
/// toward "known". The value is exactly what `POST /triage/batch` expects.
enum TriageVerdict {
  known('known'), // → known
  unknown('unknown'), // ← not known → stays new
  unsure('unsure'); // ↑ not sure → straight to learning

  const TriageVerdict(this.value);
  final String value;
}

/// One card in the triage queue (`GET /triage/queue`) — self-contained, so the
/// whole stack can be swiped offline after a single fetch.
class TriageCard {
  final String termId;
  final String text;
  final String translation;
  final String type; // word | phrase | idiom | phrasal_verb
  final String? transcription;
  final String? example;
  final String? exampleTranslation;
  final String? imageUrl; // Pexels photo, shown on the flipped (back) face

  TriageCard({
    required this.termId,
    required this.text,
    required this.translation,
    required this.type,
    this.transcription,
    this.example,
    this.exampleTranslation,
    this.imageUrl,
  });

  /// Phrase-like = anything not explicitly `word`, so new/unknown types (idiom,
  /// phrasal_verb, …) fall back to phrase behaviour instead of being treated as words.
  bool get isPhrase => type != 'word';

  factory TriageCard.fromJson(Map<String, dynamic> j) => TriageCard(
    termId: j['term_id'] as String,
    text: (j['text'] as String?) ?? '',
    translation: (j['translation'] as String?) ?? '',
    type: (j['type'] as String?) ?? 'word',
    transcription: j['transcription'] as String?,
    example: j['example'] as String?,
    exampleTranslation: j['example_translation'] as String?,
    imageUrl: j['image_url'] as String?,
  );
}

/// One page of the triage queue: the cards to swipe now, plus [remaining] — how many eligible
/// terms are left AFTER this page on the server (what the next GET will serve). Lets the screen
/// show honest progress ("N more after sync") without claiming a total it can't swipe offline.
class TriageDeck {
  final List<TriageCard> cards;
  final int remaining;

  const TriageDeck({required this.cards, required this.remaining});
}

// ---- Search -----------------------------------------------------------------

/// One hit of the FREE search over terms that already exist, plus the one fact that makes the save
/// button honest: [folders], the caller's own folders this word is already in.
class SearchHit {
  final String termId;
  final String text;
  final String type; // word | phrase | idiom | phrasal_verb
  final String? transcription;
  final String? translation;

  /// What the word MEANS, in the language being learned. Null on the store catalogue, which has no
  /// descriptions and is not being backfilled.
  final String? description;

  final String? example;
  final String? exampleTranslation;
  final String? cefr;

  /// The caller's OWN folders holding this word. Empty = not saved yet, so the main button offers
  /// «+ Сохранённые»; non-empty and the button reads «В „…"» and does nothing.
  final List<SavedFolder> folders;

  const SearchHit({
    required this.termId,
    required this.text,
    required this.type,
    this.transcription,
    this.translation,
    this.description,
    this.example,
    this.exampleTranslation,
    this.cefr,
    this.folders = const [],
  });

  bool get isSaved => folders.isNotEmpty;

  factory SearchHit.fromJson(Map<String, dynamic> j) => SearchHit(
    termId: j['term_id'] as String,
    text: (j['text'] as String?) ?? '',
    type: (j['type'] as String?) ?? 'word',
    transcription: j['transcription'] as String?,
    translation: j['translation'] as String?,
    description: j['description'] as String?,
    example: j['example'] as String?,
    exampleTranslation: j['example_translation'] as String?,
    cefr: j['cefr'] as String?,
    folders: ((j['folders'] as List?) ?? const [])
        .map((e) => SavedFolder.fromJson(e as Map<String, dynamic>))
        .toList(growable: false),
  );
}

/// A folder a word sits in, as named on a search card.
class SavedFolder {
  final String id;
  final String title;
  final bool isDefault;

  const SavedFolder({required this.id, required this.title, required this.isDefault});

  factory SavedFolder.fromJson(Map<String, dynamic> j) => SavedFolder(
    id: j['id'] as String,
    title: (j['title'] as String?) ?? '',
    isDefault: (j['is_default'] as bool?) ?? false,
  );
}

/// A word the model looked up. NOT a term yet — nothing exists in the database until the learner
/// saves it, which is what keeps the catalogue free of words nobody wanted.
class LookupCard {
  final String lookupId;
  final String text;
  final String type;
  final String? transcription;
  final String? translation;

  /// One or two A2–B1 sentences in the language being learned, guaranteed by a server-side barrier
  /// never to contain the word itself.
  final String description;

  final String? example;
  final String? exampleTranslation;
  final String? cefr;

  /// True when THIS call paid for the answer rather than being served from the shared cache.
  final bool fresh;

  const LookupCard({
    required this.lookupId,
    required this.text,
    required this.type,
    this.transcription,
    this.translation,
    this.description = '',
    this.example,
    this.exampleTranslation,
    this.cefr,
    this.fresh = false,
  });

  factory LookupCard.fromJson(Map<String, dynamic> j) => LookupCard(
    lookupId: j['lookup_id'] as String,
    text: (j['text'] as String?) ?? '',
    type: (j['type'] as String?) ?? 'word',
    transcription: j['transcription'] as String?,
    translation: j['translation'] as String?,
    description: (j['description'] as String?) ?? '',
    example: j['example'] as String?,
    exampleTranslation: j['example_translation'] as String?,
    cefr: j['cefr'] as String?,
    fresh: (j['fresh'] as bool?) ?? false,
  );
}

/// What came of a lookup: an answer, or an honest «not today».
///
/// The cap is not an error and this type is why. «На сегодня лимит» is a state the screen has a
/// face for — it shows the free results beside it — and modelling it as a thrown exception would
/// make the honest answer the exceptional path.
class LookupOutcome {
  final LookupCard? card;
  final bool limitReached;
  final int dailyCap;
  final int usedToday;

  /// The model could not name a word for what was typed. [card] is null and nothing was refused —
  /// the screen says «не получилось распознать, проверьте написание», which is advice, not an error.
  final bool notRecognized;

  const LookupOutcome({
    this.card,
    this.limitReached = false,
    this.dailyCap = 0,
    this.usedToday = 0,
    this.notRecognized = false,
  });

  factory LookupOutcome.fromJson(Map<String, dynamic> j) {
    final raw = j['lookup'] as Map<String, dynamic>?;
    return LookupOutcome(
      card: raw == null ? null : LookupCard.fromJson(raw),
      limitReached: (j['limit_reached'] as bool?) ?? false,
      dailyCap: (j['daily_cap'] as int?) ?? 0,
      usedToday: (j['used_today'] as int?) ?? 0,
      notRecognized: (j['not_recognized'] as bool?) ?? false,
    );
  }
}

/// What the one-tap save actually did. [collectionTitle] rides along because the confirmation NAMES
/// the folder and the learner may have renamed it — a hardcoded «Сохранённые» would lie to exactly
/// the person who changed it.
class SavedSearchResult {
  final String termId;
  final String collectionId;
  final String collectionTitle;
  final bool collectionIsDefault;

  /// False when the word was already in this folder — the tap was a replay, not a save.
  final bool added;

  /// False when the pair was already in the pool; the word resumes, it does not restart.
  final bool enrolled;

  const SavedSearchResult({
    required this.termId,
    required this.collectionId,
    required this.collectionTitle,
    required this.collectionIsDefault,
    required this.added,
    required this.enrolled,
  });

  factory SavedSearchResult.fromJson(Map<String, dynamic> j) => SavedSearchResult(
    termId: j['term_id'] as String,
    collectionId: j['collection_id'] as String,
    collectionTitle: (j['collection_title'] as String?) ?? '',
    collectionIsDefault: (j['collection_is_default'] as bool?) ?? false,
    added: (j['added'] as bool?) ?? false,
    enrolled: (j['enrolled'] as bool?) ?? false,
  );
}

/// The grey line under the search field: a first, cheap guess at what a word means.
///
/// It is NOT the word's card. The card (translation, description, example, level) is written by the
/// lookup model against rules about register and level that a general-purpose translator knows
/// nothing about — this is a hint that arrives first and is replaced by the real thing.
///
/// Every «nothing to show» is a field rather than an error, because there is no failure here worth
/// interrupting somebody who is typing: [translation] is simply null and the line is not drawn.
class InstantHint {
  final String query;
  final String? translation;

  /// No provider configured server-side. Nothing to retry, nothing to tell the learner.
  final bool featureDisabled;

  /// The month's translation budget is spent. The full lookup is unaffected.
  final bool limitReached;

  /// The query was in the learner's own language, so [translation] holds the ENGLISH word.
  ///
  /// Used for one thing only: deciding which of the two strings is the headline on the small card.
  /// The screen never mentions languages, directions or detection — it just answers.
  final bool reversed;

  /// Longer than a phrase. Nothing was asked and nothing was bought.
  final bool queryTooLong;

  const InstantHint({
    required this.query,
    this.translation,
    this.featureDisabled = false,
    this.limitReached = false,
    this.reversed = false,
    this.queryTooLong = false,
  });

  /// Is there a line to draw at all?
  bool get hasText => (translation ?? '').trim().isNotEmpty;

  /// The word being LEARNED, whichever side of the pair it arrived on — or null when we have none.
  ///
  /// This is the string the learner asked for: they typed «случай» to find out it is «occasion»,
  /// and they typed «occasion» already knowing how it is spelled. Either way the English word is
  /// the answer and everything else on the card is support for it.
  String? headline(String query) {
    final answer = (translation ?? '').trim();

    return reversed ? (answer.isEmpty ? null : answer) : query.trim();
  }

  /// The other string — what confirms the headline. Null when there is nothing to confirm it with.
  String? support(String query) {
    final answer = (translation ?? '').trim();
    final asked = query.trim();
    if (reversed) return asked.isEmpty ? null : asked;

    // A translation identical to the query says nothing; that is a hint that failed, not an answer.
    return answer.isEmpty || answer.toLowerCase() == asked.toLowerCase() ? null : answer;
  }

  factory InstantHint.fromJson(Map<String, dynamic> j) => InstantHint(
    query: (j['query'] as String?) ?? '',
    translation: j['translation'] as String?,
    featureDisabled: (j['feature_disabled'] as bool?) ?? false,
    limitReached: (j['limit_reached'] as bool?) ?? false,
    reversed: (j['reversed'] as bool?) ?? false,
    queryTooLong: (j['query_too_long'] as bool?) ?? false,
  );
}

// ---- The home screen's day (`GET /home-plan`) --------------------------------

/// WHICH home screen this is (кадры 17a–17d). The server names it, so the app and the API can't
/// disagree about the state the same numbers describe.
enum HomeStateKind {
  /// 17a — there is a session to run today.
  plan,

  /// 17b — it is done and something was answered today.
  done,

  /// 17d — nothing to do and nothing answered: the schedule is simply ahead.
  idle,

  /// 17c — the very first day: no collections, no pool, nothing answered.
  empty;

  static HomeStateKind parse(String? raw) => switch (raw) {
    'plan' => HomeStateKind.plan,
    'done' => HomeStateKind.done,
    'idle' => HomeStateKind.idle,
    _ => HomeStateKind.empty,
  };
}

/// «Сессия на сегодня: N слов · ~M минут», and what it is made of.
///
/// [total] is deliberately NOT `repeat + new`: [triage] is the swipe pass over words the learner
/// has not decided about yet, which is part of the DAY and not of the study session.
class HomeSession {
  const HomeSession({
    required this.repeat,
    required this.newTerms,
    required this.triage,
    required this.total,
    required this.estimatedMinutes,
    required this.avgSecondsPerCard,
    this.triageCollectionId,
    this.triageCollectionTitle,
  });

  final int repeat, newTerms, triage, total, avgSecondsPerCard;

  /// Null when there is nothing to do — «≈ 0 минут» is not a thing the screen says.
  final int? estimatedMinutes;

  /// Where the swipe pass leads; null when [triage] is 0.
  final String? triageCollectionId;
  final String? triageCollectionTitle;

  factory HomeSession.fromJson(Map<String, dynamic> j) => HomeSession(
    repeat: (j['repeat'] as int?) ?? 0,
    newTerms: (j['new'] as int?) ?? 0,
    triage: (j['triage'] as int?) ?? 0,
    total: (j['total'] as int?) ?? 0,
    estimatedMinutes: j['estimated_minutes'] as int?,
    avgSecondsPerCard: (j['avg_seconds_per_card'] as int?) ?? 0,
    triageCollectionId: j['triage_collection_id'] as String?,
    triageCollectionTitle: j['triage_collection_title'] as String?,
  );
}

/// «В работе — N слов · K ждут очереди · при T в день новым до очереди ~D дней».
class HomeInWork {
  const HomeInWork({
    required this.total,
    required this.waiting,
    required this.perDay,
    required this.newRemaining,
    this.daysUntilQueue,
  });

  final int total, waiting, perDay, newRemaining;

  /// Null when nothing waits, and null when the learner takes no new words at all.
  final int? daysUntilQueue;

  factory HomeInWork.fromJson(Map<String, dynamic> j) => HomeInWork(
    total: (j['total'] as int?) ?? 0,
    waiting: (j['waiting'] as int?) ?? 0,
    perDay: (j['per_day'] as int?) ?? 0,
    newRemaining: (j['new_remaining'] as int?) ?? 0,
    daysUntilQueue: j['days_until_queue'] as int?,
  );
}

/// One word of «На грани забывания» — with the day it falls due, not just a count.
class HomeEdgeTerm {
  const HomeEdgeTerm({
    required this.termId,
    required this.text,
    required this.inDays,
    this.translation,
  });

  final String termId, text;
  final String? translation;

  /// Whole days until the repeat; 1 = «выпадет завтра».
  final int inDays;

  factory HomeEdgeTerm.fromJson(Map<String, dynamic> j) => HomeEdgeTerm(
    termId: j['term_id'] as String? ?? '',
    text: j['text'] as String? ?? '',
    translation: j['translation'] as String?,
    inDays: (j['in_days'] as int?) ?? 1,
  );
}

/// One word of «Далось труднее всего» — and how often the last run missed it.
class HomeHardTerm {
  const HomeHardTerm({required this.termId, required this.text, required this.errors, this.translation});

  final String termId, text;
  final String? translation;
  final int errors;

  factory HomeHardTerm.fromJson(Map<String, dynamic> j) => HomeHardTerm(
    termId: j['term_id'] as String? ?? '',
    text: j['text'] as String? ?? '',
    translation: j['translation'] as String?,
    errors: (j['errors'] as int?) ?? 0,
  );
}

/// What today produced — STUDY answers only, so a free practice run never closes the day.
class HomeToday {
  const HomeToday({required this.answered, required this.seconds});

  final int answered, seconds;

  factory HomeToday.fromJson(Map<String, dynamic> j) =>
      HomeToday(answered: (j['answered'] as int?) ?? 0, seconds: (j['seconds'] as int?) ?? 0);
}

/// «Следующий повтор — 28 августа, 14 слов».
class HomeNextReview {
  const HomeNextReview({required this.date, required this.count});

  /// The learner's own calendar day, `YYYY-MM-DD`.
  final String date;
  final int count;

  factory HomeNextReview.fromJson(Map<String, dynamic> j) =>
      HomeNextReview(date: j['date'] as String? ?? '', count: (j['count'] as int?) ?? 0);
}

/// «Продолжить „Ветклинику“ — 4 из 16 слов · брошено 5 дней назад».
class HomeContinue {
  const HomeContinue({
    required this.collectionId,
    required this.title,
    required this.done,
    required this.total,
    required this.remaining,
    this.abandonedDays,
  });

  final String collectionId, title;
  final int done, total, remaining;
  final int? abandonedDays;

  factory HomeContinue.fromJson(Map<String, dynamic> j) => HomeContinue(
    collectionId: j['collection_id'] as String? ?? '',
    title: j['title'] as String? ?? '',
    done: (j['done'] as int?) ?? 0,
    total: (j['total'] as int?) ?? 0,
    remaining: (j['remaining'] as int?) ?? 0,
    abandonedDays: j['abandoned_days'] as int?,
  );
}

/// «…или выбрать из 17 готовых» — the store as the home needs it: a number and a taste.
class HomeStore {
  const HomeStore({required this.count, required this.topics});

  final int count;
  final List<String> topics;

  factory HomeStore.fromJson(Map<String, dynamic> j) => HomeStore(
    count: (j['count'] as int?) ?? 0,
    topics: ((j['topics'] as List?) ?? const []).map((e) => e as String).toList(),
  );
}

/// THE HOME SCREEN'S DAY (`GET /home-plan`, кадры 17a–17d).
///
/// Every nullable field here is a block the design does not draw when it has nothing to say. The
/// screen's rule — «блок без данных не рисуется» — is enforced against exactly these nulls, which
/// is why they are nullable instead of zeroed.
class HomePlan {
  const HomePlan({
    required this.state,
    required this.session,
    required this.inWork,
    required this.edge,
    required this.hardest,
    required this.store,
    this.today,
    this.nextReview,
    this.unfinished,
  });

  final HomeStateKind state;
  final HomeSession session;
  final HomeInWork inWork;
  final List<HomeEdgeTerm> edge;
  final List<HomeHardTerm> hardest;
  final HomeStore store;

  /// Null when nothing was answered today.
  final HomeToday? today;

  /// Null when nothing is scheduled ahead at all.
  final HomeNextReview? nextReview;

  /// Null when no collection was started and left.
  final HomeContinue? unfinished;

  factory HomePlan.fromJson(Map<String, dynamic> j) => HomePlan(
    state: HomeStateKind.parse(j['state'] as String?),
    session: HomeSession.fromJson((j['session'] as Map<String, dynamic>?) ?? const {}),
    inWork: HomeInWork.fromJson((j['in_work'] as Map<String, dynamic>?) ?? const {}),
    edge: ((j['edge'] as List?) ?? const [])
        .map((e) => HomeEdgeTerm.fromJson(e as Map<String, dynamic>))
        .toList(),
    hardest: ((j['hardest'] as List?) ?? const [])
        .map((e) => HomeHardTerm.fromJson(e as Map<String, dynamic>))
        .toList(),
    store: HomeStore.fromJson((j['store'] as Map<String, dynamic>?) ?? const {}),
    today: j['today'] == null ? null : HomeToday.fromJson(j['today'] as Map<String, dynamic>),
    nextReview: j['next_review'] == null
        ? null
        : HomeNextReview.fromJson(j['next_review'] as Map<String, dynamic>),
    unfinished: j['continue'] == null
        ? null
        : HomeContinue.fromJson(j['continue'] as Map<String, dynamic>),
  );
}

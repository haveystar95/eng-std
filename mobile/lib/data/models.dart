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
  });

  bool get isAi => source == 'ai';

  /// The user's own, fully-editable collection (created or generated). Store sets are `system`/`shared`.
  bool get isOwned => type == 'custom';

  /// A store set in «Мои»: full learning cycle (triage/session/progress) but no editing — no
  /// rename, no add/edit/delete words; removing it means unsubscribe, not delete.
  bool get readOnly => isSubscribed || !isOwned;

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
  final String type; // word | phrase | idiom | phrasal_verb
  final String? audioUrl; // optional override; null → system TTS
  final String? ttsHint; // reading fix for system TTS, e.g. "ATM" → "A T M"
  final String? status; // learning state from local progress (new|learning|review|relearning|known); null → not started
  final String? imageUrl; // Pexels photo; null → type-badge placeholder
  final String? imageAuthor;
  final String? imageAuthorUrl;

  Word({
    required this.termId,
    required this.term,
    required this.translation,
    this.transcription,
    this.example,
    required this.type,
    this.audioUrl,
    this.ttsHint,
    this.status,
    this.imageUrl,
    this.imageAuthor,
    this.imageAuthorUrl,
  });

  /// Convenience so existing screens that used `word.id` keep working.
  String get id => termId;

  /// Phrase-like = anything the server didn't explicitly tag `word`. Keeps the client
  /// forward-compatible: new/unknown types (idiom, phrasal_verb, …) fall back to phrase
  /// behaviour rather than being mis-treated as single words.
  bool get isPhrase => type != 'word';

  factory Word.fromJson(Map<String, dynamic> j) => Word(
        termId: (j['term_id'] ?? j['id']) as String,
        term: ((j['text'] ?? j['term']) as String?) ?? '',
        translation: (j['translation'] as String?) ?? '',
        transcription: j['transcription'] as String?,
        example: j['example'] as String?,
        type: (j['type'] as String?) ?? 'word',
        audioUrl: j['audio_url'] as String?,
        ttsHint: j['tts_hint'] as String?,
      );
}

class ReviewCard {
  final Word word;
  ReviewCard({required this.word});

  /// backend2 `/study/due` returns flat cards (no `word` wrapper).
  factory ReviewCard.fromJson(Map<String, dynamic> j) =>
      ReviewCard(word: Word.fromJson(j));
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
  cloze('cloze');

  const ExerciseMode(this.wire);
  final String wire;

  static ExerciseMode fromWire(String? v) =>
      ExerciseMode.values.firstWhere((m) => m.wire == v, orElse: () => ExerciseMode.typing);

  /// A production mode reproduces from memory (free recall / assembly), so the answer is TYPED or
  /// assembled rather than picked. Recognition modes (pick one of four) are handled inline.
  bool get isTyped => this == typing || this == cloze || this == listening;
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

  const StudySession({
    required this.sessionId,
    required this.cards,
    this.builtLocally = false,
  });
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
        resetsAt: DateTime.tryParse((j['resets_at'] as String?) ?? '')?.toLocal() ??
            DateTime.now(),
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
        profile: j['profile'] != null
            ? Profile.fromJson(j['profile'] as Map<String, dynamic>)
            : null,
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

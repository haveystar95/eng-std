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
  });

  bool get isAi => source == 'ai';

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
  final String type; // word | phrase
  final String? audioUrl; // optional override; null → system TTS
  final String? ttsHint; // reading fix for system TTS, e.g. "ATM" → "A T M"
  final String? status; // learning state from local progress (new|learning|review|relearning|known); null → not started

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
  });

  /// Convenience so existing screens that used `word.id` keep working.
  String get id => termId;

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

class Profile {
  final String nativeLanguage;
  final String targetLanguage;
  final String cefrLevel;
  final int dailyGoal;

  Profile({
    required this.nativeLanguage,
    required this.targetLanguage,
    required this.cefrLevel,
    required this.dailyGoal,
  });

  factory Profile.fromJson(Map<String, dynamic> j) => Profile(
        nativeLanguage: (j['native_language'] as String?) ?? 'ru',
        targetLanguage: (j['target_language'] as String?) ?? 'en',
        cefrLevel: (j['cefr_level'] as String?) ?? 'B1',
        dailyGoal: (j['daily_goal'] as int?) ?? 20,
      );

  Map<String, dynamic> toJson() => {
        'native_language': nativeLanguage,
        'target_language': targetLanguage,
        'cefr_level': cefrLevel,
        'daily_goal': dailyGoal,
      };
}

class Stats {
  final int totalWords;
  final int learned;
  final int mastered;
  final int dueToday;
  final int reviewsTotal;
  final int streakDays;

  Stats({
    required this.totalWords,
    required this.learned,
    required this.mastered,
    required this.dueToday,
    required this.reviewsTotal,
    required this.streakDays,
  });

  factory Stats.fromJson(Map<String, dynamic> j) => Stats(
        totalWords: (j['total_terms'] as int?) ?? 0,
        learned: (j['learned'] as int?) ?? 0,
        mastered: (j['mastered'] as int?) ?? 0,
        dueToday: (j['due_today'] as int?) ?? 0,
        reviewsTotal: (j['reviews_today'] as int?) ?? 0,
        streakDays: (j['streak_days'] as int?) ?? 0,
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

class AppUser {
  final String id;
  final String name;
  final String? email;
  final String? avatar;
  final Profile? profile;

  AppUser({
    required this.id,
    required this.name,
    this.email,
    this.avatar,
    this.profile,
  });

  factory AppUser.fromJson(Map<String, dynamic> j) => AppUser(
        id: j['id'] as String,
        name: (j['name'] as String?) ?? 'Learner',
        email: j['email'] as String?,
        avatar: j['avatar'] as String?,
        profile: j['profile'] != null
            ? Profile.fromJson(j['profile'] as Map<String, dynamic>)
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
  final String type; // word | phrase
  final String? transcription;
  final String? example;
  final String? exampleTranslation;

  TriageCard({
    required this.termId,
    required this.text,
    required this.translation,
    required this.type,
    this.transcription,
    this.example,
    this.exampleTranslation,
  });

  bool get isPhrase => type == 'phrase';

  factory TriageCard.fromJson(Map<String, dynamic> j) => TriageCard(
        termId: j['term_id'] as String,
        text: (j['text'] as String?) ?? '',
        translation: (j['translation'] as String?) ?? '',
        type: (j['type'] as String?) ?? 'word',
        transcription: j['transcription'] as String?,
        example: j['example'] as String?,
        exampleTranslation: j['example_translation'] as String?,
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

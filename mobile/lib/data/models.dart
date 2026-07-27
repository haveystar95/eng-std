/// Rating used by the FSRS scheduler on the backend.
enum Rating {
  again(1),
  hard(2),
  good(3),
  easy(4);

  const Rating(this.value);
  final int value;
}

class WordCollection {
  final int id;
  final String title;
  final String? emoji;
  final String? topic;
  final String source; // "manual" | "ai"
  final int wordsCount;

  WordCollection({
    required this.id,
    required this.title,
    this.emoji,
    this.topic,
    required this.source,
    required this.wordsCount,
  });

  factory WordCollection.fromJson(Map<String, dynamic> j) => WordCollection(
        id: j['id'] as int,
        title: j['title'] as String,
        emoji: j['emoji'] as String?,
        topic: j['topic'] as String?,
        source: (j['source'] as String?) ?? 'manual',
        wordsCount: (j['words_count'] as int?) ?? 0,
      );
}

class Word {
  final int id;
  final int collectionId;
  final String term;
  final String translation;
  final String? transcription;
  final String? example;
  final String? cefrLevel;

  Word({
    required this.id,
    required this.collectionId,
    required this.term,
    required this.translation,
    this.transcription,
    this.example,
    this.cefrLevel,
  });

  factory Word.fromJson(Map<String, dynamic> j) => Word(
        id: j['id'] as int,
        collectionId: (j['collection_id'] as int?) ?? 0,
        term: j['term'] as String,
        translation: j['translation'] as String,
        transcription: j['transcription'] as String?,
        example: j['example'] as String?,
        cefrLevel: j['cefr_level'] as String?,
      );
}

class ReviewCard {
  final Word word;
  ReviewCard({required this.word});

  factory ReviewCard.fromJson(Map<String, dynamic> j) =>
      ReviewCard(word: Word.fromJson(j['word'] as Map<String, dynamic>));
}

class AiCheckResult {
  final bool correct;
  final int score;
  final String feedback;
  final String? corrected;

  AiCheckResult({
    required this.correct,
    required this.score,
    required this.feedback,
    this.corrected,
  });

  factory AiCheckResult.fromJson(Map<String, dynamic> j) => AiCheckResult(
        correct: j['correct'] as bool,
        score: (j['score'] as int?) ?? 0,
        feedback: (j['feedback'] as String?) ?? '',
        corrected: j['corrected'] as String?,
      );
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
}

class CollectionStat {
  final int id;
  final String title;
  final String source;
  final int total;
  final int learned;
  final int due;

  CollectionStat({
    required this.id,
    required this.title,
    required this.source,
    required this.total,
    required this.learned,
    required this.due,
  });

  double get progress => total == 0 ? 0 : learned / total;

  factory CollectionStat.fromJson(Map<String, dynamic> j) => CollectionStat(
        id: j['id'] as int,
        title: j['title'] as String,
        source: (j['source'] as String?) ?? 'manual',
        total: (j['total'] as int?) ?? 0,
        learned: (j['learned'] as int?) ?? 0,
        due: (j['due'] as int?) ?? 0,
      );
}

class Stats {
  final int totalWords;
  final int learned;
  final int mastered;
  final int dueToday;
  final int reviewsTotal;
  final List<CollectionStat> collections;

  Stats({
    required this.totalWords,
    required this.learned,
    required this.mastered,
    required this.dueToday,
    required this.reviewsTotal,
    required this.collections,
  });

  factory Stats.fromJson(Map<String, dynamic> j) => Stats(
        totalWords: (j['total_words'] as int?) ?? 0,
        learned: (j['learned'] as int?) ?? 0,
        mastered: (j['mastered'] as int?) ?? 0,
        dueToday: (j['due_today'] as int?) ?? 0,
        reviewsTotal: (j['reviews_total'] as int?) ?? 0,
        collections: ((j['collections'] as List?) ?? [])
            .map((e) => CollectionStat.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

class AppUser {
  final int id;
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
        id: j['id'] as int,
        name: (j['name'] as String?) ?? 'Learner',
        email: j['email'] as String?,
        avatar: j['avatar'] as String?,
        profile: j['profile'] != null
            ? Profile.fromJson(j['profile'] as Map<String, dynamic>)
            : null,
      );
}

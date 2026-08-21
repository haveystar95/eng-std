import '../../data/models.dart';

/// Where a word card was opened FROM — and therefore what it is for.
enum WordCardMode {
  /// From search. The card is an offer: read it, and decide whether to keep it (кадры 06/07).
  search,

  /// From one of the learner's own folders. The card is a review of something already owned, so it
  /// carries the ladder and its main action is the training run (кадр 09).
  folder,
}

/// Everything a word card draws, gathered from whichever of the three shapes it came from.
///
/// ONE view model on purpose, exactly like [SearchHit] and [LookupCard] share one card widget: the
/// three sources (a database hit, a fresh lookup, a mirrored term in a folder) differ in what they
/// KNOW, not in what they look like, and a missing field must degrade to a missing block rather
/// than to a second layout.
class WordCardSubject {
  const WordCardSubject({
    required this.text,
    required this.type,
    this.termId,
    this.lookupId,
    this.transcription,
    this.translation,
    this.description,
    this.example,
    this.exampleTranslation,
    this.cefr,
    this.imageUrl,
    this.imageAuthor,
    this.imageAuthorUrl,
    this.ladderStep,
    this.isKnown = false,
    this.enrolled = false,
    this.folders = const [],
  });

  /// The catalogue term, when the word already is one. Null for a lookup nobody has saved yet.
  final String? termId;

  /// The lookup handle `/search/add` takes. Null for a word that is already a term.
  final String? lookupId;

  final String text;
  final String type;
  final String? transcription;
  final String? translation;
  final String? description;
  final String? example;
  final String? exampleTranslation;
  final String? cefr;

  final String? imageUrl;
  final String? imageAuthor;
  final String? imageAuthorUrl;

  /// The acquisition rung, 0–5, or null when the word is outside the ladder. Only a word from a
  /// folder has one — search knows nothing about progress.
  final int? ladderStep;

  final bool isKnown;
  final bool enrolled;

  /// The caller's OWN folders already holding this word.
  final List<SavedFolder> folders;

  bool get hasPhoto => (imageUrl ?? '').isNotEmpty;

  /// The folder the card names once the word is in one.
  SavedFolder? get savedIn => folders.isEmpty ? null : folders.first;

  factory WordCardSubject.fromHit(SearchHit hit, {String? imageUrl, String? imageAuthor, String? imageAuthorUrl}) =>
      WordCardSubject(
        termId: hit.termId,
        text: hit.text,
        type: hit.type,
        transcription: hit.transcription,
        translation: hit.translation,
        description: hit.description,
        example: hit.example,
        exampleTranslation: hit.exampleTranslation,
        cefr: hit.cefr,
        // `/search` carries no photo (see the findings note in the task report): the only picture a
        // search result can have is one the local term mirror already holds, and the caller passes
        // it in when it does.
        imageUrl: imageUrl,
        imageAuthor: imageAuthor,
        imageAuthorUrl: imageAuthorUrl,
        folders: hit.folders,
      );

  factory WordCardSubject.fromLookup(LookupCard card) => WordCardSubject(
        lookupId: card.lookupId,
        text: card.text,
        type: card.type,
        transcription: card.transcription,
        translation: card.translation,
        description: card.description,
        example: card.example,
        exampleTranslation: card.exampleTranslation,
        cefr: card.cefr,
      );

  factory WordCardSubject.fromWord(Word word, {List<SavedFolder> folders = const []}) => WordCardSubject(
        termId: word.termId,
        text: word.term,
        type: word.type,
        transcription: word.transcription,
        translation: word.translation,
        description: word.description,
        example: word.example,
        exampleTranslation: word.exampleTranslation,
        // The sync feed carries no CEFR for a term, so a word opened from a folder simply has no
        // level to show — the badge is absent rather than guessed.
        imageUrl: word.imageUrl,
        imageAuthor: word.imageAuthor,
        imageAuthorUrl: word.imageAuthorUrl,
        ladderStep: word.ladderStep,
        isKnown: word.isKnown,
        enrolled: word.enrolled,
        folders: folders,
      );

  WordCardSubject copyWith({List<SavedFolder>? folders}) => WordCardSubject(
        termId: termId,
        lookupId: lookupId,
        text: text,
        type: type,
        transcription: transcription,
        translation: translation,
        description: description,
        example: example,
        exampleTranslation: exampleTranslation,
        cefr: cefr,
        imageUrl: imageUrl,
        imageAuthor: imageAuthor,
        imageAuthorUrl: imageAuthorUrl,
        ladderStep: ladderStep,
        isKnown: isKnown,
        enrolled: enrolled,
        folders: folders ?? this.folders,
      );
}

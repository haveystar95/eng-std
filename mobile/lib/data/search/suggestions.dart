import '../models.dart';
import 'word_list.dart';

/// One line under the search field, before the results.
///
/// A suggestion is either a word we ALREADY KNOW — a term in the catalogue, so it can show what it
/// means the moment it appears — or a word from the offline dictionary, which is only a spelling.
/// The two are different offers and the screen must not present them as one list of equals: the
/// first can be saved, the second still has to be looked up.
class Suggestion {
  const Suggestion({required this.word, this.translation, this.termId});

  final String word;

  /// What it means, when we know. Null for a dictionary word — that is the whole distinction.
  final String? translation;

  /// The catalogue term this suggestion is, when it is one.
  final String? termId;

  bool get isKnown => termId != null;
}

/// Merges the two sources of suggestions, and the ORDER is the entire content of this function.
///
/// Words from our own database come first, always. Not because they are better spellings, but
/// because they are a different KIND of answer: the learner can see what the word means without a
/// second step, and saving it costs nothing and reaches nobody's API. A dictionary word is a guess
/// at what they are typing; a catalogue word is a thing they can have.
///
/// The dictionary then fills the rest of the slots, minus anything the catalogue already offered —
/// the same word twice, once with a translation and once without, would read as a bug.
List<Suggestion> mergeSuggestions({
  required List<SearchHit> known,
  required List<String> dictionary,
  int limit = 5,
}) {
  final out = <Suggestion>[];
  final seen = <String>{};

  for (final hit in known) {
    final word = hit.text.trim().toLowerCase();
    if (word.isEmpty || !seen.add(word)) continue;
    out.add(Suggestion(word: hit.text, translation: hit.translation, termId: hit.termId));
    if (out.length >= limit) return out;
  }

  for (final word in dictionary) {
    final key = word.trim().toLowerCase();
    if (key.isEmpty || !seen.add(key)) continue;
    out.add(Suggestion(word: word));
    if (out.length >= limit) break;
  }

  return out;
}

/// Holds the dictionary once it has been read, and reads it at most once.
///
/// Lazy because most sessions never open search, and 363 KB plus a 47 000-element sort is not
/// something to pay for on app start. Idempotent because a screen can ask twice — the future is
/// kept, not the result, so two callers arriving during the read share one parse.
class WordListLoader {
  WordListLoader({Future<WordList> Function()? read}) : _read = read ?? WordList.load;

  final Future<WordList> Function() _read;
  Future<WordList>? _pending;
  WordList? _loaded;

  /// The dictionary if it is already in memory — for a synchronous first paint with no flicker.
  WordList? get loaded => _loaded;

  Future<WordList> ensureLoaded() {
    final loaded = _loaded;
    if (loaded != null) return Future.value(loaded);

    return _pending ??= _read().then((list) {
      _loaded = list;

      return list;
    }).catchError((Object _) {
      // A missing or unreadable asset must not break search. The suggestions simply never appear,
      // and every other part of the screen — the database results, the AI lookup — is untouched.
      _pending = null;

      return WordList.parse('');
    });
  }
}

import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/services.dart' show rootBundle;

/// The offline autocomplete dictionary: ~47 000 English words, ranked by how often people say them.
///
/// ## Why this exists at all
///
/// The word the learner is typing usually is NOT in our database yet — that is the whole reason the
/// search screen has an «find with AI» button. So until they finish typing, the app has nothing to
/// offer them, and a search field that stays blank while you type feels broken even when it is
/// working perfectly. This fills that silence from a file, with no network and no cost.
///
/// ## Two orders, one file
///
/// The asset is stored in FREQUENCY order — a line's index IS its rank — because that is what the
/// suggestions have to be sorted by, and it lets the file carry no second column.
///
/// But prefix matching wants ALPHABETICAL order, so the loader builds an alphabetical index over
/// the same lines once, at load. Prefix search is then a binary search for the first matching line,
/// a walk to the last, and a rank sort over the (few) hits. No scan of 47 000 words per keystroke.
///
/// ## Memory
///
/// The words are never materialised as 47 000 Dart strings — that would be roughly four megabytes
/// held for the life of the app, for a garnish under a text field. Instead the asset is kept as ONE
/// string plus two `Uint32List`s of offsets (~750 KB total), and only the handful of words actually
/// shown are ever cut out of it. Prefix comparison walks code units in place.
class WordList {
  WordList._(this._blob, this._lineStart, this._alphabetical);

  /// The whole asset, newline-separated, in frequency order.
  final String _blob;

  /// Start offset of each line, indexed by FREQUENCY RANK (0 = the most frequent word).
  final Uint32List _lineStart;

  /// Frequency ranks, ordered ALPHABETICALLY by the word they point at. The binary search's array.
  final Uint32List _alphabetical;

  static const assetPath = 'assets/wordlist/en_frequency.txt';

  /// Below two characters a prefix matches thousands of words and ranks them by nothing useful —
  /// the learner has not said enough yet for a suggestion to be a suggestion.
  static const minPrefix = 2;

  int get length => _lineStart.length;

  /// Parse the asset. Cheap enough to do on the UI isolate (~40 ms for 47 000 words) and done once,
  /// lazily, the first time the search screen opens — see [WordListLoader].
  static WordList parse(String rawBlob) {
    // The trailing newline is trimmed HERE rather than worked around in every reader: without it
    // the last line's end is the end of the blob, so the last word of the file comes back with a
    // `\n` glued to it — and being the last word, it is the one nothing else would ever catch.
    final blob = rawBlob.endsWith('\n') ? rawBlob.substring(0, rawBlob.length - 1) : rawBlob;

    // Offsets of every line start. Counting first would mean two passes; growing a plain list of
    // ints and copying once is faster and simpler.
    final starts = <int>[];
    if (blob.isNotEmpty) starts.add(0);
    for (var i = 0; i < blob.length; i++) {
      if (blob.codeUnitAt(i) == 0x0A && i + 1 < blob.length) starts.add(i + 1);
    }
    final lineStart = Uint32List.fromList(starts);

    final order = Uint32List(lineStart.length);
    for (var i = 0; i < order.length; i++) {
      order[i] = i;
    }
    final list = WordList._(blob, lineStart, order);

    // Sort the INDEX, not the words: the strings stay in the blob and are never copied.
    final sorted = order.toList()..sort((a, b) => list._compareLines(a, b));
    for (var i = 0; i < sorted.length; i++) {
      order[i] = sorted[i];
    }

    return list;
  }

  /// Read and parse the asset.
  ///
  /// `load()` + an explicit decode rather than `loadString()`, and that is not a style preference:
  /// `AssetBundle.loadString` hands anything over 50 KB to `compute()`, which spawns an isolate —
  /// and a spawned isolate never completes under `flutter test`, so the asset test hung for the
  /// full ten-minute timeout instead of failing. This file is 363 KB, so it took that path every
  /// time. Decoding here is also simply cheaper: the content is pure ASCII, and we were going to
  /// walk its code units ourselves anyway.
  static Future<WordList> load() async {
    final bytes = await rootBundle.load(assetPath);

    return parse(utf8.decode(bytes.buffer.asUint8List(bytes.offsetInBytes, bytes.lengthInBytes)));
  }

  /// The [limit] most FREQUENT words starting with [prefix].
  ///
  /// Empty for a prefix shorter than [minPrefix], and empty rather than throwing for anything that
  /// is not plain lowercase letters — a learner typing Cyrillic is searching by translation, which
  /// the server-side search handles and this cannot.
  List<String> startingWith(String prefix, {int limit = 5}) {
    final needle = prefix.trim().toLowerCase();
    if (needle.length < minPrefix || _lineStart.isEmpty) return const [];

    final from = _lowerBound(needle);
    if (from == _alphabetical.length) return const [];

    // Walk forward while the prefix still matches. The hits are contiguous in alphabetical order,
    // which is the entire reason for the index.
    final hits = <int>[];
    for (var i = from; i < _alphabetical.length; i++) {
      final line = _alphabetical[i];
      if (!_startsWith(line, needle)) break;
      hits.add(line);
      // A prefix like «un» matches thousands. Collecting them all to take five would be the scan
      // the binary search exists to avoid, so the walk stops once no unseen word could outrank
      // what we already hold — and since a line's index IS its rank, that is simply a bounded
      // window: past a few hundred, the rest cannot be in the top five of anything.
      if (hits.length >= _maxScan) break;
    }

    // A line's index is its frequency rank, so sorting the hits numerically IS sorting by frequency.
    hits.sort();

    return [for (final line in hits.take(limit)) _wordAt(line)];
  }

  /// How many alphabetical neighbours to look at before giving up on finding a better-ranked one.
  ///
  /// Not a correctness knob — a cap on the pathological case. «a» would match every word beginning
  /// with it; two characters already narrows that to hundreds, and the most frequent word in any
  /// such run is essentially always inside the first few hundred alphabetical neighbours.
  static const _maxScan = 600;

  /// First index in [_alphabetical] whose word is >= [needle].
  int _lowerBound(String needle) {
    var low = 0;
    var high = _alphabetical.length;
    while (low < high) {
      final mid = (low + high) >> 1;
      if (_compareWithNeedle(_alphabetical[mid], needle) < 0) {
        low = mid + 1;
      } else {
        high = mid;
      }
    }

    return low;
  }

  int _lineEnd(int line) {
    final next = line + 1 < _lineStart.length ? _lineStart[line + 1] - 1 : _blob.length;

    return next > _blob.length ? _blob.length : next;
  }

  String _wordAt(int line) => _blob.substring(_lineStart[line], _lineEnd(line));

  /// Compare two lines lexicographically WITHOUT cutting them out of the blob.
  int _compareLines(int a, int b) {
    var i = _lineStart[a];
    var j = _lineStart[b];
    final endA = _lineEnd(a);
    final endB = _lineEnd(b);
    while (i < endA && j < endB) {
      final diff = _blob.codeUnitAt(i) - _blob.codeUnitAt(j);
      if (diff != 0) return diff;
      i++;
      j++;
    }

    return (endA - _lineStart[a]) - (endB - _lineStart[b]);
  }

  /// Compare one line against a needle, treating the needle as a full word (not as a prefix).
  int _compareWithNeedle(int line, String needle) {
    var i = _lineStart[line];
    final end = _lineEnd(line);
    var k = 0;
    while (i < end && k < needle.length) {
      final diff = _blob.codeUnitAt(i) - needle.codeUnitAt(k);
      if (diff != 0) return diff;
      i++;
      k++;
    }

    return (end - _lineStart[line]) - needle.length;
  }

  bool _startsWith(int line, String needle) {
    final start = _lineStart[line];
    if (_lineEnd(line) - start < needle.length) return false;
    for (var k = 0; k < needle.length; k++) {
      if (_blob.codeUnitAt(start + k) != needle.codeUnitAt(k)) return false;
    }

    return true;
  }
}

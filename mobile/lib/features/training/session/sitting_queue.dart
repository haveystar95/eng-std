/// THE RUNNING ORDER OF A ПРИСЕСТ — the arithmetic of Ч-5 and Ч-6, with no widget in it.
///
/// Two facts live here and nowhere else:
///
///   **A card answered wrong comes back once, at the end of the присест it was in** (Ч-5). Not
///   immediately — re-showing a card the learner just missed tests the last twenty seconds. Not
///   tomorrow only — a slip they never revisit had a whole evening to set. Once — the second miss is
///   the server's: tomorrow's warm-up carries it and the day's next visit re-owes its step.
///
///   **The breaks fall where the server put them** (Ч-6), always on a section boundary, and they
///   MOVE with the tail: a card sent back lengthens its own присест and every boundary after it.
///
/// Which is what makes the bar honest. The position never goes backwards and no boundary ever
/// crosses it, so «пройденное не сгорает»: a mistake makes the tail longer, it does not un-do work.
///
/// Pure, and out of the session shell on purpose — the shell is a screen with a text-to-speech
/// engine, an image cache and a review queue in it, and none of those has an opinion about where the
/// next card is.
class SittingQueue {
  SittingQueue._(this._order, this._ends, this._requeued);

  /// A day dealt straight through, cut where `sittings` says.
  ///
  /// A `sittings` list that does not add up to [cards] is IGNORED rather than repaired: a short one
  /// would leave the tail of the day unreachable, and one long sitting is a far smaller failure than
  /// a day that cannot be finished. Same for an empty one, which is what a server older than this
  /// наряд sends.
  factory SittingQueue.of({required int cards, List<int> sittings = const []}) {
    final order = List<int>.generate(cards, (i) => i);
    final ends = <int>[];
    var at = 0;
    for (final size in sittings) {
      at += size;
      if (size > 0 && at <= cards) ends.add(at);
    }
    if (ends.isEmpty || ends.last != cards) {
      return SittingQueue._(order, [cards], <int>{});
    }

    return SittingQueue._(order, ends, <int>{});
  }

  /// A sitting picked back up where it was left — see `PlanSittingStore`.
  ///
  /// Returns null when the stored order does not fit the session in hand: an index past the end of
  /// the cards would open a sitting on a card that is not there, and starting the day over is a far
  /// smaller loss than that.
  static SittingQueue? resume({
    required int cards,
    required List<int> order,
    required List<int> ends,
    required List<int> requeued,
  }) {
    if (order.isEmpty || order.any((i) => i < 0 || i >= cards)) return null;
    if (ends.isEmpty || ends.last != order.length) return null;
    for (var i = 1; i < ends.length; i++) {
      if (ends[i] <= ends[i - 1]) return null;
    }

    return SittingQueue._(List<int>.of(order), List<int>.of(ends), requeued.toSet());
  }

  final List<int> _order;
  final List<int> _ends;
  final Set<int> _requeued;

  /// Card indices in the order they are played — longer than the deck once something has gone wrong.
  List<int> get order => List<int>.unmodifiable(_order);

  /// Exclusive end offsets into [order], one per присест; the last is [length].
  List<int> get ends => List<int>.unmodifiable(_ends);

  /// Cards that have already used their one repeat.
  List<int> get requeued => List<int>.unmodifiable(_requeued);

  int get length => _order.length;

  int get sittings => _ends.length;

  int cardAt(int position) => _order[position.clamp(0, _order.length - 1)];

  /// Which присест this position is in.
  int sittingAt(int position) {
    for (var i = 0; i < _ends.length; i++) {
      if (position < _ends[i]) return i;
    }

    return _ends.length - 1;
  }

  /// Is the card at [position] the last one of its присест — and is there another after it?
  bool breaksAfter(int position) =>
      position + 1 < _order.length && position + 1 >= _ends[sittingAt(position)];

  /// Send the card at [position] to the end of its own присест. Returns false when it has already
  /// been sent once — one repeat per card per sitting, and the second miss belongs to the server.
  bool requeue(int position) {
    final card = cardAt(position);
    if (!_requeued.add(card)) return false;

    final sitting = sittingAt(position);
    _order.insert(_ends[sitting], card);
    for (var i = sitting; i < _ends.length; i++) {
      _ends[i] = _ends[i] + 1;
    }

    return true;
  }
}

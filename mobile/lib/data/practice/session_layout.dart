import 'learning_ladder.dart';

/// One position in a session's running order: which term, and — when the term is on the acquisition
/// ladder — which rung this particular card is.
///
/// A term can occupy SEVERAL slots of one session. That is the point of the ladder: a word is
/// introduced and then comes back twice in the same sitting, at widening distances, which is the
/// only spacing a first session can offer. [ladderStep] is null for a graduated term, whose single
/// card's mode comes from its progress as it always did.
class SessionSlot {
  const SessionSlot(this.termId, [this.ladderStep]);

  final String termId;
  final int? ladderStep;
}

/// A term waiting to be laid out, and the rung its FIRST card is dealt at.
class LadderEntry {
  const LadderEntry(this.termId, this.step);

  final String termId;
  final int step;
}

/// Client port of the server's `Learning\Domain\Service\SessionLayout` — the RUNNING ORDER of a
/// session, so a word introduced at position 3 comes back at 6 and again at 12 rather than three
/// times in a row.
///
/// Pure and deterministic, like the server's: the same inputs must deal the same order, or a
/// rebuilt session would re-deal one the learner is halfway through.
///
/// What it enforces:
///
///  * **A word introduced in a session is ANSWERED in that session** — recognition-forward 2–4
///    positions after the intro, recognition-reverse 4–8 after that. Widening gaps, because that is
///    what spacing means; a fixed gap is just a longer block.
///  * **A term is never introduced whose chain does not fit.** An intro whose recognitions fall off
///    the end is worse than no intro: the word is "met" and then not asked for a day.
///  * **Intros never come as a block** (at most two consecutively) — and this holds structurally,
///    not by the counter alone: two intros at p and p+1 reserve p+2 and p+3, so the third position
///    is already taken by something that is not an intro.
///  * **Ladder cards and due repeats alternate** while both are available.
///
/// The empty-first-session case falls out of the same rules with nothing added: with no repeats to
/// interleave, the ladder dilutes itself — intro, intro, recognition, recognition, intro…
///
/// A HOLE (a position nothing can fill) is possible only once both queues are spent, i.e. in the
/// tail, and the result is compacted. Closing a gap there costs nothing: a gap exists to put other
/// cards between two meetings of a word, and in the tail there are none left to put.
abstract final class SessionLayout {
  /// intro → recognition-forward.
  static const int firstGapMin = 2;
  static const int firstGapMax = 4;

  /// recognition-forward → recognition-reverse.
  static const int secondGapMin = 4;
  static const int secondGapMax = 8;

  static const int maxConsecutiveIntros = 2;

  /// [ladder] are terms on rungs 0–2 in priority order; [repeats] are graduated terms with one card
  /// each, also in priority order.
  static List<SessionSlot> arrange({
    required List<LadderEntry> ladder,
    required List<String> repeats,
    required int size,
  }) {
    if (size <= 0) return const [];

    final slots = <int, SessionSlot>{};
    final pending = [...ladder];
    var repeatIndex = 0;
    var consecutiveIntros = 0;
    var lastWasLadderHead = false;

    for (var i = 0; i < size; i++) {
      if (slots.containsKey(i)) {
        // A reserved follow-up. By definition not an intro, so it breaks any run.
        consecutiveIntros = 0;
        lastWasLadderHead = false;
        continue;
      }

      final hasRepeat = repeatIndex < repeats.length;
      final head = _nextHead(pending, consecutiveIntros);
      if (head == null && !hasRepeat) continue; // a tail hole — compacted away below

      // Alternate while both are available, so the two kinds interleave instead of forming two
      // blocks. When only one queue has anything, it simply keeps its turn.
      final takeRepeat = head == null || (hasRepeat && lastWasLadderHead);
      if (takeRepeat) {
        slots[i] = SessionSlot(repeats[repeatIndex]);
        repeatIndex++;
        consecutiveIntros = 0;
        lastWasLadderHead = false;
        continue;
      }

      final entry = pending[head];
      final chain = _placeChain(slots, i, entry, size);
      if (chain == null) {
        // The whole chain does not fit before the end of the session. Defer the TERM, not just its
        // tail — and keep looking, because an entry already partway up the ladder is a shorter chain
        // and may still fit.
        pending.removeAt(head);
        i--; // this position is still empty; try to fill it with something else
        continue;
      }

      slots.addAll(chain);
      pending.removeAt(head);
      consecutiveIntros = entry.step == LearningLadder.stepIntro ? consecutiveIntros + 1 : 0;
      lastWasLadderHead = true;
    }

    final indices = slots.keys.toList()..sort();
    return [for (final index in indices) slots[index]!];
  }

  /// Index into [pending] of the next head we may deal here, or null.
  ///
  /// The consecutive-intro cap is applied by SKIPPING intro entries, not by giving up: a term
  /// already on a recognition rung is not an intro and can legitimately break the run.
  static int? _nextHead(List<LadderEntry> pending, int consecutiveIntros) {
    for (var index = 0; index < pending.length; index++) {
      if (pending[index].step == LearningLadder.stepIntro &&
          consecutiveIntros >= maxConsecutiveIntros) {
        continue;
      }
      return index;
    }
    return null;
  }

  /// Lay out one term's remaining ladder cards from [head] onwards, or null if they do not all fit.
  static Map<int, SessionSlot>? _placeChain(
    Map<int, SessionSlot> slots,
    int head,
    LadderEntry entry,
    int size,
  ) {
    final chain = <int, SessionSlot>{head: SessionSlot(entry.termId, entry.step)};
    var previous = head;

    for (var step = entry.step + 1; step <= LearningLadder.stepRecognitionReverse; step++) {
      final (min, max) = _gapBefore(step);
      final index =
          _firstFree(slots, chain, previous + min, previous + max) ??
          // The window is full because other terms' cards landed in it. Take the first free slot
          // after it rather than dropping the card: a follow-up that is slightly late is spacing, a
          // follow-up that never happens is a word met and abandoned.
          _firstFree(slots, chain, previous + max + 1, size - 1);

      if (index == null || index >= size) return null;
      chain[index] = SessionSlot(entry.termId, step);
      previous = index;
    }

    return chain;
  }

  /// The min/max distance from the previous card of the same term.
  static (int, int) _gapBefore(int step) => step == LearningLadder.stepRecognitionForward
      ? (firstGapMin, firstGapMax)
      : (secondGapMin, secondGapMax);

  static int? _firstFree(
    Map<int, SessionSlot> slots,
    Map<int, SessionSlot> chain,
    int from,
    int to,
  ) {
    for (var i = from < 0 ? 0 : from; i <= to; i++) {
      if (!slots.containsKey(i) && !chain.containsKey(i)) return i;
    }
    return null;
  }
}

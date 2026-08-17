import '../models.dart';
import 'learning_ladder.dart';

/// Which card a recognition SLOT actually plays, decided at the moment it is shown.
///
/// A session is dealt whole, before the first answer: `SessionLayout` lays out a term's chain —
/// intro, recognition-forward, recognition-reverse — from the rung the pair stood on when the
/// session was built, and the server assembles all of those cards up front so the sitting works
/// with the network off. That plan is right about ORDER and wrong about one thing: it assumes every
/// rung in the chain will be passed. Fail rung 1 and the pair stays on rung 1, but the slot four
/// cards later still holds the rung-2 card — the learner is asked the reverse direction without
/// having got the forward one, and the review log records an answer at a rung the pair was never on
/// (QA-9, приёмка 17.08).
///
/// So the slot's rung is a PLAN and this is the deal-time resolution of it: **a recognition slot is
/// played at the pair's current rung, never above it.** Below it is impossible — the ladder only
/// moves on success, and success is what raises the current rung here.
///
/// Two things it deliberately does NOT do:
///
///  * it never REORDERS the session. The slot keeps its position and its spacing; only what fills it
///    changes. Re-arranging the tail after every answer would re-deal a session the learner is
///    halfway through, which is the thing `SessionLayout` is pure and deterministic to prevent.
///  * it never invents a card. The replacement is the term's OWN card at the current rung, already
///    in this session — the layout always places a chain in ascending rung order, so a lower rung's
///    card is always earlier in the list than the slot being resolved. With no such card (a chain
///    that started above the current rung, which cannot happen, or a hand-built session) the plan is
///    played unchanged rather than the slot dropped.
///
/// Advancement is read from the client's own instant check, and that is exact where it has to be:
/// rung 1 is graded by IDENTITY (the tapped option's term id against the card's own), so «passed» is
/// a string equality both runtimes compute the same way. On rung 2 the local check is the usual
/// text one, which is never STRICTER than the server's — it can only let the learner move on, never
/// hold them back on a rung the server has already passed them off.
///
/// Free practice is off the ladder entirely (it advances nothing), so it is constructed disabled and
/// every slot resolves to itself.
class RecognitionReplay {
  /// [cards] is the session as dealt, in running order.
  RecognitionReplay(this.cards, {this.enabled = true}) {
    if (!enabled) return;
    for (final card in cards) {
      final step = card.ladderStep;
      if (!LearningLadder.isRecognitionStep(step)) continue;
      final known = _current[card.termId];
      // The lowest recognition rung planned for this term is where the pair stood when the session
      // was built — the chain is laid out from there upwards.
      _current[card.termId] = known == null ? step! : (step! < known ? step : known);
    }
  }

  final List<SessionCard> cards;

  /// False in free practice: nothing there moves the ladder, so nothing may be resolved against it.
  final bool enabled;

  /// term id → the rung the pair stands on right now, as this session has watched it move.
  final Map<String, int> _current = {};

  /// The rung [termId] stands on as far as this session knows, or null for a term it is not
  /// tracking (a graduated repeat, or free practice).
  int? currentStepOf(String termId) => _current[termId];

  /// The index of the card to PLAY at slot [index] — [index] itself unless the slot's planned rung
  /// is above the pair's current one, in which case the term's card at the current rung is replayed.
  int resolve(int index) {
    if (!enabled || index < 0 || index >= cards.length) return index;

    final card = cards[index];
    final planned = card.ladderStep;
    if (!LearningLadder.isRecognitionStep(planned)) return index;

    final current = _current[card.termId];
    if (current == null || current >= planned!) return index;

    for (var i = 0; i < cards.length; i++) {
      final candidate = cards[i];
      if (candidate.termId == card.termId && candidate.ladderStep == current) return i;
    }
    return index; // the rung has no card in this session — play the plan rather than nothing
  }

  /// Fold the answer to [played] back into the ladder. Only a PASSED recognition card moves it; a
  /// failed one leaves the pair where it is, which is what makes the next slot replay this rung.
  void record(SessionCard played, {required bool accepted}) {
    if (!enabled || !accepted) return;
    final step = played.ladderStep;
    if (!LearningLadder.isRecognitionStep(step)) return;

    final current = _current[played.termId];
    if (current != null && current > step!) return; // already further along; nothing to move
    _current[played.termId] = step! + 1;
  }
}

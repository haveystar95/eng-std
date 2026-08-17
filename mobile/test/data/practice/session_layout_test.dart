import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/session_layout.dart';
import 'package:flutter_test/flutter_test.dart';

/// The RUNNING ORDER of a ladder session, ported from the server's `SessionLayout`.
///
/// The expectations here are the same ones the server's own layout test asserts — same gaps, same
/// cap, same empty-first-session shape — because they are the same algorithm and a session dealt
/// offline must read like one dealt online.
void main() {
  List<LadderEntry> introducing(List<String> terms) =>
      [for (final t in terms) LadderEntry(t, LearningLadder.stepIntro)];

  List<String> readable(List<SessionSlot> slots) =>
      [for (final s in slots) '${s.termId}:${s.ladderStep ?? "due"}'];

  List<int> positionsOf(List<SessionSlot> slots, String termId) => [
        for (var i = 0; i < slots.length; i++)
          if (slots[i].termId == termId) i,
      ];

  test('brings every introduced word back TWICE in the same session, at widening gaps', () {
    // Enough repeats that no slot goes unfilled — the gaps are then exactly as laid out.
    final repeats = [for (var i = 1; i <= 16; i++) 'r$i'];
    final slots = SessionLayout.arrange(
      ladder: introducing(['a', 'b', 'c', 'd']),
      repeats: repeats,
      size: 20,
    );

    for (final term in ['a', 'b', 'c', 'd']) {
      final positions = positionsOf(slots, term);
      expect(positions, hasLength(3));
      final [intro, first, second] = positions;

      expect(first - intro, inInclusiveRange(SessionLayout.firstGapMin, SessionLayout.firstGapMax));
      expect(second - first, inInclusiveRange(SessionLayout.secondGapMin, SessionLayout.secondGapMax));
      expect([for (final p in positions) slots[p].ladderStep], [0, 1, 2]);
    }
  });

  test('dilutes an empty first session with the ladder itself — no due cards needed', () {
    // THE case from the spec: twenty slots, nothing but brand-new words. The ladder has to break up
    // its own intros, because there is nothing else in the session to do it.
    final slots = SessionLayout.arrange(
      ladder: introducing(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']),
      repeats: const [],
      size: 20,
    );

    expect(readable(slots), [
      'a:0', 'b:0', 'a:1', 'b:1', //
      'c:0', 'd:0', 'a:2', 'b:2', 'c:1', 'd:1', //
      'e:0', 'f:0', 'c:2', 'd:2', 'e:1', 'f:1', //
      'e:2', 'f:2',
    ]);
  });

  test('never lets intros run more than two deep', () {
    final slots = SessionLayout.arrange(
      ladder: introducing(['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h']),
      repeats: const [],
      size: 20,
    );

    var run = 0;
    for (final slot in slots) {
      run = slot.ladderStep == LearningLadder.stepIntro ? run + 1 : 0;
      expect(run, lessThanOrEqualTo(SessionLayout.maxConsecutiveIntros));
    }
  });

  test('interleaves ladder cards with due repeats instead of stapling two sessions together', () {
    final slots = SessionLayout.arrange(
      ladder: introducing(['a', 'b']),
      repeats: const ['r1', 'r2', 'r3', 'r4'],
      size: 12,
    );

    expect(readable(slots), [
      'a:0', 'r1:due', 'a:1', 'b:0', 'r2:due', 'b:1', //
      'a:2', 'r3:due', 'r4:due', 'b:2',
    ]);
  });

  test('defers a whole word rather than introducing one whose recognitions fall off the end', () {
    // Seven slots is room for exactly one chain (0, 2, 6): a word met and then not asked until
    // tomorrow is worse than a word not met, so the second one waits for the next session, whole.
    final slots = SessionLayout.arrange(ladder: introducing(['a', 'b']), repeats: const [], size: 7);

    expect(positionsOf(slots, 'a'), hasLength(3));
    expect(positionsOf(slots, 'b'), isEmpty);
  });

  test('fills what is left with due repeats when no more words fit', () {
    final slots = SessionLayout.arrange(
      ladder: introducing(['a', 'b']),
      repeats: const ['r1', 'r2'],
      size: 7,
    );

    expect(positionsOf(slots, 'b'), isEmpty);
    expect(readable(slots), containsAll(['r1:due', 'r2:due']));
  });

  test('starts a word already partway up the ladder at ITS rung, not at the intro', () {
    // «Не уверен» in triage, or a session abandoned after the intro: a shorter chain, opening with
    // the card the pair is actually owed.
    final slots = SessionLayout.arrange(
      ladder: const [
        LadderEntry('a', LearningLadder.stepRecognitionForward),
        LadderEntry('b', LearningLadder.stepRecognitionReverse),
      ],
      repeats: const [],
      size: 20,
    );

    expect([for (final p in positionsOf(slots, 'a')) slots[p].ladderStep], [1, 2]);
    expect([for (final p in positionsOf(slots, 'b')) slots[p].ladderStep], [2]);
  });

  test('closes the gaps when there is genuinely nothing to put in them', () {
    // One word, nothing else. A gap exists to interpose OTHER cards; with none to interpose, holding
    // the slots open would end the session in dead air rather than in spacing.
    final slots = SessionLayout.arrange(ladder: introducing(['a']), repeats: const [], size: 20);

    expect(readable(slots), ['a:0', 'a:1', 'a:2']);
  });

  test('is deterministic — the same inputs always deal the same order', () {
    // A retried build must not re-deal a session the learner is halfway through.
    List<String> run() => readable(SessionLayout.arrange(
          ladder: introducing(['a', 'b', 'c']),
          repeats: const ['r1', 'r2'],
          size: 15,
        ));

    expect(run(), run());
  });

  test('never overruns the session size, and never repeats a slot', () {
    final slots = SessionLayout.arrange(
      ladder: introducing(['a', 'b', 'c', 'd', 'e']),
      repeats: const ['r1', 'r2', 'r3'],
      size: 12,
    );

    expect(slots.length, lessThanOrEqualTo(12));
    expect(readable(slots).toSet(), hasLength(slots.length));
  });

  test('returns nothing for a session with no room', () {
    expect(SessionLayout.arrange(ladder: introducing(['a']), repeats: const ['r1'], size: 0), isEmpty);
  });
}

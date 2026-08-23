import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/practice/learning_ladder.dart';
import 'package:eng_std/data/practice/recognition_replay.dart';
import 'package:eng_std/data/practice/session_layout.dart';
import 'package:eng_std/features/training/session/session_grading.dart';
import 'package:flutter_test/flutter_test.dart';

/// QA-9: the rung a recognition card is PLAYED at must be the rung the pair stands on when it is
/// shown, not the rung the session planned for it before anything was answered.
///
/// The layout stays exactly as it was — same slots, same spacing, same determinism. What changes is
/// which card fills a recognition slot, and these tests pin both halves of that: the replay itself,
/// and the layout property it leans on (a chain is always laid out in ascending rung order, so the
/// lower rung's card is already in the session when the higher one comes up).
void main() {
  SessionCard intro(String term) => SessionCard(
    termId: term,
    mode: ExerciseMode.intro,
    type: 'phrase',
    answer: term,
    ladderStep: LearningLadder.stepIntro,
  );

  /// Rung 1 — term → translation, graded by identity: the answer is the card's own term id.
  SessionCard forward(String term) => SessionCard(
    termId: term,
    mode: ExerciseMode.multipleChoice,
    type: 'phrase',
    prompt: term,
    answer: term,
    options: const ['перевод', 'не тот', 'и не тот'],
    optionIds: [term, 'other-1', 'other-2'],
    ladderStep: LearningLadder.stepRecognitionForward,
  );

  /// Rung 2 — translation → term, graded as text.
  SessionCard reverse(String term) => SessionCard(
    termId: term,
    mode: ExerciseMode.multipleChoice,
    type: 'phrase',
    prompt: 'перевод',
    answer: term,
    options: [term, 'не тот', 'и не тот'],
    ladderStep: LearningLadder.stepRecognitionReverse,
  );

  SessionCard due(String term) => SessionCard(
    termId: term,
    mode: ExerciseMode.typing,
    type: 'word',
    prompt: 'перевод',
    answer: term,
  );

  group('the exact case from the phone acceptance', () {
    // «Where can I find dog food?»: intro, rung 1 failed, and four cards later the session dealt
    // rung 2 — a card the pair had not earned, logged as an answer at rung 2 while the pair stood
    // at rung 1.
    List<SessionCard> session() => [
      intro('dogfood'),
      due('r1'),
      forward('dogfood'),
      due('r2'),
      due('r3'),
      reverse('dogfood'),
    ];

    test('a FAILED rung 1 is replayed instead of dealing rung 2', () {
      final cards = session();
      final replay = RecognitionReplay(cards);

      expect(replay.resolve(2), 2, reason: 'the forward card plays where it was laid out');
      replay.record(cards[replay.resolve(2)], accepted: false);

      final played = cards[replay.resolve(5)];
      expect(played.ladderStep, LearningLadder.stepRecognitionForward);
      expect(identical(played, cards[2]), isTrue, reason: 'the term\'s own rung-1 card, replayed');
      // …and what the review log will carry is the rung of the card actually shown.
      expect(played.ladderStep, replay.currentStepOf('dogfood'));
    });

    test('a PASSED rung 1 leaves rung 2 exactly where the session put it', () {
      final cards = session();
      final replay = RecognitionReplay(cards);

      replay.record(cards[2], accepted: true);

      expect(replay.resolve(5), 5);
      expect(cards[replay.resolve(5)].ladderStep, LearningLadder.stepRecognitionReverse);
    });

    test('the slot keeps its POSITION — a replay refills it, it never reorders the session', () {
      final cards = session();
      final replay = RecognitionReplay(cards);
      replay.record(cards[2], accepted: false);

      final order = [for (var i = 0; i < cards.length; i++) cards[replay.resolve(i)].termId];
      expect(order, ['dogfood', 'r1', 'dogfood', 'r2', 'r3', 'dogfood']);
    });
  });

  test('replays the same rung again after a second miss', () {
    // Three chances at rung 1 in one sitting is not a scenario the layout builds, but the rule must
    // not depend on that: nothing may promote the pair except passing.
    final cards = [forward('a'), forward('a'), reverse('a')];
    final replay = RecognitionReplay(cards);

    replay.record(cards[replay.resolve(0)], accepted: false);
    // Slot 1 is already a rung-1 card, so it plays itself — the rule is about the RUNG, not about
    // reaching for a particular index.
    expect(cards[replay.resolve(1)].ladderStep, LearningLadder.stepRecognitionForward);
    replay.record(cards[replay.resolve(1)], accepted: false);
    expect(cards[replay.resolve(2)].ladderStep, LearningLadder.stepRecognitionForward);
    expect(replay.resolve(2), 0, reason: 'the rung-2 slot reaches back to a rung-1 card');
  });

  test('a typo counts as passed — the local check is never stricter than the server', () {
    final cards = [forward('a'), reverse('a')];
    final replay = RecognitionReplay(cards);

    // LocalCheck.typo is accepted, so the ladder moves exactly as the server will move it.
    replay.record(cards[0], accepted: LocalCheck.typo.isAccepted);

    expect(replay.resolve(1), 1);
  });

  test('leaves graduated repeats and intros alone', () {
    final cards = [intro('a'), due('r1'), forward('a')];
    final replay = RecognitionReplay(cards);

    expect(replay.resolve(0), 0);
    expect(replay.resolve(1), 1);
    expect(replay.currentStepOf('r1'), isNull);
    // Acknowledging an intro is not an answer and moves nothing.
    replay.record(cards[0], accepted: true);
    expect(replay.currentStepOf('a'), LearningLadder.stepRecognitionForward);
  });

  test('a chain that STARTS at rung 2 has no lower card to replay, so it plays as planned', () {
    // «Не уверен» in triage puts the pair straight on rung 2; a miss there simply re-queues it into
    // a later session, and this one deals what it has rather than nothing.
    final cards = [reverse('a'), reverse('a')];
    final replay = RecognitionReplay(cards);

    replay.record(cards[0], accepted: false);
    expect(replay.resolve(1), 1);
    expect(cards[replay.resolve(1)].ladderStep, LearningLadder.stepRecognitionReverse);
  });

  test('free practice is off the ladder, so every slot resolves to itself', () {
    final cards = [reverse('a'), reverse('a')];
    final replay = RecognitionReplay(cards, enabled: false);

    replay.record(cards[0], accepted: false);
    expect(replay.resolve(1), 1);
    expect(replay.currentStepOf('a'), isNull);
  });

  test('the layout puts a chain in ASCENDING rung order — the property the replay leans on', () {
    // Without this, «the term's card at the current rung» could sit AFTER the slot being resolved
    // and the replay would have nothing to reach for. Asserted here as well as in the layout's own
    // test, because it is this rule that depends on it. The server's SessionLayout test asserts the
    // same property against the same algorithm.
    final slots = SessionLayout.arrange(
      ladder: const [
        LadderEntry('a', LearningLadder.stepIntro),
        LadderEntry('b', LearningLadder.stepIntro),
        LadderEntry('c', LearningLadder.stepRecognitionForward),
      ],
      repeats: const ['r1', 'r2', 'r3', 'r4'],
      size: 20,
    );

    for (final term in ['a', 'b', 'c']) {
      final steps = [
        for (final slot in slots)
          if (slot.termId == term) slot.ladderStep!,
      ];
      expect(steps, isNotEmpty);
      for (var i = 1; i < steps.length; i++) {
        expect(steps[i], steps[i - 1] + 1, reason: '$term must climb one rung at a time, in order');
      }
    }
  });
}

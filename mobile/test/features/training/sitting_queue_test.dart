import 'package:eng_std/features/training/session/sitting_queue.dart';
import 'package:flutter_test/flutter_test.dart';

/// THE ARITHMETIC OF A ПРИСЕСТ — Ч-5 (ошибка в хвост) and Ч-6 (рез по границе секции), наряд SIT-1.
void main() {
  group('the breaks are the server’s, and they only fall between sections', () {
    test('a day with no sittings on the payload is one long sitting, as it always was', () {
      final q = SittingQueue.of(cards: 9);

      expect(q.sittings, 1);
      expect(q.ends, [9]);
      expect(q.breaksAfter(4), isFalse);
    });

    test('a day cut into three breaks after each of the first two', () {
      final q = SittingQueue.of(cards: 12, sittings: const [5, 4, 3]);

      expect(q.ends, [5, 9, 12]);
      expect(q.breaksAfter(4), isTrue, reason: 'the last card of sitting 1');
      expect(q.breaksAfter(3), isFalse);
      expect(q.breaksAfter(8), isTrue, reason: 'the last card of sitting 2');
      // …and never after the last card of the DAY: that is the summary, not a break.
      expect(q.breaksAfter(11), isFalse);
    });

    test('a sittings list that does not add up is ignored rather than repaired', () {
      // A short one would leave the tail of the day unreachable, which is worse than one long
      // sitting — and a day that cannot be finished is a day n+1 that is never written.
      expect(SittingQueue.of(cards: 10, sittings: const [3, 3]).ends, [10]);
      expect(SittingQueue.of(cards: 10, sittings: const [6, 6]).ends, [10]);
      expect(SittingQueue.of(cards: 10, sittings: const []).ends, [10]);
    });
  });

  group('a wrong answer goes to the tail of THIS sitting, once', () {
    test('it lengthens the sitting it was in, and every boundary after it', () {
      final q = SittingQueue.of(cards: 9, sittings: const [3, 3, 3]);

      // The learner misses the second card of the first sitting.
      expect(q.requeue(1), isTrue);

      expect(q.order, [0, 1, 2, 1, 3, 4, 5, 6, 7, 8]);
      expect(q.ends, [4, 7, 10], reason: 'sitting 1 grew; the ones after it moved with it');
      expect(q.length, 10);
    });

    test('the same card is never sent back twice — the second miss is the server’s', () {
      final q = SittingQueue.of(cards: 6, sittings: const [6]);

      expect(q.requeue(0), isTrue);
      // It comes back at the end and is missed again: tomorrow's warm-up carries it (Ч-4) and the
      // day's next visit re-owes its checklist step. Playing it a third time in one sitting is how a
      // learner ends up fighting one card all evening.
      expect(q.requeue(q.length - 1), isFalse);
      expect(q.length, 7);
    });

    test('nothing that was passed can burn: the position never moves and no boundary crosses it', () {
      final q = SittingQueue.of(cards: 8, sittings: const [4, 4]);
      const position = 2; // the learner is on the third card

      final before = q.ends.first;
      q.requeue(position);

      // The bar's numerator is the position and it did not move; its denominator grew.
      expect(q.ends.first, before + 1);
      expect(q.ends.first, greaterThan(position));
      expect(q.cardAt(position), 2, reason: 'the card under the finger is still the same one');
    });

    test('a miss in the LAST sitting extends that one, and creates no new break', () {
      final q = SittingQueue.of(cards: 6, sittings: const [3, 3]);

      q.requeue(4);

      expect(q.ends, [3, 7]);
      expect(q.sittings, 2);
      expect(q.order.last, 4);
    });
  });
}

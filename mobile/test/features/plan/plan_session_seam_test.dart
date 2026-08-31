import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan_models.dart';

/// THE SEAM between the day and the top-up, from the client's side.
///
/// A plan session deals the day and then tops the sitting up with whatever else the learner has
/// due. The client used to fold the two together, so a day of fourteen cards was announced as
/// «День 1 пройден · 21 фраза и слово» — seven of them out of another plan and, on the account the
/// bug was found on, another language.
///
/// The server names the seam now, twice over: `section` per task and `day_task_count` on the
/// session. Both are read here, and so is the fallback for a payload written before they existed.
void main() {
  /// The wire shape of one task, with only the parts of it this file is about.
  Map<String, dynamic> task({
    required String termId,
    int? fromDayIndex,
    String? section,
  }) => {
    'stage': 'a',
    'ordinal': 1,
    'of_steps': 1,
    'from_day_index': fromDayIndex,
    'softened': false,
    'source': fromDayIndex == null ? 'other_review' : 'new',
    'section': ?section,
    'knobs_applied': <String>[],
    'knobs_ignored': <String>[],
    'card': {
      'term_id': termId,
      'exercise_mode': 'intro',
      'type': 'phrase',
      'answer': termId,
    },
  };

  group('plan session task', () {
    test('reads the section the server names', () {
      final day = PlanSessionTask.fromJson(task(termId: 't1', fromDayIndex: 1, section: 'day'));
      final review = PlanSessionTask.fromJson(task(termId: 't2', section: 'review'));

      expect(day.section, PlanSessionTask.sectionDay);
      expect(day.isDay, isTrue);
      expect(review.section, PlanSessionTask.sectionReview);
      expect(review.isDay, isFalse);
    });

    test('falls back to `from_day_index` when the server has not sent `section` yet', () {
      // The rule the client was supposed to apply before the field existed — and the one it got
      // wrong. A task of no day of this plan is the top-up.
      final day = PlanSessionTask.fromJson(task(termId: 't1', fromDayIndex: 2));
      final review = PlanSessionTask.fromJson(task(termId: 't2'));

      expect(day.isDay, isTrue);
      expect(review.isDay, isFalse);
    });
  });

  group('plan session', () {
    /// The live shape, shrunk: three cards of the day, then two the ordinary queue added.
    PlanSession sessionWithTopUp() => PlanSession.fromJson({
      'session_id': '01SESSION',
      'plan_id': '01PLAN',
      'day_index': 1,
      'strict': true,
      'day_task_count': 3,
      'tasks': [
        task(termId: 'd1', fromDayIndex: 1, section: 'day'),
        task(termId: 'd2', fromDayIndex: 1, section: 'day'),
        task(termId: 'd3', fromDayIndex: 1, section: 'day'),
        task(termId: 'fr1', section: 'review'),
        task(termId: 'other1', section: 'review'),
      ],
    });

    test('counts the day out of its own tasks, not out of the whole sitting', () {
      final session = sessionWithTopUp();

      expect(session.tasks.length, 5);
      expect(session.dayTaskCount, 3);
      expect(
        session.dayTasks.map((t) => t.card.termId),
        ['d1', 'd2', 'd3'],
      );
    });

    test('answers per card index, which is what the screens hold', () {
      final session = sessionWithTopUp();

      expect([for (var i = 0; i < 5; i++) session.isDayTaskAt(i)], [
        true,
        true,
        true,
        false,
        false,
      ]);

      // Out of range on both ends, because the screen asks while a session is being rebuilt.
      expect(session.isDayTaskAt(-1), isFalse);
      expect(session.isDayTaskAt(99), isFalse);
    });

    test('a session with nothing else due is all day', () {
      final session = PlanSession.fromJson({
        'session_id': '01SESSION',
        'plan_id': '01PLAN',
        'day_index': 1,
        'strict': true,
        'day_task_count': 2,
        'tasks': [
          task(termId: 'd1', fromDayIndex: 1, section: 'day'),
          task(termId: 'd2', fromDayIndex: 1, section: 'day'),
        ],
      });

      expect(session.dayTaskCount, session.tasks.length);
    });
  });
}

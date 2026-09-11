import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'package:eng_std/features/plan/plan_format.dart';

/// КАНОН НАРЯДА PLAN-UI, §3–4: what the CLIENT formats and decides itself.
///
/// The server sends dates raw and the client formats them by locale (day and month, the weekday);
/// the shortening of the plan by an early date (кадр 22-3b) and the nudge under a short goal
/// (22-1c) are the two rules the entry applies locally. Everything that inflects the event arrives
/// ready and is not composed here — so there is nothing of that to test on the client.
void main() {
  setUpAll(() async {
    await initializeDateFormatting('ru');
    await initializeDateFormatting('en');
  });

  group('даты — по локали, без года (кадры 21-2, 21-10, 22-3b)', () {
    test('«15 сентября» и «вторник» в русской локали', () {
      final day = DateTime(2026, 9, 15);
      expect(PlanFormat.date(day, 'ru'), '15 сентября');
      expect(PlanFormat.weekday(day, 'ru'), 'вторник');
    });

    test('та же дата в английской локали — своими словами', () {
      final day = DateTime(2026, 10, 3);
      expect(PlanFormat.date(day, 'en'), '3 October');
      expect(PlanFormat.weekday(day, 'en'), 'saturday');
    });

    test('дата на провод — календарный день YYYY-MM-DD, и обратно', () {
      expect(PlanFormat.wireDate(DateTime(2026, 9, 5, 23, 59)), '2026-09-05');
      expect(PlanFormat.parseWireDate('2026-09-15'), DateTime(2026, 9, 15));
      expect(PlanFormat.parseWireDate(null), isNull);
      expect(PlanFormat.parseWireDate('приём'), isNull);
    });
  });

  group('сокращение плана датой (кадр 22-3b)', () {
    final today = DateTime(2026, 9, 10);

    test('дней до события меньше выбранных — план сокращается до них', () {
      final left = PlanFormat.daysUntil(DateTime(2026, 9, 13), today);
      expect(left, 3);
      expect(PlanFormat.shortenedDays(chosen: 5, daysLeft: left), 3);
    });

    test('дней хватает — ничего не меняется', () {
      expect(PlanFormat.shortenedDays(chosen: 5, daysLeft: 12), isNull);
      expect(PlanFormat.shortenedDays(chosen: 5, daysLeft: 5), isNull);
    });

    test('без даты сокращать нечего', () {
      expect(PlanFormat.shortenedDays(chosen: 7, daysLeft: null), isNull);
    });

    test('событие сегодня или завтра — план не короче одного дня', () {
      expect(PlanFormat.shortenedDays(chosen: 3, daysLeft: 0), 1);
      expect(PlanFormat.shortenedDays(chosen: 3, daysLeft: 1), 1);
      expect(PlanFormat.shortenedDays(chosen: 1, daysLeft: 0), isNull);
    });

    test('дни считаются по календарю, а не по часам', () {
      expect(PlanFormat.daysUntil(DateTime(2026, 9, 11, 0, 5), DateTime(2026, 9, 10, 23, 50)), 1);
    });
  });

  group('короткая цель (кадр 22-1c) — меньше восьми слов', () {
    test('«врач» и семь слов — коротко, восемь — нет', () {
      expect(PlanFormat.isShortGoal('врач'), isTrue);
      expect(PlanFormat.isShortGoal('иду к врачу с ребёнком болит спина'), isTrue);
      expect(PlanFormat.isShortGoal('иду к врачу с ребёнком, у него болит спина'), isFalse);
    });

    test('пустое поле — не короткий ответ, а никакой', () {
      expect(PlanFormat.isShortGoal(''), isFalse);
      expect(PlanFormat.isShortGoal('   '), isFalse);
    });
  });
}

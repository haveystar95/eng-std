import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/plan_providers.dart';

/// КАНОН НАРЯДА PLAN-UI, §3: the server's answer names the state of the tab, and the tab does not
/// re-derive it. What the client DOES decide is which day the plate is about — and that rule is
/// pinned here against the frames: today's day (21-2/21-3), the day just closed while the next
/// one opens tomorrow (21-4), nothing once every day is closed (21-7).
void main() {
  Map<String, dynamic> day({
    required int number,
    required String status,
    required String slot,
    String type = 'scene',
    String? lesson = 'ready',
    String? date,
  }) => {
    'id': 'day$number',
    'number': number,
    'type': type,
    'status': status,
    'scene_id': type == 'scene' ? 'scene$number' : null,
    'title_native': type == 'scene' ? 'Сцена $number' : null,
    'teaches_native': null,
    'lesson_status': lesson,
    'opens_on': null,
    'slot': {'code': slot, 'date': date, 'label_native': slot == 'today' ? 'сегодня' : (slot == 'tomorrow' ? 'завтра' : null)},
    'cards_total': 10,
    'cards_done': status == 'closed' ? 10 : 0,
    'minutes_spent': 0,
  };

  Plan plan(List<Map<String, dynamic>> days, {String status = 'active'}) {
    final current = days.cast<Map<String, dynamic>?>().firstWhere((d) => d!['status'] != 'closed', orElse: () => null);

    return Plan.fromJson({
      'id': 'plan',
      'status': status,
      'goal_text': 'Иду к врачу',
      'target_lang': 'en',
      'native_lang': 'ru',
      'level': 'beginner',
      'days_total': days.length,
      'days_requested': days.length,
      'route_summary': '',
      'current_day': current,
      'days': days,
      'scenes': const [],
      'rescue_kit': const [],
      'versions': const {'build': 'abc', 'prompt_plan': 'plan-builder-v2', 'prompt_lesson': 'lesson-v3'},
      'created_at': '2026-09-10T10:00:00Z',
    });
  }

  group('какой день на плите', () {
    test('день идёт сегодня — плита про него (21-2, 21-3)', () {
      final p = plan([
        day(number: 1, status: 'closed', slot: 'past'),
        day(number: 2, status: 'open', slot: 'today'),
        day(number: 3, status: 'locked', slot: 'tomorrow', type: 'review'),
      ]);
      final focus = PlanTabState.focusDayOf(p);
      expect(focus?.number, 2);
      expect(PlanTabState(plan: p, finished: const []).showsClosedDay, isFalse);
    });

    test('день закрыт сегодня, следующий завтра — плита про закрытый (21-4)', () {
      final p = plan([
        day(number: 1, status: 'closed', slot: 'past'),
        day(number: 2, status: 'closed', slot: 'past'),
        day(number: 3, status: 'locked', slot: 'tomorrow', type: 'review'),
      ]);
      final s = PlanTabState(plan: p, finished: const []);
      expect(s.focusDay?.number, 2);
      expect(s.showsClosedDay, isTrue);
    });

    test('все дни закрыты — плиты нет, план пройден (21-7)', () {
      final p = plan([
        day(number: 1, status: 'closed', slot: 'past'),
        day(number: 2, status: 'closed', slot: 'past', type: 'rehearsal'),
      ]);
      expect(PlanTabState.focusDayOf(p), isNull);
      expect(p.allDaysClosed, isTrue);
      expect(p.closedDays, 2);
    });

    test('день 1 ещё пишется — плита в шиммере, статус слова сервера (22-5a)', () {
      final p = plan([
        day(number: 1, status: 'open', slot: 'today', lesson: 'building'),
        day(number: 2, status: 'locked', slot: 'tomorrow'),
      ]);
      final focus = PlanTabState.focusDayOf(p)!;
      expect(focus.lessonBuilding, isTrue);
      expect(focus.lessonFailed, isFalse);
    });

    test('день не собрался — так и сказано (22-5c)', () {
      final p = plan([day(number: 1, status: 'open', slot: 'today', lesson: 'failed')]);
      expect(PlanTabState.focusDayOf(p)!.lessonFailed, isTrue);
    });
  });

  group('слот дня — правило «сегодня / завтра / дата» приходит с сервера', () {
    test('коды читаются, подпись — только у сегодня и завтра', () {
      final today = PlanDaySlot.fromJson({'code': 'today', 'date': '2026-09-10', 'label_native': 'сегодня'});
      final tomorrow = PlanDaySlot.fromJson({'code': 'tomorrow', 'date': '2026-09-11', 'label_native': 'завтра'});
      final dated = PlanDaySlot.fromJson({'code': 'date', 'date': '2026-09-13', 'label_native': null});
      expect(today.code, PlanSlotCode.today);
      expect(today.labelNative, 'сегодня');
      expect(tomorrow.code, PlanSlotCode.tomorrow);
      expect(dated.code, PlanSlotCode.date);
      expect(dated.labelNative, isNull);
      expect(dated.date, '2026-09-13');
    });

    test('незнакомый код не роняет таб', () {
      expect(PlanDaySlot.fromJson({'code': 'someday'}).code, PlanSlotCode.unknown);
      expect(PlanStatus.fromWire('paused'), PlanStatus.unknown);
      expect(PlanDayType.fromWire('party'), PlanDayType.unknown);
    });
  });

  group('статус плана — слово сервера', () {
    test('overdue приходит готовым и считается живым', () {
      final p = plan([day(number: 1, status: 'open', slot: 'today')], status: 'overdue');
      expect(p.status, PlanStatus.overdue);
      expect(p.status.isLive, isTrue);
    });

    test('finished — не живой: таб покажет «плана нет»', () {
      expect(PlanStatus.finished.isLive, isFalse);
      expect(PlanStatus.ready.isLive, isFalse);
    });
  });

  group('кабинет дня — что плита читает из него', () {
    final room = PlanDayRoom.fromJson({
      'plan_id': 'plan',
      'day': day(number: 2, status: 'in_progress', slot: 'today'),
      'scene': null,
      'goals_native': const [],
      'stages': [
        {'stage': 'words', 'total': 32, 'done': 32, 'state': 'done'},
        {'stage': 'phrases', 'total': 18, 'done': 6, 'state': 'current'},
        {'stage': 'dialogue', 'total': 1, 'done': 0, 'state': 'locked'},
        {'stage': 'listen', 'total': 0, 'done': 0, 'state': 'absent'},
        {'stage': 'speak', 'total': 8, 'done': 0, 'state': 'locked'},
      ],
      'metrics': null,
      'program': [
        {'unit_kind': 'word', 'unit_ref': 'v1', 'scene_id': 's', 'source': 'today', 'cards_total': 4, 'cards_done': 4, 'state': 'passed'},
        {'unit_kind': 'word', 'unit_ref': 'v2', 'scene_id': 's', 'source': 'today', 'cards_total': 4, 'cards_done': 4, 'state': 'failed'},
        {'unit_kind': 'word', 'unit_ref': 'v3', 'scene_id': 's', 'source': 'returned', 'cards_total': 4, 'cards_done': 0, 'state': 'pending'},
        {'unit_kind': 'phrase', 'unit_ref': 'p1', 'scene_id': 's', 'source': 'today', 'cards_total': 3, 'cards_done': 0, 'state': 'failed'},
      ],
      'sheet_available': false,
    });

    test('новые слова — только сегодняшние слова, вернувшиеся не в счёт', () {
      expect(room.newWordsCount, 2);
    });

    test('«вернутся в следующий день» — единицы, не сданные дважды', () {
      expect(room.returningUnits, 2);
    });

    test('остаток дня — сумма остатков этапов', () {
      expect(room.cardsLeft, 12 + 1 + 8);
      expect(room.stages[1].state, PlanStageState.current);
    });
  });
}

import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/plan_sitting_store.dart';
import 'package:flutter_test/flutter_test.dart';

/// THE ПРИСЕСТ SURVIVES THE PROCESS — наряд SIT-1, Ч-6.
///
/// «Выход между присестами и смерть приложения сохраняют позицию durable — продолжение с места, без
/// пересоздания посадки.» The kill is modelled the only way a unit test honestly can: a SECOND store
/// over the same database file, holding none of the first one's memory. That is exactly what the
/// next launch is.
void main() {
  late AppDatabase db;

  setUp(() => db = AppDatabase.forTesting(NativeDatabase.memory()));
  tearDown(() => db.close());

  Map<String, dynamic> payload() => {
    'session_id': '01SESSION',
    'plan_id': '01PLAN',
    'day_index': 2,
    'strict': true,
    'sittings': [3, 3],
    'tasks': [
      for (var i = 0; i < 6; i++)
        {
          'stage': 'b',
          'section': 'day',
          'shelf': 'say',
          'card': {'term_id': 'T$i', 'exercise_mode': 'situational_say', 'answer': 'line $i'},
        },
    ],
  };

  PlanSittingState state({int position = 4}) => PlanSittingState(
    planId: '01PLAN',
    dayIndex: 2,
    payload: payload(),
    order: const [0, 1, 2, 1, 3, 4, 5],
    sittingEnds: const [4, 7],
    position: position,
    requeued: const [1],
  );

  test('a sitting written down is read back by a store that never saw it written', () async {
    await PlanSittingStore(db).save(state());

    // The kill: a new store, a new object graph, the same database.
    final resumed = await PlanSittingStore(db).restore(planId: '01PLAN', dayIndex: 2);

    expect(resumed, isNotNull);
    expect(resumed!.position, 4);
    expect(resumed.order, const [0, 1, 2, 1, 3, 4, 5]);
    expect(resumed.sittingEnds, const [4, 7]);
    expect(resumed.requeued, const [1]);
  });

  test('the session comes back from the stored payload, not from the network', () async {
    await PlanSittingStore(db).save(state());
    final resumed = await PlanSittingStore(db).restore(planId: '01PLAN', dayIndex: 2);

    // «Без пересоздания посадки»: the same cards, in the same order, dealt at whatever the ladder
    // said WHEN THE SITTING WAS BUILT — which is the whole reason the payload is stored rather than
    // the sitting being asked for again.
    final session = resumed!.session;
    expect(session.sessionId, '01SESSION');
    expect(session.tasks, hasLength(6));
    expect(session.sittings, const [3, 3]);
    expect(session.tasks.first.card.mode.wire, 'situational_say');
  });

  test('a sitting of another day is not resumed — and not thrown away either', () async {
    await PlanSittingStore(db).save(state());

    expect(await PlanSittingStore(db).restore(planId: '01PLAN', dayIndex: 3), isNull);
    expect(await PlanSittingStore(db).restore(planId: '01OTHER', dayIndex: 2), isNull);
    // Reading is never what loses somebody their place: only finishing the day clears it.
    expect(await PlanSittingStore(db).restore(planId: '01PLAN', dayIndex: 2), isNotNull);
  });

  test('finishing the day takes the position with it', () async {
    final store = PlanSittingStore(db);
    await store.save(state());
    await store.clear();

    expect(await store.restore(planId: '01PLAN', dayIndex: 2), isNull);
  });

  test('a half-written row starts the day over instead of crashing into it', () async {
    await db.setMeta('plan_sitting', '{"plan_id": "01PLAN", "day_in');

    expect(await PlanSittingStore(db).restore(planId: '01PLAN', dayIndex: 2), isNull);
  });

  test('a position past the end is clamped to the last card, never to a card that is not there',
      () async {
    await PlanSittingStore(db).save(state(position: 99));

    final resumed = await PlanSittingStore(db).restore(planId: '01PLAN', dayIndex: 2);

    expect(resumed!.position, 6);
  });
}

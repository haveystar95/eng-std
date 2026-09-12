import 'package:flutter/material.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/plan_providers.dart';
import 'package:eng_std/features/plan/plan_tab_screen.dart';
import 'package:eng_std/theme/theme.dart';

import '../../support/plan_goldens.dart';

/// СОСТОЯНИЯ ТАБА «ПЛАН» — ответ сервера → экран → снимок (серия 21, плюс 22-5a/b/c).
///
/// Каждый тест — одно предложение контракта: «сервер ответил ВОТ ТАК — экран выглядит ВОТ ТАК».
/// Фикстуры сняты с ЖИВОГО backend2 после PLAN-API-FIX-1 (12.09.2026), и состояния, до которых
/// план сам не доходит, тоже СНЯТЫ, а не подделаны: закрытый день прогнан по API карточка за
/// карточкой, прошедшее событие получено `plan:shift-day` + `start`, пересборка — переносом даты.
///
/// Правкой фикстуры остались кадры, которые проверяют КЛИЕНТА, а не сервер: 21-6 (нет короткого
/// названия, дней вдвое больше), 21-7 (все дни закрыты), 22-5a/22-5c (урок в сборке / отказ).
/// Фикстуры PLAN-UI-3 сняты живым прогоном 12.09 (план «Приём у врача», QA `qa-planui3@wt.test`).
///
/// `room_unopened` — кабинет дня, который ещё НЕ ОТКРЫВАЛИ: после PLAN-API-FIX-1 он уже отдаёт
/// все пять этапов с их `total`, поэтому плита не начатого дня (21-2) рисуется целиком. До
/// исправления тот же запрос отдавал пять `absent`, и плита стояла пустой.
///
/// Снимки — `test/goldens/plan/`. Обновлять: `flutter test --update-goldens test/features/plan/`.
void main() {
  setUpAll(setUpPlanGoldens);

  /// Настоящий экран таба с подменённым ответом сервера.
  Widget tab(
    PlanTabState state, {
    PlanHints hints = const PlanHints(tabShown: true, closeShown: true, howShown: true),
  }) => planGoldenApp(
    ProviderScope(
      overrides: [planTabProvider.overrideWith(() => _StubTab(state))],
      child: const Scaffold(
        extendBody: true,
        backgroundColor: AppColors.ground,
        body: PlanTabScreen(),
      ),
    ),
    hints: hints,
  );

  /// Завершённый план, открытый из списка (кадр 21-7 в режиме чтения).
  Widget readingMode(PlanTabState state) => planGoldenApp(
    Scaffold(
      backgroundColor: AppColors.ground,
      body: SafeArea(
        bottom: false,
        child: PlanTabBody(state: state, bottomInset: 26, readOnly: true),
      ),
    ),
  );

  PlanRow finishedRow(String id, String title, String created) => PlanRow.fromJson({
    'id': id,
    'status': 'finished',
    'goal_text': title,
    'title_native': title,
    'days_total': 5,
    'created_at': created,
    'finished_at': created,
    'collection_id': 'col$id',
  });

  // ── 21-1 · витрина ────────────────────────────────────────────────────────────────────────
  group('плана нет (кадр 21-1)', () {
    testWidgets('витрина: заголовок, подпись, три правила, одно действие', (tester) async {
      await expectPlanGolden(
        tester,
        tab(const PlanTabState(plan: null, finished: [])),
        'plan/21-1-empty',
      );
    });

    testWidgets('прокручен: карточка-пример и завершённые планы', (tester) async {
      await expectPlanGolden(
        tester,
        tab(
          PlanTabState(
            plan: null,
            finished: [
              finishedRow('p1', 'Приём с ребёнком', '2026-08-30T10:00:00Z'),
              finishedRow('p2', 'Аренда квартиры', '2026-07-14T10:00:00Z'),
            ],
          ),
        ),
        'plan/21-1-empty-scrolled',
        size: const Size(390, 1250),
      );
    });

    testWidgets('офлайн без кэша — «Не получилось загрузить план»', (tester) async {
      await expectPlanGolden(
        tester,
        planGoldenApp(
          ProviderScope(
            overrides: [planTabProvider.overrideWith(_FailingTab.new)],
            child: const Scaffold(
              extendBody: true,
              backgroundColor: AppColors.ground,
              body: PlanTabScreen(),
            ),
          ),
        ),
        'plan/21-1-load-failed',
      );
    });
  });

  // ── 21-2 · план идёт, день не начат ───────────────────────────────────────────────────────
  group('день не начат (кадры 21-2, 21-2b, 21-2c)', () {
    // Канонический кадр 21-2: день 1 пройден, день 2 сегодня и не начат — снято живьём.
    PlanTabState fresh() => PlanTabState(
      plan: planFrom('current_day2'),
      room: roomFrom('room_day2'),
      finished: const [],
    );

    testWidgets('верх: шапка, плита с «Начать», начало маршрута', (tester) async {
      await expectPlanGolden(tester, tab(fresh()), 'plan/21-2');
    });

    testWidgets('прокручен: маршрут целиком и мишень события', (tester) async {
      await expectPlanGolden(
        tester,
        tab(fresh()),
        'plan/21-2b-route',
        size: const Size(390, 1500),
      );
    });

    testWidgets('первый план — подсказки под плитой и маршрутом', (tester) async {
      await expectPlanGolden(
        tester,
        tab(
          PlanTabState(plan: planFrom('current_started'), room: roomFrom('room_unopened'), finished: const []),
          hints: const PlanHints(tabShown: false, closeShown: false, howShown: false),
        ),
        'plan/21-2c-first-hints',
      );
    });

    testWidgets('нет сети — то же состояние из кэша под строкой «нет сети»', (tester) async {
      await expectPlanGolden(
        tester,
        tab(
          PlanTabState(plan: planFrom('current_day2'), finished: const [], offline: true),
        ),
        'plan/21-2-offline',
      );
    });
  });

  // ── 21-3 · день идёт ──────────────────────────────────────────────────────────────────────
  testWidgets('день идёт — «Продолжить» (кадр 21-3)', (tester) async {
    await expectPlanGolden(
      tester,
      tab(
        PlanTabState(
          plan: planFrom('current_progress'),
          room: roomFrom('room_progress'),
          finished: const [],
        ),
      ),
      'plan/21-3',
    );
  });

  // ── 21-4 · день закрыт ────────────────────────────────────────────────────────────────────
  group('день закрыт (кадры 21-4, 21-4c, 21-5)', () {
    PlanTabState closed() => PlanTabState(
      plan: planFrom('current_closed'),
      room: roomFrom('room_closed'),
      finished: const [],
    );

    testWidgets('светлая бумага, этапы с полным счётом, две строки подвала', (tester) async {
      await expectPlanGolden(tester, tab(closed()), 'plan/21-4');
    });

    testWidgets('первое закрытие — подсказка про возврат карточек', (tester) async {
      await expectPlanGolden(
        tester,
        tab(
          closed(),
          hints: const PlanHints(tabShown: true, closeShown: false, howShown: true),
        ),
        'plan/21-4c-close-hint',
      );
    });

    testWidgets('маршрут целиком после закрытия дня (кадр 21-5)', (tester) async {
      await expectPlanGolden(
        tester,
        tab(closed()),
        'plan/21-5-route-after-close',
        size: const Size(390, 1400),
      );
    });
  });

  // ── 21-6 · масштаб и фолбэк шапки ─────────────────────────────────────────────────────────
  group('масштаб: нет короткого названия, десять дней (кадр 21-6)', () {
    testWidgets('верх: формулировка цели вместо названия, три строки', (tester) async {
      await expectPlanGolden(
        tester,
        tab(PlanTabState(plan: planFrom('current_started', _tenDaysNoTitle), finished: const [])),
        'plan/21-6',
      );
    });

    testWidgets('прокручен: середина и хвост маршрута', (tester) async {
      await expectPlanGolden(
        tester,
        tab(PlanTabState(plan: planFrom('current_started', _tenDaysNoTitle), finished: const [])),
        'plan/21-6-scrolled',
        size: const Size(390, 2100),
      );
    });
  });

  // ── 21-7 · план пройден ───────────────────────────────────────────────────────────────────
  group('план пройден (кадр 21-7)', () {
    testWidgets('итог плана и «Собрать новый план»', (tester) async {
      await expectPlanGolden(
        tester,
        tab(PlanTabState(plan: planFrom('current_closed', _allClosed), finished: const [])),
        'plan/21-7',
        size: const Size(390, 1200),
      );
    });

    testWidgets('завершённый план из списка — режим чтения', (tester) async {
      await expectPlanGolden(
        tester,
        readingMode(PlanTabState(plan: planFrom('current_done'), finished: const [])),
        'plan/21-7-reading',
        size: const Size(390, 1200),
      );
    });
  });

  // ── 21-9 · меню плана ─────────────────────────────────────────────────────────────────────
  testWidgets('меню под «…» — четыре действия текстом (кадр 21-9)', (tester) async {
    await expectPlanGolden(
      tester,
      tab(
        PlanTabState(
          plan: planFrom('current_day2'),
          room: roomFrom('room_day2'),
          finished: const [],
        ),
      ),
      'plan/21-9-menu',
      // Меню открывает сам экран — тест жмёт «…» там же, где человек.
      prime: (tester) async {
        await tester.tap(find.byIcon(LucideIcons.ellipsis));
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 300));
      },
    );
  });

  // ── 21-13 · маршрут пересобран ────────────────────────────────────────────────────────────
  testWidgets('маршрут пересобран — плашка над плитой (кадр 21-13)', (tester) async {
    await expectPlanGolden(
      tester,
      tab(
        PlanTabState(
          plan: planFrom('current_rebuilt'),
          room: roomFrom('room_day2'),
          finished: const [],
        ),
      ),
      'plan/21-13',
    );
  });

  // ── 21-14 · событие прошло ────────────────────────────────────────────────────────────────
  testWidgets('событие прошло, план не закончен (кадр 21-14)', (tester) async {
    await expectPlanGolden(
      tester,
      tab(PlanTabState(plan: planFrom('current_overdue'), finished: const [])),
      'plan/21-14',
    );
  });

  // ── 22-5a/b/c · после «Начать» ────────────────────────────────────────────────────────────
  group('после «Начать» (кадры 22-5a, 22-5b, 22-5c)', () {
    testWidgets('день 1 собирается — срок и разрешение уйти', (tester) async {
      await expectPlanGolden(
        tester,
        tab(PlanTabState(plan: planFrom('current_started', _lesson('building')), finished: const [])),
        'plan/22-5a-building',
      );
    });

    testWidgets('день готов — этапы со счётом и «Начать»', (tester) async {
      await expectPlanGolden(
        tester,
        tab(
          PlanTabState(
            plan: planFrom('current_started'),
            room: roomFrom('room_unopened'),
            finished: const [],
          ),
          // Подсказки первого плана здесь НЕ показываются — они приходят позже и один раз.
          hints: const PlanHints(tabShown: true, closeShown: true, howShown: true),
        ),
        'plan/22-5b-ready',
      );
    });

    testWidgets('день не собрался — «Повторить», маршрут на месте', (tester) async {
      await expectPlanGolden(
        tester,
        tab(PlanTabState(plan: planFrom('current_started', _lesson('failed')), finished: const [])),
        'plan/22-5c-failed',
      );
    });
  });
}

// ── правки фикстуры ───────────────────────────────────────────────────────────────────────────

/// Урок дня 1 в другом состоянии — `building` (22-5a) или `failed` (22-5c).
///
/// Единственное, чего нельзя дождаться от снятого плана: сборка дня идёт секунды, а её отказ
/// требует уронить сеть ровно в эти секунды.
Map<String, dynamic> Function(Map<String, dynamic>) _lesson(String status) => (json) {
  final days = (json['days'] as List).cast<Map<String, dynamic>>();
  days.first['lesson_status'] = status;
  json['current_day'] = days.first;

  return json;
};

/// Все дни закрыты, текущего нет — «план пройден» (21-7).
Map<String, dynamic> _allClosed(Map<String, dynamic> json) {
  for (final d in (json['days'] as List).cast<Map<String, dynamic>>()) {
    d['status'] = 'closed';
    d['slot'] = {'code': 'past', 'date': d['slot']['date'], 'label_native': null};
    d['cards_done'] = d['cards_total'];
    // Закрытый день — все его этапы пройдены: состояние узлов сервер отдаёт словом, и у закрытого
    // дня это слово одно.
    for (final st in (d['stages'] as List).cast<Map<String, dynamic>>()) {
      st['state'] = 'done';
    }
  }
  json['current_day'] = null;
  json['until_phrase'] = null;

  return json;
}

/// КАДР 21-6 проверяет КЛИЕНТА, а не сервер: что он делает, когда короткого названия нет и дней
/// вдвое больше. Поэтому у снятого плана здесь отбирается `title_native` (шапка обязана перейти на
/// формулировку цели в три строки без троеточия) и дни доклеиваются до десяти — теми же полями,
/// своими номерами и датами.
Map<String, dynamic> _tenDaysNoTitle(Map<String, dynamic> json) {
  json['title_native'] = null;
  json['goal_text'] =
      'Едем в Лиссабон на неделю с ребёнком, боюсь не объясниться в отеле, в ресторане и в аптеке';
  final days = (json['days'] as List).cast<Map<String, dynamic>>();
  final titles = [
    'Приём у врача: жалобы и анализы',
    null,
    'Аптека: рецепт и дозировка',
    'Звонок в клинику: перенести приём',
    null,
  ];
  final start = days.length;
  for (var i = 0; i < 10 - start; i++) {
    final base = Map<String, dynamic>.from(days[i % start]);
    final number = start + 1 + i;
    base['id'] = 'day$number';
    base['number'] = number;
    base['status'] = 'locked';
    base['lesson_status'] = 'pending';
    base['title_native'] = titles[i % titles.length];
    base['teaches_native'] = titles[i % titles.length] == null
        ? null
        : 'назвать симптомы, понять назначение и сроки';
    base['slot'] = {'code': 'date', 'date': '2026-09-${16 + i}', 'label_native': null};
    days.add(base);
  }
  json['days'] = days;
  json['days_total'] = days.length;
  json['days_requested'] = days.length;
  json['route_summary'] = '10 дней · 6 ситуаций, 2 повторения, репетиция';

  return json;
}

/// Ответ сервера, уже разобранный: таб рисует его и ничего не спрашивает.
class _StubTab extends PlanTabController {
  _StubTab(this._state);

  final PlanTabState _state;

  @override
  Future<PlanTabState> build() async => _state;

  @override
  Future<void> refresh({bool silent = true}) async {}
}

/// Таб, которому сервер не ответил и кэша нет.
class _FailingTab extends PlanTabController {
  @override
  Future<PlanTabState> build() async => throw Exception('no network');

  @override
  Future<void> refresh({bool silent = true}) async {}
}

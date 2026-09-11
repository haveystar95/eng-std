import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/plan_providers.dart';
import 'package:eng_std/features/plan/plan_tab_screen.dart';
import 'package:eng_std/theme/theme.dart';

import '../../support/plan_goldens.dart';

/// СОСТОЯНИЯ ТАБА «ПЛАН» — ответ сервера → экран → снимок (кадры 21-x, 22-5x).
///
/// Каждый тест — это одно предложение контракта: «сервер ответил ВОТ ТАК — экран выглядит ВОТ
/// ТАК». Фикстуры сняты с живого backend2 (план врача, 5 дней, 11.09.2026) и лежат как пришли;
/// состояния, до которых живой план за один вечер не доходит (пройден, событие прошло, десять
/// дней, день не собрался), получены ЯВНОЙ правкой той же фикстуры — правка видна в тесте, и
/// видно, чем именно состояние отличается от снятого.
///
/// Снимки — `test/goldens/`. Обновлять: `flutter test --update-goldens test/features/plan/`.
void main() {
  setUpAll(setUpPlanGoldens);

  /// Настоящий экран таба с подменённым ответом сервера — снимается то, что видит человек, вместе
  /// с его подложкой и безопасной зоной. Оболочка табов даёт `Scaffold` (материал под чернильными
  /// откликами) и нижний отступ под плавающий таб-бар.
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

  group('плана нет (кадр 21-1)', () {
    testWidgets('«К чему готовишься?» и ничего больше', (tester) async {
      await expectPlanGolden(
        tester,
        tab(const PlanTabState(plan: null, finished: [])),
        'plan/21-1-empty',
      );
    });

    testWidgets('под приглашением — завершённые планы', (tester) async {
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
        'plan/21-1-empty-finished',
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

  group('день идёт сегодня (кадры 21-2, 21-2c)', () {
    testWidgets('день открыт, урок готов — плита с кнопкой «Начать»', (tester) async {
      await expectPlanGolden(
        tester,
        tab(
          PlanTabState(
            plan: planFrom('current_open'),
            room: roomFrom('room_day1_fresh'),
            finished: const [],
          ),
        ),
        'plan/21-2-plate-fresh',
      );
    });

    testWidgets('первый план — три подсказки первого раза', (tester) async {
      await expectPlanGolden(
        tester,
        tab(
          PlanTabState(
            plan: planFrom('current_open'),
            room: roomFrom('room_day1_fresh'),
            finished: const [],
          ),
          hints: const PlanHints(tabShown: false, closeShown: false, howShown: false),
        ),
        'plan/21-2c-first-hints',
      );
    });

    testWidgets('нет сети — то же состояние из кэша под строкой «нет сети»', (tester) async {
      await expectPlanGolden(
        tester,
        tab(
          PlanTabState(plan: planFrom('current_open'), finished: const [], offline: true),
        ),
        'plan/21-2-offline',
      );
    });

    testWidgets('маршрут и спасательный набор целиком', (tester) async {
      await expectPlanGolden(
        tester,
        tab(
          PlanTabState(
            plan: planFrom('current_abandoned'),
            room: roomFrom('room_day1_abandoned'),
            finished: const [],
          ),
        ),
        'plan/21-2b-route-and-kit',
        size: const Size(390, 1500),
      );
    });
  });

  testWidgets('день брошен на середине (кадр 21-3)', (tester) async {
    await expectPlanGolden(
      tester,
      tab(
        PlanTabState(
          plan: planFrom('current_abandoned'),
          room: roomFrom('room_day1_abandoned'),
          finished: const [],
        ),
      ),
      'plan/21-3-abandoned',
    );
  });

  group('день закрыт (кадры 21-4, 21-4c, 21-5)', () {
    PlanTabState closed() => PlanTabState(
      plan: planFrom('current_closed'),
      room: roomFrom('room_day1_closed'),
      finished: const [],
    );

    testWidgets('плита про закрытый день, следующий — завтра', (tester) async {
      await expectPlanGolden(tester, tab(closed()), 'plan/21-4-closed');
    });

    testWidgets('первое закрытие — подсказка про возврат', (tester) async {
      await expectPlanGolden(
        tester,
        tab(
          closed(),
          hints: const PlanHints(tabShown: true, closeShown: false, howShown: true),
        ),
        'plan/21-4c-close-hint',
      );
    });

    testWidgets('маршрут целиком после закрытия дня', (tester) async {
      await expectPlanGolden(
        tester,
        tab(closed()),
        'plan/21-5-route-after-close',
        size: const Size(390, 1500),
      );
    });
  });

  testWidgets('масштаб: десять дней и длинные строки (кадр 21-6)', (tester) async {
    await expectPlanGolden(
      tester,
      tab(
        PlanTabState(plan: planFrom('current_open', _tenDays), finished: const []),
      ),
      'plan/21-6-ten-days',
      size: const Size(390, 1900),
    );
  });

  group('план пройден (кадр 21-7)', () {
    testWidgets('живой план, все дни закрыты — «Собрать новый план»', (tester) async {
      await expectPlanGolden(
        tester,
        tab(PlanTabState(plan: planFrom('current_closed', _allClosed), finished: const [])),
        'plan/21-7-done',
        size: const Size(390, 1400),
      );
    });

    testWidgets('завершённый план из списка — режим чтения', (tester) async {
      await expectPlanGolden(
        tester,
        readingMode(
          PlanTabState(plan: planFrom('current_closed', _finished), finished: const []),
        ),
        'plan/21-7-done-reading',
        size: const Size(390, 1400),
      );
    });
  });

  testWidgets('событие прошло, план не закончен (кадр 21-14)', (tester) async {
    await expectPlanGolden(
      tester,
      tab(PlanTabState(plan: planFrom('current_closed', _overdue), finished: const [])),
      'plan/21-14-overdue',
    );
  });

  group('после «Начать» (кадры 22-5a, 22-5c)', () {
    testWidgets('день 1 ещё пишется — плита в шиммере', (tester) async {
      await expectPlanGolden(
        tester,
        tab(PlanTabState(plan: planFrom('current_open', _lesson('building')), finished: const [])),
        'plan/22-5a-building',
      );
    });

    testWidgets('день не собрался — «Повторить»', (tester) async {
      await expectPlanGolden(
        tester,
        tab(PlanTabState(plan: planFrom('current_open', _lesson('failed')), finished: const [])),
        'plan/22-5c-lesson-failed',
      );
    });
  });
}

// ── правки фикстуры: состояния, которых у снятого плана не было ──────────────────────────────

/// Урок дня 1 в другом состоянии — `building` (22-5a) или `failed` (22-5c).
Map<String, dynamic> Function(Map<String, dynamic>) _lesson(String status) => (json) {
  final days = (json['days'] as List).cast<Map<String, dynamic>>();
  days.first['lesson_status'] = status;
  json['current_day'] = days.first;

  return json;
};

/// Все дни закрыты, текущего нет — «план пройден» (21-7). Коллекция у плана уже есть.
Map<String, dynamic> _allClosed(Map<String, dynamic> json) {
  for (final d in (json['days'] as List).cast<Map<String, dynamic>>()) {
    d['status'] = 'closed';
    d['slot'] = {'code': 'past', 'date': d['slot']['date'], 'label_native': null};
    d['cards_done'] = d['cards_total'];
  }
  json['current_day'] = null;
  json['until_phrase'] = null;

  return json;
}

/// План, закрытый кнопкой «Собрать новый» — в списке завершённых и открытый оттуда (21-7).
Map<String, dynamic> _finished(Map<String, dynamic> json) {
  _allClosed(json);
  json['status'] = 'finished';
  json['finished_at'] = '2026-09-15T18:20:00Z';

  return json;
}

/// Событие прошло, а дни ещё остались — сервер отдаёт `overdue` и готовую строку (21-14).
Map<String, dynamic> _overdue(Map<String, dynamic> json) {
  json['status'] = 'overdue';
  json['overdue_native'] = 'Приём был вчера';
  json['until_phrase'] = null;
  // Дата события — во вчера снятой фикстуры (её «сегодня» — 11.09): мишень маршрута рисует ту же
  // дату, что и строка «Приём был вчера», иначе снимок показывал бы небывалое состояние.
  json['event_date'] = '2026-09-10';
  json['days_left'] = -1;

  return json;
}

/// Десять дней и длинные названия сцен — проверка масштаба (21-6): маршрут вдвое длиннее, строки
/// переносятся. Дни 6–10 слеплены из снятых: те же поля, свои номера, даты и заголовки.
Map<String, dynamic> _tenDays(Map<String, dynamic> json) {
  final days = (json['days'] as List).cast<Map<String, dynamic>>();
  final titles = [
    'Приём у врача: жалобы и анализы',
    'Повторение',
    'Аптека: рецепт и дозировка',
    'Звонок в клинику: перенести приём',
    'Репетиция',
  ];
  for (var i = 0; i < 5; i++) {
    final base = Map<String, dynamic>.from(days[i % days.length]);
    final number = 6 + i;
    base['id'] = 'day$number';
    base['number'] = number;
    base['status'] = 'locked';
    base['title_native'] = base['type'] == 'scene' ? titles[i] : null;
    base['teaches_native'] = base['type'] == 'scene'
        ? 'назвать симптомы, понять назначение и сроки'
        : null;
    base['slot'] = {'code': 'date', 'date': '2026-09-${16 + i}', 'label_native': null};
    days.add(base);
  }
  json['days'] = days;
  json['days_total'] = days.length;
  json['days_requested'] = days.length;
  json['route_summary'] = '10 дней · 6 ситуаций, 2 повторения, репетиция';

  return json;
}

/// Таб, которому сервер не ответил и кэша нет: `PlanTabScreen` рисует «Не получилось загрузить».
/// Ответ сервера, уже разобранный: таб рисует его и ничего не спрашивает.
class _StubTab extends PlanTabController {
  _StubTab(this._state);

  final PlanTabState _state;

  @override
  Future<PlanTabState> build() async => _state;

  @override
  Future<void> refresh({bool silent = true}) async {}
}

class _FailingTab extends PlanTabController {
  @override
  Future<PlanTabState> build() async => throw Exception('no network');

  @override
  Future<void> refresh({bool silent = true}) async {}
}

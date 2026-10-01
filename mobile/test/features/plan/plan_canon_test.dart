import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/plan/plan_languages.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/entry/plan_entry_screen.dart';
import 'package:eng_std/features/plan/plan_providers.dart';
import 'package:eng_std/features/plan/plan_tab_screen.dart';
import 'package:eng_std/features/plan/route/plan_route.dart';
import 'package:eng_std/features/plan/route/route_marks.dart';
import 'package:eng_std/features/plan/route/route_view.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../support/nbsp.dart';
import '../../support/plan_goldens.dart';

/// КАНОН ПЛАНА — правила канвы и наряда, а не снимок текущего кода.
///
/// Каждый тест назван правилом и в комментарии говорит, какой дефект ловит. Тест, который лишь
/// закрепляет то, как сейчас написан код, здесь не нужен: он запрещает исправления, а не ошибки.
/// Фикстуры — живые ответы backend2 (PLAN-UI-3, 12.09): `current_day2` — день 1 пройден, день 2
/// сегодня, дальше заперто.
void main() {
  setUpAll(setUpPlanGoldens);

  Widget tab(PlanTabState state) => planGoldenApp(
    ProviderScope(
      overrides: [planTabProvider.overrideWith(() => _StubTab(state))],
      child: const Scaffold(extendBody: true, backgroundColor: AppColors.ground, body: PlanTabScreen()),
    ),
  );

  Widget route(Plan plan, {ValueChanged<PlanDayRoute>? onOpen}) => planGoldenApp(
    Scaffold(
      backgroundColor: AppColors.ground,
      body: SingleChildScrollView(padding: const EdgeInsets.all(20), child: PlanRoute(plan: plan, onOpenDay: onOpen ?? (_) {})),
    ),
  );

  Future<void> pump(WidgetTester tester, Widget app, {Size size = const Size(390, 2400)}) async {
    tester.view
      ..devicePixelRatio = 2
      ..physicalSize = size * 2;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(app);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
  }

  // ── ЗАПЕРТЫЙ ДЕНЬ ─────────────────────────────────────────────────────────────────────────
  group('запертый день не открывается', () {
    // ПРАВИЛО (наряд §1): тап по запертому дню не ведёт в кабинет — строка «откроется после дня N»
    // на месте, без экрана. Тест-замок наряда: тап по дню 3 при текущем дне 2.
    // ЛОВИТ: узел, который зовёт кабинет у любого дня (сервер ответил бы 409 уже в кабинете, и
    // человек увидел бы экран ошибки вместо строки на маршруте).
    testWidgets('тап по дню 3 при текущем дне 2 — навигации нет, причина на месте', (tester) async {
      final opened = <int>[];
      await pump(tester, route(planFrom('current_day2'), onOpen: (d) => opened.add(d.number)));

      await tester.tap(find.text('День 3 · Повторение'));
      await tester.pump();

      expect(opened, isEmpty, reason: 'запертый день не открывает кабинет');
      expect(_texts(tester).where((t) => t.contains('откроется завтра') || t.contains('откроется после дня 2')), isNotEmpty);
    });

    // ЛОВИТ: причина, которая есть только у первого запертого дня, — тап по дальнему дню молчал бы.
    testWidgets('тап по дальнему запертому дню дописывает ЕГО причину', (tester) async {
      final opened = <int>[];
      await pump(tester, route(planFrom('current_day2'), onOpen: (d) => opened.add(d.number)));

      await tester.tap(find.textContaining('День 5 ·'));
      await tester.pump();

      expect(opened, isEmpty);
      expect(_texts(tester).any((t) => t.contains('откроется после дня 4')), isTrue);
    });

    // ПРАВИЛО: сегодняшний и пройденный дни открываются.
    // ЛОВИТ: «замок», который перестарался и запер весь маршрут.
    testWidgets('тап по сегодняшнему и пройденному дню — кабинет', (tester) async {
      final opened = <int>[];
      await pump(tester, route(planFrom('current_day2'), onOpen: (d) => opened.add(d.number)));

      await tester.tap(find.textContaining('День 2 ·'));
      await tester.tap(find.textContaining('День 1 ·'));
      await tester.pump();

      expect(opened, [2, 1]);
    });
  });

  // ── УЗЛЫ ЭТАПОВ ───────────────────────────────────────────────────────────────────────────
  group('узлы этапов — ровно те, что пришли с сервера', () {
    // ПРАВИЛО (решение владельца 12.09): пять этапов как на сервере; у повторения и репетиции
    // столько узлов, сколько этапов сервер отдал для дня; нет этапа в ответе — узла нет.
    // ЛОВИТ: клиент, который дорисовывает пять узлов каждому дню по привычке.
    testWidgets('у дня столько точек, сколько этапов в ответе', (tester) async {
      final plan = planFrom('current_day2');
      await pump(tester, route(plan));

      final expected = plan.days.fold<int>(0, (n, d) => n + d.stages.length);
      expect(tester.widgetList(find.byType(RouteChildDot)).length, expected);
      // У репетиции ровно один этап — «Говорю сам».
      expect(plan.days.last.type, PlanDayType.rehearsal);
      expect(plan.days.last.stages.map((s) => s.stage), [PlanStage.speak]);
    });

    // ЛОВИТ: ответ без ключа `stages` (кэш до наряда), нарисованный пятью выдуманными узлами.
    testWidgets('нет этапов в ответе — нет узлов', (tester) async {
      await pump(tester, route(planFrom('current_day2', _noStages)));

      expect(find.byType(RouteChildDot), findsNothing);
    });

    // ПРАВИЛО (наряд CLIENT-CONV-1a): шестой этап — такой же узел, как остальные, а этап, которого
    // эта сборка не знает, ПРОПУСКАЕТСЯ: план, который весь таб не может нарисовать из-за одного
    // чужого слова, хуже линии на один узел короче.
    // ЛОВИТ: `PlanContractError` на `conversation` — с включённым рубильником это роняло весь план.
    test('шестой этап — узел; незнакомый этап пропущен, а не роняет план', () {
      final day = PlanDayRoute.fromJson({
        'id': 'd',
        'number': 1,
        'slot': {'code': 'today'},
        'stages': [
          {'stage': 'words', 'state': 'done'},
          {'stage': 'conversation', 'state': 'current'},
          {'stage': 'telepathy', 'state': 'locked'},
        ],
      });
      expect(day.stages.map((s) => s.stage), [PlanStage.words, PlanStage.conversation]);
    });

    // ПРАВИЛО (наряд §1): неизвестное СОСТОЯНИЕ этапа — честная ошибка, а не догадка: имя этапа
    // можно пропустить, а его прогресс — нет.
    // ЛОВИТ: `fromWire` с запасным `locked` — узел нарисовал бы неправду о прогрессе.
    test('незнакомое слово состояния этапа — PlanContractError', () {
      expect(
        () => PlanDayRoute.fromJson({
          'id': 'd',
          'number': 1,
          'slot': {'code': 'today'},
          'stages': [
            {'stage': 'words', 'state': 'absent'},
          ],
        }),
        throwsA(isA<PlanContractError>()),
      );
    });
  });

  // ── ЛИНИЯ = ПРОГРЕСС ──────────────────────────────────────────────────────────────────────
  group('линия заливается до последнего пройденного этапа', () {
    List<Color> lineColors(WidgetTester tester) => tester
        .widgetList<ColoredBox>(
          find.descendant(of: find.byType(PlanRoute), matching: find.byWidgetPredicate((w) => w is ColoredBox && w.child == null)),
        )
        .map((b) => b.color)
        .toList();

    // ПРАВИЛО (канва PLAN-DES-3, эталон 21-2): день 1 пройден — шалфей через все его узлы и до
    // картинки дня 2; отрезок в текущий этап дня 2 — латунь; дальше серое.
    // ЛОВИТ: линию одного цвета (прогресса не видно) и латунь на двух отрезках сразу.
    testWidgets('шалфей до дня 2, одна латунь, дальше серое', (tester) async {
      final plan = planFrom('current_day2');
      await pump(tester, route(plan));
      final colors = lineColors(tester);

      // Каждый отрезок нарисован двумя половинами (у двух соседних узлов).
      final walked = colors.where((c) => c == AppColors.verdictKnown).length ~/ 2;
      final current = colors.where((c) => c == AppColors.brassInk).length ~/ 2;
      final day1Nodes = 1 + plan.days.first.stages.length;
      expect(walked, day1Nodes, reason: 'узлы дня 1 и отрезок в картинку дня 2');
      expect(current, 1, reason: 'латунь — только отрезок в текущий этап');
      expect(colors.contains(AppColors.routeAhead), isTrue);
    });

    // ЛОВИТ: заливку у плана, который ещё не начат (превью и `ready`-план на табе).
    testWidgets('не начатый план — ни шалфея, ни латуни', (tester) async {
      await pump(tester, route(planFrom('current_ready')));
      final colors = lineColors(tester);

      expect(colors.where((c) => c == AppColors.verdictKnown), isEmpty);
      expect(colors.where((c) => c == AppColors.brassInk), isEmpty);
    });
  });

  // ── ТРИ СОСТОЯНИЯ ДНЯ ─────────────────────────────────────────────────────────────────────
  group('три состояния дня — цвет и вуаль, не прозрачность целиком', () {
    // ПРАВИЛО (наряд §1): запертый день — вуаль на картинке и серый текст; без Opacity над узлом.
    // ЛОВИТ: `Opacity(.4)` на весь узел — гаснут и линия, и точки этапов, и латунь соседей.
    testWidgets('в маршруте нет Opacity, у запертого дня — вуаль', (tester) async {
      await pump(tester, route(planFrom('current_day2')));

      expect(find.descendant(of: find.byType(PlanRoute), matching: find.byType(Opacity)), findsNothing);
      final veils = tester.widgetList<ColoredBox>(find.byWidgetPredicate((w) => w is ColoredBox && w.color == AppColors.routeVeil));
      expect(veils, isNotEmpty);
    });

    // ПРАВИЛО: латунная обводка 2 — ровно у одного узла, у сегодняшнего дня.
    // ЛОВИТ: обводку у каждого незакрытого дня.
    testWidgets('латунная обводка — у одного дня', (tester) async {
      await pump(tester, route(planFrom('current_day2')));
      final ringed = tester.widgetList<RouteDayCircle>(find.byType(RouteDayCircle)).where((c) => c.tone == RouteDayTone.current);

      expect(ringed.length, 1);
    });

    // ПРАВИЛО: подпись «сегодня» — у одного дня маршрута (слот сервера), «завтра» — не больше одного.
    // ЛОВИТ: два открытых дня сразу.
    testWidgets('«сегодня» — у одного дня', (tester) async {
      await pump(tester, tab(PlanTabState(plan: planFrom('current_day2'), room: roomFrom('room_day2'), finished: const [])));

      final route = find.byType(PlanRoute);
      final today = tester.widgetList<Text>(find.descendant(of: route, matching: find.text('сегодня')));
      expect(today.length, 1);
    });

    // ПРАВИЛО: мета-строка стоит ПОД заголовком дня, номер дня — в заголовке.
    // ЛОВИТ: мету на линии или над заголовком.
    testWidgets('мета под заголовком', (tester) async {
      await pump(tester, route(planFrom('current_day2')));

      final titleY = tester.getTopLeft(find.textContaining('День 1 ·')).dy;
      final metaY = tester.getTopLeft(find.textContaining('пройден')).dy;
      expect(titleY, lessThan(metaY));
    });
  });

  // ── ТРОЕТОЧИЙ НЕТ ─────────────────────────────────────────────────────────────────────────
  // ПРАВИЛО: ни одна строка плана не обрывается троеточием (21-6: «ни одна строка не сжата»).
  // ЛОВИТ: `ellipsis` в новом маршруте и на плите.
  testWidgets('таб: ellipsis 0 во всех снятых состояниях', (tester) async {
    for (final (plan, room) in const [
      ('current_started', 'room_unopened'),
      ('current_progress', 'room_progress'),
      ('current_closed', 'room_closed'),
      ('current_day2', 'room_day2'),
    ]) {
      await pump(tester, tab(PlanTabState(plan: planFrom(plan), room: roomFrom(room), finished: const [])));
      final ellipsised = tester.widgetList<Text>(find.byType(Text)).where((t) => t.overflow == TextOverflow.ellipsis).map((t) => t.data);

      expect(ellipsised, isEmpty, reason: '$plan: ${ellipsised.toList()}');
    }
  });

  // ── ПЛИТА ─────────────────────────────────────────────────────────────────────────────────
  // ПРАВИЛО (решение владельца 12.09): плита — те же пять строк, что в кабинете, состояние
  // СЛОВАМИ, одно действие.
  // ЛОВИТ: возврат счётчиков «0 / 32» вместо слов и вторую кнопку на плите.
  testWidgets('плита дня: пять этапов, состояние словами, одно действие', (tester) async {
    await pump(tester, tab(PlanTabState(plan: planFrom('current_day2'), room: roomFrom('room_day2'), finished: const [])));
    final plate = find.byType(DayPlate);

    Iterable<String> onPlate(String t) => tester.widgetList<Text>(find.descendant(of: plate, matching: find.text(t))).map((w) => w.data!);
    expect(onPlate('идёт').length, 1);
    expect(onPlate('впереди').length, 4);
    expect(find.descendant(of: plate, matching: find.textContaining(' / ')), findsNothing);
    expect(find.descendant(of: plate, matching: find.text('Начать')), findsOneWidget);
  });

  // ── ВХОД ──────────────────────────────────────────────────────────────────────────────────
  group('вход в план', () {
    // ONE flat scope: the language lists are read through `planLanguagesProvider`, which depends on
    // the api — in a nested scope it would be created at the root and ask the real network.
    Widget entry() => ProviderScope(
      overrides: [
        authControllerProvider.overrideWith(_NoProfileAuth.new),
        apiClientProvider.overrideWithValue(_LanguagesApi()),
        connectivityProvider.overrideWith((ref) => Stream.value(true)),
      ],
      child: planGoldenShell(const PlanEntryScreen()),
    );

    // ПРАВИЛО (22-1): «Далее» неактивно при пустом поле и оживает от первого слова.
    testWidgets('«Далее» неактивно при пустом поле и оживает от первого слова', (tester) async {
      await pump(tester, entry(), size: const Size(390, 844));
      await tester.tap(find.text('Далее'));
      await tester.pump(const Duration(milliseconds: 200));
      expect(find.text(nbTypo('К чему готовишься?')), findsOneWidget);

      await tester.enterText(find.byType(TextField), 'врач');
      await tester.pump();
      await tester.tap(find.text('Далее'));
      await tester.pump(const Duration(milliseconds: 200));
      expect(find.text(nbTypo('На каком языке говорить?')), findsOneWidget);
    });

    // ПРАВИЛО (наряд §2): состояния «распознаю…» нет — голос печатается в поле по мере речи.
    // ЛОВИТ: возврат экрана распознавания вместе с его строкой.
    testWidgets('«распознаю…» на шаге цели нет', (tester) async {
      await pump(tester, entry(), size: const Size(390, 844));

      expect(find.textContaining('распозна'), findsNothing);
    });

    // ПРАВИЛО (решение владельца 12.09): список языков — с сервера, а не константа клиента;
    // названия — русскими именами, флаг — по коду.
    // ЛОВИТ: константу на три языка (кадр с тремя карточками при серверном списке из двух).
    testWidgets('языки — ровно серверный список, русскими именами, с флагом', (tester) async {
      await pump(tester, entry(), size: const Size(390, 1000));
      await tester.enterText(find.byType(TextField), 'иду к врачу с ребёнком в клинику');
      await tester.pump();
      await tester.tap(find.text('Далее'));
      await tester.pump(const Duration(milliseconds: 200));

      expect(find.text('Английский'), findsOneWidget);
      expect(find.text('Немецкий'), findsOneWidget);
      expect(find.text('Испанский'), findsNothing, reason: 'сервер его не предлагает');
      expect(find.text('English'), findsNothing);
      expect(find.text('\u{1F1EC}\u{1F1E7}'), findsOneWidget);
    });
  });
}

Iterable<String> _texts(WidgetTester tester) => tester.widgetList<Text>(find.byType(Text)).map((t) => t.data).whereType<String>();

Map<String, dynamic> _noStages(Map<String, dynamic> json) {
  for (final d in (json['days'] as List).cast<Map<String, dynamic>>()) {
    d.remove('stages');
  }

  return json;
}

class _StubTab extends PlanTabController {
  _StubTab(this._state);

  final PlanTabState _state;

  @override
  Future<PlanTabState> build() async => _state;

  @override
  Future<void> refresh({bool silent = true}) async {}
}

/// The entry's server: only the language lists — two targets here, so it shows that the screen draws
/// exactly the server's list and not the bundled reserve (which has seven).
class _LanguagesApi implements ApiClient {
  @override
  Future<PlanLanguages> pairLanguages() async => PlanLanguages.fromJson(languagesFixture(targets: const ['en', 'de']));

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// The account as the plan snapshots have it — a name and no profile yet.
class _NoProfileAuth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(id: 'u1', name: 'Денис');
}

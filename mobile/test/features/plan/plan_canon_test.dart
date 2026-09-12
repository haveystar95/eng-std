import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/entry/plan_entry_screen.dart';
import 'package:eng_std/features/plan/plan_providers.dart';
import 'package:eng_std/features/plan/plan_route.dart';
import 'package:eng_std/features/plan/plan_tab_screen.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../support/plan_goldens.dart';

/// КАНОН ПЛАНА — правила канвы, а не снимок текущего кода.
///
/// Разница принципиальная: снимок падает от любой правки пикселя и ничего не утверждает, а эти
/// тесты утверждают ПРАВИЛО и упадут ровно тогда, когда правило нарушено — даже если экран при
/// этом выглядит прилично. Тест, который лишь закрепляет то, как сейчас написан код, здесь не
/// нужен: он запрещает исправления, а не ошибки.
void main() {
  setUpAll(setUpPlanGoldens);

  Widget tab(PlanTabState state) => planGoldenApp(
    ProviderScope(
      overrides: [planTabProvider.overrideWith(() => _StubTab(state))],
      child: const Scaffold(
        extendBody: true,
        backgroundColor: AppColors.ground,
        body: PlanTabScreen(),
      ),
    ),
  );

  Future<void> pumpTab(WidgetTester tester, Widget app, {Size size = const Size(390, 2200)}) async {
    tester.view
      ..devicePixelRatio = 2
      ..physicalSize = size * 2;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(app);
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
  }

  // ── ЛЕСТНИЦА ДНЕЙ ─────────────────────────────────────────────────────────────────────────
  group('день N+1 открывается только когда день N пройден', () {
    testWidgets('ровно один день носит подпись «сегодня»', (tester) async {
      await pumpTab(
        tester,
        tab(PlanTabState(plan: planFrom('current_closed'), finished: const [])),
      );

      // Подпись слота приходит с сервера и стоит только у текущего дня: два «сегодня» на экране
      // означали бы, что открыто два дня сразу.
      expect(_texts(tester).where((t) => t == 'сегодня').length, lessThanOrEqualTo(1));
      expect(_texts(tester).where((t) => t == 'завтра').length, lessThanOrEqualTo(1));
    });

    testWidgets('причину открытия носит ТОЛЬКО первый запертый день', (tester) async {
      await pumpTab(
        tester,
        tab(PlanTabState(plan: planFrom('current_ready'), finished: const [])),
      );

      // «откроется после дня N» / «откроется завтра» — по одному на маршрут: иначе маршрут
      // читается как список запретов, а не как дорога.
      final reasons = _texts(tester).where(
        (t) => t.contains('откроется после дня') || t.contains('откроется завтра'),
      );
      expect(reasons.length, 1, reason: 'причин открытия на экране: ${reasons.toList()}');
    });

    testWidgets('после закрытия дня причина становится календарной, а не «после дня N»',
        (tester) async {
      // Канон 21-4: предыдущий день ПРОЙДЕН, значит ждать осталось не работу, а календарь.
      await pumpTab(
        tester,
        tab(PlanTabState(plan: planFrom('current_closed'), finished: const [])),
      );

      expect(
        _texts(tester).any((t) => t.contains('откроется завтра')),
        isTrue,
        reason: 'у первого запертого дня после закрытого должно стоять «откроется завтра»',
      );
      expect(
        _texts(tester).any((t) => t.contains('откроется после дня')),
        isFalse,
        reason: 'работа предыдущего дня уже сделана — ссылаться на неё нечем',
      );
    });
  });

  // ── ЛАТУНЬ ────────────────────────────────────────────────────────────────────────────────
  group('латунь метит ОДИН день, а не украшает экран', () {
    testWidgets('в маршруте латунную обводку носит ровно один узел', (tester) async {
      await pumpTab(
        tester,
        tab(PlanTabState(plan: planFrom('current_ready'), finished: const [])),
      );

      final ringed = tester
          .widgetList<Container>(
            find.descendant(of: find.byType(PlanRoute), matching: find.byType(Container)),
          )
          .where((c) {
            final d = c.decoration;
            if (d is! BoxDecoration || d.border == null) return false;
            final side = (d.border! as Border).top;

            return side.color == AppColors.brass && side.width == 2;
          });

      expect(ringed.length, 1, reason: 'латунная обводка 2 — только у текущего дня');
    });

    testWidgets('в шапке плана латуни нет — она метит день, а не план', (tester) async {
      await pumpTab(
        tester,
        tab(PlanTabState(plan: planFrom('current_ready'), finished: const [])),
        size: const Size(390, 400),
      );

      final headerBrass = tester
          .widgetList<Text>(find.byType(Text))
          .where((t) => t.style?.color == AppColors.brassInk || t.style?.color == AppColors.brass)
          .where((t) => (t.data ?? '').startsWith('План · '));

      expect(headerBrass, isEmpty);
    });
  });

  // ── ТРОЕТОЧИЙ НЕТ ─────────────────────────────────────────────────────────────────────────
  group('ни одна строка плана не обрывается троеточием', () {
    testWidgets('таб: ellipsis 0 во всех состояниях', (tester) async {
      for (final fixture in const ['current_ready', 'current_progress', 'current_closed']) {
        await pumpTab(tester, tab(PlanTabState(plan: planFrom(fixture), finished: const [])));
        final ellipsised = tester
            .widgetList<Text>(find.byType(Text))
            .where((t) => t.overflow == TextOverflow.ellipsis)
            .map((t) => t.data ?? '<rich>');

        expect(ellipsised, isEmpty, reason: 'фикстура $fixture: ${ellipsised.toList()}');
      }
    });

    testWidgets('длинное название дня переносится на две строки, а не обрезается',
        (tester) async {
      await pumpTab(
        tester,
        tab(
          PlanTabState(
            plan: planFrom('current_ready', _longDayTitle),
            room: roomFrom('room_unopened'),
            finished: const [],
          ),
        ),
      );

      // На ПЛИТЕ длинное название занимает ровно две строки и обрезается рамкой, не троеточием;
      // в МАРШРУТЕ оно переносится свободно — там высоту узла держит сам текст.
      final onPlate = tester.widget<Text>(
        find.descendant(of: find.byType(DayPlate), matching: find.text(_longTitle)),
      );
      expect(onPlate.maxLines, 2);
      expect(onPlate.overflow, isNot(TextOverflow.ellipsis));

      final inRoute = tester.widget<Text>(
        find.descendant(of: find.byType(PlanRoute), matching: find.text(_longTitle)),
      );
      expect(inRoute.maxLines, isNull, reason: 'узел маршрута не ограничивает название');
      expect(inRoute.overflow, isNot(TextOverflow.ellipsis));
    });
  });

  // ── ТРИ СТРОКИ УЗЛА В ОДНОМ ПОРЯДКЕ ──────────────────────────────────────────────────────
  testWidgets('узел маршрута: заголовок → описание → мета, всегда в этом порядке',
      (tester) async {
    await pumpTab(
      tester,
      tab(PlanTabState(plan: planFrom('current_ready'), finished: const [])),
    );

    // Берём день 2: он запертый, у него все три строки и он не спорит со «сегодня».
    final plan = planFrom('current_ready');
    final day = plan.days.firstWhere((d) => d.number == 2);
    final title = day.titleNative!;
    final teaches = day.teachesNative!;

    // Ищем В МАРШРУТЕ: короткое название плана в шапке может совпасть с названием дня (у снятого
    // плана так и есть — «Приём у врача»), и незакреплённый поиск нашёл бы две строки.
    Finder inRoute(Finder f) => find.descendant(of: find.byType(PlanRoute), matching: f);
    final titleY = tester.getTopLeft(inRoute(find.text(title))).dy;
    final teachesY = tester.getTopLeft(inRoute(find.text(teaches))).dy;
    final metaY = tester
        .getTopLeft(
          inRoute(
            find.byWidgetPredicate((w) => w is Text && (w.data ?? '').startsWith('День 2 ·')),
          ),
        )
        .dy;

    expect(titleY, lessThan(teachesY), reason: 'описание стоит ПОД заголовком');
    expect(teachesY, lessThan(metaY), reason: 'мета стоит ПОД описанием');
    // Номер дня — ПЕРВОЕ слово меты, а не бейдж на картинке (канва вычла бейджи).
    final meta = _texts(tester).firstWhere((t) => t.startsWith('День 2 ·'));
    expect(meta.startsWith('День 2 · '), isTrue, reason: 'мета: $meta');
  });

  // ── ВХОД ──────────────────────────────────────────────────────────────────────────────────
  group('вход в план', () {
    Widget entry() => planGoldenApp(
      ProviderScope(
        overrides: [
          apiClientProvider.overrideWithValue(_NoApi()),
          connectivityProvider.overrideWith((ref) => Stream.value(true)),
        ],
        child: const PlanEntryScreen(),
      ),
    );

    testWidgets('«Далее» неактивно при пустом поле и оживает от первого слова', (tester) async {
      await pumpTab(tester, entry(), size: const Size(390, 844));

      // Кнопка одна и стоит внизу; «неактивна» здесь — про то, что нажатие НИЧЕГО НЕ ДЕЛАЕТ,
      // а не про цвет: цвет проверяет снимок.
      await tester.tap(find.text('Далее'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 200));
      expect(
        find.text('К чему готовишься?'),
        findsOneWidget,
        reason: 'с пустым полем вход остаётся на шаге цели',
      );

      await tester.enterText(find.byType(TextField), 'врач');
      await tester.pump();
      await tester.tap(find.text('Далее'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 200));
      expect(
        find.text('На каком языке говорить?'),
        findsOneWidget,
        reason: 'одно слово — валидный ответ (22-1c), и он пропускает дальше',
      );
    });

    testWidgets('одно слово проходит, а подсказка про уточнение не блокирует', (tester) async {
      await pumpTab(tester, entry(), size: const Size(390, 844));
      await tester.enterText(find.byType(TextField), 'врач');
      await tester.pump();

      expect(find.text('Добавь, с кем и что важно — план будет точнее'), findsOneWidget);
      // Подсказка есть И шаг проходится — это и значит «не блокирует».
      await tester.tap(find.text('Далее'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 200));
      expect(find.text('На каком языке говорить?'), findsOneWidget);
    });

    testWidgets('языки названы РУССКИМИ именами, а не эндонимами', (tester) async {
      await pumpTab(tester, entry(), size: const Size(390, 1000));
      await tester.enterText(find.byType(TextField), 'иду к врачу с ребёнком в клинику');
      await tester.pump();
      await tester.tap(find.text('Далее'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 200));

      expect(find.text('Английский'), findsOneWidget);
      expect(find.text('Немецкий'), findsOneWidget);
      // Эндонимов на этом шаге быть не должно: строка ПРО язык, а не на нём.
      expect(find.text('English'), findsNothing);
      expect(find.text('Deutsch'), findsNothing);
    });

    testWidgets('шапки «Отмена / Новый план / Далее» больше нет', (tester) async {
      await pumpTab(tester, entry(), size: const Size(390, 844));

      expect(find.text('Отмена'), findsNothing);
      expect(find.text('Новый план'), findsNothing);
      // «Далее» осталось РОВНО ОДНО — кнопкой внизу, а не ещё и в шапке.
      expect(find.text('Далее'), findsOneWidget);
    });
  });
}

/// Все строки, которые экран сейчас показывает.
Iterable<String> _texts(WidgetTester tester) => tester
    .widgetList<Text>(find.byType(Text))
    .map((t) => t.data)
    .whereType<String>();

const _longTitle = 'Повторный визит к врачу с результатами анализов и снимком';

Map<String, dynamic> _longDayTitle(Map<String, dynamic> json) {
  final days = (json['days'] as List).cast<Map<String, dynamic>>();
  days.first['title_native'] = _longTitle;
  json['current_day'] = days.first;

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

/// Сервер, к которому эти тесты не ходят: они проверяют шаги входа до сборки.
class _NoApi implements ApiClient {
  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

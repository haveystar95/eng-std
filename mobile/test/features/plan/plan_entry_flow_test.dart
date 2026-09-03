import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/token_store.dart';
import 'package:eng_std/features/plan/entry/entry_ui.dart';
import 'package:eng_std/features/plan/entry/plan_assembly_screen.dart';
import 'package:eng_std/features/plan/entry/plan_entry_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// СБОРКА ПЛАНА, когда она не удаётся — кадры V4·05в, 05г и 05б.
///
/// Three states that look almost the same and mean three different things, which is exactly why
/// they are worth a test: the first failure is the app's own business and grows NO buttons, the
/// second hands over control, and a lost connection is neither («подождём сеть», not «попробуем
/// ещё»). A refactor that collapses them would read as a tidy-up and would take the difference with
/// it.
MaterialApp _app(Widget home) => MaterialApp(
  locale: const Locale('ru'),
  localizationsDelegates: AppLocalizations.localizationsDelegates,
  supportedLocales: const [Locale('ru')],
  home: home,
);

/// An API that answers the draft and then fails the skeleton as many times as told.
class _FailingApi extends ApiClient {
  _FailingApi({required this.failures, this.offline = false, this.hangAfterFailures = false})
    : super(TokenStore());

  /// After the failures are used up, never answer at all — so a test can look at the screen while
  /// the retry is still in flight, which is the only moment кадр V4·05в exists.
  final bool hangAfterFailures;

  /// How many times `buildPlanOutline` refuses before it would succeed.
  final int failures;

  /// Refuse as «нет сети» rather than as «не собралось» — a different frame.
  final bool offline;

  int outlineCalls = 0;
  int createCalls = 0;

  @override
  Future<LearningPlan> createPlan({
    required String goalText,
    required String targetLang,
    required String level,
    required String? eventDate,
    required int minutesPerDay,
    List<ListenAnswer> listening = const [],
  }) async {
    createCalls++;

    return LearningPlan.fromJson({'id': '01DRAFT', 'status': 'draft', 'title': goalText});
  }

  @override
  Future<LearningPlan> buildPlanOutline(String planId) async {
    outlineCalls++;
    if (outlineCalls <= failures) {
      throw offline
          ? DioException.connectionError(
              requestOptions: RequestOptions(path: '/plans/$planId/outline'),
              reason: 'no network',
            )
          : StateError('the model refused');
    }

    if (hangAfterFailures) return Completer<LearningPlan>().future;

    return LearningPlan.fromJson({'id': planId, 'status': 'draft', 'title': 'план'});
  }
}

Widget _assembly() => const PlanAssemblyScreen(
  goalText: 'Иду к врачу, болит спина, надо объяснить и понять назначение',
  targetLang: 'en',
  level: PlanLevel.basic,
  eventDate: '2026-09-10',
  minutesPerDay: 20,
  listened: [],
);

Future<void> _pumpAssembly(WidgetTester tester, ApiClient api) async {
  tester.view.physicalSize = const Size(1170, 3000);
  tester.view.devicePixelRatio = 3;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    ProviderScope(
      overrides: [apiClientProvider.overrideWithValue(api)],
      child: _app(_assembly()),
    ),
  );
  // Past the 900 ms after which the screen is allowed to appear at all.
  await tester.pump(const Duration(milliseconds: 1000));
  await tester.pump(const Duration(milliseconds: 400));
}

/// Нажать на кнопку шага, где бы она ни оказалась в ленте.
///
/// `find.text` пропускает то, что список ещё не вывел на экран, а шаги входа длиннее любого
/// тестового окна: сначала подводим кнопку в видимую часть, потом жмём.
Future<void> _tapStepButton(WidgetTester tester, String label) async {
  final button = find.text(label, skipOffstage: false);
  await tester.ensureVisible(button);
  await tester.pumpAndSettle();
  await tester.tap(button);
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('nothing is painted for the first 900 ms — a fast answer never flashes a screen', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1170, 3000);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiClientProvider.overrideWithValue(_FailingApi(failures: 9))],
        child: _app(_assembly()),
      ),
    );
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.text('Разбираю цель'), findsNothing);

    await tester.pump(const Duration(milliseconds: 800));
    expect(find.text('Разбираю цель'), findsOneWidget);
  });

  testWidgets('the FIRST failure changes one line and grows no buttons (кадр V4·05в)', (
    tester,
  ) async {
    final api = _FailingApi(failures: 1, hangAfterFailures: true);
    await _pumpAssembly(tester, api);

    // The retry is the app's job: the line says so, and there is nothing to press.
    expect(find.textContaining('Не получилось собрать с первого раза'), findsOneWidget);
    expect(find.text('Вернуться к ответам'), findsNothing);
    expect(find.text('Написать нам'), findsNothing);
    expect(find.text('Попробовать снова'), findsNothing);
    // The same list of steps, so it is visible WHERE it stopped.
    expect(find.text('Разбираю цель'), findsOneWidget);
    expect(find.text('Подбираю реплики'), findsOneWidget);
    expect(find.text('Собираю слова'), findsOneWidget);
    // …and one draft, not two: a retry buys one more skeleton, never a second plan.
    expect(api.createCalls, 1);
  });

  testWidgets('the SECOND failure hands over control and keeps the answers (кадр V4·05г)', (
    tester,
  ) async {
    final api = _FailingApi(failures: 2);
    await _pumpAssembly(tester, api);
    // The self-retry, then its failure.
    await tester.pump(const Duration(milliseconds: 400));

    expect(find.textContaining('Не собралось. Твои ответы сохранены'), findsOneWidget);
    expect(find.text('Вернуться к ответам'), findsOneWidget);
    expect(find.text('Написать нам'), findsOneWidget);
    // TWO paid attempts, and no third: each one costs money.
    expect(api.outlineCalls, 2);
    expect(api.createCalls, 1);
  });

  testWidgets('a lost connection is its own frame, and the notify button says it cannot work', (
    tester,
  ) async {
    final api = _FailingApi(failures: 9, offline: true);
    await _pumpAssembly(tester, api);

    expect(find.text('Пропала связь на середине'), findsOneWidget);
    expect(find.text('Попробовать снова'), findsOneWidget);
    // A BUTTON THAT CANNOT WORK, said out loud rather than armed with nothing behind it.
    expect(find.text('Уведомления ещё не подключены'), findsOneWidget);
    final notify = tester.widget<EntrySecondary>(
      find.widgetWithText(EntrySecondary, 'Сообщить, когда будет готов'),
    );
    expect(notify.enabled, isFalse);
    // Offline is NOT a failed attempt: the model was never asked twice.
    expect(api.outlineCalls, 1);
  });

  testWidgets('the flow reaches the DATE step and draws it — «Без даты» included', (tester) async {
    // Каждый шаг входа рисуется хоть раз. Написан после живого прогона: экран даты выходил ПУСТЫМ
    // (`EntryChoice` без галочки держал внутри `Expanded`, а «Без даты» стоит в строке без
    // ограничения по ширине), и ни один тест до этого шага не доходил, поэтому падение было видно
    // только глазами на симуляторе.
    tester.view.physicalSize = const Size(1170, 3400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        // Разогрев не отвечает ничем — шаг слуха молча не предлагается, и «Дальше» с уровня ведёт
        // прямо к дате. Это же и есть проверка тихого фолбэка со стороны клиента.
        overrides: [apiClientProvider.overrideWithValue(_FailingApi(failures: 9))],
        child: _app(
          const PlanEntryScreen(
            initialGoal: 'Иду к врачу с ребёнком, надо объяснить симптомы и понять назначение',
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await _tapStepButton(tester, 'Дальше');
    expect(find.text('Какой язык учишь?'), findsOneWidget);

    await _tapStepButton(tester, 'Дальше');

    expect(find.text('Когда это случится?'), findsOneWidget);
    expect(find.text('Без даты', skipOffstage: false), findsOneWidget);
    expect(find.text('Сколько минут в день?', skipOffstage: false), findsOneWidget);
    expect(find.text('Собрать план', skipOffstage: false), findsOneWidget);
    // Шага слуха в ленте нет вовсе: его не предлагали, значит и возвращаться некуда.
    expect(find.textContaining('Слух', skipOffstage: false), findsNothing);
  });

  testWidgets('«Без даты» меняет строку-ориентир и не выдумывает дату', (tester) async {
    tester.view.physicalSize = const Size(1170, 3400);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiClientProvider.overrideWithValue(_FailingApi(failures: 9))],
        child: _app(
          const PlanEntryScreen(
            initialGoal: 'Иду к врачу с ребёнком, надо объяснить симптомы и понять назначение',
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await _tapStepButton(tester, 'Дальше');
    await _tapStepButton(tester, 'Дальше');

    expect(
      find.textContaining('сервер разложит подготовку по этим дням', skipOffstage: false),
      findsOneWidget,
    );

    await _tapStepButton(tester, 'Без даты');

    expect(
      find.textContaining('идём в своём темпе, по одной сцене за подход', skipOffstage: false),
      findsOneWidget,
    );
    expect(
      find.textContaining('сервер разложит подготовку по этим дням', skipOffstage: false),
      findsNothing,
    );
  });

  test('the listening verdict is «говорение» only when every line landed', () {
    ListenAnswer answer(bool understood) => ListenAnswer(
      line: const ListenLine(text: 'x', translation: 'x', place: ''),
      understood: understood,
    );

    expect(
      ListenEmphasis.of([answer(true), answer(true), answer(true)]),
      ListenEmphasis.speaking,
    );
    // MIXED counts as understanding — two out of three is not a pass.
    expect(
      ListenEmphasis.of([answer(true), answer(true), answer(false)]),
      ListenEmphasis.understanding,
    );
    expect(
      ListenEmphasis.of([answer(false), answer(false), answer(false)]),
      ListenEmphasis.understanding,
    );
  });
}

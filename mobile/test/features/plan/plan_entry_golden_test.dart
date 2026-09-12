import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/entry/plan_entry_screen.dart';

import '../../support/plan_goldens.dart';

/// ВХОД В ПЛАН, ШАГ ЗА ШАГОМ — кадры 22-1 … 22-4d (наряд PLAN-UI-2).
///
/// Снимается НАСТОЯЩИЙ экран входа со своей машиной состояний: тест печатает цель, жмёт кнопку
/// внизу и ждёт сборку ровно так, как это делает человек, а сервер подменён снятой фикстурой.
/// Поэтому снимок заодно отвечает на вопрос «дойдёт ли экран до этого состояния сам» — чего снимок
/// отдельно собранного виджета не отвечает.
///
/// Кадр 22-3b снимается здесь же: единственное место входа, которое считало от «сегодня», теперь
/// принимает этот день параметром ([EntryDateStep.today]), и снимок больше не протухает назавтра.
void main() {
  setUpAll(setUpPlanGoldens);

  Widget entry(_Api api, {bool online = true}) => planGoldenApp(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(api),
        connectivityProvider.overrideWith((ref) => Stream.value(online)),
      ],
      child: const PlanEntryScreen(),
    ),
  );

  /// Кнопка шага — одна, внизу, и называется по-разному; тест жмёт её по имени, как человек.
  Future<void> tapDock(WidgetTester tester, String label) async {
    await tester.tap(find.text(label));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 200));
  }

  testWidgets('цель → язык → длина → дата → превью (22-1, 22-1c, 22-2, 22-3a, 22-3b, 22-4a, 22-4b)',
      (tester) async {
    final api = _Api();
    await expectPlanGolden(tester, entry(api), 'plan/22-1-goal');

    // 22-1c: одно слово — валидный ответ, подсказка появляется и НЕ блокирует.
    await tester.enterText(find.byType(TextField), 'врач');
    await tester.pump();
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('../../goldens/plan/22-1c-goal-short.png'),
    );

    // Тап по истории «так пишут другие» подставляет её текст в поле.
    await tester.tap(find.text('Звонок арендодателю про залог'));
    await tester.pump();
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('../../goldens/plan/22-1-goal-filled.png'),
    );

    await tapDock(tester, 'Далее');
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('../../goldens/plan/22-2-language-level.png'),
    );

    await tapDock(tester, 'Далее');
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('../../goldens/plan/22-3a-days.png'),
    );

    await tapDock(tester, 'Далее');
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('../../goldens/plan/22-3b-date.png'),
    );

    // «Собрать план» просит план: 202 и опрос сборки — три растущих узла (22-4a).
    await tapDock(tester, 'Собрать план');
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('../../goldens/plan/22-4a-building.png'),
    );

    // Через такт опроса сборка готова — маршрут и закреплённая «Начать» (22-4b).
    api.buildStatus = 'ready';
    await tester.pump(const Duration(seconds: 3));
    await tester.pump(const Duration(milliseconds: 200));
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('../../goldens/plan/22-4b-ready.png'),
    );
  });

  testWidgets('22-4b прокручен — событие последним, кнопка не уезжает', (tester) async {
    final api = _Api(buildStatus: 'ready');
    await expectPlanGolden(
      tester,
      entry(api),
      'plan/22-4b-ready-scrolled',
      size: const Size(390, 1700),
      settle: const Duration(seconds: 3),
      prime: _toPreview,
    );
  });

  testWidgets('дата пока неизвестна — равноправный вариант (кадр 22-3b)', (tester) async {
    await expectPlanGolden(
      tester,
      entry(_Api()),
      'plan/22-3b-date-unknown',
      prime: (tester) async {
        await _toDate(tester);
        await tester.tap(find.text('Дата пока неизвестна'));
        await tester.pump();
      },
    );
  });

  testWidgets('план не собрался — «Попробовать ещё» (кадр 22-4c)', (tester) async {
    await expectPlanGolden(
      tester,
      entry(_Api(buildStatus: 'failed')),
      'plan/22-4c-failed',
      settle: const Duration(seconds: 3),
      prime: _toPreview,
    );
  });

  testWidgets('цель непонятна — «К цели» (кадр 22-4d)', (tester) async {
    await expectPlanGolden(
      tester,
      entry(_Api(buildStatus: 'unclear')),
      'plan/22-4d-unclear',
      settle: const Duration(seconds: 3),
      prime: _toPreview,
    );
  });

  testWidgets('без сети план не собрать — тот же кадр со своей строкой (§6)', (tester) async {
    // Сеть отвечает так, как она отвечает на самом деле: сокет не открылся. Экран узнаёт в этом
    // «нет сети» тем же `isOffline`, что и в бою, — а не по флагу, подсунутому тестом.
    await expectPlanGolden(
      tester,
      entry(_Api()..offline = true, online: false),
      'plan/22-4c-offline',
      settle: const Duration(seconds: 3),
      prime: _toPreview,
    );
  });
}

/// Доводит вход до шага ДАТЫ: история в поле, «Далее» трижды.
Future<void> _toDate(WidgetTester tester) async {
  await tester.tap(find.text('К врачу с ребёнком, первый раз в местной клинике'));
  await tester.pump();
  for (var i = 0; i < 3; i++) {
    await tester.tap(find.text('Далее'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 200));
  }
}

/// Доводит вход до превью: до даты, затем «Собрать план».
Future<void> _toPreview(WidgetTester tester) async {
  await _toDate(tester);
  await tester.tap(find.text('Собрать план'));
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 200));
}

/// Сервер входа: `POST /plans` отвечает 202 «строится», опрос отдаёт [buildStatus], готовый план —
/// снятая фикстура. Ничего не выдумывает: и статус сборки, и план пришли с живого backend2.
class _Api implements ApiClient {
  _Api({this.buildStatus = 'building'});

  String buildStatus;
  bool offline = false;

  PlanBuild _build() => PlanBuild.fromJson({
    ...planFixture('build_ready'),
    'status': buildStatus,
    if (buildStatus == 'unclear') 'unclear_reason': 'no_situation',
    if (buildStatus == 'failed') 'fail_reason': 'model_error',
  });

  @override
  Future<PlanBuild> createPlan({
    required String goalText,
    required String targetLang,
    required PlanLevel level,
    required int daysTotal,
    String? eventDate,
  }) async {
    if (offline) {
      throw DioException(
        requestOptions: RequestOptions(path: '/plans'),
        type: DioExceptionType.connectionError,
        error: const SocketException('Network is unreachable'),
      );
    }

    return _build();
  }

  @override
  Future<PlanBuild> planBuild(String planId) async => _build();

  @override
  Future<PlanBuild> retryPlanBuild(String planId) async => _build();

  @override
  Future<Plan> plan(String planId) async => planFrom('plan_ready_preview');

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

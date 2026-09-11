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

/// ВХОД В ПЛАН, ШАГ ЗА ШАГОМ — кадры 22-1 … 22-4 (наряд PLAN-UI, §4).
///
/// Снимается НАСТОЯЩИЙ экран входа со своей машиной состояний: тест печатает цель, жмёт «Далее» и
/// ждёт сборку ровно так, как это делает человек, а сервер подменён фикстурой. Поэтому снимок
/// заодно отвечает на вопрос «а дойдёт ли экран до этого состояния сам» — чего снимок отдельно
/// собранного виджета не отвечает.
///
/// Кадра 22-3b (дата сокращает план) здесь нет: строка «до события N дней» считается от
/// сегодняшнего числа, и снимок протух бы назавтра. Он снят живьём — `shots/22-3b.png`.
void main() {
  setUpAll(setUpPlanGoldens);

  Widget entry(_Api api) => planGoldenApp(
    ProviderScope(
      overrides: [
        apiClientProvider.overrideWithValue(api),
        connectivityProvider.overrideWith((ref) => Stream.value(true)),
      ],
      child: const PlanEntryScreen(),
    ),
  );

  testWidgets('цель → язык → дни → превью (кадры 22-1a, 22-1c, 22-1b, 22-2, 22-3a, 22-4a, 22-4b)', (
    tester,
  ) async {
    final api = _Api();
    await expectPlanGolden(tester, entry(api), 'plan/22-1a-goal-empty');

    // Короткий ответ — строка «Добавь, с кем и что важно» (22-1c).
    await tester.enterText(find.byType(TextField), 'иду к врачу');
    await tester.pump();
    await expectLater(find.byType(MaterialApp), matchesGoldenFile('../../goldens/plan/22-1c-goal-short.png'));

    // Чип «Врач» подставляет заготовку — поле заполнено, строка гаснет (22-1b).
    await tester.tap(find.text('Врач'));
    await tester.pump();
    await expectLater(find.byType(MaterialApp), matchesGoldenFile('../../goldens/plan/22-1b-goal-chip.png'));

    await tester.tap(find.text('Далее'));
    await tester.pump();
    await expectLater(find.byType(MaterialApp), matchesGoldenFile('../../goldens/plan/22-2-language-level.png'));

    await tester.tap(find.text('Далее'));
    await tester.pump();
    await expectLater(find.byType(MaterialApp), matchesGoldenFile('../../goldens/plan/22-3a-days.png'));

    // «Далее» с шага дней просит план: 202 и опрос сборки — превью в скелете (22-4a).
    await tester.tap(find.text('Далее'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
    await expectLater(find.byType(MaterialApp), matchesGoldenFile('../../goldens/plan/22-4a-preview-building.png'));

    // Через два такта опроса сборка готова — маршрут с обложкой и «Начать» (22-4b).
    api.buildStatus = 'ready';
    await tester.pump(const Duration(seconds: 2));
    await tester.pump(const Duration(milliseconds: 100));
    await expectLater(find.byType(MaterialApp), matchesGoldenFile('../../goldens/plan/22-4b-preview-ready.png'));
  });

  testWidgets('цель непонятна — «К цели» (кадр 22-4d)', (tester) async {
    await expectPlanGolden(
      tester,
      entry(_Api(buildStatus: 'unclear')),
      'plan/22-4d-preview-unclear',
      settle: const Duration(seconds: 3),
      prime: _toPreview,
    );
  });

  testWidgets('маршрут не собрался — «Ещё раз» (кадр 22-4c)', (tester) async {
    await expectPlanGolden(
      tester,
      entry(_Api(buildStatus: 'failed')),
      'plan/22-4c-preview-failed',
      settle: const Duration(seconds: 3),
      prime: _toPreview,
    );
  });

  testWidgets('без сети сборку не начать — тот же кадр со своей строкой (§6)', (tester) async {
    final api = _Api()..offline = true;
    // Сеть отвечает так, как она отвечает на самом деле: сокет не открылся. Экран узнаёт в этом
    // «нет сети» тем же `isOffline`, что и в бою, — а не по флагу, который ему подсунул тест.
    await expectPlanGolden(
      tester,
      planGoldenApp(
        ProviderScope(
          overrides: [
            apiClientProvider.overrideWithValue(api),
            connectivityProvider.overrideWith((ref) => Stream.value(false)),
          ],
          child: const PlanEntryScreen(),
        ),
      ),
      'plan/22-4c-preview-offline',
      settle: const Duration(seconds: 3),
      prime: _toPreview,
    );
  });
}

/// Доводит вход до превью: цель с чипа, «Далее» трижды.
Future<void> _toPreview(WidgetTester tester) async {
  await tester.tap(find.text('Врач'));
  await tester.pump();
  for (var i = 0; i < 3; i++) {
    await tester.tap(find.text('Далее'));
    await tester.pump();
  }
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

/// Значки плана приезжают из `assets/` — проверка, что они вообще доезжают до снимка.
///
/// Отдельным тестом, потому что провал здесь ни на что не похож: `SvgPicture.asset` грузит файл
/// асинхронно и на пустом бандле не падает, а рисует ПУСТОТУ — и тогда каждый снимок таба тихо
/// теряет пять значков этапов и три иллюстрации маршрута, оставаясь «зелёным».
library;

import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/ui/ui.dart';

import '../../support/plan_goldens.dart';

void main() {
  setUpAll(setUpPlanGoldens);

  testWidgets('каждый значок канвы грузится из бандла и рисует непустую картинку', (tester) async {
    await tester.pumpWidget(
      planGoldenApp(
        ColoredBox(
          color: const Color(0xFFEFEBE3),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  for (final k in PlanStageMarkKind.values)
                    PlanStageMark(kind: k, state: PlanStageMarkState.current, onDark: false),
                ],
              ),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  for (final d in PlanSystemDay.values)
                    PlanSystemDayMark(day: d, color: const Color(0xFF2E2620)),
                ],
              ),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  for (final i in PlanIcon.values) PlanIconMark(icon: i),
                ],
              ),
            ],
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    // Счёт берётся из самих перечислений: набор значков канвы растёт, и число в тесте, которое
    // надо помнить обновить, ловило бы не пропавший файл, а собственную несвежесть.
    final expected = PlanStageMarkKind.values.length +
        PlanSystemDay.values.length +
        PlanIcon.values.length;
    expect(find.byType(SvgPicture), findsNWidgets(expected));

    // Пустой svg рисуется без ошибок, поэтому мало найти виджет — у каждого должна быть
    // РАСПАКОВАННАЯ картинка с ненулевым размером.
    for (final element in find.byType(SvgPicture).evaluate()) {
      final size = element.size;
      expect(size, isNotNull);
      expect(size!.width, greaterThan(0), reason: 'значок нулевой ширины');
      expect(size.height, greaterThan(0), reason: 'значок нулевой высоты');
    }
    expect(tester.takeException(), isNull);
  });
}

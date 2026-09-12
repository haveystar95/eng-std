import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/features/plan/plan_sheets.dart';
import 'package:eng_std/theme/theme.dart';

import '../../support/plan_goldens.dart';

/// ЛИСТЫ И АЛЕРТ ТАБА — кадры 21-8, 21-10, 21-11, 21-12.
///
/// Лист открывается тем же вызовом, что и с экрана, и снимается поверх подложки таба: снимок ловит
/// и саму бумагу, и затемнение под ней. План — снятая фикстура, поэтому дата в листе «Изменить
/// дату» и «День N из M» в «Собрать новый план» — те, что пришли с сервера.
void main() {
  setUpAll(setUpPlanGoldens);

  Widget host(void Function(BuildContext context) open) => planGoldenApp(
    Scaffold(
      backgroundColor: AppColors.ground,
      // Хост невидим: на снимке должна быть подложка таба и лист, а не кнопка теста.
      body: Builder(
        builder: (context) => GestureDetector(
          key: const Key('open-sheet'),
          behavior: HitTestBehavior.opaque,
          onTap: () => open(context),
          child: const SizedBox.expand(),
        ),
      ),
    ),
  );

  Future<void> shoot(WidgetTester tester, Widget app, String name) async {
    await expectPlanGolden(
      tester,
      app,
      name,
      prime: (t) async {
        await t.tap(find.byKey(const Key('open-sheet')));
        await t.pump();
        await t.pump(const Duration(milliseconds: 400));
      },
    );
  }

  testWidgets('«Как устроен план» — один раз после первого «Начать» (кадр 21-8)', (tester) async {
    await shoot(tester, host(showPlanHowSheet), 'plan/21-8-sheet-how');
  });

  testWidgets('«Изменить дату» — дата плана, другая дата, «Без даты» (кадр 21-10)', (tester) async {
    final plan = planFrom('current_ready');
    await shoot(
      tester,
      host((context) => showPlanDateSheet(context, plan)),
      'plan/21-10-sheet-date',
    );
  });

  testWidgets('«Начать другой план?» (кадр 21-11)', (tester) async {
    final plan = planFrom('current_progress');
    await shoot(tester, host((context) => showPlanNewSheet(context, plan)), 'plan/21-11-sheet-new');
  });

  testWidgets('«Удалить план?» — терракотовая кнопка (кадр 21-12)', (tester) async {
    final plan = planFrom('current_closed');
    await shoot(
      tester,
      host((context) => showPlanDeleteAlert(context, plan)),
      'plan/21-12-alert-delete',
    );
  });

  testWidgets('у плана без закрытого дня коллекции ещё нет — другой текст (кадр 21-12)', (
    tester,
  ) async {
    final plan = planFrom('current_ready', (json) {
      json['collection_id'] = null;

      return json;
    });
    await shoot(
      tester,
      host((context) => showPlanDeleteAlert(context, plan)),
      'plan/21-12-alert-delete-no-collection',
    );
  });
}

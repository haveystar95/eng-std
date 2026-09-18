import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/session/parts/session_stage.dart';
import 'package:eng_std/features/plan/session/session_texts.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// ПОВТОР ЭТАПА — «Ещё раз» с итога дня (наряд FIX-1, п. 5).
///
/// На входе в этап карточки повтора стоят на телефоне неотвеченными, и это НЕ «не начат»: этап уже пройден, его
/// проходят второй раз. Состояние называется своим словом.
void main() {
  Widget host(List<StageRow> rows) => ProviderScope(
    child: MaterialApp(
    theme: buildAppTheme(),
    locale: const Locale('ru'),
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    supportedLocales: const [Locale('ru'), Locale('en')],
    home: Builder(
      builder: (context) => Scaffold(
        body: SessionStageEntry(
          stage: PlanStage.speak,
          stageName: (s) => SessionTexts.stage(AppLocalizations.of(context), s),
          description: '',
          minutes: null,
          rows: rows,
          scene: null,
          noHints: false,
          onNoHints: (_) {},
          onStart: () {},
          onBack: () {},
        ),
        ),
      ),
    ),
  );

  List<StageRow> rows({required bool replay}) => [
    for (final s in PlanStage.known)
      (
        stage: s,
        status: s == PlanStage.speak
            ? StageRowStatus.current
            : (s == PlanStage.words ? StageRowStatus.done : StageRowStatus.ahead),
        started: false,
        replay: replay && s == PlanStage.speak,
      ),
  ];

  // ДЕФЕКТ: «этап повтора показан как не начат» (живой проход 18.09).
  testWidgets('в повторе состояние этапа — «повтор», а не «не начат»', (tester) async {
    await tester.pumpWidget(host(rows(replay: true)));
    await tester.pump();
    expect(find.text('повтор'), findsOneWidget);
    expect(find.text('не начат'), findsNothing);
  });

  testWidgets('обычный вход с неотвеченными карточками по-прежнему «не начат»', (tester) async {
    await tester.pumpWidget(host(rows(replay: false)));
    await tester.pump();
    expect(find.text('не начат'), findsOneWidget);
    expect(find.text('повтор'), findsNothing);
  });
}

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/data/models.dart' show AppUser;
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/profile/qa_report_button.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// THE REPORT BUTTON AND THE DAY SESSION (screenshot fixes of SESSION-1b): a QA account has the button on ordinary
/// screens; while a screen under [QaReportHidden] (the day session) is open there is none, its sheets included;
/// once that screen is closed the button is back.
class _QaUser extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(id: '01QA', name: 'QA', qaTools: true);
}

void main() {
  testWidgets('no button while QaReportHidden is mounted; back after leaving', (tester) async {
    final navigator = GlobalKey<NavigatorState>();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [authControllerProvider.overrideWith(_QaUser.new)],
        child: MaterialApp(
          navigatorKey: navigator,
          theme: buildAppTheme(),
          locale: const Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: const [Locale('ru'), Locale('en')],
          builder: (context, child) => QaReportOverlay(child: child ?? const SizedBox.shrink()),
          home: const Scaffold(body: Center(child: Text('таб'))),
        ),
      ),
    );
    await tester.pumpAndSettle();
    expect(find.byIcon(LucideIcons.flag), findsOneWidget, reason: 'an ordinary screen has the button');

    unawaited(navigator.currentState!.push(MaterialPageRoute<void>(
      builder: (_) => const QaReportHidden(child: Scaffold(body: Center(child: Text('сессия')))),
    )));
    await tester.pumpAndSettle();
    expect(find.text('сессия'), findsOneWidget);
    expect(find.byIcon(LucideIcons.flag), findsNothing, reason: 'the session has no button');

    unawaited(showModalBottomSheet<void>(
      context: navigator.currentContext!,
      builder: (_) => const SizedBox(height: 120, child: Text('шит')),
    ));
    await tester.pumpAndSettle();
    expect(find.text('шит'), findsOneWidget);
    expect(find.byIcon(LucideIcons.flag), findsNothing, reason: 'a sheet over the session has none either');

    navigator.currentState!.pop();
    await tester.pumpAndSettle();
    navigator.currentState!.pop();
    await tester.pumpAndSettle();
    expect(find.text('таб'), findsOneWidget);
    expect(find.byIcon(LucideIcons.flag), findsOneWidget, reason: 'the session is closed — the button is back');
  });
}

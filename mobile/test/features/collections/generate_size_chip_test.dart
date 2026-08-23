import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/collections/generate_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// The size chips hold a TRANSLATED hint inside a third of the screen width, and the English one
/// («approximately 15 words») ran straight over the ink fill of the selected chip on the phone.
/// The rule: whatever the locale, the hint stays inside its own chip and on one line.
void main() {
  Widget host(Locale locale) => ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWith((ref) {
        final db = AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(db.close);
        return db;
      }),
      generationQuotaProvider.overrideWith((ref) async => null),
    ],
    child: MaterialApp(
      locale: locale,
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru'), Locale('en')],
      home: const GenerateScreen(),
    ),
  );

  /// On-screen width — `getSize` would report the Text's natural size and miss the FittedBox's
  /// scale-down, which is exactly the mechanism under test.
  double drawnWidth(WidgetTester tester, Finder f) =>
      tester.getBottomRight(f).dx - tester.getTopLeft(f).dx;

  Future<void> check(WidgetTester tester, Locale locale, List<String> hints) async {
    tester.view.physicalSize = const Size(
      320 * 3,
      812 * 3,
    ); // an iPhone SE — the narrowest we ship to
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(host(locale));
    await tester.pump();

    for (final hint in hints) {
      final text = find.text(hint);
      expect(text, findsOneWidget, reason: 'the hint is on screen at all');
      final chip = find.ancestor(of: text, matching: find.byType(Material)).first;
      expect(
        drawnWidth(tester, text),
        lessThanOrEqualTo(drawnWidth(tester, chip)),
        reason: '«$hint» spills out of its chip in $locale',
      );
      final height = tester.getBottomRight(text).dy - tester.getTopLeft(text).dy;
      expect(height, lessThan(20), reason: '«$hint» wrapped to a second line and broke the row');
    }

    // The screen owns a Timer.periodic for its rotating hints; the binding fails on a pending one.
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(milliseconds: 1));
  }

  testWidgets('the size hints fit their chips in English', (tester) async {
    await check(tester, const Locale('en'), ['10 words', '15 words', '22 words']);
  });

  testWidgets('the size hints fit their chips in Russian', (tester) async {
    await check(tester, const Locale('ru'), ['10 слов', '15 слов', '22 слова']);
  });
}

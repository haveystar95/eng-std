import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/search/search_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

/// The bottom of the search list, against the floating tab bar.
///
/// The tab bar is not part of this screen: it is a `Positioned(bottom: 0)` overlay in the home
/// Stack, drawn OVER whichever tab is showing. So the only thing keeping the last line of the list
/// out from under it is this screen's own bottom padding — and with the keyboard up, both the tab
/// bar and this screen's viewport move, which is exactly the case nobody had measured.
///
/// The assertion is a real measurement rather than a number copied from the layout code: it finds
/// the last line on screen and the strip the tab bar occupies, and checks they do not overlap.
class _Api implements ApiClient {
  @override
  Future<List<SearchHit>> search(String query, {int limit = 20, String? source, String? target}) async =>
      const [];

  @override
  Future<InstantHint> instantHint(String query, {String? source, String? target}) async =>
      InstantHint(query: query);

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// The screen as the app really mounts it: under the home Stack, with the tab bar floating on top.
Future<void> _pumpUnderTabBar(WidgetTester tester, {required double keyboard}) async {
  final db = AppDatabase.forTesting(NativeDatabase.memory());
  addTearDown(db.close);

  await tester.pumpWidget(ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWithValue(db),
      apiClientProvider.overrideWithValue(_Api()),
      collectionsProvider.overrideWith((ref) => Stream.value(const <WordCollection>[])),
    ],
    child: MaterialApp(
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru')],
      theme: buildAppTheme(),
      home: Builder(
        builder: (context) => MediaQuery(
          // The keyboard, as the framework reports it to everything below.
          data: MediaQuery.of(context).copyWith(viewInsets: EdgeInsets.only(bottom: keyboard)),
          child: Scaffold(
            extendBody: true,
            backgroundColor: AppColors.paper,
            body: Stack(
              children: [
                const SearchScreen(),
                Positioned(
                  left: 0,
                  right: 0,
                  bottom: 0,
                  child: SafeArea(
                    top: false,
                    minimum: const EdgeInsets.only(bottom: AppTabBarMetrics.bottomInset),
                    child: Center(
                      child: Container(
                        key: const ValueKey('tab-bar'),
                        height: AppTabBarMetrics.height,
                        width: 200,
                        color: AppColors.paper,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  ));
  await tester.pump();
  await tester.pump();
  await tester.pump();
}

Future<void> _type(WidgetTester tester, String text) async {
  await tester.enterText(find.byType(TextField), text);
  await tester.pump(const Duration(milliseconds: 400));
  await tester.pump();
  await tester.pump();
}

void main() {
  testWidgets('«Нажмите Enter» clears the tab bar with the keyboard DOWN', (tester) async {
    await _pumpUnderTabBar(tester, keyboard: 0);
    await _type(tester, 'holl');

    final line = tester.getRect(find.textContaining('Enter'));
    final bar = tester.getRect(find.byKey(const ValueKey('tab-bar')));

    expect(line.bottom, lessThanOrEqualTo(bar.top),
        reason: 'the line must not sit under the bar it is drawn behind');
  });

  testWidgets('…and with the keyboard UP, scrolled to the very end', (tester) async {
    // 336 is a plain iPhone keyboard. The point is not the number — it is that the list's bottom
    // padding is measured against a viewport the keyboard has already shortened, while the tab bar
    // has moved up by the same amount. Scrolled to the end is the only place the reservation is
    // actually exercised; short content never reaches it.
    await _pumpUnderTabBar(tester, keyboard: 336);
    await _type(tester, 'holl');

    await tester.drag(find.byType(ListView), const Offset(0, -2000));
    await tester.pump();

    final line = tester.getRect(find.textContaining('Enter'));
    final bar = tester.getRect(find.byKey(const ValueKey('tab-bar')));

    // Not merely «does not overlap»: flush against the bar is what was reported, and a line the eye
    // reads as touching the chrome is the defect whether or not the rectangles intersect.
    expect(bar.top - line.bottom, greaterThanOrEqualTo(AppSpacing.s16),
        reason: 'the last line needs air under it, not just an absence of collision');
  });

  testWidgets('a long list of results ends clear of the bar too', (tester) async {
    await _pumpUnderTabBar(tester, keyboard: 336);
    await _type(tester, 'a');

    await tester.drag(find.byType(ListView), const Offset(0, -4000));
    await tester.pump();

    final bar = tester.getRect(find.byKey(const ValueKey('tab-bar')));
    final line = tester.getRect(find.textContaining('Enter'));

    expect(bar.top - line.bottom, greaterThanOrEqualTo(AppSpacing.s16));
  });
}

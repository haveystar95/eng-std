import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/feature_flags.dart';
import 'package:eng_std/features/paywall/paywall_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// A3.9 paywall (кадры 2.13/4ж): opens only behind the flag, defaults to the year plan, and rewrites
/// the auto-renew legal line when the period switches.
void main() {
  Future<void> pump(WidgetTester tester, {required bool paywall}) {
    return tester.pumpWidget(
      ProviderScope(
        overrides: [
          featureFlagsProvider.overrideWith(
            () => _FakeFlags(
              FeatureFlags(storeEnabled: false, paywallEnabled: paywall, devPremium: false),
            ),
          ),
        ],
        child: MaterialApp(
          locale: const Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: const [Locale('ru')],
          home: Consumer(
            builder: (context, ref, _) => Scaffold(
              body: Center(
                child: TextButton(
                  onPressed: () =>
                      showPaywall(context, ref, const PaywallArgs(PaywallEntry.profile)),
                  child: const Text('open'),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  testWidgets('flag on: opens the paywall with year selected by default', (tester) async {
    await pump(tester, paywall: true);
    await tester.pumpAndSettle();

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.text('Продолжить'), findsOneWidget);
    expect(find.text(r'$29.99'), findsOneWidget);
    expect(find.text(r'$4.99'), findsOneWidget);
    // Year is the default → the legal line is the yearly one.
    expect(find.textContaining('за год списываются'), findsOneWidget);
    expect(find.textContaining('в месяц списываются'), findsNothing);
  });

  testWidgets('switching to the monthly card rewrites the legal line', (tester) async {
    await pump(tester, paywall: true);
    await tester.pumpAndSettle();
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Месяц'));
    await tester.pumpAndSettle();

    expect(find.textContaining('в месяц списываются'), findsOneWidget);
    expect(find.textContaining('за год списываются'), findsNothing);
  });

  testWidgets('flag off: showPaywall is a no-op (nothing opens)', (tester) async {
    await pump(tester, paywall: false);
    await tester.pumpAndSettle();

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.text('Продолжить'), findsNothing);
  });
}

class _FakeFlags extends FeatureFlagsController {
  _FakeFlags(this._flags);
  final FeatureFlags _flags;
  @override
  FeatureFlags build() => _flags;
}

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/practice_dialog/dialog_entry_button.dart';
import 'package:eng_std/features/practice_dialog/dialog_models.dart';
import 'package:eng_std/features/practice_dialog/dialog_providers.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Widget test: the collection-screen entry adapts to the last-dialog result — «Пройти ещё раз» +
/// a result row when one exists, the plain «Разговор · 3 мин» otherwise, and nothing for free tier.
void main() {
  AppUser user(String tier) => AppUser(
    id: 'u1',
    name: 'D',
    profile: Profile(
      nativeLanguage: 'ru',
      targetLanguage: 'en',
      cefrLevel: 'B1',
      dailyGoal: 20,
      tier: tier,
    ),
  );

  Future<void> pump(WidgetTester tester, {required String tier, LastDialogResult? last}) {
    return tester.pumpWidget(
      ProviderScope(
        overrides: [
          authControllerProvider.overrideWith(() => _FakeAuth(user(tier))),
          connectivityProvider.overrideWith((ref) => Stream.value(true)),
          lastDialogProvider('c1').overrideWith((ref) async => last),
        ],
        child: const MaterialApp(
          locale: Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: [Locale('ru')],
          home: Scaffold(
            body: DialogEntryButton(collectionId: 'c1', title: 'At the Bank'),
          ),
        ),
      ),
    );
  }

  testWidgets('with a last-dialog result: «Пройти ещё раз» + result row', (tester) async {
    await pump(
      tester,
      tier: 'premium',
      last: LastDialogResult(finishedAt: DateTime(2026, 8, 7), wordsUsed: 3, wordsTotal: 5),
    );
    await tester.pump();
    await tester.pump();

    expect(find.text('Пройти ещё раз'), findsOneWidget);
    expect(find.textContaining('слов: 3 из 5'), findsOneWidget);
    expect(find.text('Разговор · 3 мин'), findsNothing);
  });

  testWidgets('without a result: the plain «Разговор · 3 мин» and no result row', (tester) async {
    await pump(tester, tier: 'premium', last: null);
    await tester.pump();
    await tester.pump();

    expect(find.text('Разговор · 3 мин'), findsOneWidget);
    expect(find.text('Пройти ещё раз'), findsNothing);
    expect(find.textContaining('слов:'), findsNothing);
  });

  testWidgets('free tier: the entry is hidden entirely', (tester) async {
    await pump(tester, tier: 'free', last: null);
    await tester.pump();
    await tester.pump();

    expect(find.text('Разговор · 3 мин'), findsNothing);
    expect(find.text('Пройти ещё раз'), findsNothing);
  });
}

class _FakeAuth extends AuthController {
  _FakeAuth(this._user);
  final AppUser? _user;

  @override
  Future<AppUser?> build() async => _user;
}

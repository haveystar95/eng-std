import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/local/day_key.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/progress/progress_providers.dart';
import 'package:eng_std/features/progress/progress_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// Render smoke test — the screen is device-batched, so this catches layout throws (e.g. the
/// activity-bar Expanded/Align class of bug) and confirms the local stats surface.
void main() {
  testWidgets('Progress screen renders streak, counters, density from local providers', (
    tester,
  ) async {
    final today = localDayKey(DateTime.now());
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWith((ref) {
            final db = AppDatabase.forTesting(NativeDatabase.memory());
            ref.onDispose(db.close);
            return db;
          }),
          statsProvider.overrideWith(
            (ref) => Stream.value(
              Stats(
                totalWords: 146,
                learned: 60,
                mastered: 82,
                dueToday: 0,
                reviewsTotal: 12,
                streakDays: 12,
              ),
            ),
          ),
          bestStreakProvider.overrideWith((ref) async => 19),
          dailyActivityProvider.overrideWith((ref) => Stream.value({today: 12})),
          globalDensityProvider.overrideWith(
            (ref) =>
                Stream.value(const CollectionDensity(mastered: 82, inWork: 41, toSort: 23)),
          ),
        ],
        child: const MaterialApp(
          locale: Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: [Locale('ru')],
          home: ProgressScreen(),
        ),
      ),
    );
    await tester.pump(); // resolve the streams / future
    await tester.pump();

    expect(tester.takeException(), isNull);
    expect(find.text('Прогресс'), findsOneWidget);
    expect(find.text('12 дней подряд'), findsOneWidget);
    expect(find.text('Лучший результат — 19 дней'), findsOneWidget);
    expect(find.text('82'), findsOneWidget); // «Выучено всего» = mastered
    expect(find.text('12'), findsNWidgets(2)); // «За неделю» + «Повторений сегодня», both local
    // The status vocabulary, the same three words the collection screen uses (Ч.4).
    expect(find.text('Освоено 82'), findsOneWidget);
  });
}

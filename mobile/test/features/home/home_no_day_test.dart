import 'dart:async';

import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/local/sync_service.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/home/home_screen.dart';
import 'package:eng_std/features/training/training_home_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// «НЕТ ДНЯ» — THREE SITUATIONS, THREE PICTURES. And a server that is down says so.
///
/// The live report (BUG-1, 27.08.2026): the backend was down while the phone had Wi-Fi. The app
/// opened on a main screen holding one search bar and nothing else — no data, no explanation, no
/// «нет связи». It looked like an app loading emptiness forever.
///
/// Two separate causes, and the fix has two halves:
///
///   * the screen read `.value` off the day provider, so «ещё не знаю» (loading) and «знаю, что дня
///     нет» rendered the same blank page — and «кэш есть, но не читается» was swallowed by a bare
///     `catch (_)` into that same page;
///   * the only offline signal was the RADIO (connectivity_plus). Wi-Fi up + server down = «online»,
///     so no banner appeared and a failed sync was visually identical to a successful one.
void main() {
  late AppDatabase db;
  late SyncService sync;

  setUp(() {
    db = AppDatabase.forTesting(NativeDatabase.memory());
    sync = _FakeSync();
  });
  tearDown(() async => db.close());

  Widget host(Stream<HomePlanView> day, {Widget? screen}) => ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWith((ref) => db),
      syncServiceProvider.overrideWithValue(sync),
      homePlanProvider.overrideWith((ref) => day),
      statsProvider.overrideWith(
        (ref) => Stream.value(
          Stats(
            totalWords: 0,
            learned: 0,
            mastered: 0,
            dueToday: 0,
            reviewsTotal: 0,
            streakDays: 0,
          ),
        ),
      ),
      // The radio is UP throughout. That is the whole point: the server being down is a different
      // fact, and the app used to have no way to say it.
      connectivityProvider.overrideWith((ref) => Stream.value(true)),
    ],
    child: MaterialApp(
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru')],
      home: Scaffold(body: screen ?? TrainingHomeScreen(onOpenStore: () {})),
    ),
  );

  group('the three states of «no day»', () {
    testWidgets('loading is drawn as waiting, not as emptiness', (tester) async {
      // A stream that has not emitted — exactly the frame the screen used to render as «нет дня».
      await tester.pumpWidget(host(const Stream<HomePlanView>.empty()));
      await tester.pump();

      expect(find.byKey(HomeBlockKeys.loading), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.unreachable), findsNothing);
      expect(find.byKey(HomeBlockKeys.generate), findsNothing);
    });

    testWidgets('nothing cached and no sync in flight: the server is named', (tester) async {
      await tester.pumpWidget(host(Stream.value(const HomePlanView.missing())));
      await tester.pump();

      expect(find.byKey(HomeBlockKeys.unreachable), findsOneWidget);
      expect(find.text('Сервер не отвечает'), findsOneWidget);
      expect(find.textContaining('Потяни вниз'), findsOneWidget);
      // …and the door that does not need the day is still there, under the sentence.
      expect(find.byKey(HomeBlockKeys.generate), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.loading), findsNothing);
    });

    testWidgets('an unreadable cache gets the same card under its own key', (tester) async {
      await tester.pumpWidget(host(Stream.value(const HomePlanView.unreadable())));
      await tester.pump();

      expect(find.byKey(HomeBlockKeys.unreadable), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.unreachable), findsNothing);
      expect(find.byKey(HomeBlockKeys.loading), findsNothing);
    });

    testWidgets('a first sync IN FLIGHT waits — it is not «нет связи» yet', (tester) async {
      sync.state.value = SyncState.syncing;
      await tester.pumpWidget(host(Stream.value(const HomePlanView.missing())));
      await tester.pump();

      expect(find.byKey(HomeBlockKeys.loading), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.unreachable), findsNothing);

      // …and when it fails, the wait ends with an answer instead of spinning forever. The state is
      // a ValueNotifier and no row is written on failure, so this only works if it is LISTENED to.
      sync.state.value = SyncState.offline;
      await tester.pump();

      expect(find.byKey(HomeBlockKeys.unreachable), findsOneWidget);
      expect(find.byKey(HomeBlockKeys.loading), findsNothing);
    });
  });

  group('the sync indicator says what the radio cannot', () {
    const banner = 'Сервер недоступен · показываю сохранённое';

    /// The strip alone. The shell it normally sits in starts a sync, a queue flush and a generation
    /// reconcile the moment it mounts, none of which this is about.
    Widget strip() => host(
      Stream.value(const HomePlanView.missing()),
      screen: const SyncIndicator(),
    );

    testWidgets('a failed sync raises the banner even on a live connection', (tester) async {
      sync.state.value = SyncState.offline;
      await tester.pumpWidget(strip());
      await tester.pump();

      expect(find.text(banner), findsOneWidget);
    });

    testWidgets('and a successful one takes it down again', (tester) async {
      sync.state.value = SyncState.offline;
      await tester.pumpWidget(strip());
      await tester.pump();
      expect(find.text(banner), findsOneWidget);

      sync.state.value = SyncState.idle; // the sync landed
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400)); // the switcher's cross-fade

      expect(find.text(banner), findsNothing);
    });

    testWidgets('an ordinary idle strip says nothing at all', (tester) async {
      await tester.pumpWidget(strip());
      await tester.pump();

      expect(find.text(banner), findsNothing);
    });

    testWidgets('the strip is exactly as tall as the tabs below reserve for it', (tester) async {
      // Caught live, on the first sync that timed out: the indicator FLOATS over the tabs, so a
      // banner taller than the reservation lands across «Четверг, 27 августа · Стрик 4» and both
      // become unreadable. One number, two sides.
      sync.state.value = SyncState.offline;
      await tester.pumpWidget(strip());
      await tester.pump();

      final strips = find.ancestor(
        of: find.text(banner),
        matching: find.byType(Container),
      );
      expect(tester.getSize(strips.first).height, SyncIndicator.bannerHeight);
    });
  });
}

/// A [SyncService] whose state the test drives by hand. It never touches the network: what is under
/// test is what the UI does with the state, not how the state is reached.
class _FakeSync implements SyncService {
  @override
  final ValueNotifier<SyncState> state = ValueNotifier(SyncState.idle);

  @override
  final ValueNotifier<SyncReport?> lastReport = ValueNotifier(null);

  @override
  Future<void> sync() async {}

  @override
  Future<void> resync() async {}

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

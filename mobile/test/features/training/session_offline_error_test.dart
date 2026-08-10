import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/training/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

/// Sessions are still built server-side, so «нет сети» is a state the user WILL hit — and it used
/// to appear as `Не удалось загрузить сессию: DioException [connection error] …`, with no way out
/// but the close button. Pinned: a human sentence, no exception dump, and a retry that really
/// re-runs the build.
void main() {
  late AppDatabase db;

  /// What the next session build should throw; null → it succeeds.
  Object? failWith;
  var builds = 0;

  setUp(() {
    db = AppDatabase.forTesting(NativeDatabase.memory());
    failWith = null;
    builds = 0;
  });
  tearDown(() => db.close());

  Widget host() => ProviderScope(
        overrides: [
          appDatabaseProvider.overrideWithValue(db),
          studySessionProvider.overrideWith((ref, args) async {
            builds++;
            final failure = failWith;
            if (failure != null) throw failure;
            return const StudySession(sessionId: 's', cards: []);
          }),
        ],
        child: const MaterialApp(
          locale: Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: [Locale('ru')],
          home: SessionScreen(title: 'Тест', practice: true),
        ),
      );

  DioException offline() => DioException(
        requestOptions: RequestOptions(path: '/study/sessions'),
        type: DioExceptionType.connectionError,
      );

  testWidgets('no network shows «Нет соединения», not a DioException dump', (tester) async {
    failWith = offline();

    await tester.pumpWidget(host());
    await tester.pumpAndSettle();

    expect(find.text('Нет соединения'), findsOneWidget);
    expect(find.textContaining('DioException'), findsNothing);
    expect(find.textContaining('/study/sessions'), findsNothing);
  });

  testWidgets('a server-side failure gets the short generic text, still no dump', (tester) async {
    failWith = DioException(
      requestOptions: RequestOptions(path: '/study/sessions'),
      response: Response(requestOptions: RequestOptions(path: '/study/sessions'), statusCode: 500),
    );

    await tester.pumpWidget(host());
    await tester.pumpAndSettle();

    expect(find.text('Не удалось загрузить сессию'), findsOneWidget);
    expect(find.text('Нет соединения'), findsNothing);
    expect(find.textContaining('DioException'), findsNothing);
  });

  testWidgets('«Повторить» rebuilds the session', (tester) async {
    failWith = offline();

    await tester.pumpWidget(host());
    await tester.pumpAndSettle();
    final before = builds;
    expect(find.text('Нет соединения'), findsOneWidget);

    failWith = null; // the network came back
    await tester.tap(find.text('Повторить'));
    await tester.pumpAndSettle();

    expect(builds, greaterThan(before), reason: 'the provider was invalidated and ran again');
    expect(find.text('Нет соединения'), findsNothing, reason: 'the retry succeeded');
  });
}

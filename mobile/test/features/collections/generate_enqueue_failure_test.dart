import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/generation_controller.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/collections/generate_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/ui/ui.dart';

/// QA-23 — the create screen must survive a failing enqueue.
///
/// The live report was a brand-new account on a fresh install: «Сгенерировать» went grey and never
/// came back, and nothing was ever said. The server-side evidence was that `POST /generations` had
/// never arrived — because the enqueue's FIRST act is a local-DB write, and [GenerationController.
/// start] only reaches the network after it. `_submit` had no catch and no finally, so a local
/// store that could not be written left `_submitting` true for the life of the screen and sent the
/// exception to the Flutter error handler, where no user will ever see it.
///
/// The rule this pins: whatever the enqueue does, the button comes BACK and the failure is SAID.
class _FailingGenerationController implements GenerationController {
  _FailingGenerationController(this.error);

  final Object error;
  int starts = 0;

  @override
  Future<String> start({
    required String topic,
    required List<String> levels,
    required int size,
    required String sourceLang,
    required String targetLang,
    required bool targetLangExplicit,
  }) async {
    starts++;
    throw error;
  }

  @override
  noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

void main() {
  Widget host(GenerationController controller) => ProviderScope(
    overrides: [
      // In-memory, so the screen never touches the real on-disk store (whose open now carries
      // a 10s timeout timer the test binding would report as pending).
      appDatabaseProvider.overrideWith((ref) {
        final db = AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(db.close);
        return db;
      }),
      generationControllerProvider.overrideWithValue(controller),
      // The quota is network-backed; a null one is the honest offline shape and, importantly,
      // is NOT «exhausted» — so the button's only reason to be disabled is `_submitting`.
      generationQuotaProvider.overrideWith((ref) async => null),
    ],
    child: const MaterialApp(
      locale: Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: [Locale('ru'), Locale('en')],
      home: GenerateScreen(initialTopic: 'популярные фразы и слова'),
    ),
  );

  /// Tear the tree down INSIDE the test: the screen owns a `Timer.periodic` for its rotating
  /// hints (cancelled only by `dispose`) and the SnackBar owns its auto-dismiss timer, and the
  /// binding fails any test that leaves either pending.
  Future<void> close(WidgetTester tester) async {
    await tester.pump(const Duration(seconds: 6)); // let the SnackBar dismiss itself
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(milliseconds: 1));
  }

  testWidgets('a failing enqueue says so instead of failing silently', (tester) async {
    final controller = _FailingGenerationController(Exception('database is locked'));
    await tester.pumpWidget(host(controller));
    await tester.pump();

    await tester.tap(find.text('Сгенерировать'));
    await tester.pump(); // let _submit run to its catch
    await tester.pump(const Duration(milliseconds: 400)); // the SnackBar slides in

    expect(controller.starts, 1);
    // The whole point: «ошибка, которую не видно» becomes visible.
    expect(find.textContaining('Не удалось поставить генерацию в очередь'), findsOneWidget);
    // …and the screen is still there, with the typed prompt intact.
    expect(find.text('популярные фразы и слова'), findsOneWidget);

    await close(tester);
  });

  testWidgets('the button works AGAIN after a failure — it is not a one-shot screen', (
    tester,
  ) async {
    final controller = _FailingGenerationController(Exception('database is locked'));
    await tester.pumpWidget(host(controller));
    await tester.pump();

    await tester.tap(find.text('Сгенерировать'));
    await tester.pump();
    expect(controller.starts, 1);

    // Asserted on the button's own state rather than by tapping again: the SnackBar docks over the
    // footer where the button lives, so a second tap would land on the toast and prove nothing.
    // `enabled` is exactly what regressed — before QA-23 `_submitting` stayed true for the life of
    // the screen, so this was false forever and the only way out was to kill the app.
    final button = tester.widget<PrimaryButton>(
      find.widgetWithText(PrimaryButton, 'Сгенерировать'),
    );
    expect(button.enabled, isTrue, reason: 'the button came back after the failure');
    expect(button.onPressed, isNotNull);

    await close(tester);
  });
}

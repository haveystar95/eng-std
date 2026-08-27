import 'dart:io';

import 'package:drift/drift.dart' show Value;
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/languages.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/review_sync.dart';
import 'package:eng_std/features/training/session/session_exercise.dart';
import 'package:eng_std/features/training/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// WHICH VOICE reads a card — the bug from the owner's phone (MIX-1b).
///
/// A study session is mixed by design: the pool deals words from folders of different languages in
/// one stream. The language was taken from the session (a scoped collection) or, failing that, from
/// the PROFILE — so every card of a mixed session was read in one language, and an Italian word came
/// out in English phonetics. The pair is a property of the CARD, and so is the voice.
///
/// Pinned at the `flutter_tts` method channel, because «в каком голосе это прозвучало» has no other
/// honest answer: the locale in force at the moment `speak` was called.
class _SilentReviewSync extends ReviewSync {
  _SilentReviewSync(Ref ref)
    : super(
        ref.read(apiClientProvider),
        ref.read(reviewQueueProvider),
        ref.read(seqCounterProvider),
        ref,
      );

  @override
  Future<void> record({
    required String termId,
    required String exerciseMode,
    required String response,
    bool usedHint = false,
    bool isPractice = false,
    int? latencyMs,
    String? sessionId,
    int? ladderStep,
  }) async {}

  @override
  Future<void> flush() async {}
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const ttsChannel = MethodChannel('flutter_tts');

  /// Everything the engine was told, in order. The locale of an utterance is the last `setLanguage`
  /// before it — exactly how the native side sees it.
  final calls = <({String method, Object? args})>[];
  late AppDatabase db;

  /// What was actually said, and in what voice.
  List<({String locale, String text})> utterances() {
    String locale = '(unset)';
    final out = <({String locale, String text})>[];
    for (final c in calls) {
      if (c.method == 'setLanguage') locale = c.args as String;
      if (c.method == 'speak') out.add((locale: locale, text: c.args as String));
    }
    return out;
  }

  final t0 = DateTime.utc(2026, 8, 26, 9);

  /// Two folders of different pairs, and one term in each — the shape of a mixed session.
  Future<void> seed() => db.applyDelta(
    collectionUpserts: [
      // Ordered by id on purpose: `pairByTerms` resolves «first collection by id wins», as the
      // server does, and a term that sits in only one folder must not depend on that tie-break.
      CollectionsCompanion.insert(
        id: 'c1',
        updatedAt: t0,
        title: const Value('Ristorante'),
        targetLang: const Value('it'),
        sourceLang: const Value('ru'),
      ),
      CollectionsCompanion.insert(
        id: 'c2',
        updatedAt: t0,
        title: const Value('Airport'),
        targetLang: const Value('en'),
        sourceLang: const Value('ru'),
      ),
      CollectionsCompanion.insert(
        id: 'c3',
        updatedAt: t0,
        title: const Value('Lotnisko'),
        targetLang: const Value('pl'),
        sourceLang: const Value('ru'),
      ),
    ],
    termUpserts: [
      TermsCompanion.insert(id: 'IT1', updatedAt: t0, termText: const Value('trattoria')),
      TermsCompanion.insert(id: 'EN1', updatedAt: t0, termText: const Value('luggage')),
      TermsCompanion.insert(id: 'PL1', updatedAt: t0, termText: const Value('bagaż')),
    ],
    itemUpserts: [
      CollectionItemsCompanion.insert(collectionId: 'c1', termId: 'IT1', updatedAt: t0),
      CollectionItemsCompanion.insert(collectionId: 'c2', termId: 'EN1', updatedAt: t0),
      CollectionItemsCompanion.insert(collectionId: 'c3', termId: 'PL1', updatedAt: t0),
    ],
  );

  setUp(() async {
    calls.clear();
    FlutterSecureStorage.setMockInitialValues({});
    db = AppDatabase.forTesting(NativeDatabase.memory());
    await seed();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
      ttsChannel,
      (call) async {
        calls.add((method: call.method, args: call.arguments));
        return 1; // flutter_tts reads 1 as «accepted»
      },
    );
  });

  tearDown(() async {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger.setMockMethodCallHandler(
      ttsChannel,
      null,
    );
    await db.close();
  });

  SessionCard heard(String termId, String answer) => SessionCard(
    termId: termId,
    mode: ExerciseMode.listening,
    type: 'word',
    prompt: null, // the audio IS the task
    answer: answer,
  );

  SessionCard choice(String termId, String answer, List<String> options) => SessionCard(
    termId: termId,
    mode: ExerciseMode.multipleChoice,
    type: 'word',
    prompt: 'багаж',
    answer: answer,
    options: options,
  );

  Widget host(List<SessionCard> cards) => ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWithValue(db),
      reviewSyncProvider.overrideWith((ref) => _SilentReviewSync(ref)),
      studySessionProvider.overrideWith(
        (ref, args) async => StudySession(sessionId: args.sessionId, cards: cards),
      ),
    ],
    child: const MaterialApp(
      locale: Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: [Locale('ru')],
      // No `targetLang`: this is the CROSS-COLLECTION session — the one that used to fall back to
      // the profile for every card in it.
      home: SessionScreen(title: 'Повторение'),
    ),
  );

  /// Tear the tree down INSIDE the test and drain what that schedules — drift cancels its query
  /// streams with a zero-duration timer the binding would otherwise report as pending.
  Future<void> close(WidgetTester tester) async {
    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump(const Duration(milliseconds: 1));
  }

  testWidgets('a listening card of an Italian folder is spoken by an Italian voice', (tester) async {
    await tester.pumpWidget(host([heard('IT1', 'trattoria')]));
    await tester.pumpAndSettle();

    expect(utterances(), [(locale: 'it-IT', text: 'trattoria')]);

    await close(tester);
  });

  testWidgets('an English folder still gets the English voice', (tester) async {
    await tester.pumpWidget(host([heard('EN1', 'luggage')]));
    await tester.pumpAndSettle();

    expect(utterances(), [(locale: 'en-US', text: 'luggage')]);

    await close(tester);
  });

  testWidgets('dictation follows the pair too — its content IS the sound', (tester) async {
    final card = SessionCard(
      termId: 'PL1',
      mode: ExerciseMode.dictation,
      type: 'word',
      prompt: null,
      answer: 'Mam bagaż podręczny w kabinie.',
      example: 'Mam bagaż podręczny w kabinie.',
    );

    await tester.pumpWidget(host([card]));
    await tester.pumpAndSettle();

    expect(utterances(), [(locale: 'pl-PL', text: 'Mam bagaż podręczny w kabinie.')]);

    await close(tester);
  });

  testWidgets('a MIXED session changes voice between cards', (tester) async {
    await tester.pumpWidget(
      host([
        choice('IT1', 'trattoria', const ['trattoria', 'pasticceria']),
        heard('EN1', 'luggage'),
      ]),
    );
    await tester.pumpAndSettle();

    // The verdict pronounces the correct form — in the language of the card that asked it. It is
    // fired 420 ms after the feedback settles (F20), which is past the point `pumpAndSettle` stops.
    await tester.tap(find.text('trattoria'));
    await tester.pumpAndSettle();
    await tester.pump(const Duration(milliseconds: 500));
    expect(utterances(), [(locale: 'it-IT', text: 'trattoria')]);

    await tester.tap(find.text('Дальше'));
    await tester.pumpAndSettle();

    // …and the next card, from another folder, brings its own voice with it. This is the whole bug:
    // one session, two languages, and the voice has to move with the card.
    expect(utterances(), [
      (locale: 'it-IT', text: 'trattoria'),
      (locale: 'en-US', text: 'luggage'),
    ]);

    await close(tester);
  });

  // ── the EAR follows the same pair as the voice (BUGFIX-2 Ч.3в) ──────────────────────────────
  //
  // «Мои слова» drills a WORD, with no collection to name — the pool outlives the folders its words
  // came from — so the session has no `targetLang` to fall back on and every language on it is
  // resolved per card. The voice was already pinned above; the MICROPHONE is the other half of the
  // same fact, and it was never asserted: a Polish card listened to by an English recogniser hears
  // English words, and the learner is marked wrong for saying the right thing.
  //
  // Both come off `_langOfCard`, which reads `pairByTerms` — so what is pinned here is that the two
  // resolvers are ONE resolver, on the path where nothing else could supply the language.

  /// The `speechLocaleId` the exercise card was actually handed.
  String sttLocaleOnScreen(WidgetTester tester) =>
      tester.widget<SessionExerciseCard>(find.byType(SessionExerciseCard)).speechLocaleId;

  SessionCard speakingCard(String termId, String answer) => SessionCard(
    termId: termId,
    mode: ExerciseMode.speaking,
    type: 'word',
    prompt: 'перевод',
    answer: answer,
    ladderStep: null, // free practice carries no rung — the word form
  );

  for (final (name, termId, term, locale) in [
    ('an Italian word', 'IT1', 'trattoria', 'it_IT'),
    ('a Polish word', 'PL1', 'bagaż', 'pl_PL'),
    ('an English word', 'EN1', 'luggage', 'en_US'),
  ]) {
    testWidgets('«Мои слова»: $name is listened to by its own recogniser', (tester) async {
      await tester.pumpWidget(host([speakingCard(termId, term)]));
      await tester.pumpAndSettle();

      expect(sttLocaleOnScreen(tester), locale);
      // …and it is the TTS locale of the same pair, spelled the way `speech_to_text` wants it.
      // One table, one resolver — never two lists that agree today.
      expect(sttLocaleOnScreen(tester), ttsLocaleFor(term == 'trattoria'
          ? 'it'
          : term == 'bagaż'
              ? 'pl'
              : 'en').replaceAll('-', '_'));

      await close(tester);
    });
  }

  _noTrainerPinsItsOwnLocale();
}

/// The watchdog, in the shape of the transliteration guard (SYN-1b): a locale is DERIVED from the
/// card's pair, never written down at a call site. A literal like `'en-US'` anywhere in the trainer
/// is the bug re-entering by the same door it came in the first time — and the two widget defaults
/// that used to read `speechLocaleId = 'en_US'` are exactly what it would have caught.
void _noTrainerPinsItsOwnLocale() {
  test('no trainer surface writes a locale down', () {
    // `xx-XX` (TTS) and `xx_XX` (speech_to_text) — the two shapes a hardcoded locale can take.
    final locale = RegExp(r"'[a-z]{2}[-_][A-Z]{2}'");
    final offenders = <String>[];

    final files = <File>[
      ...Directory('lib/features/training').listSync(recursive: true).whereType<File>(),
      File('lib/data/pronouncer.dart'),
    ];
    for (final file in files) {
      if (!file.path.endsWith('.dart')) continue;
      if (locale.hasMatch(file.readAsStringSync())) offenders.add(file.path);
    }

    expect(
      offenders,
      isEmpty,
      reason:
          'A trainer asks `ttsLocaleFor` / `sttLocaleFor` for the CARD\'s language (lib/data/'
          'languages.dart is the only table). A locale spelled out here reads one folder\'s words '
          'in another folder\'s voice:\n${offenders.join('\n')}',
    );
  });
}

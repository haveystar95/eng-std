import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/plan/plan_dialogue.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../support/speech_probe_channel.dart';

/// МИКРОФОН ВМЕСТО ЧЕСТНОГО СЛОВА — наряд SCENE-RUN, Ч.4.
///
/// Кнопка «Сказал вслух» спрашивала человека, сделал ли он то, чего экран не видел. Теперь экран
/// слушает — и по-прежнему НИЧЕГО не оценивает: пузырь ставит сам факт речи. Здесь прибиты три
/// вещи, которые легко потерять: тишина не запирает ход, вторая попытка его отпускает, а телефон
/// без разрешения на микрофон остаётся с прежней текстовой кнопкой.
class _ScriptedRecognizer implements SpeechRecognizer {
  _ScriptedRecognizer(this._script, {this.permitted = true});

  final List<SpeechAttempt> _script;
  final bool permitted;
  int calls = 0;

  /// Язык, на котором у плагина просили слушать, — по вызову.
  final List<String> locales = [];

  @override
  bool get isReady => permitted;

  @override
  Future<bool> prepare() async => permitted;

  @override
  Future<bool> get hasPermission async => permitted;

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
  }) async {
    locales.add(localeId);
    final attempt = _script[calls.clamp(0, _script.length - 1)];
    calls++;

    return attempt;
  }

  @override
  Future<void> stop() async {}

  @override
  Future<void> cancel() async {}
}

void main() {
  const turn = PlanDialogueTurn(
    turn: 'you',
    termId: '01SAY',
    text: 'My background is in backend development.',
    shelf: 'say',
  );

  late int done;

  /// Ответ ОС про разрешения — {@see mockSpeechProbe}.
  mockSpeechProbe();

  setUp(() => done = 0);

  Widget host(SpeechRecognizer recognizer) => ProviderScope(
    overrides: [
      appDatabaseProvider.overrideWith((ref) {
        final db = AppDatabase.forTesting(NativeDatabase.memory());
        ref.onDispose(db.close);
        return db;
      }),
      speechRecognizerProvider.overrideWithValue(recognizer),
    ],
    child: MaterialApp(
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru'), Locale('en')],
      home: Scaffold(
        body: SingleChildScrollView(
          child: PlanDialogueSayAloud(
            turn: turn,
            speechLocaleId: 'en_US',
            onSpeak: (_) {},
            onDone: () => done++,
          ),
        ),
      ),
    ),
  );

  testWidgets('услышанная реплика ставит пузырь — и ничего не оценивает', (tester) async {
    await tester.pumpWidget(host(_ScriptedRecognizer(const [SpeechAttempt.heard('my background')])));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Сказать вслух'));
    // Ход не оценивается, ключа у него нет — попытку закрывает тишина после последнего слова.
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();

    expect(done, 1);
    // Ни «верно», ни «не то»: разбора произношения здесь не обещали и не делают.
    expect(find.textContaining('Не то'), findsNothing);
  });

  testWidgets('первая тишина просит повторить, вторая отпускает ход', (tester) async {
    final recognizer = _ScriptedRecognizer(const [SpeechAttempt.silent(), SpeechAttempt.silent()]);
    await tester.pumpWidget(host(recognizer));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Сказать вслух'));
    // ТИШИНУ ЗАКРЫВАЕТ СТОРОЖ ДВИЖКА (DAY-FIX-3, Ч.1.3): пустой ответ плагина попытку больше не
    // кончает — микрофон переоткрывается, и только пятнадцать секунд без единого слова от
    // открытия отдают ход назад.
    await tester.pump(const Duration(seconds: 16));
    await tester.pumpAndSettle();

    // Ход ещё за человеком, и экран говорит почему.
    expect(done, 0);
    expect(find.textContaining('Не расслышали'), findsOneWidget);
    expect(recognizer.calls, greaterThan(1), reason: 'микрофон переоткрывался, а не сдался');

    await tester.tap(find.text('Сказать вслух'));
    await tester.pump(const Duration(seconds: 16));
    await tester.pumpAndSettle();

    // Микрофон, который не расслышал дважды, не имеет права держать человека в этом ходу.
    expect(done, 1);
  });

  testWidgets('без разрешения на микрофон остаётся прежняя текстовая кнопка', (tester) async {
    await tester.pumpWidget(host(_ScriptedRecognizer(const [], permitted: false)));
    await tester.pumpAndSettle();

    expect(find.text('Сказал вслух'), findsOneWidget);
    expect(find.text('Сказать вслух'), findsNothing);

    // И она работает: ход всё равно не оценивается, а человек, который сказал реплику вслух,
    // сказал её вслух.
    await tester.tap(find.text('Сказал вслух'));
    await tester.pumpAndSettle();
    expect(done, 1);
  });

  // ПРАВИЛО: наряд DAY-GATE-1, Ч.0.2 — микрофон слушает НА ЯЗЫКЕ ЦЕЛИ.
  // ЛОВИТ: пустую строку вместо локали. Этот шаг открывал распознаватель с `localeId: ''`, а на
  // iOS `SFSpeechRecognizer(locale: Locale(identifier: ""))` — это nil: `noRecognizerError` на
  // каждом ходу, микрофон здесь не работал НИКОГДА, и молча — экран показывал «Не расслышали» и
  // отпускал ход, будто в комнате было шумно.
  testWidgets('микрофон открывается на языке плана, а не с пустой локалью', (tester) async {
    final recognizer = _ScriptedRecognizer(const [SpeechAttempt.heard('my background')]);
    await tester.pumpWidget(host(recognizer));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Сказать вслух'));
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();

    expect(recognizer.locales, isNotEmpty);
    expect(recognizer.locales.every((l) => l.trim().isNotEmpty), isTrue);
    expect(recognizer.locales.first, 'en_US');
  });
}

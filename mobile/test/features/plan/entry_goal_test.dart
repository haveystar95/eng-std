import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/plan/plan_languages.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/data/typography.dart';
import 'package:eng_std/features/plan/entry/dictation_wave.dart' show DictatedText;
import 'package:eng_std/features/plan/entry/entry_goal_step.dart';
import 'package:eng_std/features/plan/entry/plan_entry_screen.dart';
import 'package:eng_std/theme/theme.dart';

import '../../support/held_recognizer.dart';
import '../../support/nbsp.dart';
import '../../support/plan_goldens.dart';

/// WHO YOU ARE AND WHAT MATTERS (наряд CLIENT-22-1 §1) — the goal step suggests the details the plan is built from and
/// never demands them: the companion under the field, the examples in the empty field, the goal sent as entered.
void main() {
  setUpAll(setUpPlanGoldens);

  const companion = 'Кто ты и что важно — профессия, опыт, город. План соберётся под это.';
  const companionShort = 'Добавь пару слов о себе: профессия, опыт — и план будет про тебя';
  const examples = [
    'Собеседование на повара в пятницу. Работал три года в ресторане',
    'Приём у врача в Берлине — болит спина, нужен рецепт',
    'Снять квартиру в Лиссабоне на год, с собакой',
    'Звонок в банк: заблокировали карту, я не резидент',
  ];

  Future<_Api> open(WidgetTester tester, {SpeechRecognizer? recognizer}) async {
    final api = _Api();
    tester.view
      ..devicePixelRatio = kGoldenDpr
      ..physicalSize = kFrameSize * kGoldenDpr;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      planGoldenApp(
        ProviderScope(
          overrides: [
            apiClientProvider.overrideWithValue(api),
            planLanguagesProvider.overrideWith((ref) => loadPlanLanguages(api)),
            connectivityProvider.overrideWith((ref) => Stream.value(true)),
            if (recognizer != null) speechRecognizerProvider.overrideWithValue(recognizer),
          ],
          child: PlanEntryScreen(now: () => DateTime(2026, 10, 1, 12)),
        ),
      ),
    );
    await tester.pump();
    return api;
  }

  Finder companionText(String text) => find.text(nbTypo(text));

  /// Which example is opaque — the one the field shows.
  int shownExample(WidgetTester tester) {
    final shown = [
      for (var i = 1; i <= 4; i++)
        if (tester.widget<AnimatedOpacity>(find.byKey(ValueKey('goal-example-$i'))).opacity == 1) i,
    ];
    expect(shown, hasLength(1), reason: 'one example at a time');
    return shown.single;
  }

  // RULE (§1a, §1c): the companion is always under the field; fewer than four words turn its text, four and more turn it
  // back; «Далее» is live whenever the field has a word.
  // CATCHES: a hint that appears only on a short goal, a short goal that blocks the button, a threshold off by one.
  testWidgets('the companion: always there; fewer than four words — «Добавь пару слов о себе», four — back', (tester) async {
    await open(tester);
    expect(companionText(companion), findsOneWidget, reason: 'the empty field has the companion too');

    Future<void> type(String text) async {
      await tester.enterText(find.byType(TextField), text);
      await crossfaded(tester);
    }

    await type('врач');
    expect(companionText(companionShort), findsOneWidget);
    expect(companionText(companion), findsNothing);
    expect(tester.widget<GestureDetector>(find.ancestor(of: find.text('Далее'), matching: find.byType(GestureDetector)).first).onTap,
        isNotNull, reason: 'one word is a valid goal — the button never blocks');

    await type('Собеседование на повара');
    expect(companionText(companionShort), findsOneWidget, reason: 'three words');
    await type('Собеседование на повара завтра');
    expect(companionText(companion), findsOneWidget, reason: 'four words');
    await type('врач —');
    expect(companionText(companionShort), findsOneWidget, reason: 'a lone dash is no word');
    await type('');
    expect(companionText(companion), findsOneWidget, reason: 'an empty field is no short goal');
  });

  test('a short goal: fewer than four words, a word has a letter or a digit', () {
    expect(EntryGoalStep.isShort(''), isFalse);
    expect(EntryGoalStep.isShort('врач'), isTrue);
    expect(EntryGoalStep.isShort('Иду к врачу'), isTrue);
    expect(EntryGoalStep.isShort('Иду к врачу завтра'), isFalse);
    expect(EntryGoalStep.isShort('Приём — 3 октября'), isTrue);
    expect(EntryGoalStep.isShort('  врач  \n  '), isTrue);
  });

  // RULE (§1b): the four examples, one at a time, change every 4 s while the field is empty; a focus in the field does
  // not stop them, the first character does; an emptied field lets them go on.
  // CATCHES: examples that stop on a tap into the field, change under a typed text, or stand frozen on the first.
  testWidgets('the examples: one at a time, every 4 s; focus goes on, the first character stops', (tester) async {
    await open(tester);
    for (final e in examples) {
      expect(find.text(nbTypo(e)), findsOneWidget, reason: 'every example is laid out, so the field does not jump');
    }
    expect(shownExample(tester), 1);
    await tester.pump(AppMotion.goalExampleEvery);
    await tester.pump(AppMotion.goalExampleFade);
    expect(shownExample(tester), 2);

    await tester.tap(find.byType(TextField));
    await tester.pump();
    await tester.pump(AppMotion.goalExampleEvery);
    await tester.pump(AppMotion.goalExampleFade);
    expect(shownExample(tester), 3, reason: 'a focus in the field does not stop them');

    await tester.enterText(find.byType(TextField), 'Я');
    await tester.pump();
    await tester.pump(AppMotion.goalExampleEvery * 3);
    expect(shownExample(tester), 3, reason: 'the first character stops them');

    await tester.enterText(find.byType(TextField), '');
    await tester.pump();
    await tester.pump(AppMotion.goalExampleEvery);
    await tester.pump(AppMotion.goalExampleFade);
    expect(shownExample(tester), 4, reason: 'an empty field again — they go on');
    await tester.pump(AppMotion.goalExampleEvery);
    await tester.pump(AppMotion.goalExampleFade);
    expect(shownExample(tester), 1, reason: 'round and round');
  });

  // RULE (§1b): while the learner dictates, the examples stand; the companion keeps its text until the dictation closes
  // and then reads the goal it left.
  // CATCHES: a companion flickering with every heard word; examples turning under the dictated text.
  testWidgets('dictation: the examples stand, the companion waits for the dictation to close', (tester) async {
    await open(tester, recognizer: HeldRecognizer(['Собеседование', 'Собеседование на повара']));
    await tester.tap(find.byKey(const ValueKey('goal-mic')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    expect(find.byType(DictatedText), findsOneWidget, reason: 'the learner is speaking');
    expect(companionText(companion), findsOneWidget, reason: 'mid-dictation the companion does not change');

    // Two seconds of silence after the last word close the recording by themselves.
    await tester.pump(const Duration(seconds: 3));
    await crossfaded(tester);
    expect(find.byType(DictatedText), findsNothing);
    expect(tester.widget<TextField>(find.byType(TextField)).controller!.text, 'Собеседование на повара');
    expect(companionText(companionShort), findsOneWidget, reason: 'the dictation left three words');

    await tester.enterText(find.byType(TextField), '');
    await tester.pump();
    expect(shownExample(tester), 1, reason: 'they stood still while the learner spoke');
  });

  // RULE (§1d): the goal leaves AS IT WAS ENTERED — no trimming, no cutting, no typography: every word the learner wrote
  // about themselves is a detail the server builds the lessons from.
  // CATCHES: `.trim()` before `POST /plans` (the build before this one sent the goal trimmed), a no-break space from the
  // interface's typography riding into the goal.
  testWidgets('the goal goes to the server as entered — spaces, line breaks and all', (tester) async {
    final api = await open(tester);
    const goal = '  Собеседование на повара в пятницу.\nРаботал три года в ресторане «Прага»  ';
    await tester.enterText(find.byType(TextField), goal);
    await tester.pump();
    await _toPreview(tester);
    expect(api.goals, [goal]);
    expect(api.goals.single.contains(kNoBreakSpace), isFalse);
  });

  // RULE (§1d, §2): a story from «Так пишут другие» comes in as words — the no-break spaces of its display (the `.arb`'s
  // typography) stay out of the field and out of the goal.
  // CATCHES: «К врачу с<nbsp>ребёнком…» sent to the server.
  testWidgets('a tapped story is entered with plain spaces, and sent so', (tester) async {
    final api = await open(tester);
    const story = 'К врачу с ребёнком, первый раз в местной клинике';
    await tester.tap(find.text(nbTypo(story)));
    await tester.pump();
    expect(tester.widget<TextField>(find.byType(TextField)).controller!.text, story);
    await _toPreview(tester);
    expect(api.goals, [story]);
  });
}

/// The companion crossfades: the frame that starts the fade, its first tick, the fade, and the frame the old text leaves
/// the tree in.
Future<void> crossfaded(WidgetTester tester) async {
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 1));
  await tester.pump(AppMotion.goalCompanionFade);
  await tester.pump();
}

/// «Далее» three times and «Собрать план» — the plan is asked for.
Future<void> _toPreview(WidgetTester tester) async {
  for (var i = 0; i < 3; i++) {
    await tester.tap(find.text('Далее'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 200));
  }
  await tester.tap(find.text('Собрать план'));
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 200));
}

/// `POST /plans` records the goal it was sent and answers «building»; the rest of the entry as the golden test has it.
class _Api implements ApiClient {
  final goals = <String>[];

  PlanBuild _build() => PlanBuild.fromJson({...planFixture('build_ready'), 'status': 'building'});

  @override
  Future<PlanBuild> createPlan({
    required String goalText,
    required String targetLang,
    required PlanLevel level,
    required int daysTotal,
    String? eventDate,
  }) async {
    goals.add(goalText);
    return _build();
  }

  @override
  Future<PlanBuild> planBuild(String planId) async => _build();

  @override
  Future<PlanLanguages> pairLanguages() async => PlanLanguages.fromJson(languagesFixture());

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

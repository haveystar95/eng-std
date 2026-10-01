import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/typography.dart';
import 'package:eng_std/features/plan/conversation/talk_entry.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart';
import 'package:eng_std/features/plan/session/parts/session_bubbles.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/native_text.dart';

import '../../support/nbsp.dart';
import '../../support/plan_goldens.dart' show setUpPlanGoldens;
import '../../support/session_harness.dart';

/// THE TYPOGRAPHY CANON (наряд CLIENT-22-1 §2c) — on the screens, with the real fonts: the interface's lines and the
/// server's native titles do not tear at a short word; a frame in the language being learned stays byte for byte.
void main() {
  setUpAll(setUpPlanGoldens);

  /// The first line of [text] set in [style] at [width] — where the line really breaks.
  String firstLine(String text, TextStyle style, double width) {
    final painter = TextPainter(text: TextSpan(text: text, style: style), textDirection: TextDirection.ltr)..layout(maxWidth: width);
    final end = painter.getLineBoundary(const TextPosition(offset: 0)).end;
    painter.dispose();
    return text.substring(0, end).trimRight();
  }

  /// A width that holds [head] on a line of its own and holds [glued] (the short word with the word it keeps) too — so
  /// the plain text tears right after [head], and the set text has room to carry the short word down instead of
  /// breaking the glued pair as a last resort.
  double widthFor(String head, String glued, TextStyle style) {
    double measure(String text) {
      final painter = TextPainter(text: TextSpan(text: text, style: style), textDirection: TextDirection.ltr)..layout();
      final width = painter.width;
      painter.dispose();
      return width;
    }

    return [measure(head), measure(glued)].reduce((a, b) => a > b ? a : b) + 2;
  }

  // THE INTERFACE'S LINES (the `.arb`): each canon phrase carries its no-break spaces, and none of them tears where the
  // plain text did — «именно под / него» (41-2a), «на те / дни» (41-2c), «Отвечай и / спрашивай сам» (37-5).
  test('the interface: «…именно под него», «на те дни», «Отвечай и спрашивай сам»', () {
    final l = lookupAppLocalizations(const Locale('ru'));
    expect(l.introAThought, contains('именно под$nbspнего'));
    expect(l.introCThought, contains('на$nbspте$nbspдни'));
    expect(l.planTalkEntryRuleStart, contains('Отвечай и$nbspспрашивай сам'));

    const style = TextStyle(fontFamily: AppFonts.inter, fontSize: 15);
    for (final (plain, head, glued) in [
      ('Назови событие — план соберётся именно под него.', 'Назови событие — план соберётся именно под', 'под него.'),
      ('Ровно на те дни, что остались до события.', 'Ровно на те', 'на те дни,'),
      ('Отвечай и спрашивай сам.', 'Отвечай и', 'и спрашивай'),
    ]) {
      final width = widthFor(head, glued, style);
      expect(firstLine(plain, style, width), head, reason: 'the plain text tears after «$head»');
      final set = typeset(plain, 'ru');
      final line = firstLine(set, style, width).replaceAll(nbsp, ' ');
      expect(head.startsWith(line) && line.length < head.length, isTrue, reason: '«$line» — the short word went down with its word');
    }
  });

  // THE SERVER'S TITLE (кадр 37-5b): «Поговори с регистратором» comes as the server wrote it, and the screen sets it.
  // CATCHES: «Поговори с / регистратором» — the preposition left at the end of the line (доработка CLIENT-START, вопрос 2).
  testWidgets('the server\'s title «Поговори с регистратором» (37-5b): «с» stays with the role', (tester) async {
    tester.view
      ..physicalSize = const Size(390, 844) * 2
      ..devicePixelRatio = 2;
    addTearDown(tester.view.reset);
    await tester.pumpWidget(
      MaterialApp(
        theme: buildAppTheme(),
        locale: const Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru'), Locale('en')],
        home: Scaffold(
          body: TalkEntryView(
            scene: null,
            minutes: 4,
            title: 'Поговори с регистратором',
            targets: const [],
            rehearsal: true,
            scenesCount: 2,
            noHints: false,
            onNoHints: (_) {},
            onStart: () {},
            onBack: () {},
          ),
        ),
      ),
    );
    await tester.pump();
    expect(find.text('Поговори с$nbspрегистратором'), findsOneWidget);
    final width = widthFor('Поговори с', 'с регистратором', AppTextSession.stageTitle);
    expect(firstLine('Поговори с регистратором', AppTextSession.stageTitle, width), 'Поговори с');
    expect(firstLine('Поговори с$nbspрегистратором', AppTextSession.stageTitle, width), 'Поговори',
        reason: 'set as the screen sets it, the line breaks before «с»');
  });

  // THE LANGUAGE BEING LEARNED STAYS AS IT CAME: a frame the learner reads aloud and the recognizer compares — «I worked
  // at a ___» — keeps its plain spaces byte for byte, while its native line beside it is set.
  // CATCHES: «at a<nbsp>___» in the frame — a comparison with the recognized speech that no longer matches.
  testWidgets('a frame of the target language: «I worked at a ___» byte for byte, its native line set', (tester) async {
    final card = fixtureCardEdited('day-doctor', 'phrase_intro', (p) {
      final frame = p['frame'] as Map<String, dynamic>;
      frame['frame_target'] = 'I worked at a ___.';
      frame['frame_native'] = 'Я работал в ___.';
      final fillers = ((frame['slot'] as Map<String, dynamic>)['fillers'] as List).cast<Map<String, dynamic>>();
      for (final f in fillers) {
        f['native_line'] = 'Я работал в ${f['native']}.';
      }
    });
    await pumpCard(tester, probeEnv(card, CardProbe()));
    final frame = tester.widget<SessionFrameText>(find.byType(SessionFrameText).first);
    expect(frame.before, 'I worked at a ');
    expect(frame.before.codeUnits, 'I worked at a '.codeUnits, reason: 'byte for byte — no no-break space in the target');
    final native = tester.widget<Text>(find.byKey(const ValueKey('lesson-native'))).data!;
    expect(native, startsWith('Я$nbspработал в$nbsp'), reason: 'the native line beside it is the learner\'s — set');
    await settleCard(tester);
  });

  // BY THE NATIVE OF THE PLAN, NOT BY THE SCREEN (§2a): a Polish learner's translation binds one-letter words only, the
  // English line above it stays as it came.
  testWidgets('a pl native: one-letter words bound, two-letter words free, the target line untouched', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: buildAppTheme(),
        home: NativeLanguageScope(
          language: 'pl',
          child: const Scaffold(
            body: SessionBubble(own: false, text: 'It hurts in his lower back.', translation: 'Boli go w dolnej części pleców i nie może spać.'),
          ),
        ),
      ),
    );
    expect(find.text('It hurts in his lower back.'), findsOneWidget);
    expect(find.text('Boli go w${nbsp}dolnej części pleców i${nbsp}nie może spać.'), findsOneWidget);
  });

  // The phrase card's kind stays what the fixture dealt (a guard for the edit above).
  test('the edited card is still a lesson card', () {
    final card = fixtureCardEdited('day-doctor', 'phrase_intro', (_) {});
    expect(card.kind, SessionKind.phraseIntro);
  });
}

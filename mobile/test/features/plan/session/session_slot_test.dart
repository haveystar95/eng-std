import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';

import '../../../support/plan_goldens.dart' show setUpPlanGoldens;
import '../../../support/session_harness.dart';

/// THE FRAME'S SLOT IS SIZED BY ITS TEXT (polish pass SESSION-1b′, item 10): 8 on the sides and 4 above and below
/// inside the outline; the text never touches the outline and is never cut — in every kind with a slot (32-1, 32-4,
/// 32-7, 32-9, 31-7), with long fillers, on a narrow screen. Real fonts: in the test font every glyph is an em square
/// and «appointment» alone is wider than the phone.
void main() {
  setUpAll(setUpPlanGoldens);

  const appointment = 'a follow-up appointment';
  const apartment = 'this apartment';
  const narrow = Size(375, 812);

  SessionCard card(String kind, void Function(Map<String, dynamic> payload) edit) {
    final raw = jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;
    final json = [
      for (final stage in (raw['stages'] as List).cast<Map<String, dynamic>>()) ...(stage['cards'] as List).cast<Map<String, dynamic>>(),
    ].firstWhere((c) => c['kind'] == kind);
    edit(json['payload'] as Map<String, dynamic>);
    return SessionCard.fromJson(json)!;
  }

  Map<String, dynamic> option(Map<String, dynamic> payload, String id) =>
      (payload['options'] as List).cast<Map<String, dynamic>>().firstWhere((o) => o['id'] == id);

  Map<String, dynamic> filler(Map<String, dynamic> payload, int index) =>
      ((payload['frame'] as Map<String, dynamic>)['slot'] as Map<String, dynamic>)['fillers'][index] as Map<String, dynamic>;

  /// The slot showing [text]: the text sits inside the outline with 8 / 4 of padding and is laid out whole.
  void expectSlotFits(WidgetTester tester, String text) {
    final texts = tester.elementList(find.byKey(const ValueKey('session-slot-text'))).where((e) => (e.widget as Text).data == text).toList();
    expect(texts, hasLength(1), reason: '«$text» stands in the slot');
    final textFinder = find.byElementPredicate((e) => identical(e, texts.single));
    final outer = tester.getRect(find.ancestor(of: textFinder, matching: find.byKey(const ValueKey('session-slot'))).first);
    // The slot's rect includes its 2 of margin on the sides; the outline is 1.5 wide.
    final box = Rect.fromLTRB(outer.left + 2 + 1.5, outer.top + 1.5, outer.right - 2 - 1.5, outer.bottom - 1.5);
    final paragraph = tester.renderObject<RenderParagraph>(find.descendant(of: textFinder, matching: find.byType(RichText)));
    final inner = paragraph.localToGlobal(Offset.zero) & paragraph.size;
    const eps = 0.01;
    expect(inner.left - box.left, greaterThanOrEqualTo(8 - eps), reason: 'left padding');
    expect(box.right - inner.right, greaterThanOrEqualTo(8 - eps), reason: 'right padding');
    expect(inner.top - box.top, greaterThanOrEqualTo(4 - eps), reason: 'top padding');
    expect(box.bottom - inner.bottom, greaterThanOrEqualTo(4 - eps), reason: 'bottom padding');
    expect(paragraph.didExceedMaxLines, isFalse);
    expect(paragraph.size.height, closeTo(paragraph.getMaxIntrinsicHeight(paragraph.size.width), eps), reason: 'every line laid out');
    expect(paragraph.size.width, greaterThanOrEqualTo(paragraph.getMinIntrinsicWidth(double.infinity) - eps), reason: 'the longest word fits');
    expect(tester.takeException(), isNull);
  }

  // CATCHES: a slot whose text height is forced below the frame's line (the text touches the outline), and a slot that
  // keeps a fixed size around a long filler.
  testWidgets('32-1 phrase_intro: the said filler and a chip filler — filled and highlighted', (tester) async {
    final intro = card('phrase_intro', (p) {
      filler(p, 0)['target'] = appointment;
      filler(p, 1)['target'] = apartment;
    });
    await pumpCard(tester, probeEnv(intro, CardProbe()), size: narrow);
    expectSlotFits(tester, appointment);

    await tester.tap(find.byKey(const ValueKey('chip-1')));
    await tester.pump();
    expectSlotFits(tester, apartment);
    await tester.pump(const Duration(milliseconds: 600));
    await tester.pump();
    expectSlotFits(tester, apartment);
    await settleCard(tester);
  });

  testWidgets('32-4 phrase_slot: a long correct filler in sage', (tester) async {
    final slot = card('phrase_slot', (p) => option(p, p['correct'] as String)['text'] = appointment);
    await pumpCard(tester, probeEnv(slot, CardProbe()), size: narrow);
    await tapText(tester, appointment);
    expectSlotFits(tester, appointment);
    await settleCard(tester);
  });

  testWidgets('32-7 «Скажи целиком»: the round\'s long meaning, then what was heard, in the window', (tester) async {
    final other = card('phrase_other_slot', (p) {
      // The FIRST round's meaning is the one that must be long: the rounds walk the fillers in order (FIX-1 §6).
      filler(p, 0)['target'] = apartment;
    });
    final probe = CardProbe();
    await pumpCard(tester, probeEnv(other, probe), size: narrow);
    expectSlotFits(tester, apartment);
    await sayDebug(tester, 'It hurts in his $apartment');
    await tester.pump();
    expect(probe.answers, isEmpty, reason: 'a round is not an answer');
    expectSlotFits(tester, apartment);
    await settleCard(tester);
  });

  testWidgets('32-7 own word: live words with the caret, then the judge\'s long value in sage', (tester) async {
    final own = card('phrase_own_slot', (_) {});
    final probe = CardProbe()
      ..verdict = (_) => const SessionJudgeOutcome(accepted: true, slotValue: appointment, result: SessionResult.passed, attempts: 1);
    await pumpCard(tester, probeEnv(own, probe), size: narrow);
    // Through the three meanings of the card to the round of the learner's own word.
    for (final said in ['It started three days ago', 'It started last night', 'It started this morning']) {
      await sayDebug(tester, said);
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 700));
    }
    await enterHeard(tester, 'It started $appointment');
    expectSlotFits(tester, appointment);
    await tester.pump(const Duration(milliseconds: 1010));
    await tester.pump();
    expect(probe.judged, ['It started $appointment']);
    expectSlotFits(tester, appointment);
    await settleCard(tester);
  });

  testWidgets('31-7 word_in_line: a long correct word in the line\'s slot', (tester) async {
    final inLine = card('word_in_line', (p) => option(p, p['correct'] as String)['text'] = appointment);
    await pumpCard(tester, probeEnv(inLine, CardProbe()), size: narrow);
    await tapText(tester, appointment);
    expectSlotFits(tester, appointment);
    await settleCard(tester);
  });
}

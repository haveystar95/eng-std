import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart';

import '../../../support/plan_goldens.dart' show setUpPlanGoldens;
import '../../../support/session_harness.dart';

/// THE ROUNDS OF «СКАЖИ ЦЕЛИКОМ» (work order FIX-2 §5): the values of the window one after another, then the
/// learner's OWN word — and the list is the SERVER'S (`payload.rounds`), not the device's. Two misses in ANY value
/// round close the card `skipped` (the frame lapses, DECISIONS п. 327); the own round is practice and closes it
/// `passed` instead.
///
/// `phrase_repeat` has one round again: it is dealt to a frame WITHOUT a window, and the second round the phone used
/// to invent out of the frame's fillers went with the rounds becoming the server's.
void main() {
  // REAL FONTS: p6's values are long («a follow-up appointment»), and in the test font every glyph is an em square —
  // its chip row would overflow here and nowhere else.
  setUpAll(setUpPlanGoldens);

  final intermediate = sessionFixture('day-doctor');
  final beginner = sessionFixture('day-doctor-beginner');

  List<SessionResult> results(CardProbe probe) => [for (final a in probe.answers) a.result];
  String? slotOf(WidgetTester tester) => tester.widget<SessionFrameText>(find.byType(SessionFrameText).first).slot;

  // CATCHES: the rounds worked out on the device instead of read off the payload — a level given another number of
  // them would then not reach the phone at all — and a value round whose phrase or task is not the server's.
  //
  // p6 is the frame the dialogue says MOST, so the stage's ceiling leaves it whole at either level (DECISIONS
  // п. 354) and the level difference is the only thing left between the two days.
  testWidgets('the rounds are the server\'s: the beginner walks two values, the intermediate three, then the own word', (tester) async {
    for (final (day, values) in [(beginner, 2), (intermediate, 3)]) {
      final card = fixtureCard(day, SessionKind.phraseOtherSlot, ref: 'p6');
      final payload = card.payload as PhraseOtherSlotPayload;
      expect(payload.rounds, hasLength(values));

      final probe = CardProbe();
      await pumpCard(tester, probeEnv(card, probe, day: day));
      for (final round in payload.rounds) {
        expect(slotOf(tester), payload.frame.filler(round.fillerIndex)!.target);
        expect(find.text(round.taskNative), findsOneWidget, reason: 'the round\'s own task');
        await sayDebug(tester, round.expectedText);
        await tester.pump();
        expect(probe.answers, isEmpty, reason: 'no answer before the last round');
        await tester.pump(const Duration(milliseconds: 700));
      }
      expect(find.text('а теперь со своим словом'), findsOneWidget);
      await sayDebug(tester, '${payload.frame.parts.before} my elbow');
      await tester.pump();
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);
    }
  });

  // RULE (наряды FIX-1 §6 и FIX-2 §5): две неудачи в круге ЗНАЧЕНИЯ закрывают карточку `skipped` — это провал
  // каркаса (DECISIONS п. 327), и правило попыток у голосовой карточки одно на все круги.
  // CATCHES: попытки, которые считаются на всю карточку, а не на круг, и карточка, пережившая две неудачи подряд.
  testWidgets('«Скажи целиком»: two misses in a VALUE round close the card, the round\'s meaning in the answer', (tester) async {
    final card = fixtureCard(intermediate, SessionKind.phraseOtherSlot);
    final probe = CardProbe();
    await pumpCard(tester, probeEnv(card, probe));
    // Round 1 — the first meaning of the window.
    expect(slotOf(tester), 'lower back');
    await sayDebug(tester, 'It hurts in his lower back');
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 700));
    expect(slotOf(tester), 'neck', reason: 'the next meaning moved into the window');

    await sayDebug(tester, 'hello');
    await tester.pump();
    expect(probe.answers, isEmpty, reason: 'one miss — once more');
    expect(slotOf(tester), 'neck', reason: 'the round keeps its meaning');
    await sayDebug(tester, 'it hurts in his shoulder');
    await tester.pump();
    expect(results(probe), [SessionResult.skipped], reason: 'the round\'s meaning was not said twice');
    final answer = probe.answers.single;
    expect(answer.attempts, 3, reason: 'attempts are counted over the card, misses over the round');
    expect(answer.response?.fillerIndex, 0, reason: 'the last meaning actually said');
    expect(find.text('Дальше'), findsOneWidget);
    await settleCard(tester);
  });

  // RULE (DECISIONS п. 354): the stage's ceiling may take the own-word round off the card — `own_round: null`. The
  // phone then walks the value rounds and ends there; it never tops the card back up, and the card still passes.
  // The answer names the last meaning actually said, however few rounds the card had.
  // CATCHES: a phone that draws a round the server did not send; an own-word chip or task on a cut card; a
  // `filler_index` read off the ROUND COUNT instead of the round (a one-round card then names no meaning at all).
  testWidgets('the ceiling took the own word: the card is its value rounds, and the answer still names the meaning', (tester) async {
    final raw = jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;
    final json = [
      for (final stage in (raw['stages'] as List).cast<Map<String, dynamic>>()) ...(stage['cards'] as List).cast<Map<String, dynamic>>(),
    ].firstWhere((c) => c['kind'] == 'phrase_other_slot');
    final payload = json['payload'] as Map<String, dynamic>;
    final keep = (payload['rounds'] as List).cast<Map<String, dynamic>>().first;
    payload['rounds'] = [keep];
    payload['own_round'] = null;

    final card = SessionCard.fromJson(json)!;
    expect((card.payload as PhraseOtherSlotPayload).ownRound, isNull);
    final probe = CardProbe();
    await pumpCard(tester, probeEnv(card, probe));
    await sayDebug(tester, keep['expected_text'] as String);
    await tester.pump();

    expect(find.text('а теперь со своим словом'), findsNothing, reason: 'there is no own round to announce');
    expect(probe.judged, isEmpty, reason: 'nothing on this card is the judge\'s');
    expect(results(probe), [SessionResult.passed]);
    expect(probe.answers.single.response?.fillerIndex, keep['filler_index'], reason: 'the meaning actually said');
    await settleCard(tester);
  });

  // CATCHES: a chip row on a frame with a single meaning, and a card that never reaches its own-word round.
  testWidgets('one meaning — one round and the own word after it', (tester) async {
    final raw = jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;
    final json = [
      for (final stage in (raw['stages'] as List).cast<Map<String, dynamic>>()) ...(stage['cards'] as List).cast<Map<String, dynamic>>(),
    ].firstWhere((c) => c['kind'] == 'phrase_other_slot');
    // The card cut down to its LAST round — a state the fixture does not carry, and the shape a frame of one value
    // is dealt in.
    final payload = json['payload'] as Map<String, dynamic>;
    final keep = (payload['rounds'] as List).cast<Map<String, dynamic>>().last;
    final slot = (payload['frame'] as Map<String, dynamic>)['slot'] as Map<String, dynamic>;
    slot['fillers'] = [(slot['fillers'] as List).cast<Map<String, dynamic>>().firstWhere((f) => f['index'] == keep['filler_index'])];
    payload['rounds'] = [keep];
    final probe = CardProbe();
    await pumpCard(tester, probeEnv(SessionCard.fromJson(json)!, probe));
    expect(find.byKey(const ValueKey('chip-0')), findsNothing, reason: 'the frame has one meaning');
    await sayDebug(tester, keep['expected_text'] as String);
    await tester.pump();
    expect(probe.answers, isEmpty, reason: 'the own word is still to come');
    await tester.pump(const Duration(milliseconds: 700));
    expect(find.text('а теперь со своим словом'), findsOneWidget);

    await sayDebug(tester, 'It hurts in his knee');
    await tester.pump();
    expect(results(probe), [SessionResult.passed]);
    expect(probe.answers.single.response?.fillerIndex, isNull, reason: 'the own word is nobody\'s meaning');
    await settleCard(tester);
  });
}

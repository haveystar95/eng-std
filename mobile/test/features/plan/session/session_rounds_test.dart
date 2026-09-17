import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/voice_rounds.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart';

import '../../../support/session_harness.dart';

/// ROUNDS OF PHRASE_REPEAT (polish pass SESSION-1b′, item 12): round 1 — the card's filler, round 2 — the frame's
/// next visible filler by index; both rounds pass by coverage; two misses in any round — `skipped`; one answer at the
/// end with the last filler said; a frame without a second filler — one round, as before.
///
/// `phrase_other_slot` (32-7) lost its rounds with SESSION-2b: the learner chooses the meaning with a chip, and the
/// card asks once — the tests of that card live in `session_cards_test.dart`.
void main() {
  final intermediate = sessionFixture('day-doctor');
  final beginner = sessionFixture('day-doctor-beginner');

  List<SessionResult> results(CardProbe probe) => [for (final a in probe.answers) a.result];
  String? slotOf(WidgetTester tester) => tester.widget<SessionFrameText>(find.byType(SessionFrameText).first).slot;
  String roundLabel(WidgetTester tester) => tester.widget<Text>(find.byKey(const ValueKey('voice-round'))).data!;

  group('VoiceRounds', () {
    // CATCHES: a round 2 that repeats round 1, stops at the last index instead of wrapping, or appears for a frame
    // with nothing to change.
    test('round 2 — the next visible filler by index, cyclically', () {
      final repeats = beginner.stages.expand((s) => s.cards).map((c) => c.payload).whereType<PhraseRepeatPayload>().toList();
      final p1 = VoiceRounds.ofRepeat(repeats.firstWhere((p) => p.frame.ref == 'p1'));
      expect([for (final r in p1) r.fillerIndex], [1, 2]);
      expect(p1.last.expectedText, 'It hurts in his shoulder.');
      expect(p1.last.native, 'У него болит плечо.');
      expect(p1.last.audio?.ref, 'p1.f3');

      final p6 = VoiceRounds.ofRepeat(repeats.firstWhere((p) => p.frame.ref == 'p6'));
      expect([for (final r in p6) r.fillerIndex], [2, 0], reason: 'after the last index — from the first');
      expect(p6.last.expectedText, 'Do we need an X-ray?');

      final p4 = VoiceRounds.ofRepeat(repeats.firstWhere((p) => p.frame.ref == 'p4'));
      expect(p4, hasLength(1), reason: 'a frame without a slot — one round');
    });
  });

  // CATCHES: an answer written after round 1, a round 2 that keeps round 1's phrase or sample, a response without
  // the last filler said.
  testWidgets('phrase_repeat: two rounds pass — «1 of 2», «2 of 2», one answer with the last filler', (tester) async {
    final card = fixtureCard(beginner, SessionKind.phraseRepeat);
    final probe = CardProbe();
    final voice = QuietVoice();
    await pumpCard(tester, probeEnv(card, probe, voice: voice));
    expect(roundLabel(tester), '1 из 2');
    expect(find.text('It hurts in his neck.'), findsOneWidget);
    await tester.pump(const Duration(milliseconds: 300));
    expect(voice.played, ['p1.f2@0.85']);

    await sayDebug(tester, 'It hurts in his neck');
    expect(probe.answers, isEmpty, reason: 'round 1 passed — no answer yet');
    await tester.pump(const Duration(milliseconds: 600));
    await tester.pump();
    expect(roundLabel(tester), '2 из 2');
    expect(find.text('It hurts in his shoulder.'), findsOneWidget);
    expect(find.text('У него болит плечо.'), findsOneWidget, reason: 'frame_native with the filler\'s native');
    await tester.pump(const Duration(milliseconds: 300));
    expect(voice.played, ['p1.f2@0.85', 'p1.f3@0.85'], reason: 'round 2 plays its own sample');

    await sayDebug(tester, 'It hurts in his shoulder');
    expect(results(probe), [SessionResult.passed]);
    final answer = probe.answers.single;
    expect(answer.attempts, 2);
    expect(answer.response?.fillerIndex, 2, reason: 'the last filler said');
    expect(answer.response?.mode, isNull, reason: '«rounds» is not a mode the server accepts yet — not sent');
    await settleCard(tester);
    expect(probe.nexts, 1);
  });

  // RULE (SESSION-2b §1): 32-7 asks ONCE — the chip chooses the meaning, and two misses close the card as `skipped`,
  // with the chosen meaning in the answer. No round header and no second forced filler.
  // CATCHES: rounds coming back to the card (the chip's choice taken away), misses counted per round.
  testWidgets('phrase_other_slot: no rounds — two misses close the card, the chosen meaning in the answer', (tester) async {
    final card = fixtureCard(intermediate, SessionKind.phraseOtherSlot);
    final probe = CardProbe();
    await pumpCard(tester, probeEnv(card, probe));
    expect(find.byKey(const ValueKey('voice-round')), findsNothing, reason: 'one round');
    await tester.tap(find.byKey(const ValueKey('chip-1')));
    await tester.pump();

    await sayDebug(tester, 'hello');
    expect(probe.answers, isEmpty, reason: 'one miss — once more');
    expect(slotOf(tester), 'neck', reason: 'the chosen meaning stays in the slot');
    await sayDebug(tester, 'it hurts in his shoulder');
    expect(results(probe), [SessionResult.skipped], reason: 'the chosen meaning was not said twice');
    final answer = probe.answers.single;
    expect(answer.attempts, 2);
    expect(answer.response?.fillerIndex, 1, reason: 'the meaning the learner chose');
    expect(find.text('Дальше'), findsOneWidget);
    await settleCard(tester);
  });

  // CATCHES: a chip row on a frame with a single visible meaning (there is nothing to choose), and an answer that
  // names a filler the card never offered.
  testWidgets('one visible filler — no chips, the answer at once', (tester) async {
    final raw = jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;
    final json = [
      for (final stage in (raw['stages'] as List).cast<Map<String, dynamic>>()) ...(stage['cards'] as List).cast<Map<String, dynamic>>(),
    ].firstWhere((c) => c['kind'] == 'phrase_other_slot');
    final slot = ((json['payload'] as Map<String, dynamic>)['frame'] as Map<String, dynamic>)['slot'] as Map<String, dynamic>;
    slot['fillers'] = [(slot['fillers'] as List).cast<Map<String, dynamic>>().firstWhere((f) => f['index'] == 2)];
    final probe = CardProbe();
    await pumpCard(tester, probeEnv(SessionCard.fromJson(json)!, probe));
    expect(find.byKey(const ValueKey('voice-round')), findsNothing);
    expect(find.byKey(const ValueKey('chip-2')), findsNothing, reason: 'one meaning — nothing to choose');
    await sayDebug(tester, 'It hurts in his shoulder');
    expect(results(probe), [SessionResult.passed]);
    expect(probe.answers.single.response?.fillerIndex, 2, reason: 'the only meaning of the frame');
    await settleCard(tester);
  });
}

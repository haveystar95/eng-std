import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/voice_rounds.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart';

import '../../../support/session_harness.dart';

/// ROUNDS OF PHRASE_REPEAT AND PHRASE_OTHER_SLOT (polish pass SESSION-1b′, item 12): round 1 — the card's filler,
/// round 2 — the frame's next visible filler by index (for 32-7 — one the dialogue does not say); both rounds pass
/// by coverage; two misses in any round — `skipped`; one answer at the end with the last filler said; a frame
/// without a second filler — one round, as before.
void main() {
  final intermediate = sessionFixture('day-doctor');
  final beginner = sessionFixture('day-doctor-beginner');

  List<SessionResult> results(CardProbe probe) => [for (final a in probe.answers) a.result];
  String? slotOf(WidgetTester tester) => tester.widget<SessionFrameText>(find.byType(SessionFrameText).first).slot;
  String roundLabel(WidgetTester tester) => tester.widget<Text>(find.byKey(const ValueKey('voice-round'))).data!;

  group('VoiceRounds', () {
    // CATCHES: a round 2 that repeats round 1, takes a filler the dialogue says (32-7), stops at the last index
    // instead of wrapping, or appears for a frame with nothing to change.
    test('round 2 — the next visible filler by index, cyclically; 32-7 skips the dialogue\'s fillers', () {
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

      final others = intermediate.stages.expand((s) => s.cards).map((c) => c.payload).whereType<PhraseOtherSlotPayload>().toList();
      final o1 = VoiceRounds.ofOtherSlot(others.firstWhere((p) => p.frame.ref == 'p1'));
      expect([for (final r in o1) r.fillerIndex], [2, 1], reason: 'after 2 — 0 is said in the dialogue, so 1');
      expect(o1.last.slotExpected, 'neck');
      expect(o1.last.native, 'У него болит шея.');
      final o6 = VoiceRounds.ofOtherSlot(others.firstWhere((p) => p.frame.ref == 'p6'));
      expect(o6, hasLength(1), reason: 'every other filler of p6 is said in the dialogue');
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

  // CATCHES: misses counted across rounds (one miss in each closing the card), a card that passes on round 1 alone.
  testWidgets('phrase_other_slot: a pass, then two misses in round 2 — skipped, the filler of round 1', (tester) async {
    final card = fixtureCard(intermediate, SessionKind.phraseOtherSlot);
    final probe = CardProbe();
    await pumpCard(tester, probeEnv(card, probe));
    await sayDebug(tester, 'It hurts in his shoulder');
    expect(probe.answers, isEmpty);
    await tester.pump(const Duration(milliseconds: 600));
    await tester.pump();
    expect(roundLabel(tester), '2 из 2');
    expect(slotOf(tester), isNull);

    await sayDebug(tester, 'hello');
    expect(probe.answers, isEmpty, reason: 'one miss in round 2 — once more');
    await sayDebug(tester, 'it hurts in his shoulder');
    expect(results(probe), [SessionResult.skipped], reason: 'round 1\'s filler is not round 2\'s — a second miss');
    final answer = probe.answers.single;
    expect(answer.attempts, 3);
    expect(answer.response?.fillerIndex, 2, reason: 'the last filler said — round 1\'s');
    expect(find.text('Дальше'), findsOneWidget);
    await settleCard(tester);
  });

  // CATCHES: rounds invented for a frame with a single visible filler, and a header on a one-round card.
  testWidgets('one visible filler — one round, as before: no header, the answer at once', (tester) async {
    final raw = jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;
    final json = [
      for (final stage in (raw['stages'] as List).cast<Map<String, dynamic>>()) ...(stage['cards'] as List).cast<Map<String, dynamic>>(),
    ].firstWhere((c) => c['kind'] == 'phrase_other_slot');
    final slot = ((json['payload'] as Map<String, dynamic>)['frame'] as Map<String, dynamic>)['slot'] as Map<String, dynamic>;
    slot['fillers'] = [(slot['fillers'] as List).cast<Map<String, dynamic>>().firstWhere((f) => f['index'] == 2)];
    final probe = CardProbe();
    await pumpCard(tester, probeEnv(SessionCard.fromJson(json)!, probe));
    expect(find.byKey(const ValueKey('voice-round')), findsNothing);
    await sayDebug(tester, 'It hurts in his shoulder');
    expect(results(probe), [SessionResult.passed]);
    expect(probe.answers.single.response?.fillerIndex, isNull, reason: 'the answer as before rounds');
    await settleCard(tester);
  });
}

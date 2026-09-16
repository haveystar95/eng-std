import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/dialogue_feed.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';

/// THE CONVERSATION SO FAR (work order SESSION-1c §2, canvas 33-7): the bubbles above a dialogue card are read off the
/// stage's answered cards — each line once, in the walk's order, none the current card draws itself; a chip answer is
/// the frame with that chip.
Map<String, dynamic> _raw() => jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;

/// The day with the dialogue cards up to [position] (exclusive) answered — [responses] by position.
SessionDay _dialogueAnsweredBefore(int position, {Map<int, Map<String, dynamic>> responses = const {}, Set<int> skipped = const {}}) {
  final raw = _raw();
  final stage = (raw['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == 'dialogue');
  for (final c in (stage['cards'] as List).cast<Map<String, dynamic>>()) {
    final p = c['position'] as int;
    if (p >= position) continue;
    c['result'] = skipped.contains(p) ? 'skipped' : 'passed';
    c['attempts'] = 1;
    c['response'] = responses[p];
  }
  return SessionDay.fromJson(raw);
}

void main() {
  List<SessionCard> dialogueOf(SessionDay day) => day.stageOf(PlanStage.dialogue)!.cards;
  SessionCard at(SessionDay day, int position) => dialogueOf(day).firstWhere((c) => c.position == position);

  // CATCHES: an empty feed after the first exchange, a partner line repeated above the card that shows it, and a
  // learner line the walk has not reached yet.
  test('the feed above x2: the whole first exchange; the current exchange is not repeated', () {
    final day = _dialogueAnsweredBefore(3);
    final feed = DialogueFeed.before(dialogueOf(day), at(day, 3));
    expect([for (final f in feed) f.line.ref], ['x1', 'x1b']);
    expect([for (final f in feed) f.own], [false, true]);
    expect(feed.last.mark, FeedMark.passed);
    expect(feed.first.line.textTarget, 'Where does it hurt: his upper back or his lower back?');

    // On dialogue_answer x2 (position 4) the partner's x2 is the card's own bubble — the feed stops at x1b.
    final answer = _dialogueAnsweredBefore(4);
    expect([for (final f in DialogueFeed.before(dialogueOf(answer), at(answer, 4))) f.line.ref], ['x1', 'x1b']);
  });

  test('the feed before the first card is empty; lines of one exchange stay together', () {
    final day = _dialogueAnsweredBefore(1);
    expect(DialogueFeed.before(dialogueOf(day), at(day, 1)), isEmpty);

    final late = _dialogueAnsweredBefore(13);
    final feed = DialogueFeed.before(dialogueOf(late), at(late, 13));
    // x7's partner reply is what dialogue_partner x7 (position 13) shows — not above it.
    expect([for (final f in feed) f.line.ref], ['x1', 'x1b', 'x2', 'x2b', 'x3', 'x3b', 'x4', 'x4b', 'x5', 'x6b', 'x6', 'x5b', 'x7b']);
    expect(feed.where((f) => f.line.ref == 'x5'), hasLength(1), reason: 'the asked line of the rescue is x5 — once');
  });

  // CATCHES: the answer after a rescue drawing the rescued partner line again under the slow repeat — the
  // conversation reading «Sorry, could you say that more slowly?» before the line it asks about (live pass, SESSION-1c).
  test('after «Didn\'t catch that» the partner line stays in its place, before the rescue; the answer draws only its own', () {
    final day = _dialogueAnsweredBefore(11);
    final answer = at(day, 11);
    final feed = DialogueFeed.before(dialogueOf(day), answer);
    expect([for (final f in feed) f.line.ref].sublist(8), ['x5', 'x6b', 'x6'], reason: 'x5 before the rescue, once');
    expect(DialogueFeed.partnerInFeed(feed, (answer.payload as DialogueAnswerPayload).partnerLine), isTrue);

    // Without a rescue of its line the answer draws its partner's bubble itself — and the feed does not.
    final plain = _dialogueAnsweredBefore(4);
    final plainFeed = DialogueFeed.before(dialogueOf(plain), at(plain, 4));
    expect(DialogueFeed.partnerInFeed(plainFeed, (at(plain, 4).payload as DialogueAnswerPayload).partnerLine), isFalse);
  });

  // CATCHES: a chip answer shown as the lesson's line («lower back») when the learner chose «neck».
  test('a chip answer is the frame with that chip, its native line and its sound; a skipped answer has no mark', () {
    final day = _dialogueAnsweredBefore(3, responses: {2: {'mode': 'chips', 'filler_index': 1}});
    final feed = DialogueFeed.before(dialogueOf(day), at(day, 3));
    final own = feed.firstWhere((f) => f.own);
    expect(own.line.textTarget, 'It hurts in his neck.');
    expect(own.line.textNative, 'У него болит шея.');
    expect(own.line.audio?.ref, 'p1.f2');

    final skipped = _dialogueAnsweredBefore(3, skipped: {2});
    expect(DialogueFeed.before(dialogueOf(skipped), at(skipped, 3)).firstWhere((f) => f.own).mark, FeedMark.none);
  });

  test('an exchange\'s pair from any card that carries it; an ask starts with the learner', () {
    final day = SessionDay.fromJson(_raw());
    final all = [for (final s in day.stages) ...s.cards];
    final x1 = DialogueFeed.pairOf(all, 'x1');
    expect(x1.partner?.textTarget, 'Where does it hurt: his upper back or his lower back?');
    expect(x1.own?.textTarget, 'It hurts in his lower back.');
    expect(x1.learnerFirst, isFalse);
    final x7 = DialogueFeed.pairOf(all, 'x7');
    expect(x7.own?.textTarget, 'Do we need an X-ray?');
    expect(x7.partner?.textTarget, 'No, an X-ray is not needed for a muscle strain.');
    expect(x7.learnerFirst, isTrue);
  });
}

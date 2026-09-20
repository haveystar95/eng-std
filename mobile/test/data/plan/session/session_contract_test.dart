import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/speech_match.dart';

/// THE DAY SESSION CONTRACT AT THE CLIENT'S DOOR (work order SESSION-1b §6): both server fixtures
/// (`backend2/docs/fixtures/day-doctor*.json` — the body of `GET /plans/{id}/days/1`, kept byte-for-byte by the
/// server) parse in full — 156 cards since FIX-2 (a frame with a window is said once, as «Скажи целиком», and no third
/// recognition fits after it), all 27 dealt kinds; a card of an unknown kind is skipped without an error.
Map<String, dynamic> _fixture(String name) =>
    jsonDecode(File('../backend2/docs/fixtures/$name.json').readAsStringSync()) as Map<String, dynamic>;

void main() {
  final intermediate = _fixture('day-doctor');
  final beginner = _fixture('day-doctor-beginner');

  test('both fixtures parse in full: 78 + 78 cards, none skipped', () {
    final a = SessionDay.fromJson(intermediate);
    final b = SessionDay.fromJson(beginner);

    int count(SessionDay d) => d.stages.fold(0, (n, s) => n + s.cards.length);
    expect(count(a), 78);
    expect(count(b), 78);
    expect(a.skipped + b.skipped, 0);
    expect(count(a) + count(b), 156);
  });

  // The clean doctor day carries every dealt kind but ONE: with «Скажи целиком» costing a series' worth of seconds
  // no frame gets a third recognition any more (FIX-2 §5), and on THIS scene the two that fit never open on
  // `phrase_slot` — the cycle's opener is seeded per frame. The kind is alive and dealt elsewhere; what this test
  // guards is that no kind of the enum went missing from the fixtures by accident.
  test('the two fixtures carry every dealt kind but phrase_slot, whose turn this scene never reaches', () {
    final kinds = <SessionKind>{
      for (final day in [SessionDay.fromJson(intermediate), SessionDay.fromJson(beginner)])
        for (final s in day.stages)
          for (final c in s.cards) c.kind,
    };
    expect(kinds, SessionKind.values.toSet()..remove(SessionKind.phraseSlot));
    expect(SessionKind.values, hasLength(27));
  });

  test('the envelope: stage, position, unit, source; the payload is its kind\'s model', () {
    final day = SessionDay.fromJson(intermediate);
    for (final s in day.stages) {
      var last = 0;
      for (final c in s.cards) {
        expect(c.stage, s.stage);
        expect(c.kind.stage, s.stage);
        expect(c.position, greaterThan(last));
        last = c.position;
        expect(c.id, isNotEmpty);
        expect(c.result, isNull);
        expect(c.payload.sceneId, isNotEmpty);
      }
    }
    final words = day.stageOf(PlanStage.words)!.cards;
    expect(words.first.kind, SessionKind.wordIntro);
    expect(words.first.unit.ref, 'v1');
    expect(words.first.unit.kind, PlanUnitKind.word);
    final listen = day.stageOf(PlanStage.listen)!.cards;
    expect(listen.first.unit.isDay, isTrue);
    expect(day.minutesLeft(PlanStage.words), 5);
    expect(day.minutesLeft(PlanStage.phrases), isNull);
  });

  test('word and phrase payloads — the contract\'s fields in their places', () {
    final day = SessionDay.fromJson(intermediate);
    final phrases = day.stageOf(PlanStage.phrases)!.cards;
    final intro = day.stageOf(PlanStage.words)!.cards.first.payload as WordIntroPayload;
    expect(intro.term.textTarget, 'lower back');
    expect(intro.usedIn!.termSpan, (start: 16, end: 26));
    expect(intro.termAudio!.url, contains('/plans/audio/'));

    final assemble = phrases.map((c) => c.payload).whereType<PhraseAssemblePayload>().first;
    expect(assemble.expectedWords, ['it', 'hurts', 'in', 'his']);
    expect(assemble.slotAt, 4);
    expect(assemble.fillerIndex, 1);

    // «Скажи целиком» (FIX-2 §5): the rounds are the SERVER'S, and the own-word round is always last.
    final whole = phrases.map((c) => c.payload).whereType<PhraseOtherSlotPayload>().first;
    expect(whole.speechMode, SpeechMode.repeat);
    expect([for (final r in whole.rounds) r.fillerIndex], [0, 1, 2]);
    expect(whole.rounds.first.expectedText, 'It hurts in his lower back.');
    expect(whole.rounds.first.taskNative, 'У него болит поясница.');
    expect(whole.ownRound.speechMode, SpeechMode.free);
    expect(whole.ownRound.examples, hasLength(3));
    expect(whole.frame.fillers, hasLength(3));
    // The line the judge reads, never shown: 32-7 has no partner line.
    expect(whole.partnerLine, isNotNull);

    final combine = phrases.map((c) => c.payload).whereType<PhraseCombinePayload>().single;
    expect(combine.frames, hasLength(3));
    expect(combine.correctFrame, 'p1');
    expect([for (final f in combine.frames) f.said?.textTarget], ['He will rest at home.', 'Do we need an X-ray?', 'It hurts in his lower back.']);
    expect(combine.frames.every((f) => f.said?.audio != null), isTrue);
  });

  // SESSION-1e contract: word_choose comes in both directions at any level — the card's `direction` decides, not
  // the plan's level.
  test('word_choose — both directions on both levels, the prompt sound only on term_to_native', () {
    for (final fixture in [intermediate, beginner]) {
      final chooses = SessionDay.fromJson(fixture).stageOf(PlanStage.words)!.cards.map((c) => c.payload).whereType<WordChoosePayload>().toList();
      expect([for (final c in chooses) c.direction], ['term_to_native', 'native_to_term']);
      expect([for (final c in chooses) c.correctOption!.text], ['температура', 'X-ray']);
      expect([for (final c in chooses) c.promptAudio != null], [true, false]);
    }
  });

  // SESSION-1e contract: word_listen — sound → translation, `direction: term_to_native`, native options; a day dealt
  // before it has no `direction` and spellings; any other direction is a broken card.
  // CATCHES: native options drawn as target spellings; a stored pre-1e day whose «By ear» cards stop loading.
  test('word_listen — native options with term_to_native; no direction — spellings; another direction — skipped', () {
    final listens = SessionDay.fromJson(intermediate).stageOf(PlanStage.words)!.cards.map((c) => c.payload).whereType<WordListenPayload>().toList();
    expect(listens, hasLength(2));
    expect(listens.every((l) => l.nativeOptions), isTrue);
    expect(listens.first.correctOption!.text, 'растяжение мышцы');

    final raw = jsonDecode(jsonEncode(intermediate)) as Map<String, dynamic>;
    final card = ((raw['stages'] as List).first['cards'] as List).cast<Map<String, dynamic>>().firstWhere((c) => c['kind'] == 'word_listen');
    final payload = card['payload'] as Map<String, dynamic>;
    payload.remove('direction');
    expect((SessionCard.fromJson(card)!.payload as WordListenPayload).nativeOptions, isFalse);
    payload['direction'] = 'native_to_term';
    expect(() => SessionCard.fromJson(card), throwsA(isA<SessionContractError>()));
  });

  // SESSION-1b′, item 1: a combination option is a whole sentence — `frames[].said`, otherwise the day's frame with
  // the filler said in the dialogue.
  test('the day\'s frame as a whole sentence — the intro\'s said line, otherwise the frame with its dialogue filler', () {
    final day = SessionDay.fromJson(intermediate);
    expect(day.frameSentence('p1'), 'It hurts in his lower back.');
    expect(day.frameSentence('p2'), 'It started three days ago.');
    expect(day.frameSentence('p5'), 'He will rest at home.');
    expect(day.frameSentence('p6'), 'Do we need an X-ray?');
    expect(day.frameSentence('p404'), isNull);
  });

  test('the day\'s word by its unit — what «By ear» reads without a file', () {
    final day = SessionDay.fromJson(intermediate);
    expect(day.termText('v4'), 'muscle strain');
    expect(day.termText('v8'), 'sick note');
    expect(day.termText('v404'), isNull);
  });

  // Canon (FIX-2 §2): the card carries a MODE, not a share, and the language's own lists come with the day.
  // CATCHES: a build that still reads `coverage_min`; a day whose `speech` block is dropped on the way in.
  test('the mode comes on the card, the language\'s lists come with the day', () {
    final day = SessionDay.fromJson(intermediate);
    final repeat = day.stageOf(PlanStage.words)!.cards.map((c) => c.payload).whereType<WordRepeatPayload>().first;
    expect(repeat.speechMode, SpeechMode.repeat);

    expect(day.speech.articles, {'a', 'an', 'the'});
    expect(day.speech.unstressed, contains('would'));
    expect(day.speech.unstressed, isNot(contains('not')), reason: '«not» flips the meaning — it is not unstressed');
    expect(day.speech.abbreviations, contains('p.m.'));
    expect(day.speech.numberWords['three'], '3');
    expect(day.speech.repeatMisses, 0);

    // A day without the block forgives nothing — an older server, or a reply that lost it.
    final raw = jsonDecode(jsonEncode(intermediate)) as Map<String, dynamic>;
    raw.remove('speech');
    expect(SessionDay.fromJson(raw).speech.articles, isEmpty);
  });

  test('an unknown kind — skipped without an error and without a card; listen_pairs too', () {
    final raw = jsonDecode(jsonEncode(intermediate)) as Map<String, dynamic>;
    final words = (raw['stages'] as List).first as Map<String, dynamic>;
    final cards = words['cards'] as List;
    final template = Map<String, dynamic>.from(cards.first as Map<String, dynamic>);
    cards.add({...template, 'id': 'ulid-future', 'position': 99, 'kind': 'word_teleport'});
    cards.add({...template, 'id': 'ulid-pairs', 'position': 100, 'kind': 'listen_pairs', 'payload': null});

    expect(SessionCard.fromJson({...template, 'kind': 'word_teleport'}), isNull);
    final day = SessionDay.fromJson(raw);
    expect(day.stageOf(PlanStage.words)!.cards, hasLength(24));
    expect(day.skipped, 2);
  });

  test('a known kind with a broken payload — that card is skipped, the rest intact', () {
    final raw = jsonDecode(jsonEncode(intermediate)) as Map<String, dynamic>;
    final words = (raw['stages'] as List).first as Map<String, dynamic>;
    ((words['cards'] as List).first as Map<String, dynamic>)['payload'] = {'scene_id': 'x'};
    final day = SessionDay.fromJson(raw);
    expect(day.stageOf(PlanStage.words)!.cards, hasLength(23));
    expect(day.skipped, 1);
  });
}

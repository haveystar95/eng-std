import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/data/plan/session/speech_match.dart';

import '../../../support/server_fixtures.dart';

/// THE DAY SESSION CONTRACT AT THE CLIENT'S DOOR (work order SESSION-1b §6): both server fixtures
/// (`day-doctor*.json` — the body of `GET /plans/{id}/days/1`, kept byte-for-byte by the server; `server_fixtures.dart`)
/// parse in full — 158 cards since FIX-2 (a frame with a window is said once, as «Скажи целиком», and no third
/// recognition fits after it), every dealt kind; a card of an unknown kind is skipped without an error.
Map<String, dynamic> _fixture(String name) => serverFixtureJson(name);

void main() {
  final intermediate = _fixture('day-doctor');
  final beginner = _fixture('day-doctor-beginner');

  test('both fixtures parse in full: 83 + 83 cards, none skipped', () {
    final a = SessionDay.fromJson(intermediate);
    final b = SessionDay.fromJson(beginner);

    int count(SessionDay d) => d.stages.fold(0, (n, s) => n + s.cards.length);
    // Both days are the same length now: the rounds of «Скажи целиком» are never cut (наряд FIX-3 §3), and the
    // stage's ceiling (900 с) holds every recognition of both levels.
    expect(count(a), 83);
    expect(count(b), 83);
    expect(a.skipped + b.skipped, 0);
    expect(count(a) + count(b), 166);
  });

  // The fixtures together carry EVERY dealt kind: `phrase_slot` opens a frame's series on the beginner day, whose
  // third recognitions fit under the stage's ceiling (DECISIONS п. 354) — the intermediate day's do not, and it has
  // none; the rehearsal's overview `recall_scenes` is dealt on the rehearsal only (CLIENT-CONV-1b). What this test
  // guards is that no kind of the enum went missing from the fixtures by accident.
  test('the fixtures carry every dealt kind between them', () {
    final kinds = <SessionKind>{
      for (final day in [intermediate, beginner, _fixture('day-review'), _fixture('day-rehearsal')].map(SessionDay.fromJson))
        for (final s in day.stages)
          for (final c in s.cards) c.kind,
    };
    expect(kinds, SessionKind.values.toSet());
    expect(SessionKind.values, hasLength(28));
  });

  // CLIENT-CONV-1b, BACK-TAILS-2 §3, §5–6: the system days as the e2e stand dealt them. A review — its cards of «Говорю
  // сам» kinds under the stage `repetition` («Повторение», кадр 37-2), no scene of its own; the rehearsal — the overview
  // (a day unit, envelope stage `recall`) and the retells of EVERY scene with their envelope stage `recall` too,
  // although 35-4 is a kind of «Говорю сам»: the envelope decides where a card is walked. The overview's scenes are
  // named as the plan names them.
  // CATCHES: review cards filed under `speak` (the review would open on a stage it does not have), a retell filed
  // under `speak`, an overview skipped as an unknown kind.
  test('the system days: a review\'s cards under «repetition»; the rehearsal\'s overview and retells under «recall»', () {
    final review = SessionDay.fromJson(_fixture('day-review'));
    expect(review.scene, isNull);
    expect([for (final s in review.stages) if (s.cards.isNotEmpty) s.stage], [PlanStage.repetition]);
    final cards = review.stageOf(PlanStage.repetition)!.cards;
    expect(cards, hasLength(7));
    expect(cards.map((c) => c.kind).toSet(), {SessionKind.speakAnswer});
    expect(cards.every((c) => c.stage == PlanStage.repetition), isTrue);
    expect(review.skipped, 0);

    final rehearsal = SessionDay.fromJson(_fixture('day-rehearsal'));
    expect(rehearsal.skipped, 0);
    final recall = rehearsal.stageOf(PlanStage.recall)!.cards;
    expect(recall, hasLength(10));
    expect(recall.first.kind, SessionKind.recallScenes);
    expect(recall.first.unit.isDay, isTrue);
    expect(recall.skip(1).every((c) => c.kind == SessionKind.speakRetell && c.stage == PlanStage.recall), isTrue);
    final overview = recall.first.payload as RecallScenesPayload;
    expect([for (final s in overview.scenes) s.titleNative], ['Запись к врачу', 'Приём у врача'], reason: 'names of the plan');
    expect([for (final s in overview.scenes) s.lines.length], [7, 7]);
    expect(overview.scenes.first.lines.first.textTarget, 'It hurts in his lower back.');
    expect(overview.audios, hasLength(14), reason: 'every own line has its sound');
  });

  // RULE (CLIENT-CONV-1c §9д): «Вспомнить» is the learner's own lines only — a partner's line is not drawn even if the
  // server still sends one. Whose a line is: its `speaker`, else the voice of its sound, else its ref (`x3` / `x3b`).
  // CATCHES: the partner's lines in the rehearsal's sheet (the stand before BACK-TAILS-2 sent them), a learner's line
  // lost to the filter.
  test('the rehearsal\'s overview keeps the learner\'s lines only, however the partner\'s line is marked', () {
    final json = _fixture('day-rehearsal');
    final card = ((json['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == 'recall')['cards'] as List)
        .cast<Map<String, dynamic>>()
        .first;
    final scene = ((card['payload'] as Map<String, dynamic>)['scenes'] as List).cast<Map<String, dynamic>>().first;
    final lines = (scene['lines'] as List).cast<Map<String, dynamic>>();
    lines.insertAll(0, [
      {'ref': 'x1', 'speaker': 'partner', 'text_target': 'Where does it hurt?', 'text_native': 'Где болит?'},
      {'ref': 'x1', 'audio': {'ref': 'x1', 'url': null, 'voice': 'partner'}, 'text_target': 'How long?', 'text_native': 'Как давно?'},
      {'ref': 'x2', 'text_target': 'Does it hurt now?', 'text_native': 'Сейчас болит?'},
    ]);
    final overview = SessionDay.fromJson(json).stageOf(PlanStage.recall)!.cards.first.payload as RecallScenesPayload;
    expect([for (final s in overview.scenes) s.lines.length], [7, 7]);
    expect(overview.scenes.first.lines.first.textTarget, 'It hurts in his lower back.');
    expect([for (final s in overview.scenes) for (final l in s.lines) l.ref].every((r) => r.endsWith('b')), isTrue);
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
    // BACK-TAILS-2 (CLIENT-CONV-1c §9а): every row carries its planned `minutes` — a stage ahead says them («Дальше ·
    // Фразы ≈ 12 мин» on 30-6); the current one keeps its remainder.
    expect(day.minutesLeft(PlanStage.words), 3);
    expect(day.minutesLeft(PlanStage.phrases), 14);
    List<Map<String, dynamic>> rows(Map<String, dynamic> json) =>
        ((json['window'] as Map<String, dynamic>)['stages'] as List).cast<Map<String, dynamic>>();
    final longer = _fixture('day-doctor');
    rows(longer).firstWhere((r) => r['stage'] == 'words')['minutes'] = 6;
    expect(SessionDay.fromJson(longer).minutesLeft(PlanStage.words), 3, reason: 'the current row\'s remainder, not its plan');
    // A server before it sent none — no number.
    final before = _fixture('day-doctor');
    for (final r in rows(before)) {
      r.remove('minutes');
    }
    expect(SessionDay.fromJson(before).minutesLeft(PlanStage.phrases), isNull);
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

    // «Скажи целиком» (FIX-2 §5, наряд FIX-3 §3): the rounds are the SERVER'S, and they are NEVER cut — the ladder
    // takes recognitions, never a round, and the own-word round is last.
    final whole = phrases.map((c) => c.payload).whereType<PhraseOtherSlotPayload>().firstWhere((p) => p.frame.ref == 'p1');
    expect(whole.speechMode, SpeechMode.repeat);
    expect([for (final r in whole.rounds) r.fillerIndex], [0, 1, 2], reason: 'круги не режутся');
    expect(whole.rounds.first.expectedText, 'It hurts in his lower back.');
    expect(whole.rounds.first.taskNative, 'У него болит поясница.');
    expect(whole.ownRound!.speechMode, SpeechMode.free);
    expect(whole.ownRound!.examples, hasLength(3));
    expect(whole.frame.fillers, hasLength(3), reason: 'каждое наполнение — свой круг');
    final kept = phrases.map((c) => c.payload).whereType<PhraseOtherSlotPayload>().firstWhere((p) => p.frame.ref == 'p6');
    expect([for (final r in kept.rounds) r.fillerIndex], [0, 1, 2]);
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

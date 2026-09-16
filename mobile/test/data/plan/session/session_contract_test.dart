import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';

/// КОНТРАКТ СЕССИИ ДНЯ НА ВХОДЕ КЛИЕНТА (наряд SESSION-1b, разд. 6): обе фикстуры сервера
/// (`backend2/docs/fixtures/day-doctor*.json` — тело `GET /plans/{id}/days/1`, сервер держит их байт-в-байт)
/// разбираются целиком — 150 карточек, все 28 раздаваемых видов; карточка незнакомого вида пропускается без
/// ошибки.
Map<String, dynamic> _fixture(String name) =>
    jsonDecode(File('../backend2/docs/fixtures/$name.json').readAsStringSync()) as Map<String, dynamic>;

void main() {
  final intermediate = _fixture('day-doctor');
  final beginner = _fixture('day-doctor-beginner');

  test('обе фикстуры разбираются целиком: 75 + 75 карточек, ни одна не пропущена', () {
    final a = SessionDay.fromJson(intermediate);
    final b = SessionDay.fromJson(beginner);

    int count(SessionDay d) => d.stages.fold(0, (n, s) => n + s.cards.length);
    expect(count(a), 75);
    expect(count(b), 75);
    expect(a.skipped + b.skipped, 0);
    expect(count(a) + count(b), 150);
  });

  test('в двух фикстурах встречаются все 28 раздаваемых видов', () {
    final kinds = <SessionKind>{
      for (final day in [SessionDay.fromJson(intermediate), SessionDay.fromJson(beginner)])
        for (final s in day.stages)
          for (final c in s.cards) c.kind,
    };
    expect(kinds, SessionKind.values.toSet());
    expect(SessionKind.values, hasLength(28));
  });

  test('конверт: этап, позиция, единица, источник; payload — модель своего вида', () {
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

  test('payload слов и фраз — поля контракта на своих местах', () {
    final day = SessionDay.fromJson(intermediate);
    final phrases = day.stageOf(PlanStage.phrases)!.cards;
    final intro = day.stageOf(PlanStage.words)!.cards.first.payload as WordIntroPayload;
    expect(intro.term.textTarget, 'lower back');
    expect(intro.usedIn!.termSpan, (start: 16, end: 26));
    expect(intro.termAudio!.url, contains('/plans/audio/'));

    final assemble = phrases.map((c) => c.payload).whereType<PhraseAssemblePayload>().first;
    expect(assemble.expectedWords, ['it', 'hurts', 'in', 'his']);
    expect(assemble.slotAt, 4);
    expect(assemble.fillerIndex, 0);

    final other = phrases.map((c) => c.payload).whereType<PhraseOtherSlotPayload>().first;
    expect(other.coverageMin, 0.7);
    expect(other.slotExpected, 'shoulder');

    final own = phrases.map((c) => c.payload).whereType<PhraseOwnSlotPayload>().first;
    expect(own.chips, hasLength(3));
    expect(own.partnerLine, isNotNull);

    final combine = phrases.map((c) => c.payload).whereType<PhraseCombinePayload>().single;
    expect(combine.frames, hasLength(3));
    expect(combine.correctFrame, 'p2');

    final choose = SessionDay.fromJson(beginner).stageOf(PlanStage.words)!.cards.map((c) => c.payload).whereType<WordChoosePayload>().single;
    expect(choose.termToNative, isTrue);
    expect(choose.promptAudio, isNotNull);
    expect(choose.correctOption!.text, 'рентген');
  });

  test('доли читаются числом: 1.0 и целое 1 — одно и то же', () {
    final day = SessionDay.fromJson(intermediate);
    final repeat = day.stageOf(PlanStage.words)!.cards.map((c) => c.payload).whereType<WordRepeatPayload>().first;
    expect(repeat.coverageMin, 1.0);

    final raw = jsonDecode(jsonEncode(intermediate)) as Map<String, dynamic>;
    final card = (raw['stages'] as List).first['cards'][2] as Map<String, dynamic>;
    expect(card['kind'], 'word_repeat');
    (card['payload'] as Map<String, dynamic>)['coverage_min'] = 1;
    final parsed = SessionCard.fromJson(card)!.payload as WordRepeatPayload;
    expect(parsed.coverageMin, 1.0);
  });

  test('незнакомый вид — пропуск без ошибки и без карточки; listen_pairs тоже', () {
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

  test('известный вид со сломанным payload — пропуск этой карточки, остальные целы', () {
    final raw = jsonDecode(jsonEncode(intermediate)) as Map<String, dynamic>;
    final words = (raw['stages'] as List).first as Map<String, dynamic>;
    ((words['cards'] as List).first as Map<String, dynamic>)['payload'] = {'scene_id': 'x'};
    final day = SessionDay.fromJson(raw);
    expect(day.stageOf(PlanStage.words)!.cards, hasLength(23));
    expect(day.skipped, 1);
  });
}

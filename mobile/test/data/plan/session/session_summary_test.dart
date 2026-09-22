import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_queue.dart';
import 'package:eng_std/data/plan/session/session_summary.dart';
import 'package:eng_std/features/plan/session/session_texts.dart';
import 'package:eng_std/l10n/app_localizations.dart';

import '../../../support/nbsp.dart';
import '../../../support/server_fixtures.dart';

/// THE NUMBERS OF THE SUMMARIES (наряды SESSION-1c, CLIENT-CONV-1c; кадры 30-6, 30-7) — read off the server's cards:
/// the three lines of a stage summary for every stage with cards (решение архитектора 22.09), and «comes back
/// tomorrow» of the day.
void main() {
  late AppLocalizations l;
  setUpAll(() async => l = await AppLocalizations.delegate.load(const Locale('ru')));

  Map<String, dynamic> raw([String name = 'day-doctor']) {
    final json = serverFixtureJson(name);
    return (json['data'] as Map<String, dynamic>?) ?? json;
  }

  List<Map<String, dynamic>> cardsOf(Map<String, dynamic> json, String stage) =>
      ((json['stages'] as List).cast<Map<String, dynamic>>().firstWhere((x) => x['stage'] == stage)['cards'] as List).cast<Map<String, dynamic>>();

  void answer(
    Map<String, dynamic> json,
    String stage,
    int position,
    String result, {
    int attempts = 1,
    bool returns = false,
    Map<String, dynamic>? response,
  }) {
    final c = cardsOf(json, stage).firstWhere((x) => x['position'] == position);
    c['result'] = result;
    c['attempts'] = attempts;
    c['returns'] = returns;
    if (response != null) c['response'] = response;
  }

  /// Every card of [stage] answered `passed` at the first attempt — the rest of a test then spoils what it needs.
  void passAll(Map<String, dynamic> json, String stage) {
    for (final c in cardsOf(json, stage)) {
      answer(json, stage, c['position'] as int, 'passed');
    }
  }

  List<String> linesOf(Map<String, dynamic> json, PlanStage stage, {String? role = 'Врач'}) {
    final queue = SessionQueue(SessionDay.fromJson(json).stages);
    return [for (final line in SessionTexts.stageLines(l, stage, SessionSummaries.stageTally(queue, stage), role: role)) line.text];
  }

  // RULE (30-6, решение архитектора 22.09): the title is the stage's own words and the server's minutes of the stage —
  // «Слова пройдены · 6 минут», «Слушаю и отвечаю — пройдено · 5 минут»; without minutes (a replay sends nothing) the
  // words alone, never «0 минут». The tail «пройдено · N минут» holds together and the dash stays with the name
  // (приёмка 22.09, третий заход) — `nbPassed`.
  // CATCHES: «Слова пройдены» on a stage that is not words (the fallback of the old 30-6), and «· 0 минут».
  test('30-6: the title of every stage — its words and the server\'s minutes', () {
    expect(SessionTexts.passed(l, PlanStage.words, 6), nbPassed('Слова пройдены · 6 минут'));
    expect(SessionTexts.passed(l, PlanStage.phrases, 5), nbPassed('Фразы пройдены · 5 минут'));
    expect(SessionTexts.passed(l, PlanStage.dialogue, 3), nbPassed('Диалог пройден · 3 минуты'));
    expect(SessionTexts.passed(l, PlanStage.listen, 5), nbPassed('Слушаю и отвечаю — пройдено · 5 минут'));
    expect(SessionTexts.passed(l, PlanStage.speak, 1), nbPassed('Говорю сам — пройдено · 1 минута'));
    expect(SessionTexts.passed(l, PlanStage.recall, 4), nbPassed('Вспомнить — пройдено · 4 минуты'));
    expect(SessionTexts.passed(l, PlanStage.repetition, 7), nbPassed('Повторение пройдено · 7 минут'));
    expect(SessionTexts.passed(l, PlanStage.speak, null), nbPassed('Говорю сам — пройдено'));
  });

  // RULE (30-6, the frame's own three lines): «Диалог» says «N реплик, M с первого раза» — its exchanges, the ones every
  // card of which passed at the first attempt without a hint; «сказал вслух K своих реплик» — its own lines passed BY
  // VOICE (a beginner's chip is not said aloud); «дважды переспросил — врач повторил медленнее» — its «Не понял»
  // exchanges walked, the scene's role in the line. Nothing to say — no line.
  // CATCHES: a lapse or a hint counted first-time, chips counted as said aloud, the rescue line with no rescue walked.
  test('30-6 «Диалог»: the frame\'s three lines off the stage\'s cards', () {
    final json = raw();
    passAll(json, 'dialogue');
    answer(json, 'dialogue', 2, 'passed', attempts: 2, response: {'mode': 'voice_hint'}); // x1: the second attempt
    answer(json, 'dialogue', 4, 'passed', response: {'mode': 'chips', 'filler_index': 0}); // x2: a chip, not aloud
    answer(json, 'dialogue', 6, 'passed', response: {'mode': 'voice_hint'});
    answer(json, 'dialogue', 8, 'hinted', response: {'mode': 'voice_hint'}); // x4: hinted — aloud, not first-time
    answer(json, 'dialogue', 11, 'passed', response: {'mode': 'voice_blind'});
    expect(linesOf(json, PlanStage.dialogue), nbAll([
      '8 реплик, 6 с первого раза',
      'сказал вслух 6 своих реплик', // four answers by voice and the two «спроси сам»; the chip is not aloud
      'переспросил — врач повторил медленнее',
    ]));

    // Two rescues read as the frame reads them; no rescue walked — two lines.
    final twice = raw();
    passAll(twice, 'dialogue');
    cardsOf(twice, 'dialogue').add({...cardsOf(twice, 'dialogue').firstWhere((c) => c['kind'] == 'dialogue_rescue'), 'id': 'ulid-rescue-2', 'position': 14});
    expect(linesOf(twice, PlanStage.dialogue).last, 'дважды переспросил — врач повторил медленнее');
    final none = raw();
    passAll(none, 'dialogue');
    answer(none, 'dialogue', 10, 'skipped');
    expect(linesOf(none, PlanStage.dialogue), hasLength(2));
  });

  // RULE (30-6, решение архитектора 22.09): every other stage — the frame's three slots with the stage's unit: its volume
  // and first tries, what comes back tomorrow («2 вернутся завтра» / «завтра ничего не вернётся»), its warm line.
  // Words and phrases count UNITS (a unit is first-time when all of its cards are), «Слушаю и отвечаю» its QUESTIONS,
  // «Говорю сам» its exchanges.
  // CATCHES: cards counted as words, a unit with one lapsed card counted first-time, the returns line counting cards,
  // walkthroughs counted as questions.
  test('30-6: words, phrases, listen and speak — volume, returns, the warm line', () {
    final json = raw();
    for (final stage in ['words', 'phrases', 'listen', 'speak']) {
      passAll(json, stage);
    }
    answer(json, 'words', 3, 'skipped', attempts: 2, returns: true); // v1: its voice card lapsed twice
    answer(json, 'words', 6, 'passed', returns: true); // the same unit's other card, marked by the server
    answer(json, 'words', 12, 'failed'); // v3: a choice wrong once
    answer(json, 'phrases', 18, 'passed', attempts: 2); // p4: the second attempt
    answer(json, 'listen', 3, 'failed');
    answer(json, 'listen', 7, 'failed');
    answer(json, 'speak', 2, 'hinted', attempts: 1, returns: true);
    answer(json, 'speak', 5, 'skipped', attempts: 2, returns: true);

    expect(linesOf(json, PlanStage.words), nbAll([
      '8 слов, 6 с первого раза',
      '1 вернётся завтра',
      'Эти слова ты теперь узнаёшь — дальше они встретятся во фразах',
    ]));
    expect(linesOf(json, PlanStage.phrases), nbAll([
      '6 фраз, 5 с первого раза',
      'завтра ничего не вернётся',
      'Фразы собраны и сказаны вслух — в диалоге они пригодятся',
    ]));
    expect(linesOf(json, PlanStage.listen), nbAll([
      '6 вопросов, 4 с первого раза',
      'завтра ничего не вернётся',
      'Реплики собеседника ты понимаешь на слух',
    ]), reason: '3 listen_question + 2 listen_predict + 1 listen_number — walkthroughs are no questions');
    expect(linesOf(json, PlanStage.speak), nbAll([
      '7 реплик, 5 с первого раза',
      '2 вернутся завтра',
      'Свои реплики ты сказал сам — дальше живой разговор',
    ]), reason: 'x3 carries two cards (the answer and the echo): seven exchanges');
  });

  // RULE (30-6 «Вспомнить», «Повторение»): the rehearsal's «Вспомнить» says «N реплик из S сцен» — its lines said aloud and
  // the scenes they come from (the sheet of lines is not a line), and nothing about tomorrow: a reminder returns nothing.
  // A review's «Повторение» (the stage id BACK-TAILS-2 gives it) counts its CARDS as dealt.
  // CATCHES: the overview counted as a line, scenes counted twice, a returns line on «Вспомнить».
  test('30-6: «Вспомнить» — lines and scenes, two lines; «Повторение» — cards', () {
    final rehearsal = raw('day-rehearsal');
    passAll(rehearsal, 'recall');
    expect(linesOf(rehearsal, PlanStage.recall), nbAll(['10 реплик из 2 сцен', 'Реплики на месте — дальше разговор целиком']));

    // The review as the server deals it: its cards under `repetition` (BACK-TAILS-2 §3).
    final review = raw('day-review');
    passAll(review, 'repetition');
    answer(review, 'repetition', 3, 'hinted');
    expect(linesOf(review, PlanStage.repetition), nbAll([
      '7 карточек, 6 с первого раза',
      'завтра ничего не вернётся',
      'Всё, что возвращалось, сказано ещё раз',
    ]));
  });

  // CATCHES: a unit counted twice (two cards marked), a `day` unit counted, and a sentence with nothing in it.
  test('the day returns: units marked `returns`, once each, by kind; the sentence; nothing — no sentence', () {
    final json = raw();
    answer(json, 'words', 3, 'failed', returns: true);
    answer(json, 'words', 5, 'failed', returns: true);
    answer(json, 'phrases', 2, 'failed', returns: true);
    answer(json, 'dialogue', 1, 'failed', returns: true);
    final day = SessionDay.fromJson(json);
    final returns = SessionSummaries.dayReturns(SessionQueue(day.stages));
    final words = day.stageOf(PlanStage.words)!.cards;
    final sameUnit = words.firstWhere((c) => c.position == 3).unit.ref == words.firstWhere((c) => c.position == 5).unit.ref;
    expect(returns, (words: sameUnit ? 1 : 2, phrases: 1, exchanges: 1));
    expect(SessionTexts.dayReturns(l, (words: 2, phrases: 2, exchanges: 1)), nb('5 карточек: 2 слова, 2 фразы и 1 реплика.'));
    expect(SessionTexts.dayReturns(l, (words: 1, phrases: 0, exchanges: 0)), nb('1 карточка: 1 слово.'));
    expect(SessionTexts.dayReturns(l, (words: 0, phrases: 0, exchanges: 0)), isNull);
    expect(SessionSummaries.dayReturns(SessionQueue(SessionDay.fromJson(raw()).stages)), (words: 0, phrases: 0, exchanges: 0));
  });
}

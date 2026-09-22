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

/// THE NUMBERS OF THE SUMMARIES (наряды SESSION-1c, CLIENT-CONV-1c, FIX-3 §7; кадры 30-6, 30-7).
///
/// THE VOLUME, «с первого раза» AND THE RETURNS ARE THE SERVER'S — `window.stages[].summary` ({done, total,
/// first_try, returns}); the phone counts none of them any more. What it still reads off the stage's cards is what
/// the contract does not say: how many scenes a rehearsal's lines come from, how many of the dialogue's own lines
/// were SAID ALOUD and how many rescues were walked.
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

  /// The stage's row of the day window — where its summary lives.
  Map<String, dynamic> windowStage(Map<String, dynamic> json, String stage) =>
      ((json['window'] as Map<String, dynamic>)['stages'] as List).cast<Map<String, dynamic>>().firstWhere((r) => r['stage'] == stage);

  /// The server's numbers of the stage — what the phone prints instead of counting.
  void summarize(Map<String, dynamic> json, String stage, {required int total, int done = 0, int firstTry = 0, int returns = 0}) {
    windowStage(json, stage)['summary'] = {'done': done, 'total': total, 'first_try': firstTry, 'returns': returns};
  }

  List<String> linesOf(Map<String, dynamic> json, PlanStage stage, {String? role = 'Врач'}) {
    final day = SessionDay.fromJson(json);
    final queue = SessionQueue(day.stages);
    final server = day.window?.stages.where((r) => r.stage == stage).firstOrNull?.summary;
    final tally = SessionSummaries.stageTally(queue, stage, server: server);
    return [for (final line in SessionTexts.stageLines(l, stage, tally, role: role)) line.text];
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

  // RULE (30-6, кадр; наряд FIX-3 §7): «Диалог» says «N реплик, M с первого раза» — THE SERVER'S numbers of the stage
  // (`summary.total`, `summary.first_try`); «сказал вслух K своих реплик» — its own lines passed BY VOICE (a beginner's
  // chip is not said aloud) and «дважды переспросил — врач повторил медленнее» — its «Не понял» exchanges walked, both
  // read off the stage's cards, because the contract does not say them. Nothing to say — no line.
  // CATCHES: the volume counted on the phone (it drifted from the window's own row), chips counted as said aloud, the
  // rescue line with no rescue walked.
  test('30-6 «Диалог»: числа сервера, «вслух» и «переспросил» — по карточкам', () {
    final json = raw();
    summarize(json, 'dialogue', total: 8, done: 8, firstTry: 6);
    passAll(json, 'dialogue');
    answer(json, 'dialogue', 2, 'passed', attempts: 2, response: {'mode': 'voice_hint'});
    answer(json, 'dialogue', 4, 'passed', response: {'mode': 'chips', 'filler_index': 0}); // a chip, not aloud
    answer(json, 'dialogue', 6, 'passed', response: {'mode': 'voice_hint'});
    answer(json, 'dialogue', 8, 'hinted', response: {'mode': 'voice_hint'});
    answer(json, 'dialogue', 11, 'passed', response: {'mode': 'voice_blind'});
    expect(linesOf(json, PlanStage.dialogue), nbAll([
      '8 реплик, 6 с первого раза',
      'сказал вслух 6 своих реплик', // four answers by voice and the two «спроси сам»; the chip is not aloud
      'переспросил — врач повторил медленнее',
    ]));

    // The phone does not recount: the same cards under the server's own numbers read the server's way.
    final other = raw();
    summarize(other, 'dialogue', total: 8, done: 5, firstTry: 2);
    passAll(other, 'dialogue');
    expect(linesOf(other, PlanStage.dialogue).first, nb('8 реплик, 2 с первого раза'));

    // Two rescues read as the frame reads them; no rescue walked — two lines.
    final twice = raw();
    summarize(twice, 'dialogue', total: 8, done: 8, firstTry: 8);
    passAll(twice, 'dialogue');
    cardsOf(twice, 'dialogue').add({...cardsOf(twice, 'dialogue').firstWhere((c) => c['kind'] == 'dialogue_rescue'), 'id': 'ulid-rescue-2', 'position': 14});
    expect(linesOf(twice, PlanStage.dialogue).last, 'дважды переспросил — врач повторил медленнее');
    final none = raw();
    summarize(none, 'dialogue', total: 8, done: 8, firstTry: 8);
    passAll(none, 'dialogue');
    answer(none, 'dialogue', 10, 'skipped');
    expect(linesOf(none, PlanStage.dialogue), hasLength(2));
  });

  // RULE (30-6, решение архитектора 22.09; наряд FIX-3 §7): every other stage — the frame's three slots with THE
  // SERVER'S numbers: its volume and first tries (`summary.total`, `summary.first_try`), what comes back tomorrow
  // (`summary.returns` — «2 вернутся завтра» / «завтра ничего не вернётся»), its warm line.
  // CATCHES: cards counted as words on the phone, returns counted off the cards, and a stage whose summary the server
  // did not send printing a made-up zero.
  test('30-6: слова, фразы, «слушаю» и «говорю сам» — объём, возвраты, тёплая строка', () {
    final json = raw();
    for (final stage in ['words', 'phrases', 'listen', 'speak']) {
      passAll(json, stage);
    }
    summarize(json, 'words', total: 8, done: 8, firstTry: 6, returns: 1);
    summarize(json, 'phrases', total: 6, done: 6, firstTry: 5);
    summarize(json, 'listen', total: 6, done: 6, firstTry: 4);
    summarize(json, 'speak', total: 7, done: 7, firstTry: 5, returns: 2);

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
    ]));
    expect(linesOf(json, PlanStage.speak), nbAll([
      '7 реплик, 5 с первого раза',
      '2 вернутся завтра',
      'Свои реплики ты сказал сам — дальше живой разговор',
    ]));

    // A stage the server sent no summary for says nothing about volume — the phone has no number of its own.
    final quiet = raw();
    passAll(quiet, 'words');
    windowStage(quiet, 'words').remove('summary');
    expect(linesOf(quiet, PlanStage.words), nbAll([
      'завтра ничего не вернётся',
      'Эти слова ты теперь узнаёшь — дальше они встретятся во фразах',
    ]));
  });

  // RULE (30-6 «Вспомнить», «Повторение»; наряд FIX-3 §7): the rehearsal's «Вспомнить» says «N реплик из S сцен» —
  // the server's count of lines and the scenes THEY COME FROM (the only number left to the phone: the contract does
  // not say it), and nothing about tomorrow — a reminder returns nothing. A review's «Повторение» counts the server's
  // cards.
  // CATCHES: lines counted on the phone, scenes counted twice, a returns line on «Вспомнить».
  test('30-6: «Вспомнить» — реплики и сцены, две строки; «Повторение» — карточки', () {
    final rehearsal = raw('day-rehearsal');
    summarize(rehearsal, 'recall', total: 10, done: 10, firstTry: 10);
    passAll(rehearsal, 'recall');
    expect(linesOf(rehearsal, PlanStage.recall), nbAll(['10 реплик из 2 сцен', 'Реплики на месте — дальше разговор целиком']));

    // The review as the server deals it: its cards under `repetition` (BACK-TAILS-2 §3).
    final review = raw('day-review');
    summarize(review, 'repetition', total: 7, done: 7, firstTry: 6);
    passAll(review, 'repetition');
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

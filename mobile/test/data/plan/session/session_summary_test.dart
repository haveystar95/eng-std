import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_queue.dart';
import 'package:eng_std/data/plan/session/session_summary.dart';
import 'package:eng_std/features/plan/session/session_texts.dart';
import 'package:eng_std/l10n/app_localizations.dart';

/// THE NUMBERS OF THE SUMMARIES (work order SESSION-1c §3–§5): «understood N of M» (34-8), «said N of M myself» (35-6)
/// and «comes back tomorrow» of the day (30-7) — read off the server's cards.
void main() {
  late AppLocalizations l;
  setUpAll(() async => l = await AppLocalizations.delegate.load(const Locale('ru')));

  Map<String, dynamic> raw() => jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;

  void answer(Map<String, dynamic> json, String stage, int position, String result, {bool returns = false}) {
    final s = (json['stages'] as List).cast<Map<String, dynamic>>().firstWhere((x) => x['stage'] == stage);
    final c = (s['cards'] as List).cast<Map<String, dynamic>>().firstWhere((x) => x['position'] == position);
    c['result'] = result;
    c['attempts'] = 1;
    c['returns'] = returns;
  }

  // CATCHES: the listening count taking walkthroughs as questions, a failed question counted right, the speaking count
  // missing the judge's `hinted`.
  test('understood: the listening questions answered right of all; spoke: passed or hinted of all speaking cards', () {
    final json = raw();
    answer(json, 'listen', 2, 'passed');
    answer(json, 'listen', 3, 'failed');
    answer(json, 'listen', 6, 'passed');
    answer(json, 'listen', 1, 'passed');
    answer(json, 'speak', 1, 'passed');
    answer(json, 'speak', 2, 'hinted');
    answer(json, 'speak', 3, 'skipped');
    final day = SessionDay.fromJson(json);
    final understood = SessionSummaries.understood(day.stageOf(PlanStage.listen)!.cards);
    expect(understood, (right: 2, total: 6), reason: '3 listen_question + 2 listen_predict + 1 listen_number');
    expect(l.planSessionUnderstoodCount(understood.right, understood.total), 'Понял 2 вопроса из 6');
    final spoke = SessionSummaries.spoke(day.stageOf(PlanStage.speak)!.cards);
    expect(spoke, (said: 2, total: 8));
    expect(l.planSessionSpokeCount(spoke.said, spoke.total), 'Сказал сам 2 реплики из 8');
    expect(l.planSessionSpokeCount(5, 6), 'Сказал сам 5 реплик из 6');
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
    expect(SessionTexts.dayReturns(l, (words: 2, phrases: 2, exchanges: 1)), '5 карточек: 2 слова, 2 фразы и 1 реплика.');
    expect(SessionTexts.dayReturns(l, (words: 1, phrases: 0, exchanges: 0)), '1 карточка: 1 слово.');
    expect(SessionTexts.dayReturns(l, (words: 0, phrases: 0, exchanges: 0)), isNull);
    expect(SessionSummaries.dayReturns(SessionQueue(SessionDay.fromJson(raw()).stages)), (words: 0, phrases: 0, exchanges: 0));
  });
}

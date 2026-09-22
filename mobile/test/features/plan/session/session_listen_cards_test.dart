import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart' show SessionWave;
import 'package:eng_std/features/plan/session/parts/session_bubbles.dart';
import 'package:eng_std/features/plan/session/parts/session_choice.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/nbsp.dart';
import '../../../support/session_harness.dart';

/// LISTEN AND ANSWER (work order SESSION-1c §3, canvas series 34): each kind from a fixture card — the player's order
/// of files and its marks, the questions without a copy, the review's marks, the prediction, the two tempos, the
/// number.
void main() {
  final day = sessionFixture('day-doctor');

  SessionCard listenAt(int position) => day.stageOf(PlanStage.listen)!.cards.firstWhere((c) => c.position == position);
  List<SessionResult> results(CardProbe probe) => [for (final a in probe.answers) a.result];

  group('34-1 listen_dialogue', () {
    // CATCHES: the files out of the visit's order, a text of the lines on the player, «Next» before the end, marks
    // placed by line instead of by exchange.
    testWidgets('the files play in the visit\'s order as one stream; eight marks; no text; the end — «Next» → passed', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(listenAt(1), probe, voice: voice, day: day));
      expect(find.text('Послушай разговор'), findsOneWidget);
      expect(find.text(nb('8 обменов')), findsOneWidget);
      expect(find.text('0:40'), findsOneWidget, reason: 'the whole length — total_ms 40 810');
      for (var i = 0; i < 8; i++) {
        expect(find.byKey(ValueKey('player-mark-$i')), findsOneWidget);
      }
      expect(find.text('Where does it hurt: his upper back or his lower back?'), findsNothing, reason: 'no text of the lines');

      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump();
      expect(voice.played, ['x1@1.0'], reason: 'the first line starts');
      // The first line started at 280 ms (the autoplay delay): the next one waits until 580.
      await tester.pump(const Duration(milliseconds: 270));
      expect(voice.played, ['x1@1.0'], reason: 'the lines stand 300 ms apart (SESSION-2a §5)');
      await tester.pump(const Duration(milliseconds: 20));
      expect(voice.played, ['x1@1.0', 'x1b@1.0']);
      await tester.pump(AppMotion.sessionVisitLineGap * 14);
      await tester.pump();
      expect(voice.played, [
        for (final ref in ['x1', 'x1b', 'x2', 'x2b', 'x3', 'x3b', 'x4', 'x4b', 'x5', 'x5b', 'x6b', 'x6', 'x7b', 'x7', 'x8b', 'x8']) '$ref@1.0',
      ]);
      expect(find.text('дослушал'), findsOneWidget);
      expect(results(probe), isEmpty);
      await tapText(tester, 'Дальше');
      expect(results(probe), [SessionResult.passed]);
      expect(probe.nexts, 1);
      await settleCard(tester);
    });

    // CATCHES: «In parts» that does not pause, a pause in the middle of an exchange, «Continue» that starts over.
    testWidgets('«In parts» — a pause after every exchange, «Continue» plays the next one', (tester) async {
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(listenAt(1), CardProbe(), voice: voice, day: day));
      await tester.tap(find.byKey(const ValueKey('player-by-parts')));
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump(AppMotion.sessionVisitLineGap);
      await tester.pump();
      expect(voice.played, ['x1@1.0', 'x1b@1.0']);
      expect(find.text('пауза · обмен 1'), findsOneWidget);
      await tapText(tester, 'Продолжить');
      await tester.pump(AppMotion.sessionVisitLineGap);
      await tester.pump();
      expect(voice.played, ['x1@1.0', 'x1b@1.0', 'x2@1.0', 'x2b@1.0']);
      expect(find.text('пауза · обмен 2'), findsOneWidget);
      await settleCard(tester);
    });

    // RULE (SESSION-2b §3, кадр 34-1): the main action follows the state — «Pause» while it plays, «Continue» on a
    // pause, «Next» at the end; «In parts» stands beside the first two and «Once more» only at the end. A pause takes
    // the line out of the air and «Continue» says that line again.
    // CATCHES: «Once more» offered as the main action while the visit plays, «Once more» on a pause (it started the
    // visit over by a fat finger), a «Pause» that lets the next line start anyway.
    testWidgets('«Pause» — the line stops and «Continue» says it again; «Once more» only at the end', (tester) async {
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(listenAt(1), CardProbe(), voice: voice, day: day));
      expect(dockEnabled(tester, 'Пауза'), isTrue, reason: 'the main action while the visit plays');
      expect(find.text('Ещё раз'), findsNothing, reason: 'nothing to replay yet');
      expect(find.byKey(const ValueKey('player-by-parts')), findsOneWidget);

      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump();
      expect(voice.played, ['x1@1.0']);
      await tapText(tester, 'Пауза');
      await tester.pump();
      expect(find.text('пауза · обмен 1'), findsOneWidget);
      expect(find.text('Ещё раз'), findsNothing, reason: 'on a pause only «Continue» and «In parts»');
      expect(find.byKey(const ValueKey('player-by-parts')), findsOneWidget);
      await tester.pump(AppMotion.sessionVisitLineGap * 4);
      expect(voice.played, ['x1@1.0'], reason: 'the visit stands: no next line while paused');

      await tapText(tester, 'Продолжить');
      await tester.pump();
      expect(voice.played, ['x1@1.0', 'x1@1.0'], reason: '«Continue» says the line the pause cut');
      await tester.pump(AppMotion.sessionVisitLineGap * 20);
      await tester.pump();
      expect(find.text('дослушал'), findsOneWidget);
      expect(find.byKey(const ValueKey('player-again')), findsOneWidget, reason: '«Once more» at the end');
      expect(find.byKey(const ValueKey('player-by-parts')), findsNothing);
      expect(dockEnabled(tester, 'Дальше'), isTrue);
      await settleCard(tester);
    });

    // RULE (SESSION-2b §3, кадр 34-1): the two roles are ONE PAIR — the circles overlap under a single caption
    // «the receptionist and you», and the brass ring stands on whoever speaks.
    // CATCHES: two captions under two portraits (the old layout), a ring on both at once or on nobody.
    testWidgets('the pair of faces: one caption, the ring on the one that speaks', (tester) async {
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(listenAt(1), CardProbe(), voice: voice, day: day));
      expect(find.text('регистратор и ты'), findsOneWidget);
      expect(find.text('ты'), findsNothing, reason: 'one caption for the pair, not a label per circle');
      expect(find.byKey(const ValueKey('player-role-partner')), findsOneWidget);
      expect(find.byKey(const ValueKey('player-role-learner')), findsOneWidget);

      Color? ring(String key) => (tester
                  .widget<Container>(find.descendant(of: find.byKey(ValueKey(key)), matching: find.byType(Container)).first)
                  .decoration!
              as BoxDecoration)
          .boxShadow
          ?.first
          .color;
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump();
      expect(ring('player-role-partner'), AppColors.sessionBrassRing, reason: 'the partner speaks first');
      expect(ring('player-role-learner'), isNull);
      await tapText(tester, 'Пауза');
      await settleCard(tester);
    });
  });

  group('34-2 listen_question', () {
    // CATCHES: a sound on the question, a wrong answer that leaves by itself (the review would come before it was seen).
    testWidgets('the question from memory; correct — passed; wrong — failed, final, «Next»', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(listenAt(2), probe, voice: voice));
      expect(find.text('Что ты понял?'), findsOneWidget);
      expect(find.text('Что болит у ребёнка?'), findsOneWidget);
      expect(find.text('по памяти · звука нет'), findsOneWidget);
      await tapText(tester, 'Поясница');
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);
      expect(probe.nexts, 1);
      expect(voice.played, isEmpty);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(listenAt(2), wrong));
      await tapText(tester, 'Шея');
      expect(results(wrong), [SessionResult.failed]);
      await settleCard(tester);
      expect(wrong.nexts, 0);
      await tapText(tester, 'Дальше');
      expect(wrong.nexts, 1);
    });
  });

  group('34-3 listen_review', () {
    // CATCHES: a review without the whole visit, the answers' places unmarked, the right and the missed marked alike.
    testWidgets('the whole visit with translations; the answer places — sage where right, brass where missed; «Next» → passed', (tester) async {
      final json = sessionFixtureJson('day-doctor');
      final listen = (json['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == 'listen');
      for (final c in (listen['cards'] as List).cast<Map<String, dynamic>>()) {
        if (c['kind'] != 'listen_question') continue;
        c['result'] = (c['unit'] as Map)['ref'] == 'L2' ? 'failed' : 'passed';
        c['attempts'] = 1;
      }
      final answered = sessionDayOf(json);
      final review = answered.stageOf(PlanStage.listen)!.cards.firstWhere((c) => c.kind == SessionKind.listenReview);
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(review, probe, day: answered));
      expect(find.text('Где это прозвучало'), findsOneWidget);
      expect(find.text('Только если через неделю ещё будет болеть.'), findsOneWidget);
      expect(find.byKey(const ValueKey('review-x6b')), findsOneWidget, reason: 'the learner\'s lines too');
      // L1 — right, a span in x1b; L2 — missed, the whole x5; L3 — right, the whole x8.
      expect(find.byKey(const ValueKey('mark-sage-16')), findsOneWidget);
      final marks = tester.widgetList<SessionMarkedText>(find.byType(SessionMarkedText)).toList();
      final x5 = marks.firstWhere((m) => m.text.startsWith('It looks like'));
      expect(x5.marks.single.look, MarkLook.brass);
      expect((x5.marks.single.start, x5.marks.single.end), (0, x5.text.length));
      final x8 = marks.firstWhere((m) => m.text.startsWith('Only if'));
      expect(x8.marks.single.look, MarkLook.sage);
      expect(marks.where((m) => m.marks.isEmpty), hasLength(13));
      await tapText(tester, 'Дальше');
      expect(results(probe), [SessionResult.passed]);
      await settleCard(tester);
    });

    // CATCHES: the whole visit measured short (a bubble measured at the row's 296 instead of its 274 cap) and cut
    // instead of scrolling.
    testWidgets('taller than the screen — the whole visit scrolls to its last line, nothing cut', (tester) async {
      final review = day.stageOf(PlanStage.listen)!.cards.firstWhere((c) => c.kind == SessionKind.listenReview);
      await pumpCard(tester, probeEnv(review, CardProbe(), day: day), size: const Size(390, 844));
      expect(tester.takeException(), isNull);
      final lines = (review.payload as ListenReviewPayload).lines;
      final last = find.byKey(ValueKey('review-${lines.last.ref}'));
      await tester.drag(find.byType(CustomScrollView), const Offset(0, -20000));
      await tester.pump(const Duration(seconds: 1));
      expect(tester.takeException(), isNull);
      expect(tester.getRect(last).bottom, lessThan(844), reason: 'the last line reached');
      await settleCard(tester);
    });
  });

  group('34-5 listen_predict', () {
    // RULE (SESSION-2b §3, кадр 34-5, контракт BACK-TAILS-1 §1.2): the three options are LINES — before the answer
    // the card only lets them be heard, no text at all; a tap plays, a second tap on a HEARD one marks it, and «This
    // is the answer» is active only then. After the answer every sheet opens both texts and the right one is marked.
    // CATCHES: texts of the options shown before the answer (the exercise becomes reading), «This is the answer»
    // active on a sheet that was never played, and a mark set by the first tap.
    // ПРАВИЛО (кадр 34-5, приёмка снимков CLIENT-CONV-1a): «играет / пауза» — в контурном кружке 44 на плашке озвучки:
    // заливка фона, контур чернил 22 %; внутри треугольник, пока плашка молчит (две полосы — пока играет).
    // ЛОВИТ: голый треугольник без кружка — 34-5 до приёмки.
    testWidgets('34-5: «играет/пауза» — в контурном кружке 44', (tester) async {
      final voice = QuietVoice();
      final card = listenAt(6);
      final options = (card.payload as ListenPredictPayload).options;
      await pumpCard(tester, probeEnv(card, CardProbe(), voice: voice));
      for (final o in options) {
        final circle = find.byKey(ValueKey('plate-play-${o.audio!.ref}'));
        expect(tester.getSize(circle), const Size(44, 44));
        final box = tester.widget<Container>(find.descendant(of: circle, matching: find.byType(Container)).first).decoration! as BoxDecoration;
        expect(box.shape, BoxShape.circle);
        expect(box.color, AppColors.ground, reason: 'заливка фона');
        expect((box.border! as Border).top.color, AppColors.markerOutline, reason: 'контур чернил 22 %');
        expect(tester.widget<SessionPlayCircle>(circle).playing, isFalse);
      }
      await settleCard(tester);
    });

    testWidgets('34-5: кнопка активна только после прослушивания выбранного', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      final card = listenAt(6);
      final options = (card.payload as ListenPredictPayload).options;
      expect(options, hasLength(3));
      await pumpCard(tester, probeEnv(card, probe, voice: voice));
      expect(find.text('Послушай и выбери ответ'), findsOneWidget);
      expect(find.text('Слушай целиком — ответ один'), findsOneWidget);
      expect(find.text('Что прозвучит в ответ?'), findsNothing, reason: 'вопрос ушёл в задание — сверху стоит своя реплика');
      expect(find.byKey(const ValueKey('predict-own-wave')), findsOneWidget);
      for (final o in options) {
        expect(find.text(o.textTarget), findsNothing, reason: 'no English before the answer');
        expect(find.text(o.textNative), findsNothing, reason: 'and no translation either');
      }
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['x7b@1.0'], reason: 'the learner\'s own question sounds on open');
      expect(dockEnabled(tester, 'Это ответ'), isFalse);

      final correct = options.firstWhere((o) => o.id == (card.payload as ListenPredictPayload).correct);
      await tester.tap(find.byKey(ValueKey('option-${correct.id}')));
      await tester.pump();
      expect(voice.played.last, '${correct.audio!.ref}@1.0', reason: 'the first tap plays');
      expect(dockEnabled(tester, 'Это ответ'), isFalse, reason: 'heard, but not marked yet');

      await tester.tap(find.byKey(ValueKey('option-${correct.id}')));
      await tester.pump();
      expect(dockEnabled(tester, 'Это ответ'), isTrue, reason: 'the second tap marks the heard line');
      // ПРАВИЛО НАРЯДА CLIENT-CONV-1a (кадр 34-5): длительность стоит на каждой плашке, и волна у
      // разных вариантов разная — плашки различимы на глаз, пока текста нет.
      // ЛОВИТ: одну и ту же волну на трёх плашках и плашку без длительности.
      final waves = {
        for (final o in options) SessionSoundPlate.waveOf(o.audio!.ref).join(','),
      };
      expect(waves, hasLength(3), reason: 'у каждого файла своя волна');
      expect(find.text(SessionSoundPlate.clock(correct.audio!.durationMs!)), findsWidgets);
      expect(results(probe), isEmpty, reason: 'nothing is sent until the button');

      await tapText(tester, 'Это ответ');
      await tester.pump();
      expect(results(probe), [SessionResult.passed]);
      for (final o in options) {
        expect(find.text(o.textTarget), findsOneWidget, reason: 'every sheet opens after the answer');
        expect(find.text(o.textNative), findsOneWidget);
      }
      await settleCard(tester);
      expect(probe.nexts, 1);
    });

    // ПРАВИЛО (кадр 34-5, §1.8 отчёта 1a): своя реплика — ШИРОКИЙ пузырь справа, до 312 (у ленты — 274): до ответа в нём
    // волна из восемнадцати полос и «прослушать» 44 в бумажном контуре 55 %; после ответа — текст и перевод, «прослушать»
    // остаётся и играет свою реплику.
    // ЛОВИТ: узкий пузырь общей ширины и кружок без контура — 34-5 до приёмки.
    testWidgets('34-5: свой пузырь широкий — волна и «прослушать» до ответа, текст и перевод после', (tester) async {
      final voice = QuietVoice();
      final card = listenAt(6);
      final p = card.payload as ListenPredictPayload;
      await pumpCard(tester, probeEnv(card, CardProbe(), voice: voice));
      await settleCard(tester);
      final bubble = find.byKey(const ValueKey('predict-own'));
      expect(tester.widget<SessionBubble>(bubble).maxWidth, 312);
      expect(find.byKey(const ValueKey('predict-own-text')), findsNothing, reason: 'текст закрыт до ответа');
      final wave = find.byKey(const ValueKey('predict-own-wave'));
      expect(tester.widget<SessionWave>(wave).heights, hasLength(18));
      final listen = find.byKey(const ValueKey('predict-own-listen'));
      expect(tester.getSize(listen), const Size(44, 44));
      final circle = tester.widget<Container>(find.descendant(of: listen, matching: find.byType(Container)).first).decoration! as BoxDecoration;
      expect((circle.border! as Border).top.color, AppColors.sessionOwnListenOutline);
      final plate = tester.getRect(find.byKey(ValueKey('option-${p.options.first.id}')));
      expect(tester.getRect(bubble).right, moreOrLessEquals(plate.right, epsilon: 0.5), reason: 'справа, по краю поля');
      expect(tester.getRect(bubble).width, greaterThan(kSessionBubbleMax - 60), reason: 'волна держит пузырь широким');

      final correct = p.options.firstWhere((o) => o.id == p.correct);
      await tester.tap(find.byKey(ValueKey('option-${correct.id}')));
      await tester.pump();
      await tester.tap(find.byKey(ValueKey('option-${correct.id}')));
      await tester.pump();
      await tapText(tester, 'Это ответ');
      await tester.pump();
      expect(find.byKey(const ValueKey('predict-own-text')), findsOneWidget, reason: 'после ответа — текст');
      expect(find.byKey(const ValueKey('predict-own-wave')), findsNothing);
      expect(tester.getRect(bubble).width, lessThanOrEqualTo(312));
      final before = voice.played.length;
      await tester.tap(listen);
      await tester.pump();
      expect(voice.played, hasLength(before + 1), reason: '«прослушать» играет свою реплику и после ответа');
      expect(voice.played.last, 'x7b@1.0');
      await settleCard(tester);
    });

    // CATCHES: a wrong answer that leaves by itself, and a card that lets a second answer through.
    testWidgets('a wrong line — failed, the texts open, «Next» by hand', (tester) async {
      final probe = CardProbe();
      final card = listenAt(6);
      final p = card.payload as ListenPredictPayload;
      final wrong = p.options.firstWhere((o) => o.id != p.correct);
      await pumpCard(tester, probeEnv(card, probe));
      await tester.tap(find.byKey(ValueKey('option-${wrong.id}')));
      await tester.pump();
      await tester.tap(find.byKey(ValueKey('option-${wrong.id}')));
      await tester.pump();
      await tapText(tester, 'Это ответ');
      await tester.pump();
      expect(results(probe), [SessionResult.failed]);
      expect(find.text(p.options.firstWhere((o) => o.id == p.correct).textTarget), findsOneWidget);
      await settleCard(tester);
      expect(probe.nexts, 0, reason: 'a wrong answer waits for «Next»');
      await tapText(tester, 'Дальше');
      expect(probe.nexts, 1);
    });
  });

  group('34-6 listen_pace', () {
    // CATCHES: the normal tempo shown with its text, the slow tempo without it, a pace card that grades.
    testWidgets('0.75× with the text, then 1.0× without it; «slowly» goes back; «Got it» → passed', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(listenAt(8), probe, voice: voice));
      expect(find.text('А теперь в обычном темпе'), findsOneWidget);
      expect(find.text(nb('МЕДЛЕННО · 0.75×')), findsOneWidget);
      expect(find.text('Is the pain sharp, or more of a dull ache?'), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 250));
      expect(voice.played, ['x3@0.75', 'x3@1.0']);
      expect(find.text('В ОБЫЧНОМ ТЕМПЕ'), findsOneWidget);
      expect(find.text('текст закрыт'), findsOneWidget);
      expect(find.text('Is the pain sharp, or more of a dull ache?'), findsNothing);

      await tester.tap(find.byKey(const ValueKey('pace-slowly')));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 250));
      expect(voice.played.last, 'x3@0.75');
      expect(find.text('Is the pain sharp, or more of a dull ache?'), findsOneWidget);

      await tapText(tester, 'Понял');
      expect(results(probe), [SessionResult.passed]);
      expect(probe.nexts, 1);
      await settleCard(tester);
    });
  });

  group('34-7 listen_number', () {
    // CATCHES: the line's text shown before the answer, the number not marked after it.
    testWidgets('the line sounds, text closed; after the answer the text opens with the number marked', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(listenAt(9), probe, voice: voice));
      expect(find.text('Поймай число'), findsOneWidget);
      expect(find.byKey(const ValueKey('number-wave')), findsOneWidget);
      expect(find.byType(SessionMarkedText), findsNothing);
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['x8@1.0']);
      await tapText(tester, 'Через неделю');
      expect(results(probe), [SessionResult.passed]);
      await tester.pump(const Duration(milliseconds: 250));
      final text = tester.widget<SessionMarkedText>(find.byType(SessionMarkedText));
      expect(text.text, 'Only if it still hurts after one week.');
      expect(text.marks.single, (start: 29, end: 32, look: MarkLook.sage));
      expect(text.text.substring(29, 32), 'one');
      await settleCard(tester);

      final wrong = CardProbe();
      await pumpCard(tester, probeEnv(listenAt(9), wrong));
      // The options are values now (BACK-TAILS-1 §1.3): a number beside numbers, a time beside times.
      await tapText(tester, 'Два дня');
      expect(results(wrong), [SessionResult.failed]);
      await tester.pump(const Duration(milliseconds: 250));
      expect(tester.widget<SessionMarkedText>(find.byType(SessionMarkedText)).marks.single.look, MarkLook.brass);
      await settleCard(tester);
    });
  });
}

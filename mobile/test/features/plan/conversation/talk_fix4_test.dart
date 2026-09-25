import 'dart:async';

import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/app_identity.dart';
import 'package:eng_std/data/app_version.dart';
import 'package:eng_std/data/audio_mixer.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/models.dart' show AppUser;
import 'package:eng_std/data/plan/conversation/conversation_models.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/plan_store.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/data/token_store.dart';
import 'package:eng_std/features/plan/conversation/conversation_controller.dart';
import 'package:eng_std/features/plan/conversation/talk_summary.dart';
import 'package:eng_std/features/plan/session/session_controller.dart';
import 'package:eng_std/features/plan/session/session_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/day_window_harness.dart' show RecordingLines;
import '../../../support/nbsp.dart';
import '../../../support/plan_goldens.dart' show planFrom;
import '../../../support/rehearsal_run.dart';
import '../../../support/server_fixtures.dart';
import '../../../support/session_harness.dart' show SilentRecognizer, enterHeard;
import '../../../support/talk_harness.dart';

/// THE TALK ACROSS SCENES, ITS PLATES, ITS HINT AND ITS SUMMARY — the canon of наряд CLIENT-FIX-4 (кадры 39-1, 39-1b,
/// 37-5b, 37-7…37-12b) on the real screens, over the server's own answers of the live rehearsal of FIX-4b
/// ([rehearsalTalk]): the receptionist's scene («Запись к врачу», four targets) and the doctor's («Приём у врача»,
/// three), the goodbye and the greeting in one answer, «almost» on the move of turn 6, `hints.target` on turn 7,
/// «Ещё вспомнил» on turn 15.
void main() {
  final opened = rehearsalTalk(RehearsalStep.opened);
  final scene1 = opened.scenes[0].sceneId;
  final scene2 = opened.scenes[1].sceneId;

  Finder byKey(String key) => find.byKey(ValueKey(key));
  Finder turn(int index) => byKey('turn-$index');
  Finder chip(TalkTarget t) => byKey('talk-construction-${t.sceneId}-${t.ref}');
  final row = byKey('talk-constructions');
  final plate = byKey('talk-hint-plate');

  /// How many plates the row holds — it builds only the ones on screen, so it is counted by its list.
  int platesOnRow(WidgetTester tester) =>
      tester.widget<ListView>(find.descendant(of: row, matching: find.byType(ListView))).childrenDelegate.estimatedChildCount ?? 0;

  /// The row's plates, one by one — the row scrolls sideways and builds only what it shows.
  Future<void> expectOnRow(WidgetTester tester, Iterable<TalkTarget> targets) async {
    for (final t in targets) {
      await tester.dragUntilVisible(chip(t), row, const Offset(-120, 0));
      await tester.pump();
      expect(chip(t), findsOneWidget, reason: 'в ряду: ${t.key}');
    }
  }

  group('39-1 · переход между сценами', () {
    // RULE (§1, 39-1): the answer that closed scene 1 brings the receptionist's goodbye (`end`) and the doctor's greeting
    // (`start`) together. The goodbye is said and the transition card rises under it — the next scene's photo, «СЦЕНА 2
    // ИЗ 2», its name, its role, its constructions and «Продолжить»; the row hides, the microphone is dimmed with no
    // caption and no «Не понял», the strip still names scene 1. The greeting is neither shown nor said until
    // «Продолжить»: then the card folds into the divider «СЦЕНА 2 · ПРИЁМ У ВРАЧА · ВРАЧ», the strip turns to the doctor,
    // and 300 ms later the doctor's line stands and sounds, the row holds scene 2's constructions, the hint stands under
    // the line.
    // CATCHES: the two roles run together as one ribbon (the live pass on (20)), the greeting said over the card, a
    // card that never lets the talk go on, the old scene's plates in the new scene's row.
    testWidgets('после прощания — карточка перехода; приветствие новой роли — только после «Продолжить»', (tester) async {
      final change = rehearsalTalk(RehearsalStep.sceneChange);
      final probe = TalkProbe()..documents.addAll([rehearsalTalk(RehearsalStep.almostSaid), change]);
      final stand = await pumpTalk(tester, probe);
      await finishLine(tester, stand);
      unawaited(stand.talk.say(rehearsalMove(RehearsalStep.sceneChange)!));
      await tester.pump();
      await tester.pump();

      final goodbye = change.turns[change.turns.length - 2];
      final greeting = change.turns.last;
      expect((goodbye.sceneEvent, greeting.sceneEvent), (TalkSceneEvent.end, TalkSceneEvent.start));
      expect(stand.talk.phase, TalkPhase.sceneChange);
      expect(stand.voice.played.last, 'talk-${goodbye.index}', reason: 'прощание звучит');
      expect(turn(goodbye.index), findsOneWidget);
      expect(turn(greeting.index), findsNothing, reason: 'приветствие ждёт «Продолжить»');

      final card = byKey('talk-scene-card');
      expect(card, findsOneWidget);
      expect(tester.getRect(card).top, greaterThan(tester.getRect(turn(goodbye.index)).bottom), reason: 'под прощанием');
      expect(find.descendant(of: card, matching: find.text(nb('СЦЕНА 2 ИЗ 2'))), findsOneWidget);
      expect(find.descendant(of: card, matching: find.text('Приём у врача')), findsOneWidget);
      expect(find.descendant(of: card, matching: find.text('врач')), findsOneWidget);
      final next = change.targetsOf(scene2);
      expect(next, hasLength(3));
      for (final t in next) {
        expect(find.descendant(of: card, matching: byKey('talk-entry-target-${t.sceneId}-${t.ref}')), findsOneWidget, reason: t.ref);
      }
      expect(find.descendant(of: card, matching: byKey('talk-scene-continue')), findsOneWidget, reason: 'одно действие');
      expect(row, findsNothing, reason: 'ряд скрыт, пока переход не принят');
      expect(byKey('talk-caption'), findsNothing);
      expect(byKey('talk-rescue'), findsNothing);
      expect(find.text('Запись к врачу · регистратор'), findsOneWidget, reason: 'полоса сцены ещё старая');

      // The goodbye said — the card still waits.
      await finishLine(tester, stand);
      expect(stand.talk.phase, TalkPhase.sceneChange);
      expect(turn(greeting.index), findsNothing);

      await tester.tap(byKey('talk-scene-continue'));
      await tester.pump();
      expect(byKey('talk-scene-card'), findsNothing);
      expect(byKey('talk-scene-divider-$scene2'), findsOneWidget);
      expect(find.text('СЦЕНА 2 · ПРИЁМ У ВРАЧА · ВРАЧ'), findsOneWidget);
      expect(find.text('Приём у врача · врач'), findsOneWidget, reason: 'полоса сцены — новая');
      expect(turn(greeting.index), findsNothing, reason: 'первая реплика — через 300 мс');

      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump();
      expect(turn(greeting.index), findsOneWidget);
      expect(stand.voice.played.last, 'talk-${greeting.index}', reason: 'звучит голос новой роли — файл сервера');
      expect(tester.getRect(turn(greeting.index)).top, greaterThan(tester.getRect(byKey('talk-scene-divider-$scene2')).bottom));
      expect(platesOnRow(tester), 3, reason: 'в ряду — только сцена 2');
      await expectOnRow(tester, change.targetsOf(scene2));

      await finishLine(tester, stand);
      expect(stand.talk.phase, TalkPhase.yourTurn);
      expect(find.descendant(of: plate, matching: find.text(change.hints.sentence!)), findsOneWidget);
      expect(tester.getRect(plate).top, greaterThan(tester.getRect(turn(greeting.index)).bottom), reason: 'под репликой новой роли');
      await settleTalk(tester);
    });

    // RULE (§1): a talk re-entered on that boundary (GET after a restart — the last line is the greeting, and the
    // learner has said nothing since) stands on the transition card again, and the goodbye is not said a second time.
    // CATCHES: the greeting played straight after a restart with no card, and the goodbye repeated on every re-entry.
    testWidgets('восстановление на границе — снова карточка, прощание не повторяется', (tester) async {
      final change = rehearsalTalk(RehearsalStep.sceneChange);
      final stand = await pumpTalk(tester, TalkProbe()..documents.add(change));
      expect(stand.talk.phase, TalkPhase.sceneChange);
      expect(stand.voice.played, isEmpty, reason: 'прощание уже звучало');
      expect(byKey('talk-scene-card'), findsOneWidget);
      expect(turn(change.turns.last.index), findsNothing);
      await tester.tap(byKey('talk-scene-continue'));
      await tester.pump(const Duration(milliseconds: 300));
      await tester.pump();
      expect(turn(change.turns.last.index), findsOneWidget);
      await finishLine(tester, stand);
      await settleTalk(tester);
    });

    // RULE (§1): every boundary the talk has passed stands in the ribbon as its divider — right before the next role's
    // greeting; the talk's very first line (a `start` too) is no boundary.
    // CATCHES: a re-read talk that runs the two scenes together again, and a divider over the first line.
    testWidgets('прошлые границы — разделителями; перед первой репликой разделителя нет', (tester) async {
      final talk = rehearsalTalk(RehearsalStep.secondScene);
      final stand = await pumpTalk(tester, TalkProbe()..documents.add(talk));
      await finishLine(tester, stand);
      final start = talk.turns.firstWhere((t) => t.sceneEvent == TalkSceneEvent.start && t.index > 1);
      final divider = byKey('talk-scene-divider-$scene2');
      await tester.ensureVisible(divider);
      await tester.pump();
      expect(divider, findsOneWidget);
      expect(byKey('talk-scene-divider-$scene1'), findsNothing, reason: 'первую сцену открыл вход 37-5');
      expect(byKey('talk-scene-card'), findsNothing);
      expect(tester.getRect(turn(start.index)).top, greaterThan(tester.getRect(divider).bottom));
      expect(tester.getRect(turn(start.index - 1)).bottom, lessThan(tester.getRect(divider).top));
      await settleTalk(tester);
    });

    // RULE (§1): a day talk has one scene and no boundary — its lines are not touched.
    test('разбор: начало и прощание сцен, сцена реплики; разговор до FIX-4 — без них', () {
      final change = rehearsalTalk(RehearsalStep.sceneChange);
      expect(change.pendingSceneStart?.index, change.turns.last.index);
      expect(change.currentSceneId, scene2);
      expect(change.opensScene(0), isFalse, reason: 'первая реплика — не граница');
      expect(rehearsalTalk(RehearsalStep.secondScene).pendingSceneStart, isNull, reason: 'ученик уже говорил после приветствия');
      final day = serverTalk('conversation-day-open');
      expect(day.turns.every((t) => t.sceneEvent == null && t.sceneId == null), isTrue);
      expect(day.pendingSceneStart, isNull);
      expect(day.targets.every((t) => t.state == (t.said ? TalkTargetState.said : TalkTargetState.none)), isTrue,
          reason: 'без state — по said');
    });
  });

  group('37-7…37-11 · ряд конструкций над микрофоном', () {
    // RULE (§2): the row holds only the constructions of the scene the talk is in that are still to say, in the
    // server's order — not yet: an ink outline; almost: a brass outline, brass words and a brass dot on the left. What
    // is said, and what belongs to the other scene, is not on the row.
    // CATCHES: said plates staying on the row (the live pass on (20)), the half-filled «часть», scene 2's plates in
    // scene 1.
    testWidgets('в ряду — только несказанные и «почти» сцены, где разговор сейчас', (tester) async {
      final talk = rehearsalTalk(RehearsalStep.almost);
      final stand = await pumpTalk(tester, TalkProbe()..documents.add(talk));
      await finishLine(tester, stand);
      expect(row, findsOneWidget);
      final shown = [for (final t in talk.targets) if (t.sceneId == scene1 && !t.said) t];
      expect([for (final t in shown) '${t.ref}:${t.state.name}'], ['p3:almost', 'p4:none']);
      expect(platesOnRow(tester), shown.length, reason: 'сказанных и чужой сцены в ряду нет');
      final almost = talk.targets.singleWhere((t) => t.almost);
      expect(chip(almost), findsOneWidget, reason: 'первая — на экране');
      final box = tester.widget<AnimatedContainer>(chip(almost)).decoration! as BoxDecoration;
      expect((box.border! as Border).top.color, AppColors.brassInk, reason: 'контур латунью');
      expect(box.color, AppColors.paper);
      final dot = find.descendant(of: chip(almost), matching: byKey('talk-construction-almost'));
      expect(dot, findsOneWidget);
      expect(tester.getSize(dot), const Size(6, 6));
      final none = talk.targets.firstWhere((t) => t.sceneId == scene1 && t.state == TalkTargetState.none);
      await expectOnRow(tester, [none]);
      final plain = tester.widget<AnimatedContainer>(chip(none)).decoration! as BoxDecoration;
      expect((plain.border! as Border).top.color, AppColors.markerOutline, reason: 'контур чернил');
      expect(find.descendant(of: chip(none), matching: byKey('talk-construction-almost')), findsNothing);
      await settleTalk(tester);
    });

    // RULE (§2, 37-8 → 37-8b): the answer that says a construction on the row gives its plate a 15 % sage wash and the
    // check for 600 ms, then the plate leaves the row; the others keep their order.
    // CATCHES: a said plate that stays (the live pass on (20)) and a plate that vanishes before the learner sees it
    // counted.
    testWidgets('сказанная плашка — шалфей с галкой 600 мс, потом уезжает; остальные на месте', (tester) async {
      final said = rehearsalTalk(RehearsalStep.almostSaid);
      final probe = TalkProbe()..documents.addAll([rehearsalTalk(RehearsalStep.almost), said]);
      final stand = await pumpTalk(tester, probe);
      await finishLine(tester, stand);
      final p3 = said.targets.firstWhere((t) => t.sceneId == scene1 && t.ref == 'p3');
      final p4 = said.targets.firstWhere((t) => t.sceneId == scene1 && t.ref == 'p4');
      unawaited(stand.talk.say(rehearsalMove(RehearsalStep.almostSaid)!));
      await tester.pump();
      await tester.pump();
      expect(p3.said, isTrue);
      expect(chip(p3), findsOneWidget, reason: 'ещё в ряду — мгновение');
      final box = tester.widget<AnimatedContainer>(chip(p3)).decoration! as BoxDecoration;
      expect(box.color, AppColors.sessionSageWash);
      expect(find.descendant(of: chip(p3), matching: byKey('talk-construction-said')), findsOneWidget);
      expect(find.descendant(of: chip(p3), matching: find.text(p3.valueTarget!)), findsOneWidget, reason: 'своё слово в окне');

      await tester.pump(const Duration(milliseconds: 600));
      await tester.pump();
      expect(chip(p3), findsNothing, reason: 'уехала');
      expect(chip(p4), findsOneWidget, reason: 'остальные на месте');
      await finishLine(tester, stand);
      await settleTalk(tester);
    });

    // RULE (§2, 37-11b): the last plate gone, the row folds — the dock goes down by the row's 44 and the 14 under it,
    // and the ribbon gets that room.
    // CATCHES: an empty row left standing, and a dock that keeps the room of a row it no longer has.
    testWidgets('последняя плашка уехала — ряд складывается, док опускается на 58', (tester) async {
      // Step 4 with p4 said too: every target of the scene said, the scene not closed yet — a day talk goes on so.
      final all = rehearsalTalk(RehearsalStep.almostSaid, (json) {
        for (final t in (json['targets'] as List).cast<Map<String, dynamic>>()) {
          if (t['ref'] == 'p4' && t['scene_id'] == scene1) {
            t
              ..['said'] = true
              ..['state'] = 'said';
          }
        }
      });
      final probe = TalkProbe()..documents.addAll([rehearsalTalk(RehearsalStep.almostSaid), all]);
      final stand = await pumpTalk(tester, probe);
      await finishLine(tester, stand);
      final last = rehearsalTalk(RehearsalStep.almostSaid).lastPartnerTurn!;
      final before = tester.getRect(turn(last.index)).bottom;
      unawaited(stand.talk.say("He doesn't have a fever."));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 600));
      await tester.pump();
      expect(row, findsNothing, reason: 'ряд сложился');
      // Measured in the same state of the dock as before — the learner's move again.
      await finishLine(tester, stand);
      expect(tester.getRect(turn(last.index)).bottom - before, moreOrLessEquals(58, epsilon: 0.5), reason: 'лента получила 44 + 14');
      await settleTalk(tester);
    });

    // RULE (§2, 37-8d): a tap on the row opens «Конструкции в разговоре» — every construction of the scene, in its
    // order, as the summary's plates: said — sage with the check and «ты сказал: …»; almost — the brass outline with
    // its dot and «почти — скажи целиком: <строка урока>»; not yet — the ink outline and «из урока: <строка урока>».
    // CATCHES: the old sheet of one construction, «часть», and the other scene's constructions in the sheet.
    testWidgets('37-8d: лист по тапу — все конструкции сцены в трёх состояниях', (tester) async {
      final talk = rehearsalTalk(RehearsalStep.almost);
      final stand = await pumpTalk(tester, TalkProbe()..documents.add(talk));
      await finishLine(tester, stand);
      await tester.tap(row);
      await tester.pumpAndSettle();
      final sheet = byKey('talk-construction-sheet');
      expect(sheet, findsOneWidget);
      expect(find.descendant(of: sheet, matching: find.text('Конструкции в разговоре')), findsOneWidget);
      final scene = talk.targetsOf(scene1);
      expect(scene, hasLength(4));
      final notes = {
        for (final t in scene)
          t.ref: switch (t.state) {
            TalkTargetState.said => 'ты сказал: ${t.saidWith(t.valueTarget)}',
            TalkTargetState.almost => 'почти — скажи целиком: ${t.lessonLine}',
            TalkTargetState.none => 'из урока: ${t.lessonLine}',
          },
      };
      expect(notes['p3'], 'почти — скажи целиком: The pain is sharp when he bends.');
      expect(notes['p4'], "из урока: He doesn't have a fever.");
      final tops = <double>[];
      for (final t in scene) {
        final card = find.descendant(of: sheet, matching: byKey('talk-construction-card-${t.sceneId}-${t.ref}'));
        expect(card, findsOneWidget, reason: t.ref);
        expect(find.descendant(of: card, matching: find.text(notes[t.ref]!)), findsOneWidget, reason: t.ref);
        final box = tester.widget<Container>(card).decoration! as BoxDecoration;
        switch (t.state) {
          case TalkTargetState.said:
            expect(box.color, AppColors.sessionSageWash);
            expect(find.descendant(of: card, matching: byKey('talk-construction-said')), findsOneWidget);
          case TalkTargetState.almost:
            expect((box.border! as Border).top.color, AppColors.brassInk);
            expect(find.descendant(of: card, matching: byKey('talk-construction-almost')), findsOneWidget);
          case TalkTargetState.none:
            expect((box.border! as Border).top.color, AppColors.markerOutline);
        }
        tops.add(tester.getRect(card).top);
      }
      expect([...tops]..sort(), tops, reason: 'в порядке сцены');
      for (final t in talk.targetsOf(scene2)) {
        expect(byKey('talk-construction-card-${t.sceneId}-${t.ref}'), findsNothing, reason: 'не своя сцена');
      }
      await tester.tap(byKey('talk-construction-sheet-close'));
      await tester.pumpAndSettle();
      await settleTalk(tester);
    });
  });

  group('37-7 · 37-8e · подсказка целой фразой', () {
    // RULE (§3): under the role's last line stands a light plate with the lesson's whole sentence — in EVERY state of
    // the learner's move: waiting, listening, after «не расслышал», after «Sorry?»; never while the role speaks or a
    // move is on its way. No «Скажи, что …» around it and no «Подсказать» beside it. A tap opens the target's line in
    // the target language under it (37-7e), a second tap folds it.
    // CATCHES: the old chip after 5 s of silence, the «Скажи, что …» frame, «Подсказать» with hints on, a plate that
    // goes away while the learner speaks.
    testWidgets('плашка подсказки — во всех состояниях «твоя очередь», тап раскрывает строку цели', (tester) async {
      final rephrased = rehearsalTalk(RehearsalStep.opened, (json) {
        final turns = json['turns'] as List<dynamic>;
        final first = turns.first as Map<String, dynamic>;
        turns.addAll([
          {
            ...first,
            'index': 2,
            'speaker': 'learner',
            'kind': 'rescue',
            'text_target': 'Sorry?',
            'text_native': null,
            'audio': null,
            'scene_event': null,
            'understood': null,
          },
          {
            ...first,
            'index': 3,
            'text_target': 'Which part of his back hurts — the upper or the lower part?',
            'text_native': 'Какая часть спины болит — верх или низ?',
            'audio': null,
            'scene_event': null,
          },
        ]);
      });
      final hold = Completer<void>();
      final probe = TalkProbe()
        ..documents.addAll([opened, rephrased])
        ..holdMove = hold;
      final stand = await pumpTalk(tester, probe, recognizer: ListeningRecognizer());
      final sentence = opened.hints.sentence!;
      expect(sentence, 'У него болит поясница.');
      expect(stand.talk.phase, TalkPhase.agentSpeaking);
      expect(plate, findsNothing, reason: 'пока роль говорит');

      await finishLine(tester, stand);
      expect(find.descendant(of: plate, matching: find.text(sentence)), findsOneWidget, reason: 'ждём');
      expect(find.textContaining('Скажи, что'), findsNothing);
      expect(byKey('talk-hint'), findsNothing, reason: 'при плашке «Подсказать» нет');
      final line = tester.getRect(turn(1));
      expect(tester.getRect(plate).top - line.bottom, moreOrLessEquals(8, epsilon: 0.5), reason: 'под репликой роли');
      expect(tester.getRect(plate).left, moreOrLessEquals(line.left, epsilon: 0.5), reason: 'слева, где реплика');

      await tester.tap(byKey('talk-mic'));
      await tester.pump();
      expect(stand.mics.single.state.name, 'listening');
      expect(plate, findsOneWidget, reason: 'слушаю');
      await tester.tap(byKey('talk-mic'));
      await tester.pump();
      await tester.pump();
      expect(stand.talk.trouble, TalkTrouble.unheard);
      expect(plate, findsOneWidget, reason: 'не расслышал');

      await tester.tap(byKey('talk-rescue'));
      await tester.pump();
      expect(stand.talk.phase, TalkPhase.sending);
      expect(plate, findsNothing, reason: 'ход в полёте');
      hold.complete();
      await tester.pump();
      await tester.pump();
      await finishLine(tester, stand);
      expect(find.descendant(of: plate, matching: find.text(sentence)), findsOneWidget, reason: 'после «Sorry?»');
      expect(tester.getRect(plate).top, greaterThan(tester.getRect(turn(3)).bottom), reason: 'под последней репликой роли');

      final target = opened.targets.firstWhere((t) => t.sceneId == opened.hints.sceneId && t.ref == opened.hints.ref);
      expect(byKey('talk-hint-line'), findsNothing);
      await tester.tap(plate);
      await tester.pump();
      expect(tester.widget<Text>(byKey('talk-hint-line')).data, target.lessonLine);
      expect(target.lessonLine, 'It hurts in his lower back.');
      await tester.tap(plate);
      await tester.pump();
      expect(byKey('talk-hint-line'), findsNothing, reason: 'повторный тап сворачивает');
      await settleTalk(tester);
    });

    // RULE (§3, 37-8e): the answer to a move that said a target ALMOST brings `hints.target` — the plate stands in two
    // lines at once, the sentence and the exact line in Literata 17 in ink; under the learner's own line the judge's
    // «Почти — скажи целиком». A talk re-read on that move shows the same.
    // CATCHES: «почти» that looks like nothing happened, the exact line hidden behind a tap.
    testWidgets('после «почти» — плашка в две строки и строка судьи под своим пузырём', (tester) async {
      final almost = rehearsalTalk(RehearsalStep.almost);
      final probe = TalkProbe()..documents.addAll([rehearsalTalk(RehearsalStep.beforeAlmost), almost]);
      final stand = await pumpTalk(tester, probe);
      await finishLine(tester, stand);
      unawaited(stand.talk.say(rehearsalMove(RehearsalStep.almost)!));
      await tester.pump();
      await tester.pump();
      final own = almost.lastOwnTurn!;
      expect(own.textTarget, 'The pain is sharp when she bends.');
      final judge = byKey('talk-judge-almost');
      expect(judge, findsOneWidget);
      expect(find.text('Почти — скажи целиком'), findsOneWidget);
      expect(tester.getRect(judge).top, greaterThan(tester.getRect(turn(own.index)).bottom), reason: 'под своим пузырём');
      expect(tester.getRect(judge).bottom, lessThan(tester.getRect(turn(own.index + 1)).top), reason: 'до ответа роли');

      await finishLine(tester, stand);
      expect(find.descendant(of: plate, matching: find.text('Боль острая, когда он наклоняется.')), findsOneWidget);
      expect(tester.widget<Text>(byKey('talk-hint-line')).data, 'The pain is sharp when he bends.', reason: 'сразу две строки');
      expect(tester.widget<Text>(byKey('talk-hint-line')).style?.fontFamily, AppFonts.literata);
      await settleTalk(tester);

      // Re-read on that move: `hints.target` says the same.
      final again = await pumpTalk(tester, TalkProbe()..documents.add(almost));
      await finishLine(tester, again);
      expect(byKey('talk-judge-almost'), findsOneWidget);
      expect(byKey('talk-hint-line'), findsOneWidget);
      await settleTalk(tester);
    });

    // RULE (§3, 37-7c): «Без подсказок» — no plate; «Подсказать» stands right of the microphone and puts the same plate
    // up for this move, then goes. The server sends no hint when hints are off, so the plate is the lesson's sentence of
    // the target the phone names by the server's order: the one said almost, else the first not said, of the scene the
    // talk is in — and after an «almost» its exact line stands under it.
    // CATCHES: a plate over «Без подсказок», a button that shows nothing, «Подсказать» in the normal mode.
    testWidgets('«Без подсказок»: плашки нет, «Подсказать» ставит её на этот ход', (tester) async {
      final probe = TalkProbe()..documents.add(rehearsalTalk(RehearsalStep.opened, blind));
      final stand = await pumpTalk(tester, probe, hints: false);
      await finishLine(tester, stand);
      expect(plate, findsNothing);
      final hint = byKey('talk-hint');
      expect(hint, findsOneWidget);
      expect(tester.getRect(hint).left, greaterThan(tester.getRect(byKey('talk-mic')).right - 8), reason: 'справа от микрофона');
      expect(byKey('talk-rescue'), findsOneWidget);
      await tester.tap(hint);
      await tester.pump();
      expect(find.descendant(of: plate, matching: find.text('У него болит поясница.')), findsOneWidget, reason: 'та же фраза урока');
      expect(hint, findsNothing, reason: 'кнопка ушла');
      await settleTalk(tester);

      final almostProbe = TalkProbe()
        ..documents.addAll([rehearsalTalk(RehearsalStep.beforeAlmost, blind), rehearsalTalk(RehearsalStep.almost, blind)]);
      final almost = await pumpTalk(tester, almostProbe, hints: false);
      await finishLine(tester, almost);
      unawaited(almost.talk.say(rehearsalMove(RehearsalStep.almost)!));
      await tester.pump();
      await tester.pump();
      expect(byKey('talk-judge-almost'), findsOneWidget, reason: 'судья — не подсказка');
      await finishLine(tester, almost);
      await tester.tap(byKey('talk-hint'));
      await tester.pump();
      expect(find.descendant(of: plate, matching: find.text('Боль острая, когда он наклоняется.')), findsOneWidget);
      expect(tester.widget<Text>(byKey('talk-hint-line')).data, 'The pain is sharp when he bends.');
      await settleTalk(tester);
    });
  });

  group('37-11 · 37-12 · итог разговора', () {
    Future<void> pumpSummary(WidgetTester tester, PlanConversation talk) async {
      tester.view.physicalSize = const Size(390, 844) * 2;
      tester.view.devicePixelRatio = 2;
      addTearDown(tester.view.reset);
      await tester.pumpWidget(
        MaterialApp(
          theme: buildAppTheme(),
          locale: const Locale('ru'),
          localizationsDelegates: AppLocalizations.localizationsDelegates,
          supportedLocales: const [Locale('ru'), Locale('en')],
          home: Scaffold(body: TalkSummaryView(talk: talk, scene: null, onNext: () {}, onClose: () {})),
        ),
      );
      await tester.pump();
    }

    // RULE (§4, 37-11): the last scene's goodbye is SAID first, and only then the sheet rises — «Разговор окончен», the
    // talk's minutes and «Итог»; while the goodbye sounds the dock stays as it was, with «слушай».
    // CATCHES: «Разговор окончен» over the goodbye's first word, and a sheet the learner taps through before it spoke.
    testWidgets('37-11: лист «Разговор окончен» встаёт, когда прощание сказано', (tester) async {
      final ended = rehearsalTalk(RehearsalStep.ended);
      var summaries = 0;
      final probe = TalkProbe()..documents.addAll([rehearsalTalk(RehearsalStep.extraSaid), ended]);
      final stand = await pumpTalk(tester, probe, onSummary: () => summaries++);
      await finishLine(tester, stand);
      unawaited(stand.talk.say(rehearsalMove(RehearsalStep.ended)!));
      await tester.pump();
      await tester.pump();
      expect(stand.talk.phase, TalkPhase.ended);
      expect(stand.voice.played.last, 'talk-${ended.lastPartnerTurn!.index}');
      expect(byKey('talk-end-sheet'), findsNothing, reason: 'прощание ещё звучит');
      expect(find.text('слушай'), findsOneWidget);

      await finishLine(tester, stand);
      final sheet = byKey('talk-end-sheet');
      expect(sheet, findsOneWidget);
      expect(find.descendant(of: sheet, matching: find.text('Разговор окончен')), findsOneWidget);
      expect(find.descendant(of: sheet, matching: find.text(nb('1 минута'))), findsOneWidget, reason: 'минуты сервера');
      expect(tester.getSize(sheet).width, 390, reason: 'во всю ширину');
      expect(row, findsNothing, reason: 'ряда над листом нет');
      expect(byKey('talk-ribbon'), findsOneWidget, reason: 'лента видна над листом');
      await tester.tap(byKey('talk-summary-action'));
      await tester.pump();
      expect(summaries, 1);
      await settleTalk(tester);
    });

    // RULE (§4, 37-12b): the rehearsal's summary — «Ты готов к событию», the targets as the server left them, and under
    // them «Ещё вспомнил»: the constructions said beyond the targets, sage with «ты сказал: …». Ended by its goodbye —
    // no line about the time.
    // CATCHES: a summary without «Ещё вспомнил», a group drawn empty, «к приёму» printed for every plan.
    testWidgets('37-12b: репетиция — «Ещё вспомнил», строки про время нет', (tester) async {
      final talk = rehearsalTalk(RehearsalStep.ended);
      await pumpSummary(tester, talk);
      expect(find.text('Ты готов к событию'), findsOneWidget);
      expect(byKey('talk-summary-by-time'), findsNothing);
      final extra = talk.summary!.extraSaid.single;
      expect(find.text('ЕЩЁ ВСПОМНИЛ'), findsOneWidget);
      final card = byKey('talk-construction-card-${extra.sceneId}-${extra.ref}');
      await tester.ensureVisible(card);
      expect(find.descendant(of: card, matching: find.text('ты сказал: I gave him paracetamol.')), findsOneWidget);
      expect((tester.widget<Container>(card).decoration! as BoxDecoration).color, AppColors.sessionSageWash);
      expect(tester.getRect(byKey('talk-summary-extra')).top, greaterThan(tester.getRect(find.text('КОНСТРУКЦИИ В РАЗГОВОРЕ')).bottom));

      final none = rehearsalTalk(RehearsalStep.ended, (json) => (json['summary'] as Map<String, dynamic>)['extra_said'] = <Object>[]);
      await pumpSummary(tester, none);
      expect(find.text('ЕЩЁ ВСПОМНИЛ'), findsNothing, reason: 'нечего показать — группы нет');
    });

    // RULE (§4, 37-12): the talk the time ran out on says so over the plates — «Разговор закончился по времени —
    // несказанное вернётся» where the day gives it back tomorrow, the line without the promise where nothing comes back
    // (the rehearsal, a replay).
    // CATCHES: the line missing, and «вернётся» printed where the server returns nothing.
    testWidgets('37-12: по времени — строка над плашками; обещание «вернётся» — только когда вернётся', (tester) async {
      void byLimit(Map<String, dynamic> json, {required bool returns}) {
        final summary = json['summary'] as Map<String, dynamic>;
        summary
          ..['ended_by_limit'] = true
          ..['returns_tomorrow'] = returns;
        final last = (summary['phrases'] as List).cast<Map<String, dynamic>>().last;
        last
          ..['said'] = false
          ..['state'] = 'none'
          ..['value_target'] = null;
      }

      await pumpSummary(tester, rehearsalTalk(RehearsalStep.ended, (json) => byLimit(json, returns: true)));
      final line = byKey('talk-summary-by-time');
      expect(tester.widget<Text>(line).data, 'Разговор закончился по времени — несказанное вернётся');
      expect(tester.getRect(line).top, greaterThan(tester.getRect(find.text('КОНСТРУКЦИИ В РАЗГОВОРЕ')).bottom));
      final first = rehearsalTalk(RehearsalStep.ended).summary!.phrases.first;
      expect(tester.getRect(line).bottom, lessThan(tester.getRect(byKey('talk-construction-card-${first.sceneId}-${first.ref}')).top));
      expect(find.text('вернётся завтра'), findsOneWidget);

      await pumpSummary(tester, rehearsalTalk(RehearsalStep.ended, (json) => byLimit(json, returns: false)));
      expect(tester.widget<Text>(byKey('talk-summary-by-time')).data, 'Разговор закончился по времени');
      expect(find.text('повтори перед событием'), findsOneWidget);
    });
  });

  group('§4 · итог разговора — до итога дня', () {
    // RULE (§4): the talk's summary comes BEFORE the day's: the goodbye → «Разговор окончен» → «Итог» → 37-12b →
    // «Дальше» → «День пройден». From the goodbye to «Дальше» the device owes that summary, and a session opened again
    // in between (the cross, the app killed) stands on it first — the talk read back from the server.
    // CATCHES: «День пройден» straight after the talk (Den's rehearsal on (20)) and a summary lost to a restart.
    testWidgets('конец репетиции: прощание → 37-11 → 37-12b → «День пройден»; долг итога записан и снят', (tester) async {
      final db = AppDatabase.forTesting(NativeDatabase.memory());
      addTearDown(db.close);
      final ended = rehearsalTalk(RehearsalStep.ended, _silent);
      final probe = TalkProbe()..documents.addAll([rehearsalTalk(RehearsalStep.extraSaid, _silent), ended]);
      final backend = _RehearsalDay();
      await _openSession(tester, db: db, backend: backend, talk: probe);

      // 37-5b first: the constructions by scene.
      expect(find.text('СЦЕНА 1 · ЗАПИСЬ К ВРАЧУ · РЕГИСТРАТОР'), findsOneWidget);
      expect(find.text('Регистратор начнёт первым. Отвечай и спрашивай сам.'), findsOneWidget);
      await tester.tap(byKey('talk-entry-start'));
      await tester.pump();
      await tester.pump();
      await tester.pump();

      await tester.tap(byKey('talk-mic'));
      await tester.pump();
      await enterHeard(tester, rehearsalMove(RehearsalStep.ended)!);
      await tester.pump(const Duration(milliseconds: 1600));
      await tester.pump();
      await tester.pump();
      backend.talkOver = true;
      expect(byKey('talk-end-sheet'), findsOneWidget);
      expect(find.textContaining('День пройден'), findsNothing);
      await tester.pump();
      expect(await _owed(tester, db), ended.id, reason: 'итог разговора в долгу');

      await tester.tap(byKey('talk-summary-action'));
      await tester.pump();
      expect(byKey('talk-summary-title'), findsOneWidget);
      expect(find.text('Ты готов к событию'), findsOneWidget);
      expect(find.textContaining('День пройден'), findsNothing, reason: 'итог дня — после итога разговора');

      await tester.tap(byKey('talk-next'));
      await tester.pump();
      await tester.pump();
      expect(find.textContaining('День пройден'), findsOneWidget);
      expect(await _owed(tester, db), isNull, reason: 'итог прочитан — долга нет');
    });

    testWidgets('сессия, открытая заново после прощания, — сначала итог разговора, потом «День пройден»', (tester) async {
      final db = AppDatabase.forTesting(NativeDatabase.memory());
      addTearDown(db.close);
      final ended = rehearsalTalk(RehearsalStep.ended, _silent);
      await tester.runAsync(() => PlanStore(db).setTalkSummaryOwed(_plan.id, 3, ended.id));
      final probe = TalkProbe()..documents.add(ended);
      await _openSession(tester, db: db, backend: _RehearsalDay()..talkOver = true, talk: probe);

      expect(probe.reads, 1, reason: 'разговор перечитан по id с устройства');
      expect(byKey('talk-summary-title'), findsOneWidget);
      expect(find.textContaining('День пройден'), findsNothing);
      await tester.tap(byKey('talk-next'));
      await tester.pump();
      await tester.pump();
      expect(find.textContaining('День пройден'), findsOneWidget);
      expect(await _owed(tester, db), isNull);
    });

    testWidgets('долга нет — день с оконченным разговором открывается на «День пройден»', (tester) async {
      final db = AppDatabase.forTesting(NativeDatabase.memory());
      addTearDown(db.close);
      final probe = TalkProbe();
      await _openSession(tester, db: db, backend: _RehearsalDay()..talkOver = true, talk: probe);
      expect(probe.reads, 0);
      expect(find.textContaining('День пройден'), findsOneWidget);
    });
  });

  group('§6 · заголовки для админки', () {
    // RULE (§6): every request carries `X-App-Build` — the running build, «1.0.0 (21)» — and `X-Device` — the model by
    // name and the system, «iPhone 14 Pro, iOS 26.5»; nothing that names THIS phone or its owner; plain ASCII, the only
    // bytes `dart:io` lets into a header.
    // CATCHES: a request without them, the device's own name in a header, a «·» that stops every request.
    test('каждый запрос несёт X-App-Build и X-Device, без имени устройства', () async {
      TestWidgetsFlutterBinding.ensureInitialized();
      final messenger = TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger;
      const channel = MethodChannel('com.denis.engstd/app_info');
      messenger.setMockMethodCallHandler(channel, (call) async => switch (call.method) {
        'version' => {'name': '1.0.0', 'build': '21'},
        'device' => {'machine': 'iPhone15,2', 'system': 'iOS', 'version': '26.5', 'simulator': false},
        _ => null,
      });
      addTearDown(() => messenger.setMockMethodCallHandler(channel, null));
      AppIdentity.resetForTest();
      addTearDown(AppIdentity.resetForTest);

      final adapter = _RecordingAdapter();
      final api = ApiClient(TokenStore(), adapter: adapter);
      await api.plans();
      await api.plans();
      expect(adapter.sent, hasLength(2));
      for (final headers in adapter.sent) {
        expect(headers['X-App-Build'], '1.0.0 (21)');
        expect(headers['X-Device'], 'iPhone 14 Pro, iOS 26.5');
        for (final value in headers.values) {
          expect('$value'.codeUnits.every((c) => c >= 0x20 && c < 0x7F), isTrue, reason: 'ASCII: $value');
        }
      }
      expect(AppIdentity.headers.keys, unorderedEquals(['X-App-Build', 'X-Device']), reason: 'и картинкам, и звуку сервера');
    });

    test('модель — по имени, неизвестная — как есть; симулятор назван; не-ASCII отброшен', () {
      expect(AppIdentity.deviceLine(machine: 'iPhone14,5', system: 'iOS', version: '27.0'), 'iPhone 13, iOS 27.0');
      expect(AppIdentity.deviceLine(machine: 'iPhone99,9', system: 'iOS', version: '28.1'), 'iPhone99,9, iOS 28.1');
      expect(AppIdentity.deviceLine(machine: 'iPhone18,3', system: 'iOS', version: '26.5', simulator: true), 'iPhone 17 (simulator), iOS 26.5');
      expect(AppIdentity.deviceLine(machine: '', system: 'iOS', version: '26.5'), isNull);
      expect(AppIdentity.buildLine(name: '1.0.0', build: '21'), '1.0.0 (21)');
      expect(AppIdentity.buildLine(name: '1.0.0·', build: ''), '1.0.0');
      expect(AppIdentity.buildLine(name: '', build: ''), isNull);
    });
  });
}

/// The talk the device owes the summary of, read as the session reads it.
Future<String?> _owed(WidgetTester tester, AppDatabase db) async =>
    tester.runAsync<String?>(() => PlanStore(db).talkSummaryOwed(_plan.id, 3));

/// The talk's voice files are not the point of a session test: the phone reads its lines itself.
void _silent(Map<String, dynamic> json) {
  for (final t in (json['turns'] as List).cast<Map<String, dynamic>>()) {
    t['audio'] = null;
  }
}

final Plan _plan = planFrom('plan_rehearsal');

/// The rehearsal (day 3) as the server reads it: «Вспомнить» answered, the talk still to have — or had ([talkOver]).
class _RehearsalDay implements SessionBackend {
  bool talkOver = false;

  Map<String, dynamic> get _raw {
    final raw = serverFixtureJson('day-rehearsal');
    for (final s in (raw['stages'] as List).cast<Map<String, dynamic>>()) {
      for (final c in (s['cards'] as List).cast<Map<String, dynamic>>()) {
        c
          ..['result'] = 'passed'
          ..['attempts'] = 1;
      }
    }
    final window = raw['window'] as Map<String, dynamic>;
    window['stages'] = [
      for (final r in (window['stages'] as List).cast<Map<String, dynamic>>())
        {...r, 'state': r['stage'] == 'conversation' ? (talkOver ? 'done' : 'current') : 'done'},
    ];
    return raw;
  }

  @override
  Future<SessionDay> day(String planId, int number) async => SessionDay.fromJson(_raw);

  @override
  Future<void> open(String planId, int number) async {}

  @override
  Future<Plan> plan(String planId) async => _plan;

  @override
  Future<Plan> retryLesson(String planId, String sceneId) async => _plan;

  @override
  Future<SessionAnswerOutcome> answer(String planId, int number, String cardId, SessionAnswer answer) => throw UnimplementedError();

  @override
  Future<SessionJudgeOutcome> judge(String planId, int number, String cardId, {required String heard, required bool hinted}) =>
      throw UnimplementedError();

  @override
  Future<SessionDay> close(String planId, int number) => throw UnimplementedError();
}

class _Auth extends AuthController {
  @override
  Future<AppUser?> build() async => AppUser(id: '01TEST', name: 'Тест');
}

/// The real session of the rehearsal over [backend] and the talk's [talk] server, on the device's [db].
Future<void> _openSession(WidgetTester tester, {required AppDatabase db, required _RehearsalDay backend, required TalkProbe talk}) async {
  tester.view.physicalSize = const Size(390, 844) * 2;
  tester.view.devicePixelRatio = 2;
  addTearDown(tester.view.reset);
  final messenger = tester.binding.defaultBinaryMessenger;
  for (final channel in [const MethodChannel('flutter_tts'), const MethodChannel('com.denis.engstd/app_info'), AudioMixer.channel]) {
    messenger.setMockMethodCallHandler(channel, (call) async => null);
    addTearDown(() => messenger.setMockMethodCallHandler(channel, null));
  }
  await tester.pumpWidget(
    ProviderScope(
      overrides: [
        appDatabaseProvider.overrideWithValue(db),
        lineAudioCacheProvider.overrideWithValue(RecordingLines()),
        speechRecognizerProvider.overrideWithValue(SilentRecognizer()),
        authControllerProvider.overrideWith(_Auth.new),
        appVersionProvider.overrideWith((ref) async => null),
      ],
      child: MaterialApp(
        theme: buildAppTheme(),
        locale: const Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru'), Locale('en')],
        builder: (context, child) => MediaQuery(data: MediaQuery.of(context).copyWith(disableAnimations: true), child: child!),
        home: SessionScreen(plan: _plan, number: 3, backend: backend, talkBackend: FakeTalkBackend(talk)),
      ),
    ),
  );
  for (var i = 0; i < 4; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

/// A transport that answers every request with an empty list and keeps what each request carried.
class _RecordingAdapter implements HttpClientAdapter {
  final List<Map<String, dynamic>> sent = [];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    sent.add({...options.headers});
    return ResponseBody.fromString('{"data": []}', 200, headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    });
  }

  @override
  void close({bool force = false}) {}
}

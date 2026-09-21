import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/dialogue_feed.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_models.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/features/plan/session/parts/session_bubbles.dart';
import 'package:eng_std/features/plan/session/parts/session_tiles.dart';

import '../../../support/session_harness.dart';

/// DIALOGUE (work order SESSION-1c §2, canvas series 33): each kind from a fixture card — its states, the modes of
/// `dialogue_answer` by the plan's level and «No hints», the answers it writes, the conversation above it.
void main() {
  final day = sessionFixture('day-doctor');

  SessionCard dialogueAt(int position) => day.stageOf(PlanStage.dialogue)!.cards.firstWhere((c) => c.position == position);
  List<SessionResult> results(CardProbe probe) => [for (final a in probe.answers) a.result];
  SessionFrameText ownFrame(WidgetTester tester) =>
      tester.widget<SessionFrameText>(find.descendant(of: find.byType(SessionOwnRow), matching: find.byType(SessionFrameText)).first);

  group('33-1 dialogue_partner', () {
    // CATCHES: the partner's text shown before the answer, a card that stays silent, a wrong answer that reveals the line
    // or leaves by itself.
    testWidgets('the line sounds, a wave instead of the text; correct — the text opens, passed, auto-advance', (tester) async {
      final card = dialogueAt(1);
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(card, probe, voice: voice));
      expect(find.text('О каких двух местах спрашивает врач?'), findsOneWidget, reason: 'the task line is the server\'s question');
      expect(find.byKey(const ValueKey('partner-wave')), findsOneWidget);
      expect(find.text('Where does it hurt: his upper back or his lower back?'), findsNothing);
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['x1@1.0'], reason: 'the partner\'s line plays once when the card opens');

      await tapText(tester, 'Верх или низ спины');
      expect(results(probe), [SessionResult.passed]);
      await tester.pump(const Duration(milliseconds: 250));
      expect(find.text('Where does it hurt: his upper back or his lower back?'), findsOneWidget);
      expect(find.text('Где болит: вверху спины или в пояснице?'), findsOneWidget);
      await settleCard(tester);
      expect(probe.nexts, 1);
    });

    testWidgets('wrong — failed, the text stays closed, «Next» by hand', (tester) async {
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(dialogueAt(1), probe));
      await tapText(tester, 'Колени или ступни');
      expect(results(probe), [SessionResult.failed]);
      await settleCard(tester);
      expect(find.byKey(const ValueKey('partner-wave')), findsOneWidget);
      expect(probe.nexts, 0);
      await tapText(tester, 'Дальше');
      expect(probe.nexts, 1);
    });

    // ПРАВИЛО (правка прохода 21.09, наряд CLIENT-CONV-1b, кадр 33-1): своя реплика → пузырь собеседника с волной →
    // ОТДЕЛЬНЫЙ блок «Что тебе сказали?» с вопросом и вариантами под ним. Вопрос не стоит над лентой строкой задания:
    // между прошлой своей репликой и пузырём собеседника его читали репликой разговора.
    // ЛОВИТ: вопрос снова в строке задания над лентой; блок, разрезанный пузырём; варианты под краем экрана.
    testWidgets('33-1: своя реплика → пузырь с волной → блок «Что тебе сказали?» с вариантами', (tester) async {
      final raw = jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;
      final dialogue = (raw['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == 'dialogue');
      for (final c in (dialogue['cards'] as List).cast<Map<String, dynamic>>().where((c) => (c['position'] as int) < 3)) {
        c['result'] = 'passed';
        c['attempts'] = 1;
      }
      final cards = SessionDay.fromJson(raw).stageOf(PlanStage.dialogue)!.cards;
      final card = cards.firstWhere((c) => c.position == 3);
      final payload = card.payload as DialoguePartnerPayload;
      await pumpCard(tester, probeEnv(card, CardProbe(), feed: DialogueFeed.before(cards, card)), size: const Size(390, 844));

      final own = tester.getRect(find.byType(SessionOwnRow).last);
      final wave = tester.getRect(find.byKey(const ValueKey('partner-wave')));
      final task = tester.getRect(find.text('Что тебе сказали?'));
      final question = tester.getRect(find.byKey(const ValueKey('check-question')));
      final options = [for (final o in payload.options) tester.getRect(find.byKey(ValueKey('option-${o.id}')))];
      expect(own.bottom, lessThanOrEqualTo(wave.top), reason: 'своя реплика прошлого обмена — над пузырём собеседника');
      expect(wave.bottom, lessThanOrEqualTo(task.top), reason: 'блок — под пузырём, а не над лентой');
      expect(task.bottom, lessThanOrEqualTo(question.top));
      expect(tester.widget<Text>(find.byKey(const ValueKey('check-question'))).data, payload.questionNative);
      for (final o in options) {
        expect(question.bottom, lessThanOrEqualTo(o.top), reason: 'вопрос над вариантами — один блок');
        expect(o.bottom, lessThanOrEqualTo(844), reason: 'варианты на экране');
      }
      expect(find.descendant(of: find.byKey(const ValueKey('check-block')), matching: find.text(payload.questionNative)), findsOneWidget);
      await settleCard(tester);
    });
  });

  group('33-2 · 33-3 · 33-4 dialogue_answer', () {
    // CATCHES: a beginner asked by voice, a chip that is «wrong», an answer without its mode and filler, «Next» open
    // with an empty slot.
    testWidgets('beginner — chips: the frame with an empty slot; any chip — passed (chips, filler_index); «Next»', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(dialogueAt(2), probe, voice: voice, level: PlanLevel.beginner));
      expect(find.text('Собери ответ'), findsOneWidget);
      expect(find.text('любое — твой ответ'), findsOneWidget);
      expect(ownFrame(tester).slot, isNull);
      expect(ownFrame(tester).look, SlotLook.empty);
      expect(find.text('У него болит ___.'), findsOneWidget);
      expect(dockEnabled(tester, 'Дальше'), isFalse);

      await tester.tap(find.byKey(const ValueKey('chip-1')));
      await tester.pump();
      expect(results(probe), [SessionResult.passed]);
      expect(probe.answers.single.response?.mode, 'chips');
      expect(probe.answers.single.response?.fillerIndex, 1);
      expect(ownFrame(tester).slot, 'neck');
      expect(find.text('У него болит шея.'), findsOneWidget);
      expect(tester.widget<SessionTile>(find.byKey(const ValueKey('chip-1'))).selected, isTrue);
      expect(voice.played, contains('p1.f2@1.0'), reason: 'the chip voices the phrase with its filler');
      expect(dockEnabled(tester, 'Дальше'), isTrue);
      expect(probe.nexts, 0);
      await tapText(tester, 'Дальше');
      expect(probe.nexts, 1);
      await settleCard(tester);
    });

    // CATCHES: the line of 33-3 not on screen, the pass demanding the lesson's filler, the key not underlined.
    testWidgets('intermediate — voice with the line: the key underlined; the frame covered, any slot — passed (voice_hint)', (tester) async {
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(dialogueAt(2), probe));
      expect(find.text('Скажи свою реплику'), findsOneWidget);
      expect(find.text('У него болит поясница.'), findsOneWidget);
      final line = tester.widget<SessionFrameText>(find.byType(SessionFrameText).first);
      expect(line.before, 'It hurts in his lower back.');
      expect(line.underline, const TextRange(start: 0, end: 15), reason: 'the key «It hurts in his»');

      await sayDebug(tester, 'It hurts in his knee');
      expect(results(probe), [SessionResult.passed]);
      expect(probe.answers.single.response?.mode, 'voice_hint');
      expect(probe.answers.single.response?.heard, 'It hurts in his knee');
      expect(find.byKey(const ValueKey('bubble-mark-passed')), findsOneWidget);
      await settleCard(tester);
      expect(probe.nexts, 1, reason: 'a voice pass leaves by itself');
    });

    // CATCHES: «No hints» that still shows the line, a blind answer without its mode, two misses written as failed.
    testWidgets('«No hints» — blind at any level: an empty slot, passed (voice_blind); two misses — skipped', (tester) async {
      for (final level in PlanLevel.values) {
        final probe = CardProbe();
        await pumpCard(tester, probeEnv(dialogueAt(2), probe, level: level, noHints: true));
        expect(find.text('Скажи свою реплику'), findsOneWidget, reason: level.name);
        expect(find.text('It hurts in his lower back.'), findsNothing);
        expect(ownFrame(tester).look, SlotLook.empty);
        expect(find.byType(SessionTile), findsNothing, reason: 'no chips');
        await sayDebug(tester, 'It hurts in his neck');
        expect(results(probe), [SessionResult.passed]);
        expect(probe.answers.single.response?.mode, 'voice_blind');
        expect(ownFrame(tester).slot, 'neck');
        expect(find.text('У него болит шея.'), findsOneWidget);
        await settleCard(tester);
      }

      final miss = CardProbe();
      await pumpCard(tester, probeEnv(dialogueAt(2), miss, noHints: true));
      await sayDebug(tester, 'lower back');
      expect(miss.answers, isEmpty);
      await sayDebug(tester, 'neck');
      expect(results(miss), [SessionResult.skipped]);
      expect(miss.answers.single.attempts, 2);
      expect(miss.answers.single.response?.mode, 'voice_blind');
      await tapText(tester, 'Дальше');
      expect(miss.nexts, 1);
      await settleCard(tester);
    });
  });

  group('33-5 dialogue_ask', () {
    // RULE (SESSION-2b §2, кадр 33-5, контракт BACK-TAILS-1 §1.5): the whole exchange is ONE card — the learner asks
    // by voice, the partner's reply comes with its TEXT CLOSED (a wave and «listen»), the card asks its own check,
    // and only the answer opens the text and marks the right option.
    // CATCHES: the reply's text shown before the check is answered (the question answers itself), an answer sent
    // before the choice is known (the choice then has no way to the server — no copy, no return), a check asked
    // before the learner has spoken.
    testWidgets('the reply is closed until the check is answered; the choice sends nothing', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      final card = dialogueAt(12);
      final check = (card.payload as DialogueAnswerPayload).check!;
      expect(check.options, hasLength(4));
      await pumpCard(tester, probeEnv(card, probe, voice: voice));
      expect(find.text('Скажи свою реплику'), findsOneWidget);
      expect(find.text('No, an X-ray is not needed for a muscle strain.'), findsNothing);
      expect(find.text(check.questionNative), findsNothing, reason: 'the check waits for the learner to speak');

      await sayDebug(tester, 'Do we need an X-ray');
      expect(probe.answers, isEmpty, reason: 'the answer waits for the choice — both fly together');
      await tester.pump();
      expect(find.text('Что тебе сказали?'), findsOneWidget);
      expect(find.text(check.questionNative), findsOneWidget);
      expect(find.byKey(const ValueKey('reply-wave')), findsOneWidget, reason: 'the reply sounds with its text closed');
      expect(find.text('No, an X-ray is not needed for a muscle strain.'), findsNothing);
      expect(voice.played, contains('x7@1.0'));
      for (final o in check.options) {
        expect(find.byKey(ValueKey('option-${o.id}')), findsOneWidget);
      }

      await tapText(tester, check.options.firstWhere((o) => o.id == check.correct).text);
      await tester.pump();
      expect(results(probe), [SessionResult.passed], reason: 'the voice result, as it was when the learner spoke');
      expect(probe.answers.single.choice, check.correct, reason: 'the choice rides beside it (BACK-TAILS-1 §1)');
      expect(find.text('No, an X-ray is not needed for a muscle strain.'), findsOneWidget, reason: 'the text opens');
      await settleCard(tester);
      expect(probe.nexts, 1, reason: 'a right answer leaves by itself, as in every other check');
    });

    // CATCHES: a wrong choice that leaves by itself, and one that keeps the reply closed (the learner never learns
    // what was said).
    testWidgets('a wrong option — the text opens anyway, «Next» by hand', (tester) async {
      final probe = CardProbe();
      final card = dialogueAt(12);
      final check = (card.payload as DialogueAnswerPayload).check!;
      await pumpCard(tester, probeEnv(card, probe));
      await sayDebug(tester, 'Do we need an X-ray');
      await tester.pump();
      final wrong = check.options.firstWhere((o) => o.id != check.correct);
      await tapText(tester, wrong.text);
      await tester.pump();
      expect(probe.answers.single.choice, wrong.id, reason: 'the server needs the wrong choice too — it returns the exchange');
      expect(probe.answers.single.result, SessionResult.passed, reason: 'the voice result does not change with the choice');
      expect(find.text('No, an X-ray is not needed for a muscle strain.'), findsOneWidget);
      await settleCard(tester);
      expect(probe.nexts, 0);
      await tapText(tester, 'Дальше');
      expect(probe.nexts, 1);
    });

    // ДЕФЕКТ: «варианты без вопроса» (живой проход 18.09, обмены x5/x6): на экране четыре варианта, а строки задания и
    // самого вопроса нет — отвечать не на что, и варианты читаются как чужие.
    // ПРАВИЛО (правка прохода 21.09, наряд CLIENT-CONV-1b, порядок кадра 33-1): своя реплика → закрытый ответ
    // собеседника → ОТДЕЛЬНЫЙ блок «Что тебе сказали?» с вопросом и вариантами под ним — вопрос больше не стоит между
    // репликами. Блок целиком на экране, какой бы длинной ни была лента разговора.
    // CATCHES: the question back between the two lines, where it read as a line of the talk; the task or the question
    // pushed off the screen by a long conversation.
    testWidgets('варианты без вопроса: блок «Что тебе сказали?» под пузырём, над вариантами, лента уезжает под шапку', (tester) async {
      final raw = jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;
      final dialogue = (raw['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == 'dialogue');
      for (final c in (dialogue['cards'] as List).cast<Map<String, dynamic>>().where((c) => (c['position'] as int) < 12)) {
        c['result'] = 'passed';
        c['attempts'] = 1;
      }
      final cards = SessionDay.fromJson(raw).stageOf(PlanStage.dialogue)!.cards;
      final card = cards.firstWhere((c) => c.position == 12);
      final check = (card.payload as DialogueAnswerPayload).check!;
      final feed = DialogueFeed.before(cards, card);
      expect(feed.length, greaterThan(6), reason: 'the conversation is long enough to push the task off the old layout');
      await pumpCard(tester, probeEnv(card, CardProbe(), feed: feed), size: const Size(390, 844));
      await sayDebug(tester, 'Do we need an X-ray');
      await tester.pump();

      final task = tester.getRect(find.text('Что тебе сказали?'));
      final question = tester.getRect(find.byKey(const ValueKey('check-question')));
      final reply = tester.getRect(find.byKey(const ValueKey('reply-wave')));
      final option = tester.getRect(find.byKey(ValueKey('option-${check.options.first.id}')));
      expect(reply.bottom, lessThanOrEqualTo(task.top), reason: 'блок стоит под закрытым ответом, а не между репликами');
      expect(task.bottom, lessThanOrEqualTo(question.top), reason: 'задание над вопросом');
      expect(question.bottom, lessThanOrEqualTo(option.top), reason: 'вопрос над вариантами — один блок');
      expect(option.bottom, lessThanOrEqualTo(844), reason: 'the whole block is on the screen');
      expect(tester.widget<Text>(find.byKey(const ValueKey('check-question'))).style, AppTextSession.question,
          reason: 'вопрос — крупно, стилем вопроса карточки');
      expect(find.byKey(const ValueKey('check-block')), findsOneWidget);
    });

    // ПРАВИЛО: наряд FIX-1 §1 — вопрос и варианты принадлежат обмену ЭТОЙ карточки, а не соседнему.
    // ЛОВИТ: вопрос, взятый из следующей карточки дня (ровно так это и читалось на телефоне: на «What are the
    // neighbors like?» стояли варианты про автобусную остановку — вопрос соседнего обмена, уехавший за верх).
    testWidgets('вопрос и варианты — того обмена, который ведёт карточка', (tester) async {
      final card = dialogueAt(12);
      final check = (card.payload as DialogueAnswerPayload).check!;
      // The check of another exchange of the same day — none of it may appear on this card.
      final other = (dialogueAt(1).payload as DialoguePartnerPayload);
      await pumpCard(tester, probeEnv(card, CardProbe(), feed: DialogueFeed.before(day.stageOf(PlanStage.dialogue)!.cards, card)));
      await sayDebug(tester, 'Do we need an X-ray');
      await tester.pump();
      expect(find.text(check.questionNative), findsOneWidget);
      expect(find.text(other.questionNative), findsNothing, reason: 'the question of another exchange');
      for (final o in other.options) {
        if (check.options.any((own) => own.text == o.text)) continue;
        expect(find.text(o.text), findsNothing, reason: 'the option «${o.text}» belongs to another exchange');
      }
      await settleCard(tester);
    });

    // ДЕФЕКТ: «ответ агента открывается текстом сразу и не озвучивается» (живой проход 18.09, обмен x5): микрофон
    // срезал реплику, карточка закрылась `skipped` — и ответ собеседника ученик встретил простым текстом в ленте
    // СЛЕДУЮЩЕЙ карточки, ни разу не услышав его.
    // ПРАВИЛО: наряд FIX-1 §1 — обмен доигрывается до конца и после двух неудач: ответ приходит закрытым пузырём,
    // звучит, и проверка задаётся; ответ карточки (`skipped`) ждёт выбора и уходит вместе с ним.
    testWidgets('после двух неудач ответ всё равно приходит закрытым и звучит, и проверка задаётся', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      final card = dialogueAt(12);
      final check = (card.payload as DialogueAnswerPayload).check!;
      await pumpCard(tester, probeEnv(card, probe, voice: voice));
      await sayDebug(tester, 'hello');
      await tester.pump();
      expect(find.byKey(const ValueKey('reply-wave')), findsNothing, reason: 'one miss — the exchange goes on');
      await sayDebug(tester, 'how are you');
      await tester.pump();

      expect(find.byKey(const ValueKey('reply-wave')), findsOneWidget, reason: 'the reply comes closed');
      expect(voice.played, contains('x7@1.0'), reason: 'and sounds by itself');
      expect(find.text('Что тебе сказали?'), findsOneWidget);
      expect(find.text(check.questionNative), findsOneWidget);
      expect(find.text('No, an X-ray is not needed for a muscle strain.'), findsNothing, reason: 'the text stays closed');
      expect(probe.answers, isEmpty, reason: 'the answer waits for the choice — both fly together');

      await tapText(tester, check.options.firstWhere((o) => o.id == check.correct).text);
      await tester.pump();
      expect(results(probe), [SessionResult.skipped], reason: 'the voice result is what it was');
      expect(probe.answers.single.choice, check.correct);
      expect(find.text('No, an X-ray is not needed for a muscle strain.'), findsOneWidget);
      await settleCard(tester);
    });

    // CATCHES: «Skip» that keeps the learner on the exchange it was tapped to leave.
    testWidgets('«Пропустить» закрывает карточку сразу — проверка не задаётся', (tester) async {
      final probe = CardProbe();
      await pumpCard(tester, probeEnv(dialogueAt(12), probe));
      await tapText(tester, 'Пропустить');
      await tester.pump();
      expect(results(probe), [SessionResult.skipped]);
      expect(probe.answers.single.choice, isNull);
      expect(probe.nexts, 1);
      await settleCard(tester);
    });

    // CATCHES: a card without the check left without a way out (the day could not build one — the three keys are
    // absent together).
    testWidgets('no check in the payload — the reply opens at once and «Next» stands under it', (tester) async {
      final probe = CardProbe();
      final bare = fixtureCardEdited('day-doctor', 'dialogue_ask', (p) {
        p.remove('question_native');
        p.remove('options');
        p.remove('correct');
      });
      expect((bare.payload as DialogueAnswerPayload).check, isNull);
      await pumpCard(tester, probeEnv(bare, probe));
      await sayDebug(tester, 'Do we need an X-ray');
      await tester.pump();
      expect(results(probe), [SessionResult.passed], reason: 'nothing to wait for — the answer flies at once');
      expect(probe.answers.single.choice, isNull);
      expect(find.text('No, an X-ray is not needed for a muscle strain.'), findsOneWidget);
      await tapText(tester, 'Дальше');
      expect(probe.nexts, 1);
      await settleCard(tester);
    });
  });

  group('33-6 dialogue_rescue', () {
    // CATCHES: a rescue with a microphone, a repeat at the normal tempo, a rescue that writes anything but passed.
    testWidgets('«Didn\'t catch that» in the bubble — the rescue line, the slow repeat with its text; «Next» → passed', (tester) async {
      final probe = CardProbe();
      final voice = QuietVoice();
      await pumpCard(tester, probeEnv(dialogueAt(10), probe, voice: voice));
      expect(find.text('Не понял — переспроси'), findsOneWidget);
      expect(find.text('It looks like a muscle strain, so he should rest and use a heating pad.'), findsOneWidget);
      await tester.pump(const Duration(milliseconds: 300));
      expect(voice.played, ['x5@1.0']);
      expect(find.text('Sorry, could you say that more slowly?'), findsNothing);

      await tester.tap(find.byKey(const ValueKey('rescue-not-understood')));
      await tester.pump();
      await tester.pump();
      expect(find.text('Sorry, could you say that more slowly?'), findsOneWidget);
      expect(find.text('He should rest and use a heating pad.'), findsOneWidget);
      expect(voice.played, ['x5@1.0', 'x6b@1.0', 'x6@0.75']);
      expect(find.text('медленно'), findsOneWidget);
      expect(find.byKey(const ValueKey('session-debug-heard')), findsNothing, reason: 'no microphone on the frame');

      await tapText(tester, 'Дальше');
      expect(results(probe), [SessionResult.passed]);
      expect(probe.nexts, 1);
      await settleCard(tester);
    });
  });

  group('33-7 the conversation above the card', () {
    // CATCHES: a card drawn without the exchanges before it, and a passed answer without its mark.
    testWidgets('the exchanges before stand above the card, the own lines with their marks', (tester) async {
      final raw = jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;
      final dialogue = (raw['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == 'dialogue');
      for (final c in (dialogue['cards'] as List).cast<Map<String, dynamic>>().where((c) => (c['position'] as int) < 4)) {
        c['result'] = 'passed';
        c['attempts'] = 1;
      }
      final answered = SessionDay.fromJson(raw);
      final cards = answered.stageOf(PlanStage.dialogue)!.cards;
      final current = cards.firstWhere((c) => c.position == 4);
      await pumpCard(tester, probeEnv(current, CardProbe(), feed: DialogueFeed.before(cards, current)));
      expect(find.byKey(const ValueKey('session-feed')), findsOneWidget);
      expect(find.text('Where does it hurt: his upper back or his lower back?'), findsOneWidget);
      expect(find.text('It hurts in his lower back.'), findsOneWidget);
      expect(find.byKey(const ValueKey('bubble-mark-passed')), findsOneWidget);
      expect(find.text('Did it start today, or earlier this week?'), findsOneWidget, reason: 'the current exchange once');
      expect(
        tester.getRect(find.text('It hurts in his lower back.')).bottom,
        lessThan(tester.getRect(find.text('Did it start today, or earlier this week?')).top),
        reason: 'the conversation grows from the bottom: the past above, the current exchange by the dock',
      );
      await settleCard(tester);
    });

    // CATCHES: the answer after a rescue repeating the rescued line under the slow repeat (live pass, SESSION-1c).
    testWidgets('after «Didn\'t catch that»: the partner line once, above the rescue; the own answer by the dock', (tester) async {
      final raw = jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;
      final dialogue = (raw['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == 'dialogue');
      for (final c in (dialogue['cards'] as List).cast<Map<String, dynamic>>().where((c) => (c['position'] as int) < 11)) {
        c['result'] = 'passed';
        c['attempts'] = 1;
      }
      final cards = SessionDay.fromJson(raw).stageOf(PlanStage.dialogue)!.cards;
      final current = cards.firstWhere((c) => c.position == 11);
      await pumpCard(tester, probeEnv(current, CardProbe(), feed: DialogueFeed.before(cards, current)));
      const asked = 'It looks like a muscle strain, so he should rest and use a heating pad.';
      expect(find.text(asked), findsOneWidget);
      final rescue = tester.getRect(find.text('Sorry, could you say that more slowly?'));
      final repeat = tester.getRect(find.text('He should rest and use a heating pad.'));
      expect(tester.getRect(find.text(asked)).bottom, lessThan(rescue.top));
      expect(rescue.bottom, lessThan(repeat.top));
      expect(repeat.bottom, lessThan(tester.getRect(find.byType(SessionOwnRow).last).top), reason: 'the own answer stands after the repeat');
      await settleCard(tester);
    });

    // DEFECT: «варианты без вопроса» (живой проход 18.09, обмен x6). ПРАВИЛО: наряд FIX-1 §1 — строка задания и
    // вопрос под ней НЕ уезжают за верх экрана, как бы ни выросла лента: прокручивается только разговор.
    // CATCHES: the group that used to hold the task line and the bubbles together — with six exchanges above it, the
    // task and the question stood off the screen and the learner was left with four options and no question.
    testWidgets('the last exchange on a small phone: the task and the question stand, the beginning is a scroll up', (tester) async {
      final raw = jsonDecode(File('../backend2/docs/fixtures/day-doctor.json').readAsStringSync()) as Map<String, dynamic>;
      final dialogue = (raw['stages'] as List).cast<Map<String, dynamic>>().firstWhere((s) => s['stage'] == 'dialogue');
      final all = (dialogue['cards'] as List).cast<Map<String, dynamic>>();
      for (final c in all.where((c) => (c['position'] as int) < all.length)) {
        c['result'] = 'passed';
        c['attempts'] = 1;
      }
      final cards = SessionDay.fromJson(raw).stageOf(PlanStage.dialogue)!.cards;
      final current = cards.last;
      await pumpCard(tester, probeEnv(current, CardProbe(), feed: DialogueFeed.before(cards, current)), size: const Size(375, 667));
      expect(tester.takeException(), isNull);
      final first = find.text('Where does it hurt: his upper back or his lower back?');
      expect(tester.getRect(first).top, lessThan(0), reason: 'the beginning stands above the screen');
      final task = tester.getRect(find.byType(SessionTask));
      expect(task.top, greaterThanOrEqualTo(0), reason: 'the task line is on the screen, whatever the conversation does');
      expect(task.bottom, lessThan(667));
      // …and so is the exchange the card is running: it stands under the task, not above the top edge.
      expect(tester.getRect(find.byType(SessionOwnRow).last).bottom, lessThanOrEqualTo(667.0));

      await tester.drag(find.byType(SingleChildScrollView), const Offset(0, 20000));
      await tester.pump(const Duration(seconds: 1));
      expect(tester.takeException(), isNull);
      expect(tester.getRect(first).top, greaterThanOrEqualTo(0), reason: 'scrolled back to the beginning');
      expect(tester.getRect(find.byType(SessionTask)).top, greaterThanOrEqualTo(0), reason: 'the task did not move with it');
      await settleCard(tester);
    });
  });
}

import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/gestures.dart' show PointerDeviceKind;
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/data/plan/conversation/conversation_models.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/plan/conversation/conversation_controller.dart';
import 'package:eng_std/features/plan/conversation/talk_ribbon.dart';
import 'package:eng_std/features/plan/conversation/talk_summary.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart' show SessionListenButton;
import 'package:eng_std/features/plan/session/parts/session_bubbles.dart' show SessionBubble;
import 'package:eng_std/features/plan/session/parts/session_mic_panel.dart' show SessionTextExit;
import 'package:eng_std/features/plan/session/session_mic.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/session_harness.dart' show SilentRecognizer, enterHeard;
import '../../../support/talk_harness.dart';

/// THE TALK WITH THE AGENT ON THE REAL SCREEN (наряд CLIENT-CONV-1a, кадры 37-6…37-12).
///
/// Every document here is a snapshot of the live server — a talk that was actually had on
/// `wordtrainer_e2e_test`, with its rescue, its hint and its summary.
DioException _problem(int code, String problem) => DioException(
  requestOptions: RequestOptions(path: '/x'),
  type: DioExceptionType.badResponse,
  response: Response(requestOptions: RequestOptions(path: '/x'), statusCode: code, data: {'code': problem}),
);

/// Сказать через дев-поле «что услышал» — та же дорога, что у настоящей записи: пауза, потом ход.
Future<void> _say(WidgetTester tester, {String text = 'He has had it for three days.'}) async {
  await tester.tap(find.byKey(const ValueKey('talk-mic')));
  await tester.pump();
  await enterHeard(tester, text);
  await tester.pump(const Duration(milliseconds: 1600));
  await tester.pump();
  await tester.pump();
}

DioException _offline() => DioException(requestOptions: RequestOptions(path: '/x'), type: DioExceptionType.connectionError);

void main() {
  final open = talkFixture('conversation-day-open');
  final ended = talkFixture('conversation-day-ended');

  /// The phrases of the day behind the server's `phrases_used` — the sage underline needs their text.
  const phrases = {'p1': 'My son has a fever.', 'p2': 'He has had it for three days.'};

  group('37-6…37-9 · лента и микрофон', () {
    // ПРАВИЛО НАРЯДА — ОДНО НА ВСЕ ЭКРАНЫ: микрофон открывается ТОЛЬКО по тапу. Ни конец реплики
    // роли, ни чип, ни сбой не включают запись сами (DECISIONS п. 299).
    // ЛОВИТ: «микрофон открывается сам по концу реплики собеседника» — отменённое правило DAY-FIX-3,
    // которое в разговоре выглядит особенно естественно и потому особенно легко возвращается.
    testWidgets('микрофон не открывается сам', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases);

      // Реплика роли доиграла — ход ученика, и запись всё ещё не идёт.
      await finishLine(tester, stand);
      expect(stand.talk.phase, TalkPhase.yourTurn);
      expect(stand.mics.single.state, MicState.idle);

      // Пять секунд молчания поднимают чип — и не поднимают микрофон.
      await tester.pump(const Duration(seconds: 6));
      expect(stand.talk.chipShown, isTrue);
      expect(stand.mics.single.state, MicState.idle);

      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      expect(stand.mics.single.state, MicState.listening, reason: 'и открывается по тапу');
      await settleTalk(tester);
    });

    // ПРАВИЛО (кадр 37-7): «Не понял» стоит СЛЕВА ВО ВСЕХ состояниях твоей очереди — переспрос не
    // подсказка, и в «Без подсказок» он тоже остаётся. Ход сцены он не тратит: это вопрос сервера,
    // клиент только шлёт `kind: rescue`.
    // ЛОВИТ: «Не понял», исчезающий на время записи или вместе с подсказками.
    testWidgets('«Не понял» есть во всех состояниях твоей очереди', (tester) async {
      final probe = TalkProbe()..documents.addAll([open, open]);
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases);
      await finishLine(tester, stand);
      expect(find.byKey(const ValueKey('talk-rescue')), findsOneWidget, reason: 'в покое');

      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      expect(stand.mics.single.state, MicState.listening);
      expect(find.byKey(const ValueKey('talk-rescue')), findsOneWidget, reason: 'и пока идёт запись');

      await tester.pump(const Duration(seconds: 6));
      expect(find.byKey(const ValueKey('talk-rescue')), findsOneWidget, reason: 'и после того, как встал чип');

      await tester.tap(find.byKey(const ValueKey('talk-rescue')));
      await tester.pump();
      await tester.pump();
      expect(probe.moves, [(kind: 'rescue', heard: null)]);
      await settleTalk(tester);
    });

    // ПРАВИЛО (кадр 37-9): тап по микрофону, пока роль говорит, ПРЕРЫВАЕТ её: звук останавливается,
    // реплика остаётся в ленте с пометкой «прервано», и микрофон уже слушает. Серверу об этом не
    // говорят — у него нет такого поля и разговор от этого не изменился.
    // ЛОВИТ: прерывание, которое глушит звук и оставляет микрофон закрытым (тап пропал впустую), и
    // ход, отправленный на сервер вместо тишины.
    testWidgets('прерывание останавливает звук и слушает', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases);
      expect(stand.talk.phase, TalkPhase.agentSpeaking);
      expect(stand.voice.speaking, isTrue);
      expect(find.byKey(const ValueKey('talk-interrupted')), findsNothing);

      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      await tester.pump();
      expect(stand.voice.speaking, isFalse, reason: 'звук остановлен');
      expect(stand.talk.phase, TalkPhase.yourTurn);
      expect(stand.mics.single.state, MicState.listening, reason: 'микрофон уже слушает');
      expect(find.byKey(const ValueKey('talk-interrupted')), findsOneWidget);
      expect(probe.moves, isEmpty, reason: 'прерывание — дело телефона');
      await settleTalk(tester);
    });

    // RULE (правка прохода 21.09, наряд CLIENT-CONV-1b): THE ROLE'S TEXT IS OPEN WITH ITS VOICE — the words stand from
    // the first sound, «прослушать» 28 in the top right corner of the light container (12 from the top, 14 from the
    // edge, as 37-8 draws an open line), and there is no «текст» to tap. «Прервано» stands over the words of a line cut
    // off (37-9). Under «Без подсказок» the lines stay CLOSED, as before: the wave alone while the role speaks, then
    // «прослушать» 28 alone (12 + 28 + 12), and nothing that opens them.
    // CATCHES: a line that waits for a tap to be read (the pass of 21.09), a «текст» chip left in the container, and
    // the text opened under «Без подсказок».
    testWidgets('текст роли открыт по умолчанию, закрыт в «Без подсказок»', (tester) async {
      Finder inTurn(int index, Finder f) => find.descendant(of: find.byKey(ValueKey('turn-$index')), matching: f);
      Rect plate(int index) => tester.getRect(inTurn(index, find.byType(SessionBubble)));
      Rect circle(int index) => tester.getRect(inTurn(index, find.byKey(const ValueKey('talk-listen')))).deflate(8);

      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases);

      // The role is speaking — and its words are already on screen, the corner circle holding the wave.
      expect(stand.talk.phase, TalkPhase.agentSpeaking);
      expect(find.text('слушай'), findsOneWidget, reason: '«слушай» over the dimmed button while the role speaks');
      expect(find.text('I see. How high is his temperature, and does he have a sore throat?'), findsOneWidget);
      expect(inTurn(5, find.byKey(const ValueKey('talk-line-wave'))), findsNothing, reason: 'no wave-only plate: the text is open');
      expect(tester.widget<SessionListenButton>(inTurn(5, find.byType(SessionListenButton))).playing, isTrue);
      for (final index in [1, 5]) {
        final p = plate(index);
        final c = circle(index);
        expect(tester.widget<SessionListenButton>(inTurn(index, find.byType(SessionListenButton))).size, 28);
        expect(p.right - c.right, moreOrLessEquals(14, epsilon: 0.5), reason: 'turn $index: 14 from the right edge');
        expect(c.top - p.top, moreOrLessEquals(12, epsilon: 0.5), reason: 'turn $index: 12 from the top');
      }
      expect(find.text('текст'), findsNothing, reason: 'there is nothing to open');
      expect(find.byType(SessionTextExit), findsNothing);

      // Cut off: «прервано» over the open words, inside the container.
      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      await tester.pump();
      final mark = tester.getRect(inTurn(5, find.byKey(const ValueKey('talk-interrupted'))));
      expect(mark.top, greaterThanOrEqualTo(plate(5).top));
      expect(find.text('I see. How high is his temperature, and does he have a sore throat?'), findsOneWidget);
      await settleTalk(tester);

      // «Без подсказок»: closed — the wave alone while the role speaks, then the circle alone.
      final blind = talkFixtureEdited('conversation-day-open', (json) {
        (json['hints'] as Map<String, dynamic>)
          ..['enabled'] = false
          ..['native'] = null;
      });
      final blindProbe = TalkProbe()..documents.add(blind);
      final blindStand = await pumpTalk(tester, blindProbe, hints: false, phraseTexts: phrases);
      expect(inTurn(5, find.byKey(const ValueKey('talk-line-wave'))), findsOneWidget, reason: 'the role speaks — the wave alone');
      expect(find.text('I see. How high is his temperature, and does he have a sore throat?'), findsNothing);
      final closed = plate(1);
      expect(closed.height, moreOrLessEquals(52, epsilon: 0.5), reason: '12 + 28 + 12, as in the frame');
      expect(circle(1).left - closed.left, moreOrLessEquals(14, epsilon: 0.5));
      await finishLine(tester, blindStand);
      expect(find.text('текст'), findsNothing, reason: 'no way to open a line under «Без подсказок»');
      await settleTalk(tester);
    });

    // RULE: the circle inside the container is a live button — a tap on it plays EXACTLY this line, open (with hints)
    // and closed (under «Без подсказок»): the 44 touch box stands 8 into the container's padding, the 28 circle where
    // the frame draws it.
    // CATCHES: a button the finger cannot reach because the container clipped its touch box.
    testWidgets('«прослушать» в контейнере играет свою реплику — открытую и закрытую', (tester) async {
      Finder inTurn(int index, Finder f) => find.descendant(of: find.byKey(ValueKey('turn-$index')), matching: f);

      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases);
      await finishLine(tester, stand);
      // The first line's words are open (CLIENT-CONV-1b), so the ribbon is taller than the screen and rests on the
      // newest line: bring the first one into view before aiming at its corner.
      await Scrollable.ensureVisible(tester.element(inTurn(1, find.byKey(const ValueKey('talk-listen')))), alignment: .5);
      await tester.pump();
      final opened = tester.getRect(inTurn(1, find.byKey(const ValueKey('talk-listen'))));
      await tester.tapAt(opened.topRight + const Offset(-3, 3), kind: PointerDeviceKind.touch);
      await tester.pump();
      expect(stand.voice.played.last, 'talk-1', reason: 'the corner of the open container\'s 44 touch box is the button too');
      stand.voice.finish();
      await settleTalk(tester);

      final blind = talkFixtureEdited('conversation-day-open', (json) {
        (json['hints'] as Map<String, dynamic>)
          ..['enabled'] = false
          ..['native'] = null;
      });
      final blindProbe = TalkProbe()..documents.add(blind);
      final blindStand = await pumpTalk(tester, blindProbe, hints: false, phraseTexts: phrases);
      await finishLine(tester, blindStand);
      final closed = tester.getRect(inTurn(1, find.byKey(const ValueKey('talk-listen'))));
      await tester.tapAt(closed.topLeft + const Offset(3, 3), kind: PointerDeviceKind.touch);
      await tester.pump();
      expect(blindStand.voice.played.last, 'talk-1', reason: 'the edge of the closed container\'s 44 touch box is the button too');
      blindStand.voice.finish();
      await settleTalk(tester);
    });

    // ПРАВИЛО (кадр 37-7): «Не понял» и «Подсказать» — контурные плашки 44 по бокам микрофона, не
    // текст: «Не понял» — контур чернил, «Подсказать» — латунь. Плашки не заходят на кольцо микрофона.
    // ЛОВИТ: «Не понял» и «Подсказать» серыми словами — док до правки 21.09.
    testWidgets('37-7: «Не понял» и «Подсказать» — контурные плашки 44 по бокам микрофона', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases);
      await finishLine(tester, stand);

      final rescue = find.byKey(const ValueKey('talk-rescue'));
      final hint = find.byKey(const ValueKey('talk-hint'));
      expect(tester.widget<TalkPill>(rescue).brass, isFalse, reason: '«Не понял» — контур чернил');
      expect(tester.widget<TalkPill>(hint).brass, isTrue, reason: '«Подсказать» — латунь');
      expect(tester.getSize(rescue).height, 44);
      expect(tester.getSize(hint).height, 44);
      expect(find.descendant(of: find.byType(TalkDock), matching: find.byType(SessionTextExit)), findsNothing, reason: 'не текстом');
      final mic = tester.getRect(find.byKey(const ValueKey('talk-mic')));
      expect(tester.getRect(rescue).right, lessThanOrEqualTo(mic.center.dx - 44), reason: 'не заходит на кольцо 88');
      expect(tester.getRect(hint).left, greaterThanOrEqualTo(mic.center.dx + 44), reason: 'не заходит на кольцо 88');
      await settleTalk(tester);
    });

    // ПРАВИЛО (кадр 37-7): плашка — по своему слову: контур 1,5 + 14 + слово + 14 + контур 1,5, а не во всю
    // отведённую ей половину дока.
    // ЛОВИТ: плашку, растянутую до кольца микрофона, — первый рендер правки 21.09.
    testWidgets('плашка 44 — по своему слову, а не во всю ширину', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Align(
            alignment: Alignment.centerLeft,
            child: ConstrainedBox(constraints: const BoxConstraints(maxWidth: 300), child: TalkPill(label: 'Hi', onTap: () {})),
          ),
        ),
      );
      final word = tester.getSize(find.text('Hi')).width;
      expect(tester.getSize(find.byType(TalkPill)).width, moreOrLessEquals(1.5 + 14 + word + 14 + 1.5, epsilon: 0.5));
      expect(tester.getSize(find.byType(TalkPill)).height, 44);
    });

    // ПРАВИЛО (кадр 37-8): свой пузырь — ТОЛЬКО распознанный текст; фразы плана в нём подчёркнуты
    // шалфеем, и какие именно — говорит СЕРВЕР (`phrases_used`), а не сверка на телефоне.
    // ЛОВИТ: перевод под своей репликой и подчерк, нарисованный по своему совпадению слов.
    testWidgets('свой пузырь — только сказанное, фразы плана подчёркнуты по ответу сервера', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      await pumpTalk(tester, probe, phraseTexts: phrases);
      expect(find.text('He has had it for three days.'), findsOneWidget);
      final line = tester.widget<TalkSageUnderline>(find.byType(TalkSageUnderline));
      expect(line.marks, isNotEmpty, reason: 'сервер услышал p2');
      expect([for (final m in line.marks) line.text.substring(m.start, m.end)], ['He', 'has', 'had', 'it', 'for', 'three', 'days']);
      await settleTalk(tester);
    });

    // ПРАВИЛО: `phrases_used` называет фразу ССЫЛКОЙ, без текста и без границ. Текста фразы у дня
    // нет — подчерка нет вовсе: клиент не гадает, что именно услышал сервер (отчёт §5).
    testWidgets('фраза, текста которой у дня нет, не подчёркивается', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      await pumpTalk(tester, probe);
      expect(tester.widget<TalkSageUnderline>(find.byType(TalkSageUnderline)).marks, isEmpty);
      await settleTalk(tester);
    });

    // ПРАВИЛО (кадр 37-7, «после „Не понял"»): переспрос виден в ленте — пометкой на стороне ученика.
    // Тёмный пузырь — только сказанное, а слов у переспроса сервер не пишет (`text_target` пуст),
    // поэтому «Sorry?» канвы телефон не выдумывает.
    // ЛОВИТ: две одинаковые реплики роли подряд без единого знака между ними — так лента выглядела в
    // живом прогоне; и пустой тёмный пузырь на месте переспроса.
    testWidgets('переспрос — пометка в ленте, не тёмный пузырь', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      await pumpTalk(tester, probe, phraseTexts: phrases);

      // Журнал: 1 роль · 2 переспрос · 3 роль повторяет · 4 сказал · 5 роль.
      expect(find.byType(TalkRescueMark), findsOneWidget);
      expect(find.text('переспросил'), findsOneWidget);
      expect(find.byType(TalkOwnBubble), findsOneWidget, reason: 'тёмный пузырь — только у сказанного (ход 4)');
      final mark = tester.getTopLeft(find.byKey(const ValueKey('turn-2'))).dy;
      expect(tester.getTopLeft(find.byKey(const ValueKey('turn-1'))).dy, lessThan(mark));
      expect(tester.getTopLeft(find.byKey(const ValueKey('turn-3'))).dy, greaterThan(mark), reason: 'пометка стоит между двумя репликами роли');
      await settleTalk(tester);
    });
  });

  group('37-7 · 37-9 · 37-10 · микрофон по кадру', () {
    // ПРАВИЛО (кадры 37-7 «слушаю», 37-9, приёмка снимков): кнопка слушает С ИКОНКОЙ МИКРОФОНА — квадрата «стоп» нет
    // («Вычтено: … квадрат „стоп"»); вокруг её 88-коробки — полоса шалфея 8 на 30 %; «говори, я слушаю» НАД кнопкой,
    // «тишина — конец» ПОД ней; повторный тап — стоп. Пока пишет, в доке «Не понял» и кнопка — «Подсказать» нет.
    // ЛОВИТ: квадрат «стоп» на записи и «тишина — конец» над кнопкой — снимки 06 и 08 до приёмки.
    testWidgets('слушаю: иконка микрофона, кольцо шалфея, «тишина — конец» под кнопкой; повторный тап — стоп', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases, recognizer: ListeningRecognizer());
      await finishLine(tester, stand);
      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      expect(stand.mics.single.state, MicState.listening);
      expect(tester.widget<Icon>(find.byKey(const ValueKey('talk-mic-glyph'))).icon, LucideIcons.mic, reason: 'иконка микрофона, не квадрат');
      final ring = tester.widget<TalkMicRing>(find.byKey(const ValueKey('talk-mic-ring')));
      expect(ring.color, AppColors.sessionListenRing, reason: 'шалфей 30 %');
      expect(ring.width, 8);
      // The ring stands up to 8 outside the button's 88 box; the words stand clear of it (14 in the frame).
      final ringBox = tester.getRect(find.byKey(const ValueKey('talk-mic'))).inflate(ring.width);
      expect(tester.getRect(find.text('говори, я слушаю')).bottom, lessThanOrEqualTo(ringBox.top), reason: 'над кнопкой, мимо кольца');
      expect(tester.getRect(find.text('тишина — конец')).top, greaterThanOrEqualTo(ringBox.bottom), reason: 'под кнопкой, мимо кольца');
      expect(find.byKey(const ValueKey('talk-hint')), findsNothing, reason: 'пока пишет — «Не понял» и кнопка');
      expect(find.byKey(const ValueKey('talk-rescue')), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      await tester.pump();
      expect(stand.mics.single.state, isNot(MicState.listening), reason: 'повторный тап — стоп');
      expect(probe.moves, isEmpty, reason: 'тишина не уходит на сервер');
      await settleTalk(tester);
    });

    // ПРАВИЛО (кадр 37-10, приёмка снимков): «не расслышал» — снова «твоя очередь»: вокруг кнопки латунное кольцо 2,
    // над ней «тап — говорить», а «не расслышал — скажи ещё раз» — ПОД ней.
    // ЛОВИТ: кольцо шалфея после строки «не расслышал» и строку на месте подписи над кнопкой.
    testWidgets('не расслышал: латунное кольцо «твоя очередь», строка под кнопкой', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases, recognizer: ListeningRecognizer());
      await finishLine(tester, stand);
      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      await tester.pump();
      expect(stand.talk.trouble, TalkTrouble.unheard);
      final ring = tester.widget<TalkMicRing>(find.byKey(const ValueKey('talk-mic-ring')));
      expect(ring.color, AppColors.brassInk, reason: 'латунь, не шалфей');
      expect(ring.width, 2);
      final mic = tester.getRect(find.byKey(const ValueKey('talk-mic')));
      expect(tester.getRect(find.text('тап — говорить')).bottom, lessThanOrEqualTo(mic.top), reason: '«тап — говорить» над кнопкой');
      expect(tester.getRect(find.text('не расслышал — скажи ещё раз')).top, greaterThanOrEqualTo(mic.bottom), reason: 'строка под кнопкой');
      expect(probe.moves, isEmpty);
      await settleTalk(tester);
    });
  });

  group('37-7 · «Без подсказок»', () {
    // ПРАВИЛО (кадр 37-7, примечание): в «Без подсказок» НЕТ НИ ЧИПА, НИ КНОПКИ «Подсказать», а
    // тексты реплик роли закрыты до итога — открывать их нечем.
    // ЛОВИТ: чип, встающий по таймеру независимо от режима, и кнопку «текст», оставленную на пузыре.
    testWidgets('в «Без подсказок» нет ни чипа, ни кнопки, тексты закрыты', (tester) async {
      final blind = talkFixtureEdited('conversation-day-open', (json) {
        (json['hints'] as Map<String, dynamic>)
          ..['enabled'] = false
          ..['native'] = null;
      });
      final probe = TalkProbe()..documents.add(blind);
      final stand = await pumpTalk(tester, probe, hints: false, phraseTexts: phrases);
      expect(probe.hints, isFalse, reason: 'режим уходит на сервер один раз, со стартом');
      await finishLine(tester, stand);
      await tester.pump(const Duration(seconds: 6));

      expect(find.byKey(const ValueKey('talk-hint-chip')), findsNothing);
      expect(find.byKey(const ValueKey('talk-hint')), findsNothing);
      expect(find.byKey(const ValueKey('talk-open-text')), findsNothing, reason: 'тексты закрыты до итога');
      final plate = tester.getRect(find.descendant(of: find.byKey(const ValueKey('turn-5')), matching: find.byType(SessionBubble)));
      expect(plate.width, moreOrLessEquals(56, epsilon: 0.5), reason: 'в контейнере один кружок: 14 + 28 + 14');
      expect(find.text('Hello. What brings you in today?'), findsNothing);
      expect(find.byKey(const ValueKey('talk-rescue')), findsOneWidget, reason: 'переспрос — не подсказка');
      await settleTalk(tester);
    });

    // ПРАВИЛО (кадр 37-7): подсказка приходит сама через `hints.delay_ms` молчания — или по нажатию,
    // и тогда кнопка уходит: вместе они не стоят. Префикс «Скажи, что …» печатает клиент, намерение
    // приходит готовым.
    // ЛОВИТ: чип и кнопку, стоящие рядом, и склонение намерения на телефоне.
    testWidgets('чип встаёт по молчанию, и «Подсказать» уходит вместе с ним', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases);
      await finishLine(tester, stand);
      expect(find.byKey(const ValueKey('talk-hint')), findsOneWidget);
      expect(find.byKey(const ValueKey('talk-hint-chip')), findsNothing);

      await tester.pump(const Duration(seconds: 6));
      expect(find.byKey(const ValueKey('talk-hint-chip')), findsOneWidget);
      expect(find.byKey(const ValueKey('talk-hint')), findsNothing);
      // Сервер шлёт намерение ЦЕЛЫМ предложением, и в «Скажи, что …» оно встаёт придаточным: строчная
      // буква, без точки. Живой прогон поймал «Скажи, что У моего сына температура.».
      expect(find.text('Скажи, что у моего сына температура'), findsOneWidget);
      await settleTalk(tester);
    });
  });

  group('37-8 · 37-10 · сказанное до ответа сервера', () {
    // ПРАВИЛО (кадр 37-8): сказанное встаёт в ленту СРАЗУ, а три точки «врач думает» — под ним.
    // Подчерка ещё нет: какие фразы прозвучали, скажет сервер вместе с ответом, и тогда его копия
    // реплики заменяет телефонную — не рядом с ней.
    // ЛОВИТ: ленту, где пока идёт запрос, стоят только три точки — ученик не видит, что его услышали
    // (живой прогон); и двойную реплику, когда ответ пришёл.
    testWidgets('ход в полёте — сказанное уже в ленте, три точки после него', (tester) async {
      const line = 'He has a sore throat.';
      final answered = talkFixtureEdited('conversation-day-open', (json) {
        final turns = json['turns'] as List<dynamic>;
        turns.addAll([
          {
            ...(turns[3] as Map<String, dynamic>),
            'index': 6,
            'text_target': line,
            'phrases_used': <Object>[],
          },
          {...(turns[4] as Map<String, dynamic>), 'index': 7},
        ]);
      });
      final hold = Completer<void>();
      final probe = TalkProbe()
        ..documents.addAll([open, answered])
        ..holdMove = hold;
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases);
      await finishLine(tester, stand);
      await _say(tester, text: line);

      expect(stand.talk.phase, TalkPhase.sending);
      final pending = find.byKey(const ValueKey('talk-pending'));
      expect(find.descendant(of: pending, matching: find.text(line)), findsOneWidget);
      expect(tester.widget<TalkSageUnderline>(find.descendant(of: pending, matching: find.byType(TalkSageUnderline))).marks, isEmpty,
          reason: 'подчерк — по ответу сервера, не раньше');
      expect(tester.getTopLeft(find.byKey(const ValueKey('talk-thinking'))).dy, greaterThan(tester.getTopLeft(pending).dy),
          reason: 'три точки — под сказанным');
      // ПРАВИЛО (§1.8 отчёта 1a, наряд CLIENT-CONV-1b): над погашенной кнопкой — «слушай», пока ход в полёте, как и
      // пока роль говорит: ученик ждёт ответа, его очередь не наступила.
      expect(find.text('слушай'), findsOneWidget, reason: 'the caption over the dimmed button while the turn is in flight');

      hold.complete();
      await tester.pump();
      await tester.pump();
      expect(pending, findsNothing);
      expect(find.byKey(const ValueKey('talk-thinking')), findsNothing);
      expect(find.text(line), findsOneWidget, reason: 'копия сервера заменила телефонную');
      await settleTalk(tester);
    });

    // ПРАВИЛО (кадр 37-10): сбой не стирает сказанное — реплика стоит в ленте над плашкой, пока ход
    // не уйдёт снова. «Повторить» шлёт именно её.
    // ЛОВИТ: плашку «связь пропала» над лентой, из которой пропало то, что ученик только что сказал.
    testWidgets('сбой — сказанное остаётся в ленте', (tester) async {
      const line = 'He has a sore throat.';
      final probe = TalkProbe()
        ..documents.addAll([open, open])
        ..failMove = _offline();
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases);
      await finishLine(tester, stand);
      await _say(tester, text: line);

      expect(stand.talk.trouble, TalkTrouble.offline);
      final pending = find.byKey(const ValueKey('talk-pending'));
      expect(find.descendant(of: pending, matching: find.text(line)), findsOneWidget);
      expect(find.byKey(const ValueKey('talk-thinking')), findsNothing, reason: 'никто не думает — запрос упал');
      await settleTalk(tester);
    });
  });

  group('37-10 · сбои', () {
    // ПРАВИЛО (кадр 37-10): обрыв связи — «разговор продолжится отсюда». «Повторить» СНАЧАЛА
    // перечитывает разговор и только потом решает: лента выросла — ход дошёл, второй раз не шлём;
    // лента та же — ход не дошёл, шлём ровно один раз.
    // ЛОВИТ: второй ход поверх уже принятого — ученик платит дважды и получает две реплики роли на
    // одну свою; и «Повторить», которое только перечитывает и теряет ход.
    testWidgets('обрыв сети продолжает с места: ход не дошёл — уходит снова', (tester) async {
      final probe = TalkProbe()
        ..documents.addAll([open, open, open])
        ..failMove = _offline();
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases);
      await finishLine(tester, stand);
      await _say(tester);
      expect(probe.moves, hasLength(1));
      expect(stand.talk.trouble, TalkTrouble.offline);
      expect(find.text('Связь пропала — разговор продолжится отсюда'), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('talk-retry')));
      await tester.pump();
      await tester.pump();
      await tester.pump();
      expect(probe.reads, 1, reason: 'сперва перечитать');
      expect(probe.moves, hasLength(2), reason: 'лента та же — ход не дошёл');
      expect(stand.talk.trouble, isNull);
      await settleTalk(tester);
    });

    // ЛОВИТ: второй ход поверх уже принятого — ученик платит дважды и получает две реплики роли на
    // одну свою.
    testWidgets('обрыв сети продолжает с места: ход дошёл — второй раз не шлём', (tester) async {
      final probe = TalkProbe()
        ..documents.addAll([open, ended])
        ..failMove = _offline();
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases);
      await finishLine(tester, stand);
      await _say(tester);
      expect(probe.moves, hasLength(1));

      await tester.tap(find.byKey(const ValueKey('talk-retry')));
      await tester.pump();
      await tester.pump();
      await tester.pump();
      expect(probe.reads, 1);
      expect(probe.moves, hasLength(1), reason: 'ход уже был в журнале');
      expect(stand.talk.talk!.turns, hasLength(ended.turns.length), reason: 'лента продолжается с места');
      await settleTalk(tester);
    });

    // ПРАВИЛО: 503 `plan_conversation_unavailable` — роль не ответила, В ЖУРНАЛЕ НЕ ОСТАЛОСЬ НИЧЕГО,
    // и тот же ход повторяется один в один. Слов «ошибка», «сервер» и «API» на экране нет.
    // ЛОВИТ: сбой роли, показанный как обрыв связи (и наоборот), и ход, потерянный вместе с плашкой.
    testWidgets('роль не ответила — «попробуй ещё раз», тот же ход уходит снова', (tester) async {
      final probe = TalkProbe()
        ..documents.addAll([open, open])
        ..failMove = _problem(503, 'plan_conversation_unavailable');
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases);
      await finishLine(tester, stand);

      await _say(tester);
      expect(stand.talk.trouble, TalkTrouble.agentSilent);
      expect(find.text('Собеседник не отвечает — попробуй ещё раз'), findsOneWidget);
      expect(find.textContaining('ошибка'), findsNothing);
      expect(find.textContaining('сервер'), findsNothing);

      await tester.tap(find.byKey(const ValueKey('talk-retry')));
      await tester.pump();
      await tester.pump();
      await tester.pump();
      expect(probe.moves, [
        (kind: 'said', heard: 'He has had it for three days.'),
        (kind: 'said', heard: 'He has had it for three days.'),
      ], reason: 'один в один');
      await settleTalk(tester);
    });

    // ПРАВИЛО: микрофон, который не пишет (нет разрешения, нет распознавания на устройстве), СКАЗАН
    // словами, и рядом «Разрешить» — те же два шага, что у «Нужен микрофон» карточек (30-3). Кнопка
    // гаснет: «тап — говорить» над ней не стоит.
    // ЛОВИТ: живой прогон — симулятор без ассетов распознавания: после прерывания кнопка звала
    // «тап — говорить», а тап не делал ничего и ничего не объяснял.
    testWidgets('микрофон не пишет — сказано, и «Разрешить» спрашивает систему', (tester) async {
      final recognizer = _GrantedOnAsk();
      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases, recognizer: recognizer);
      await finishLine(tester, stand);
      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      await tester.pump();

      expect(stand.mics.single.state, MicState.unavailable);
      expect(find.text('тап — говорить'), findsNothing, reason: 'не звать туда, где ничего не случится');
      expect(find.text('Нужен микрофон — без него разговор не пройти'), findsOneWidget);
      expect(find.byKey(const ValueKey('talk-rescue')), findsOneWidget, reason: '«Не понял» остаётся');
      expect(probe.moves, isEmpty, reason: 'запись, которой не было, не покупает ход');

      recognizer.granted = true;
      await tester.tap(find.byKey(const ValueKey('talk-allow-mic')));
      await tester.pump();
      await tester.pump();
      expect(recognizer.asked, 1);
      expect(stand.mics.single.state, MicState.idle);
      expect(find.byKey(const ValueKey('talk-allow-mic')), findsNothing);
      expect(find.text('тап — говорить'), findsOneWidget, reason: 'система разрешила — кнопка снова зовёт');
      await settleTalk(tester);
    });

    // ПРАВИЛО: пустая запись НЕ уходит на сервер — «не расслышал» стоит ничего и решается на месте.
    // ЛОВИТ: тишину, купленную как ход.
    testWidgets('пустая запись не покупает ход', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases);
      await finishLine(tester, stand);

      await stand.talk.say('   ');
      await tester.pump();
      expect(probe.moves, isEmpty);
      expect(stand.talk.trouble, TalkTrouble.unheard);
      expect(find.text('не расслышал — скажи ещё раз'), findsOneWidget);
      await settleTalk(tester);
    });
  });

  group('37-11 · 37-12 · конец и итог', () {
    // ПРАВИЛО (кадры 37-11, 37-12): роль попрощалась — лента остаётся, над ней встаёт лист
    // «Разговор окончен · N минут» и одна кнопка «Итог». Итог печатает счёт СЕРВЕРА.
    // ЛОВИТ: итог, посчитанный на телефоне, и лист, накрывший ленту целиком.
    testWidgets('прощание — лист над лентой, «Итог» ведёт к счёту сервера', (tester) async {
      var summaries = 0;
      final probe = TalkProbe()..documents.add(ended);
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases, onSummary: () => summaries++);
      await finishLine(tester, stand);
      expect(stand.talk.phase, TalkPhase.ended);
      expect(find.byKey(const ValueKey('talk-end-sheet')), findsOneWidget);
      expect(find.text('Разговор окончен'), findsOneWidget);
      expect(find.byKey(const ValueKey('talk-ribbon')), findsOneWidget, reason: 'лента остаётся видна');

      await tester.tap(find.byKey(const ValueKey('talk-summary-action')));
      await tester.pump();
      expect(summaries, 1);
      await settleTalk(tester);
    });
  });

  group('37-12 · фразы — плашки', () {
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
          home: Scaffold(
            body: TalkSummaryView(talk: talk, scene: null, voice: HeldVoice(), onAgain: () {}, onNext: () {}, onClose: () {}),
          ),
        ),
      );
      await tester.pump();
    }

    // ПРАВИЛО (кадр 37-12, приёмка снимков): фразы итога — ПЛАШКИ, не строки: сказанные — на подложке шалфея 15 %,
    // несказанные — в контуре чернил; «прослушать» 28 — внутри плашки. Группы и подписи — как были. То же в
    // репетиции.
    // ЛОВИТ: строки без плашек с кружком снаружи — 37-12 до приёмки.
    for (final (name, fixture) in [('день', 'conversation-day-ended'), ('репетиция', 'conversation-rehearsal-ended')]) {
      testWidgets('$name: сказанные — на шалфее 15 %, несказанные — в контуре; «прослушать» 28 внутри', (tester) async {
        final talk = talkFixture(fixture);
        await pumpSummary(tester, talk);
        final phrases = talk.summary!.phrases;
        expect(phrases.where((p) => p.used), isNotEmpty);
        expect(phrases.where((p) => !p.used), isNotEmpty);
        for (final p in phrases) {
          final row = find.byKey(ValueKey('talk-phrase-${p.sceneId}-${p.ref}'));
          final plate = find.descendant(of: row, matching: find.byType(Container)).first;
          final box = tester.widget<Container>(plate).decoration! as BoxDecoration;
          if (p.used) {
            expect(box.color, AppColors.sessionSageWash, reason: '«${p.textTarget}» сказана — шалфей 15 %');
            expect(box.border, isNull);
          } else {
            expect(box.color, isNull, reason: '«${p.textTarget}» не сказана — без подложки');
            expect((box.border! as Border).top.color, AppColors.markerOutline, reason: 'контур чернил');
          }
          final listen = find.descendant(of: row, matching: find.byType(SessionListenButton));
          expect(tester.widget<SessionListenButton>(listen).size, 28);
          final r = tester.getRect(plate);
          final circle = tester.getRect(listen).deflate(8);
          expect(r.contains(circle.topLeft) && r.contains(circle.bottomRight), isTrue, reason: 'кружок внутри плашки');
          expect(r.right - circle.right, moreOrLessEquals(12, epsilon: 2), reason: 'в правом углу');
        }
      });
    }

    // ПРАВИЛО (кадр 37-12b, наряд CLIENT-CONV-1b): итог репетиции — «Ты готов к…», строка «понял» с галкой, и фразы
    // ГРУППАМИ ПО СЦЕНАМ в порядке разговора: у каждой сцены сперва сказанное под именем сцены, затем несказанное под
    // «<сцена> · повтори перед приёмом» — завтра у репетиции событие, не день плана.
    // ЛОВИТ: одну общую кучу фраз двух сцен, «вернётся завтра» у репетиции, строку «понял» без значка.
    testWidgets('37-12b: репетиция — группы по сценам, «повтори перед приёмом», галка у «понял»', (tester) async {
      final talk = talkFixture('conversation-rehearsal-ended');
      await pumpSummary(tester, talk);
      expect(find.text('Ты готов к приёму'), findsOneWidget);
      expect(find.byKey(const ValueKey('talk-summary-understood-check')), findsOneWidget);
      final check = tester.getRect(find.byKey(const ValueKey('talk-summary-understood-check')));
      final understood = tester.getRect(find.byKey(const ValueKey('talk-summary-understood')));
      expect(check.size, const Size(20, 20));
      expect(understood.left - check.right, moreOrLessEquals(12, epsilon: 0.5));
      expect(find.textContaining('ВЕРНЁТСЯ ЗАВТРА'), findsNothing);

      final tops = <double>[];
      for (final scene in talk.scenes) {
        final phrases = talk.summary!.phrases.where((p) => p.sceneId == scene.sceneId);
        for (final (used, label) in [(true, scene.titleNative), (false, '${scene.titleNative} · повтори перед приёмом')]) {
          final group = find.byKey(ValueKey('talk-summary-group-${scene.sceneId}-${used ? 'said' : 'not-said'}'));
          final count = phrases.where((p) => p.used == used).length;
          if (count == 0) {
            expect(group, findsNothing);
            continue;
          }
          expect(group, findsOneWidget, reason: '${scene.titleNative}: ${used ? 'said' : 'not said'}');
          expect(find.descendant(of: group, matching: find.text(label.toUpperCase())), findsOneWidget);
          final phrase = find.byWidgetPredicate((w) => w.key is ValueKey<String> && (w.key! as ValueKey<String>).value.startsWith('talk-phrase-'));
          expect(find.descendant(of: group, matching: phrase), findsNWidgets(count));
          tops.add(tester.getRect(group).top);
        }
      }
      expect(tops, hasLength(4));
      expect([...tops]..sort(), tops, reason: 'scenes in the talk\'s order, said before not said');
    });
  });

  group('контракт', () {
    // ПРАВИЛО НАРЯДА: нет поля на проводе — честная ошибка, а не догадка.
    // ЛОВИТ: разбор, который подставляет ноль вместо пропавшего счёта и рисует «0 из 0».
    test('документ без поля — ошибка контракта, а не нарисованное состояние', () {
      final json = talkFixtureJson('conversation-day-open')..remove('turns_left');
      expect(() => PlanConversation.fromJson(json), throwsA(isA<FormatException>()));

      final state = talkFixtureJson('conversation-day-open')..['state'] = 'thinking';
      expect(() => PlanConversation.fromJson(state), throwsA(isA<FormatException>()));
    });

    // ПРАВИЛО: `returns_tomorrow` — ответ сервера, а не вывод из вида дня. У репетиции завтра
    // событие, и несказанное читается «повтори перед приёмом».
    // ЛОВИТ: клиент, выводящий возврат из `type == rehearsal` вместо поля итога.
    test('итог репетиции: несказанное не возвращается завтра', () {
      final rehearsal = talkFixture('conversation-rehearsal-ended');
      expect(rehearsal.type, TalkType.rehearsal);
      expect(rehearsal.scenes, hasLength(2));
      expect(rehearsal.summary!.returnsTomorrow, isFalse);
      expect(rehearsal.summary!.notSaid, isNotEmpty);
    });
  });
}

/// A recogniser the system refuses until «Разрешить» asks it again and it is [granted].
class _GrantedOnAsk extends SilentRecognizer {
  _GrantedOnAsk() : super(available: false);

  bool granted = false;
  int asked = 0;

  @override
  bool get isReady => granted;

  @override
  Future<bool> get hasPermission async => granted;

  @override
  Future<bool> prepare() async {
    asked++;
    return granted;
  }

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
    ValueChanged<double>? onLevel,
  }) async => granted ? const SpeechAttempt.silent() : const SpeechAttempt.unavailable();
}

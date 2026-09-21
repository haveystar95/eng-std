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

/// THE TALK WITH THE AGENT ON THE REAL SCREEN (наряды CLIENT-CONV-1a, CLIENT-CONV-1c; кадры 37-6…37-12, SESSION-DES-4).
///
/// Every document here is a snapshot of the live server — a talk that was actually had on
/// `wordtrainer_e2e_test`, with its rescue, its hint and its summary: the talk before CONV-2 ([talkFixture]) and the
/// talk of CONV-2's contract ([talkV2]) — targets, the title, «Sorry?», the intention as a clause.
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
  final openV2 = talkV2('talk_day_open_v2');
  final endedV2 = talkV2('talk_day_ended_v2');

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

    // RULE (наряд CLIENT-CONV-1c §5, SESSION-DES-4 «Волна»): THE ROLE'S TEXT IS OPEN WITH ITS VOICE — the plate rises
    // with the move's answer and its words stand from that moment, «прослушать» 28 in the top right corner (12 from the
    // top, 14 from the edge), and WHILE THE LINE SOUNDS a brass wave runs along the plate's bottom edge, 10 under the
    // translation; the line said, the wave goes and «прослушать» stays. The circle itself never turns into a wave, and
    // there is no «текст» to tap. «Прервано» stands over the words of a line cut off (37-9). Under «Без подсказок» the
    // lines stay CLOSED until the summary: «прослушать» 28 alone (12 + 28 + 12), the same edge wave under it while the
    // line sounds, and nothing that opens them.
    // CATCHES: the wave inside the circle in place of the edge wave (the frame before SESSION-DES-4), a wave that stays
    // after the line, a line that waits for a tap to be read, a «текст» chip, and the text opened under «Без подсказок».
    testWidgets('текст роли открыт с голосом, волна по нижней кромке, пока реплика звучит', (tester) async {
      Finder inTurn(int index, Finder f) => find.descendant(of: find.byKey(ValueKey('turn-$index')), matching: f);
      Rect plate(int index) => tester.getRect(inTurn(index, find.byType(SessionBubble)));
      Rect circle(int index) => tester.getRect(inTurn(index, find.byKey(const ValueKey('talk-listen')))).deflate(8);
      const last = 'How long has he been sick?';

      final probe = TalkProbe()..documents.add(openV2);
      final stand = await pumpTalk(tester, probe);

      // The role is speaking — its words are on screen, and the wave runs along the plate's edge, under the words.
      expect(stand.talk.phase, TalkPhase.agentSpeaking);
      expect(find.text('слушай'), findsOneWidget, reason: '«слушай» over the dimmed button while the role speaks');
      expect(find.text(last), findsOneWidget);
      final wave = inTurn(5, find.byKey(const ValueKey('talk-line-wave')));
      expect(wave, findsOneWidget, reason: 'the edge wave while the line sounds');
      expect(tester.getRect(wave).top - tester.getRect(inTurn(5, find.text('Как долго он болеет?'))).bottom, moreOrLessEquals(10, epsilon: 0.5));
      expect(plate(5).bottom - tester.getRect(wave).bottom, moreOrLessEquals(12, epsilon: 0.5), reason: 'on the bottom edge');
      expect(tester.widget<SessionListenButton>(inTurn(5, find.byType(SessionListenButton))).playing, isFalse,
          reason: 'the circle stays «прослушать»');
      for (final index in [1, 5]) {
        final p = plate(index);
        final c = circle(index);
        expect(tester.widget<SessionListenButton>(inTurn(index, find.byType(SessionListenButton))).size, 28);
        expect(p.right - c.right, moreOrLessEquals(14, epsilon: 0.5), reason: 'turn $index: 14 from the right edge');
        expect(c.top - p.top, moreOrLessEquals(12, epsilon: 0.5), reason: 'turn $index: 12 from the top');
      }
      expect(inTurn(1, find.byKey(const ValueKey('talk-line-wave'))), findsNothing, reason: 'only the line that sounds');
      expect(find.text('текст'), findsNothing, reason: 'there is nothing to open');
      expect(find.byType(SessionTextExit), findsNothing);

      // Said: the wave goes, «прослушать» stays.
      await finishLine(tester, stand);
      expect(wave, findsNothing);
      expect(inTurn(5, find.byKey(const ValueKey('talk-listen'))), findsOneWidget);
      expect(find.text(last), findsOneWidget);
      await settleTalk(tester);

      // Cut off: «прервано» over the open words, inside the container.
      final cutProbe = TalkProbe()..documents.add(openV2);
      await pumpTalk(tester, cutProbe);
      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      await tester.pump();
      final mark = tester.getRect(inTurn(5, find.byKey(const ValueKey('talk-interrupted'))));
      expect(mark.top, greaterThanOrEqualTo(plate(5).top));
      expect(find.text(last), findsOneWidget);
      await settleTalk(tester);

      // «Без подсказок»: closed — the circle alone, the edge wave under it while the line sounds.
      final blind = talkV2('talk_day_open_v2', (json) {
        (json['hints'] as Map<String, dynamic>)
          ..['enabled'] = false
          ..['native'] = null;
      });
      final blindProbe = TalkProbe()..documents.add(blind);
      final blindStand = await pumpTalk(tester, blindProbe, hints: false);
      expect(find.text(last), findsNothing);
      expect(inTurn(5, find.byKey(const ValueKey('talk-listen'))), findsOneWidget, reason: '«прослушать» alone');
      expect(inTurn(5, find.byKey(const ValueKey('talk-line-wave'))), findsOneWidget, reason: 'the edge wave while it sounds');
      final closed = plate(1);
      expect(closed.height, moreOrLessEquals(52, epsilon: 0.5), reason: '12 + 28 + 12, as in the frame');
      expect(circle(1).left - closed.left, moreOrLessEquals(14, epsilon: 0.5));
      await finishLine(tester, blindStand);
      expect(inTurn(5, find.byKey(const ValueKey('talk-line-wave'))), findsNothing);
      expect(plate(5).height, moreOrLessEquals(52, epsilon: 0.5));
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

    // ПРАВИЛО: сервер до CONV-2 называл фразу только ССЫЛКОЙ. Текста фразы нет ни в ответе, ни у дня — подчерка нет
    // вовсе: клиент не гадает, что именно услышал сервер.
    testWidgets('фраза без текста — ни в ответе, ни у дня — не подчёркивается', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      await pumpTalk(tester, probe);
      expect(tester.widget<TalkSageUnderline>(find.byType(TalkSageUnderline)).marks, isEmpty);
      await settleTalk(tester);
    });

    // ПРАВИЛО (CONV-2 п. 10): `phrases_used` несёт текст фразы — подчерк встаёт по нему, и дню не нужно знать эту
    // фразу (у репетиции фразы чужих сцен).
    // ЛОВИТ: подчерк только по фразам дня — сказанное из другой сцены без линии шалфея.
    testWidgets('подчерк по тексту фразы из ответа сервера, без словаря дня', (tester) async {
      final probe = TalkProbe()..documents.add(openV2);
      await pumpTalk(tester, probe);
      final line = tester.widget<TalkSageUnderline>(
        find.descendant(of: find.byKey(const ValueKey('turn-2')), matching: find.byType(TalkSageUnderline)),
      );
      expect(line.text, 'My son has a fever.');
      expect([for (final m in line.marks) line.text.substring(m.start, m.end)], ['My', 'son', 'has', 'a', 'fever']);
      await settleTalk(tester);
    });

    // ПРАВИЛО (кадр 37-7 «после „Не понял"», CONV-2 п. 4а, наряд CLIENT-CONV-1c §2б): ход `rescue` несёт слова — «Sorry?»
    // на языке разговора — и стоит своим тёмным пузырём между двумя репликами роли: без перевода под ним и без
    // подчерка в нём. Пометки «переспросил» больше нет.
    // ЛОВИТ: пометку «переспросил» на месте пузыря (сборка 18) и перевод или шалфей в пузыре переспроса.
    testWidgets('переспрос — свой тёмный пузырь «Sorry?», без перевода; пометки нет', (tester) async {
      final probe = TalkProbe()..documents.add(openV2);
      await pumpTalk(tester, probe);

      // Журнал: 1 роль · 2 сказал · 3 роль · 4 переспрос · 5 роль говорит иначе.
      final rescue = find.byKey(const ValueKey('turn-4'));
      expect(tester.widget<TalkOwnBubble>(rescue).text, 'Sorry?');
      expect(tester.widget<TalkOwnBubble>(rescue).marks, isEmpty, reason: 'в переспросе нет фраз плана');
      expect(find.descendant(of: rescue, matching: find.byType(Text)), findsOneWidget, reason: 'только «Sorry?», перевода нет');
      expect(find.text('переспросил'), findsNothing);
      final bubble = tester.getRect(rescue);
      expect(tester.getRect(find.byKey(const ValueKey('turn-3'))).bottom, lessThanOrEqualTo(bubble.top));
      expect(tester.getRect(find.byKey(const ValueKey('turn-5'))).top, greaterThanOrEqualTo(bubble.bottom), reason: 'между двумя репликами роли');
      final plate = tester.widget<SessionBubble>(find.descendant(of: rescue, matching: find.byType(SessionBubble)));
      expect(plate.own, isTrue, reason: 'тёмный пузырь ученика');
      await settleTalk(tester);
    });

    // ПРАВИЛО: переспрос сервера до CONV-2 — без слов (`text_target` пуст): на его месте нет ни пузыря, ни пометки;
    // и пока «Не понял» в полёте, телефон не пишет «Sorry?» за сервер.
    // ЛОВИТ: пустой тёмный пузырь и выдуманное телефоном «Sorry?».
    testWidgets('переспрос без слов — ничего на его месте; в полёте — ничего до ответа', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      await pumpTalk(tester, probe, phraseTexts: phrases);
      expect(find.byKey(const ValueKey('turn-2')), findsNothing, reason: 'ход без слов не рисуется');
      expect(find.byType(TalkOwnBubble), findsOneWidget, reason: 'тёмный пузырь — только у сказанного (ход 4)');
      await settleTalk(tester);

      final hold = Completer<void>();
      final flight = TalkProbe()
        ..documents.addAll([openV2, openV2])
        ..holdMove = hold;
      final stand = await pumpTalk(tester, flight);
      await finishLine(tester, stand);
      await tester.tap(find.byKey(const ValueKey('talk-rescue')));
      await tester.pump();
      expect(stand.talk.phase, TalkPhase.sending);
      expect(find.byKey(const ValueKey('talk-pending')), findsNothing, reason: 'слова переспроса — сервера');
      expect(find.byKey(const ValueKey('talk-thinking')), findsOneWidget);
      hold.complete();
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
    // приходит ПРИДАТОЧНЫМ (CONV-2 п. 11) и встаёт в чип как пришло — своей правки у клиента больше нет.
    // ЛОВИТ: чип и кнопку, стоящие рядом, и правку сервера на телефоне («TalkTexts.clause», снесён).
    testWidgets('чип встаёт по молчанию, и «Подсказать» уходит вместе с ним', (tester) async {
      final probe = TalkProbe()..documents.add(openV2);
      final stand = await pumpTalk(tester, probe);
      await finishLine(tester, stand);
      expect(find.byKey(const ValueKey('talk-hint')), findsOneWidget);
      expect(find.byKey(const ValueKey('talk-hint-chip')), findsNothing);

      await tester.pump(const Duration(seconds: 6));
      expect(find.byKey(const ValueKey('talk-hint-chip')), findsOneWidget);
      expect(find.byKey(const ValueKey('talk-hint')), findsNothing);
      expect(find.text('Скажи, что он болеет уже три дня'), findsOneWidget, reason: 'hints.native — как пришло');
      await settleTalk(tester);

      // An intention the server did not make a clause stays as it came too: the phone no longer edits the server.
      final sentence = talkV2('talk_day_open_v2', (json) => (json['hints'] as Map<String, dynamic>)['native'] = 'Он болеет уже три дня.');
      final sentenceStand = await pumpTalk(tester, TalkProbe()..documents.add(sentence));
      await finishLine(tester, sentenceStand);
      await tester.tap(find.byKey(const ValueKey('talk-hint')));
      await tester.pump();
      expect(find.text('Скажи, что Он болеет уже три дня.'), findsOneWidget);
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
      expect(find.byKey(const ValueKey('talk-strip')), findsNothing, reason: 'a talk without targets has no strip');
      await settleTalk(tester);
    });

    // ПРАВИЛО (наряд CLIENT-CONV-1c §4): после конца (37-11) полоска стоит над листом «Разговор окончен» и показывает
    // итог — «фразы · 3 из 7», тот же счёт, что у итога 37-12; тап по ней — тот же лист фраз.
    // ЛОВИТ: полоску, пропавшую вместе с доком микрофона, и счёт полоски, расходящийся с итогом.
    testWidgets('37-11: полоска над листом конца — итог фраз', (tester) async {
      final probe = TalkProbe()..documents.add(endedV2);
      final stand = await pumpTalk(tester, probe);
      await finishLine(tester, stand);
      expect(stand.talk.phase, TalkPhase.ended);
      final strip = find.byKey(const ValueKey('talk-strip'));
      expect(find.descendant(of: strip, matching: find.text('фразы · 3 из 7')), findsOneWidget);
      expect(endedV2.summary!.phrasesUsed, 3, reason: 'the summary counts the same list');
      expect(tester.getRect(strip).bottom, lessThanOrEqualTo(tester.getRect(find.text('Разговор окончен')).top), reason: 'над листом');
      await tester.tap(strip);
      await tester.pumpAndSettle();
      expect(find.byKey(const ValueKey('talk-phrase-sheet')), findsOneWidget);
      await settleTalk(tester);
    });
  });

  group('37-6…37-11 · полоска фраз и её лист', () {
    Future<void> expectStrip(WidgetTester tester, String text, String state) async {
      final strip = find.byKey(const ValueKey('talk-strip'));
      expect(strip, findsOneWidget, reason: state);
      expect(find.descendant(of: strip, matching: find.text(text)), findsOneWidget, reason: state);
      expect(tester.getSize(strip).height, 44, reason: '$state: a plate 44');
    }

    // ПРАВИЛО (наряд §4, SESSION-DES-4): полоска «фразы · N из M» — часть дока, над микрофоном во ВСЕХ состояниях ленты:
    // роль говорит, твоя очередь (покой, чип, запись), ход в полёте, прервал, сбои. N и M — из `targets[]` последнего
    // ответа: N — сколько `said`, M — сколько целей. Между полоской и тем, что под ней, — 14.
    // ЛОВИТ: полоску, пропадающую в каком-то из состояний, и счёт, посчитанный на телефоне.
    testWidgets('полоска стоит во всех состояниях ленты', (tester) async {
      final hold = Completer<void>();
      final probe = TalkProbe()
        ..documents.addAll([openV2, openV2])
        ..holdMove = hold;
      final stand = await pumpTalk(tester, probe, recognizer: ListeningRecognizer());
      await expectStrip(tester, 'фразы · 1 из 5', 'роль говорит (37-6)');
      final caption = tester.getRect(find.byKey(const ValueKey('talk-caption')));
      expect(caption.top - tester.getRect(find.byKey(const ValueKey('talk-strip'))).bottom, moreOrLessEquals(14, epsilon: 0.5));

      await finishLine(tester, stand);
      await expectStrip(tester, 'фразы · 1 из 5', 'твоя очередь (37-7)');
      await tester.pump(const Duration(seconds: 6));
      expect(find.byKey(const ValueKey('talk-hint-chip')), findsOneWidget);
      await expectStrip(tester, 'фразы · 1 из 5', 'подсказка (37-7)');
      expect(tester.getRect(find.byKey(const ValueKey('talk-strip'))).bottom, lessThan(tester.getRect(find.byKey(const ValueKey('talk-hint-chip'))).top),
          reason: 'the strip over the chip');

      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      expect(stand.mics.single.state, MicState.listening);
      await expectStrip(tester, 'фразы · 1 из 5', 'слушаю (37-7)');
      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      await tester.pump();
      expect(stand.talk.trouble, TalkTrouble.unheard);
      await expectStrip(tester, 'фразы · 1 из 5', 'не расслышал (37-10)');

      await tester.tap(find.byKey(const ValueKey('talk-rescue')));
      await tester.pump();
      expect(stand.talk.phase, TalkPhase.sending);
      await expectStrip(tester, 'фразы · 1 из 5', 'ход в полёте (37-8)');
      hold.complete();
      await tester.pump();
      await tester.pump();
      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      await tester.pump();
      expect(find.byKey(const ValueKey('talk-interrupted')), findsOneWidget);
      await expectStrip(tester, 'фразы · 1 из 5', 'прервал (37-9)');
      await settleTalk(tester);

      final failing = TalkProbe()
        ..documents.addAll([openV2, openV2])
        ..failMove = _offline();
      final broken = await pumpTalk(tester, failing);
      await finishLine(tester, broken);
      await _say(tester);
      expect(broken.talk.trouble, TalkTrouble.offline);
      await expectStrip(tester, 'фразы · 1 из 5', 'связь пропала (37-10)');
      await settleTalk(tester);

      final silent = TalkProbe()
        ..documents.addAll([openV2, openV2])
        ..failMove = _problem(503, 'plan_conversation_unavailable');
      final mute = await pumpTalk(tester, silent);
      await finishLine(tester, mute);
      await _say(tester);
      expect(mute.talk.trouble, TalkTrouble.agentSilent);
      await expectStrip(tester, 'фразы · 1 из 5', 'роль не отвечает (37-10)');
      await settleTalk(tester);
    });

    // ПРАВИЛО (37-8b, SESSION-DES-4 «Зачёт»): ответ хода принёс новую сказанную фразу — полоска уходит в шалфей 15 % и
    // слева от шеврона встаёт галка шалфеем на 260 мс, потом снова бумага; «2 из 5» — счёт сервера.
    // ЛОВИТ: вспышку без роста счёта, счёт без вспышки и шалфей, оставшийся навсегда.
    testWidgets('37-8b: фраза прозвучала — вспышка шалфея 260 мс и галка', (tester) async {
      final answered = talkV2('talk_day_open_v2', (json) {
        ((json['targets'] as List<dynamic>)[1] as Map<String, dynamic>)['said'] = true;
      });
      final probe = TalkProbe()..documents.addAll([openV2, answered]);
      final stand = await pumpTalk(tester, probe);
      await finishLine(tester, stand);
      BoxDecoration plate() => tester.widget<Container>(find.byKey(const ValueKey('talk-strip'))).decoration! as BoxDecoration;
      expect(plate().color, AppColors.paper);
      expect(find.byKey(const ValueKey('talk-strip-check')), findsNothing);

      await _say(tester);
      expect(find.text('фразы · 2 из 5'), findsOneWidget);
      expect(plate().color, AppColors.sessionSageWash, reason: 'шалфей 15 %');
      final check = find.byKey(const ValueKey('talk-strip-check'));
      expect(check, findsOneWidget);
      expect(tester.getSize(check), const Size(20, 20));
      expect(tester.widget<Icon>(find.descendant(of: check, matching: find.byType(Icon))).color, AppColors.verdictKnown);
      await tester.pump(const Duration(milliseconds: 270));
      expect(plate().color, AppColors.paper, reason: 'через 260 мс — снова бумага');
      expect(check, findsNothing);
      await settleTalk(tester);
    });

    // ПРАВИЛО (37-8d): тап по полоске — лист снизу над затемнением 40 %: «Фразы дня» и крестик 24, строки фраз в порядке
    // сервера через 12; сказанная — на шалфее 15 % с галкой 20 справа, несказанная — в контуре 1,5 без заливки; в каждой
    // фраза Literata 17, перевод 15 второй строкой, «прослушать» 28 — он играет свою фразу. Крестик закрывает лист.
    // ЛОВИТ: лист с фразами дня вместо целей разговора, галки не по `said` и «прослушать» не той фразы.
    testWidgets('37-8d: лист фраз — сказанные на шалфее с галкой, несказанные в контуре', (tester) async {
      final probe = TalkProbe()..documents.add(openV2);
      final stand = await pumpTalk(tester, probe);
      await finishLine(tester, stand);
      await tester.tap(find.byKey(const ValueKey('talk-strip')));
      await tester.pumpAndSettle();

      final sheet = find.byKey(const ValueKey('talk-phrase-sheet'));
      expect(find.descendant(of: sheet, matching: find.text('Фразы дня')), findsOneWidget);
      final tops = <double>[];
      for (final t in openV2.targets) {
        final row = find.byKey(ValueKey('talk-phrase-${t.sceneId}-${t.ref}'));
        expect(row, findsOneWidget, reason: t.ref);
        final box = tester.widget<Container>(row).decoration! as BoxDecoration;
        expect(find.descendant(of: row, matching: find.text(t.textTarget)), findsOneWidget);
        expect(find.descendant(of: row, matching: find.text(t.textNative)), findsOneWidget);
        expect(tester.widget<Text>(find.descendant(of: row, matching: find.text(t.textTarget))).style, AppTextSession.phrase17);
        final said = find.descendant(of: row, matching: find.byKey(const ValueKey('talk-phrase-said')));
        if (t.said) {
          expect(box.color, AppColors.sessionSageWash, reason: '${t.ref}: шалфей');
          expect(box.border, isNull);
          expect(said, findsOneWidget, reason: '${t.ref}: галка');
        } else {
          expect(box.color, isNull, reason: '${t.ref}: без заливки');
          expect((box.border! as Border).top.width, 1.5);
          expect(said, findsNothing);
        }
        tops.add(tester.getRect(row).top);
      }
      expect([...tops]..sort(), tops, reason: 'в порядке сервера');
      final second = tester.getRect(find.byKey(ValueKey('talk-phrase-${openV2.targets[1].sceneId}-p2')));
      final first = tester.getRect(find.byKey(ValueKey('talk-phrase-${openV2.targets[0].sceneId}-p1')));
      expect(second.top - first.bottom, moreOrLessEquals(12, epsilon: 0.5), reason: '12 между строками');

      await tester.tap(find.descendant(of: find.byKey(ValueKey('talk-phrase-${openV2.targets[2].sceneId}-p3')), matching: find.byType(SessionListenButton)));
      await tester.pump();
      expect(stand.voice.played.last, 'talk-target-${openV2.targets[2].sceneId}-p3', reason: 'своя фраза');
      stand.voice.finish();
      await tester.tap(find.byKey(const ValueKey('talk-phrase-sheet-close')));
      await tester.pumpAndSettle();
      expect(sheet, findsNothing);
      await settleTalk(tester);
    });

    // ПРАВИЛО: целей у разговора нет (сервер до CONV-2) — полоски нет ни в одном состоянии: клиент не считает фразы сам.
    // ЛОВИТ: полоску «фразы · 0 из 0» и счёт по фразам дня на телефоне.
    testWidgets('без targets полоски нет', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe, phraseTexts: phrases);
      expect(find.byKey(const ValueKey('talk-strip')), findsNothing);
      await finishLine(tester, stand);
      expect(find.byKey(const ValueKey('talk-strip')), findsNothing);
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

    // ПРАВИЛО (CONV-2 п. 2; наряд CLIENT-CONV-1c §9г): повтор поверх пройденного этапа (`replay: true`) ничего не
    // возвращает завтра — `returns_tomorrow: false`. У дня это не репетиция: список — по-дневному, а несказанное стоит
    // под «Не прозвучало», без «вернётся завтра» и без «повтори перед приёмом».
    // ЛОВИТ: итог повтора дня, нарисованный как итог репетиции («<сцена> · повтори перед приёмом»), и обещание вернуть
    // завтра то, что сервер не вернёт.
    testWidgets('повтор дня: несказанное — «Не прозвучало», без «завтра» и без «перед приёмом»', (tester) async {
      expect(endedV2.replay, isTrue);
      expect(endedV2.summary!.returnsTomorrow, isFalse);
      await pumpSummary(tester, endedV2);
      expect(find.text('Сказал сам 3 реплики'), findsOneWidget, reason: 'день, не «Ты готов к приёму»');
      expect(find.text('ФРАЗЫ ДНЯ В РАЗГОВОРЕ · 3 ИЗ 7'), findsOneWidget);
      expect(find.text('НЕ ПРОЗВУЧАЛО'), findsOneWidget);
      expect(find.textContaining('ЗАВТРА'), findsNothing);
      expect(find.textContaining('ПЕРЕД ПРИЁМОМ'), findsNothing);
      final said = tester.getRect(find.byKey(const ValueKey('talk-phrase-01M2H13KSAS23K4YPF1M65SJQD-p6')));
      final notSaid = tester.getRect(find.byKey(const ValueKey('talk-phrase-01M2H13KSAS23K4YPF1M65SJQD-p3')));
      expect(said.top, lessThan(notSaid.top), reason: 'сказанное — первым');
    });
  });

  group('37-5 → 37-6 · старт', () {
    // ПРАВИЛО (наряд CLIENT-CONV-1c §5, кадр 37-6): лента открывается на ОТВЕТЕ СЕРВЕРА — первая реплика роли звучит уже
    // в ней, с живой волной. `open` отвечает, когда реплика сказана, поэтому вход 37-5 и окно дня («Повторить разговор»)
    // ждут `openAnswered`.
    // ЛОВИТ: вход со спиннером, под которым роль договаривает первую реплику, — лента открывалась после неё (CONV-1a).
    test('openAnswered отвечает, пока роль ещё говорит; open — только когда договорила', () async {
      ConversationController controller(HeldVoice voice) {
        final talk = ConversationController(
          backend: FakeTalkBackend(TalkProbe()..documents.add(openV2)),
          planId: 'ulid-plan',
          day: 1,
          voice: voice,
          hints: true,
        );
        addTearDown(talk.dispose);
        return talk;
      }

      final voice = HeldVoice();
      final talk = controller(voice);
      var answered = false;
      unawaited(talk.openAnswered().then((_) => answered = true));
      await pumpEventQueue();
      expect(answered, isTrue, reason: 'the server has answered');
      expect(talk.phase, TalkPhase.agentSpeaking);
      expect(voice.speaking, isTrue, reason: 'the first line is still sounding');
      voice.finish();
      await pumpEventQueue();
      expect(talk.phase, TalkPhase.yourTurn);

      final plainVoice = HeldVoice();
      final plain = controller(plainVoice);
      var said = false;
      unawaited(plain.open().then((_) => said = true));
      await pumpEventQueue();
      expect(said, isFalse, reason: '`open` waits for the line — a screen must not');
      plainVoice.finish();
      await pumpEventQueue();
      expect(said, isTrue);
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

    // ПРАВИЛО (наряд CLIENT-CONV-1c): поля CONV-2 — ДОБАВОЧНЫЕ. Документ без них — разговор до них, а не сломанный:
    // целей нет (полоски нет), заголовка нет, `replay` — false, у `phrases_used` нет текста. Цель без своих слов
    // выпадает из списка, а не рисуется пустой строкой.
    // ЛОВИТ: разговор, который перестал открываться на сервере до CONV-2, и пустую строку в листе фраз.
    test('поля CONV-2 — добавочные: без них разговор тот же, цель без слов выпадает', () {
      expect(open.targets, isEmpty);
      expect(open.titleNative, isNull);
      expect(open.replay, isFalse);
      expect(open.turns[3].phrasesUsed.single.textTarget, isNull);

      expect(openV2.titleNative, 'Поговори с врачом');
      expect([for (final t in openV2.targets) (t.ref, t.said)], [('p1', true), ('p2', false), ('p3', false), ('p4', false), ('p5', false)]);
      expect(openV2.targetsSaid, 1);
      expect(openV2.turns[1].phrasesUsed.single.textTarget, 'My son has a fever.');

      final broken = talkV2('talk_day_open_v2', (json) {
        ((json['targets'] as List<dynamic>)[2] as Map<String, dynamic>).remove('text_target');
      });
      expect([for (final t in broken.targets) t.ref], ['p1', 'p2', 'p4', 'p5']);
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

import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/gestures.dart' show PointerDeviceKind;
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:lucide_icons_flutter/lucide_icons.dart';

import 'package:eng_std/data/plan/conversation/conversation_models.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/plan/conversation/conversation_controller.dart';
import 'package:eng_std/features/plan/conversation/talk_constructions.dart';
import 'package:eng_std/features/plan/conversation/talk_ribbon.dart';
import 'package:eng_std/features/plan/conversation/talk_summary.dart';
import 'package:eng_std/features/plan/session/parts/session_bits.dart' show SessionListenButton;
import 'package:eng_std/features/plan/session/parts/session_bubbles.dart' show SessionBubble;
import 'package:eng_std/features/plan/session/parts/session_mic_panel.dart' show SessionTextExit;
import 'package:eng_std/features/plan/session/session_mic.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/theme/theme.dart';

import '../../../support/server_fixtures.dart' show serverFixtureJson;
import '../../../support/session_harness.dart' show SilentRecognizer, enterHeard;
import '../../../support/talk_harness.dart';

/// THE TALK WITH THE AGENT ON THE REAL SCREEN (наряды CLIENT-CONV-1a, CLIENT-CONV-1c; кадры 37-6…37-12, SESSION-DES-4).
///
/// Every document here is a snapshot of the live server — a talk that was actually had on
/// `wordtrainer_e2e_test` — the server's own fixtures ([serverTalk]), re-shot by the code of FIX-3: the targets are
/// constructions, `phrases_used` is a pair, the summary's list is the same one. They are from before FIX-4, so they
/// also pin that a document without `state`, `scene_event` and `hints.sentence` still reads; the talk across scenes,
/// «almost», the hint plate and «Ещё вспомнил» are pinned over the live rehearsal of FIX-4b in `talk_fix4_test.dart`.
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
  final open = serverTalk('conversation-day-open');
  final ended = serverTalk('conversation-day-ended');
  // The role's last line of the open talk, and the learner's first own move — the ribbon is read off the document,
  // never off remembered indices: a re-shot fixture is a different talk, and a test pinned to «turn-5» would drift.
  final lastPartner = open.turns.lastWhere((t) => !t.isOwn);
  final firstPartner = open.turns.firstWhere((t) => !t.isOwn);
  final firstOwn = open.turns.firstWhere((t) => t.isOwn);

  group('37-6…37-9 · лента и микрофон', () {
    // ПРАВИЛО НАРЯДА — ОДНО НА ВСЕ ЭКРАНЫ: микрофон открывается ТОЛЬКО по тапу. Ни конец реплики
    // роли, ни подсказка, ни сбой не включают запись сами (DECISIONS п. 299).
    // ЛОВИТ: «микрофон открывается сам по концу реплики собеседника» — отменённое правило DAY-FIX-3,
    // которое в разговоре выглядит особенно естественно и потому особенно легко возвращается.
    testWidgets('микрофон не открывается сам', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe);

      // Реплика роли доиграла — ход ученика, и запись всё ещё не идёт.
      await finishLine(tester, stand);
      expect(stand.talk.phase, TalkPhase.yourTurn);
      expect(stand.mics.single.state, MicState.idle);

      // Молчание микрофона не поднимает.
      await tester.pump(const Duration(seconds: 6));
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
      final stand = await pumpTalk(tester, probe);
      await finishLine(tester, stand);
      expect(find.byKey(const ValueKey('talk-rescue')), findsOneWidget, reason: 'в покое');

      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      expect(stand.mics.single.state, MicState.listening);
      expect(find.byKey(const ValueKey('talk-rescue')), findsOneWidget, reason: 'и пока идёт запись');

      await tester.pump(const Duration(seconds: 6));
      expect(find.byKey(const ValueKey('talk-rescue')), findsOneWidget, reason: 'и после паузы');

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
      final stand = await pumpTalk(tester, probe);
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
    // lines stay CLOSED until the learner opens one (наряд CLIENT-CONV-1c §5, приёмка 22.09): the pair «прослушать» 28
    // and the «текст» chip in one row (12 + 28 + 12), the same edge wave under them while the line sounds; a tap on
    // «текст» opens THAT line — its words, «прослушать» alone in the corner — and the others stay closed.
    // CATCHES: the wave inside the circle in place of the edge wave (the frame before SESSION-DES-4), a wave that stays
    // after the line, a line that waits for a tap to be read, a «текст» chip on an open line, a closed line with no
    // way to open it (harness 08 before the acceptance), and a tap that opens every line.
    testWidgets('текст роли открыт с голосом, волна по нижней кромке, пока реплика звучит', (tester) async {
      Finder inTurn(int index, Finder f) => find.descendant(of: find.byKey(ValueKey('turn-$index')), matching: f);
      Rect plate(int index) => tester.getRect(inTurn(index, find.byType(SessionBubble)));
      Rect circle(int index) => tester.getRect(inTurn(index, find.byKey(const ValueKey('talk-listen')))).deflate(8);
      final last = lastPartner.textTarget!;

      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe);

      // The role is speaking — its words are on screen, and the wave runs along the plate's edge, under the words.
      expect(stand.talk.phase, TalkPhase.agentSpeaking);
      expect(find.text('слушай'), findsOneWidget, reason: '«слушай» over the dimmed button while the role speaks');
      expect(find.text(last), findsOneWidget);
      final wave = inTurn(lastPartner.index, find.byKey(const ValueKey('talk-line-wave')));
      expect(wave, findsOneWidget, reason: 'the edge wave while the line sounds');
      expect(tester.getRect(wave).top - tester.getRect(inTurn(lastPartner.index, find.text(lastPartner.textNative!))).bottom, moreOrLessEquals(10, epsilon: 0.5));
      expect(plate(lastPartner.index).bottom - tester.getRect(wave).bottom, moreOrLessEquals(12, epsilon: 0.5), reason: 'on the bottom edge');
      expect(tester.widget<SessionListenButton>(inTurn(lastPartner.index, find.byType(SessionListenButton))).playing, isFalse,
          reason: 'the circle stays «прослушать»');
      for (final index in [firstPartner.index, lastPartner.index]) {
        final p = plate(index);
        final c = circle(index);
        expect(tester.widget<SessionListenButton>(inTurn(index, find.byType(SessionListenButton))).size, 28);
        expect(p.right - c.right, moreOrLessEquals(14, epsilon: 0.5), reason: 'turn $index: 14 from the right edge');
        expect(c.top - p.top, moreOrLessEquals(12, epsilon: 0.5), reason: 'turn $index: 12 from the top');
      }
      expect(inTurn(firstPartner.index, find.byKey(const ValueKey('talk-line-wave'))), findsNothing, reason: 'only the line that sounds');
      expect(find.text('текст'), findsNothing, reason: 'there is nothing to open');
      expect(find.byType(SessionTextExit), findsNothing);

      // Said: the wave goes, «прослушать» stays.
      await finishLine(tester, stand);
      expect(wave, findsNothing);
      expect(inTurn(lastPartner.index, find.byKey(const ValueKey('talk-listen'))), findsOneWidget);
      expect(find.text(last), findsOneWidget);
      await settleTalk(tester);

      // Cut off: «прервано» over the open words, inside the container.
      final cutProbe = TalkProbe()..documents.add(open);
      await pumpTalk(tester, cutProbe);
      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      await tester.pump();
      final mark = tester.getRect(inTurn(lastPartner.index, find.byKey(const ValueKey('talk-interrupted'))));
      expect(mark.top, greaterThanOrEqualTo(plate(lastPartner.index).top));
      expect(find.text(last), findsOneWidget);
      await settleTalk(tester);

      // «Без подсказок»: closed — «прослушать» and «текст» in one row, the edge wave under them while the line sounds.
      final blind = serverTalk('conversation-day-open', (json) {
        (json['hints'] as Map<String, dynamic>)
          ..['enabled'] = false
          ..['native'] = null;
      });
      final blindProbe = TalkProbe()..documents.add(blind);
      final blindStand = await pumpTalk(tester, blindProbe, hints: false);
      expect(find.text(last), findsNothing);
      expect(inTurn(lastPartner.index, find.byKey(const ValueKey('talk-listen'))), findsOneWidget);
      expect(inTurn(lastPartner.index, find.byKey(const ValueKey('talk-open-text'))), findsOneWidget, reason: 'the pair: «прослушать» + «текст»');
      expect(inTurn(lastPartner.index, find.byKey(const ValueKey('talk-line-wave'))), findsOneWidget, reason: 'the edge wave while it sounds');
      final closed = plate(firstPartner.index);
      expect(closed.height, moreOrLessEquals(52, epsilon: 0.5), reason: '12 + 28 + 12, as in the frame');
      expect(circle(firstPartner.index).left - closed.left, moreOrLessEquals(14, epsilon: 0.5));
      final chip = tester.getRect(inTurn(firstPartner.index, find.byKey(const ValueKey('talk-open-text'))));
      expect(chip.left, greaterThan(circle(firstPartner.index).right), reason: 'the chip right of the circle');
      expect(chip.center.dy, moreOrLessEquals(circle(firstPartner.index).center.dy, epsilon: 0.5), reason: 'in one row');
      await finishLine(tester, blindStand);
      expect(inTurn(lastPartner.index, find.byKey(const ValueKey('talk-line-wave'))), findsNothing);
      expect(plate(lastPartner.index).height, moreOrLessEquals(52, epsilon: 0.5));

      // A tap on «текст» opens THIS line: its words, «прослушать» alone in the corner; the other lines stay closed.
      await tester.tap(inTurn(lastPartner.index, find.byKey(const ValueKey('talk-open-text'))));
      await tester.pump();
      expect(find.text(last), findsOneWidget);
      expect(inTurn(lastPartner.index, find.byKey(const ValueKey('talk-open-text'))), findsNothing, reason: 'open — «прослушать» alone');
      expect(inTurn(lastPartner.index, find.byKey(const ValueKey('talk-listen'))), findsOneWidget);
      expect(inTurn(firstPartner.index, find.byKey(const ValueKey('talk-open-text'))), findsOneWidget, reason: 'the other lines stay closed');
      await settleTalk(tester);
    });

    // RULE: the circle inside the container is a live button — a tap on it plays EXACTLY this line, open (with hints)
    // and closed (under «Без подсказок»): the 44 touch box stands 8 into the container's padding, the 28 circle where
    // the frame draws it.
    // CATCHES: a button the finger cannot reach because the container clipped its touch box.
    testWidgets('«прослушать» в контейнере играет свою реплику — открытую и закрытую', (tester) async {
      Finder inTurn(int index, Finder f) => find.descendant(of: find.byKey(ValueKey('turn-$index')), matching: f);

      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe);
      await finishLine(tester, stand);
      // The first line's words are open (CLIENT-CONV-1b), so the ribbon is taller than the screen and rests on the
      // newest line: bring the first one into view before aiming at its corner.
      await Scrollable.ensureVisible(tester.element(inTurn(firstPartner.index, find.byKey(const ValueKey('talk-listen')))), alignment: .5);
      await tester.pump();
      final opened = tester.getRect(inTurn(firstPartner.index, find.byKey(const ValueKey('talk-listen'))));
      await tester.tapAt(opened.topRight + const Offset(-3, 3), kind: PointerDeviceKind.touch);
      await tester.pump();
      expect(stand.voice.played.last, 'talk-${firstPartner.index}', reason: 'the corner of the open container\'s 44 touch box is the button too');
      stand.voice.finish();
      await settleTalk(tester);

      final blind = serverTalk('conversation-day-open', (json) {
        (json['hints'] as Map<String, dynamic>)
          ..['enabled'] = false
          ..['native'] = null;
      });
      final blindProbe = TalkProbe()..documents.add(blind);
      final blindStand = await pumpTalk(tester, blindProbe, hints: false);
      await finishLine(tester, blindStand);
      await Scrollable.ensureVisible(tester.element(inTurn(firstPartner.index, find.byKey(const ValueKey('talk-listen')))), alignment: .5);
      await tester.pump();
      final closed = tester.getRect(inTurn(firstPartner.index, find.byKey(const ValueKey('talk-listen'))));
      await tester.tapAt(closed.topLeft + const Offset(3, 3), kind: PointerDeviceKind.touch);
      await tester.pump();
      expect(blindStand.voice.played.last, 'talk-${firstPartner.index}', reason: 'the edge of the closed container\'s 44 touch box is the button too');
      blindStand.voice.finish();
      await settleTalk(tester);
    });

    // ПРАВИЛО (кадр 37-7c): «Не понял» и «Подсказать» (он — только в «Без подсказок», наряд CLIENT-FIX-4 §3) —
    // контурные плашки 44 по бокам микрофона, не текст: «Не понял» — контур чернил, «Подсказать» — латунь. Плашки не
    // заходят на кольцо микрофона.
    // ЛОВИТ: «Не понял» и «Подсказать» серыми словами — док до правки 21.09.
    testWidgets('37-7c: «Не понял» и «Подсказать» — контурные плашки 44 по бокам микрофона', (tester) async {
      final blind = serverTalk('conversation-day-open', (json) => (json['hints'] as Map<String, dynamic>)['enabled'] = false);
      final probe = TalkProbe()..documents.add(blind);
      final stand = await pumpTalk(tester, probe, hints: false);
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
    testWidgets('свой пузырь — только сказанное, конструкции подчёркнуты по ответу сервера', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      await pumpTalk(tester, probe);
      expect(find.text(firstOwn.textTarget!), findsOneWidget);
      expect(find.text(firstOwn.textNative ?? ''), findsNothing, reason: 'перевода под своей репликой нет');
      final line = tester.widget<TalkSageUnderline>(
        find.descendant(of: find.byKey(ValueKey('turn-${firstOwn.index}')), matching: find.byType(TalkSageUnderline)),
      );
      expect(firstOwn.phrasesUsed, isNotEmpty, reason: 'сервер засчитал этому ходу конструкцию');
      expect(line.text, firstOwn.textTarget);
      expect(line.marks, isNotEmpty);
      // «sure. here is my passport» против каркаса «Here is ___.» со значением «my passport»: подчёркнуты слова
      // конструкции, а не всё сказанное.
      expect([for (final m in line.marks) line.text.substring(m.start, m.end)], isNot(contains('sure')));
      await settleTalk(tester);
    });

    // ПРАВИЛО (приёмка окна 2, п. 1): подчёркиваются слова НЕПОДВИЖНОЙ ЧАСТИ каркаса и значения ученика — оба поля
    // сервера, регистр не важен, нормализация та же, что у покрытия речи. Слово, которое конструкция говорит один раз,
    // подчёркнуто везде, где его говорит реплика.
    // ЛОВИТ: «can I take» без подчерка из-за заглавной «Can» в каркасе (снимок 37-7d приёмки) и второе «I» реплики,
    // оставшееся без линии, когда бюджет слова съел первый такой же.
    testWidgets('подчерк по словам каркаса и значения — регистр не важен, каждое вхождение', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      await pumpTalk(tester, probe);
      // Ход, которому сервер засчитал две конструкции, одна из них — «Can I take ___ onboard?» с заглавной.
      final turn = open.turns.firstWhere((t) => t.phrasesUsed.length > 1);
      final capital = open.targets.firstWhere((t) => t.ref == turn.phrasesUsed.last.ref);
      expect(capital.frameTarget.startsWith('Can'), isTrue, reason: 'каркас с заглавной буквы');
      final line = tester.widget<TalkSageUnderline>(
        find.descendant(of: find.byKey(ValueKey('turn-${turn.index}')), matching: find.byType(TalkSageUnderline)),
      );
      final marked = [for (final m in line.marks) line.text.substring(m.start, m.end).toLowerCase()];
      for (final word in ['can', 'take', 'onboard', 'laptop', 'bag']) {
        expect(marked, contains(word), reason: '«$word» — слово конструкции');
      }
      expect(marked.where((w) => w == 'i').length, 2, reason: 'оба «I» реплики — слова конструкций');
      await settleTalk(tester);
    });

    // ПРАВИЛО (наряд FIX-3 §6): `phrases_used` называет конструкцию ПАРОЙ, а слов у неё в ходе нет — их клиент берёт
    // из `targets[]`. Пары, которой в списке нет, он не подчёркивает: гадать, что именно услышал сервер, нечем.
    // ЛОВИТ: подчерк по своему совпадению слов там, где сервер цель не назвал.
    testWidgets('пара, которой нет в targets, не подчёркивается', (tester) async {
      final unknown = serverTalk('conversation-day-open', (json) {
        for (final t in json['turns'] as List<dynamic>) {
          for (final u in (t as Map<String, dynamic>)['phrases_used'] as List<dynamic>) {
            (u as Map<String, dynamic>)['ref'] = 'p99';
          }
        }
      });
      await pumpTalk(tester, TalkProbe()..documents.add(unknown));
      final line = tester.widget<TalkSageUnderline>(
        find.descendant(of: find.byKey(ValueKey('turn-${firstOwn.index}')), matching: find.byType(TalkSageUnderline)),
      );
      expect(line.marks, isEmpty);
      await settleTalk(tester);
    });

    // ПРАВИЛО (кадр 37-7 «после „Не понял"», CONV-2 п. 4а, наряд CLIENT-CONV-1c §2б): ход `rescue` несёт слова — «Sorry?»
    // на языке разговора — и стоит своим тёмным пузырём между двумя репликами роли: без перевода под ним и без
    // подчерка в нём. Пометки «переспросил» больше нет.
    // ЛОВИТ: пометку «переспросил» на месте пузыря (сборка 18) и перевод или шалфей в пузыре переспроса.
    testWidgets('переспрос — свой тёмный пузырь «Sorry?», без перевода; пометки нет', (tester) async {
      // Тот же документ, у которого ход ученика — переспрос: `kind: rescue` со словами пакета («Sorry?»), как их
      // шлёт сервер (CONV-2 п. 4а). Живой прогон FIX-3 переспроса не оставил, а правило кадра 37-7 стоит.
      final asked = serverTalk('conversation-day-open', (json) {
        final turn = (json['turns'] as List<dynamic>)[firstOwn.index - 1] as Map<String, dynamic>;
        turn
          ..['kind'] = 'rescue'
          ..['text_target'] = 'Sorry?'
          ..['phrases_used'] = <Object>[];
      });
      await pumpTalk(tester, TalkProbe()..documents.add(asked));

      final rescue = find.byKey(ValueKey('turn-${firstOwn.index}'));
      expect(tester.widget<TalkOwnBubble>(rescue).text, 'Sorry?');
      expect(tester.widget<TalkOwnBubble>(rescue).marks, isEmpty, reason: 'в переспросе нет конструкций');
      expect(find.descendant(of: rescue, matching: find.byType(Text)), findsOneWidget, reason: 'только «Sorry?», перевода нет');
      expect(find.text('переспросил'), findsNothing);
      final bubble = tester.getRect(rescue);
      expect(tester.getRect(find.byKey(ValueKey('turn-${firstOwn.index - 1}'))).bottom, lessThanOrEqualTo(bubble.top));
      expect(tester.getRect(find.byKey(ValueKey('turn-${firstOwn.index + 1}'))).top, greaterThanOrEqualTo(bubble.bottom),
          reason: 'между двумя репликами роли');
      final plate = tester.widget<SessionBubble>(find.descendant(of: rescue, matching: find.byType(SessionBubble)));
      expect(plate.own, isTrue, reason: 'тёмный пузырь ученика');
      await settleTalk(tester);
    });

    // ПРАВИЛО: пока «Не понял» в полёте, телефон не пишет «Sorry?» за сервер — слова переспроса приходят с ответом.
    // ЛОВИТ: выдуманное телефоном «Sorry?» в ленте.
    testWidgets('переспрос в полёте — ничего на его месте до ответа', (tester) async {
      final hold = Completer<void>();
      final flight = TalkProbe()
        ..documents.addAll([open, open])
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
      final stand = await pumpTalk(tester, probe, recognizer: ListeningRecognizer());
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
      final stand = await pumpTalk(tester, probe, recognizer: ListeningRecognizer());
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
    // ПРАВИЛО (кадр 37-7c; наряды CLIENT-CONV-1c §5, CLIENT-FIX-4 §3): в «Без подсказок» ПЛАШКИ ПОДСКАЗКИ НЕТ — справа
    // от микрофона «Подсказать», по тапу плашка встаёт на этот ход; тексты реплик роли закрыты до тапа: у каждой — пара
    // «прослушать» + «текст».
    // ЛОВИТ: плашку, стоящую независимо от режима, и закрытую реплику без «текст» (харнесс 08).
    testWidgets('в «Без подсказок» плашки нет, есть «Подсказать»; тексты закрыты до «текст»', (tester) async {
      final blind = serverTalk('conversation-day-open', (json) {
        (json['hints'] as Map<String, dynamic>)
          ..['enabled'] = false
          ..['native'] = null;
      });
      final probe = TalkProbe()..documents.add(blind);
      final stand = await pumpTalk(tester, probe, hints: false);
      expect(probe.hints, isFalse, reason: 'режим уходит на сервер один раз, со стартом');
      await finishLine(tester, stand);
      await tester.pump(const Duration(seconds: 6));

      expect(find.byKey(const ValueKey('talk-hint-plate')), findsNothing, reason: 'по таймеру ничего не встаёт');
      expect(find.byKey(const ValueKey('talk-hint')), findsOneWidget);
      final turn = find.byKey(const ValueKey('turn-5'));
      expect(find.descendant(of: turn, matching: find.byKey(const ValueKey('talk-listen'))), findsOneWidget);
      expect(find.descendant(of: turn, matching: find.byKey(const ValueKey('talk-open-text'))), findsOneWidget,
          reason: 'закрытая реплика — «прослушать» и «текст»');
      expect(find.text('Hello. What brings you in today?'), findsNothing, reason: 'тексты закрыты до тапа');
      expect(find.byKey(const ValueKey('talk-rescue')), findsOneWidget, reason: 'переспрос — не подсказка');
      await settleTalk(tester);
    });

    // ПРАВИЛО (кадр 37-7, наряд CLIENT-FIX-4 §3): с подсказками плашка стоит сразу — целая фраза урока `hints.sentence`
    // как пришла, без «Скажи, что …»; кнопки «Подсказать» нет. Нет фразы у сервера — нет и плашки: придаточное
    // `hints.native` сборки (20) телефон больше не читает.
    // ЛОВИТ: рамку «Скажи, что …» (для цели-вопроса она давала «Скажи, что мне сказать вам…») и плашку из `native`.
    testWidgets('с подсказками плашка — фраза сервера как есть; без sentence — плашки нет', (tester) async {
      final whole = serverTalk('conversation-day-open', (json) => (json['hints'] as Map<String, dynamic>)['sentence'] = 'Мне сказать вам его температуру?');
      final stand = await pumpTalk(tester, TalkProbe()..documents.add(whole));
      await finishLine(tester, stand);
      expect(find.text('Мне сказать вам его температуру?'), findsOneWidget);
      expect(find.textContaining('Скажи, что'), findsNothing);
      expect(find.byKey(const ValueKey('talk-hint')), findsNothing);
      await settleTalk(tester);

      final clauseOnly = await pumpTalk(tester, TalkProbe()..documents.add(open));
      await finishLine(tester, clauseOnly);
      expect(open.hints.sentence, isNull, reason: 'фикстура до FIX-4b');
      expect(find.byKey(const ValueKey('talk-hint-plate')), findsNothing);
      expect(find.text('я сдаю в багаж …'), findsNothing, reason: 'hints.native не читается');
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
      // The answer of the move: the same talk with the learner's line and the role's reply at the end of the ribbon.
      final answered = serverTalk('conversation-day-open', (json) {
        final turns = json['turns'] as List<dynamic>;
        final own = turns.lastWhere((t) => (t as Map<String, dynamic>)['speaker'] == 'learner') as Map<String, dynamic>;
        final role = turns.last as Map<String, dynamic>;
        final next = (role['index'] as int) + 1;
        turns.addAll([
          {...own, 'index': next, 'text_target': line, 'phrases_used': <Object>[]},
          {...role, 'index': next + 1},
        ]);
      });
      final hold = Completer<void>();
      final probe = TalkProbe()
        ..documents.addAll([open, answered])
        ..holdMove = hold;
      final stand = await pumpTalk(tester, probe);
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
      final stand = await pumpTalk(tester, probe);
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
      final stand = await pumpTalk(tester, probe);
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
      final stand = await pumpTalk(tester, probe);
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
      final stand = await pumpTalk(tester, probe);
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
      final stand = await pumpTalk(tester, probe, recognizer: recognizer);
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
      final stand = await pumpTalk(tester, probe);
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
      final stand = await pumpTalk(tester, probe, onSummary: () => summaries++);
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

  group('37-7…37-11 · плашки конструкций и их лист', () {
    /// The plate of a construction, by the pair the server names it with.
    Finder chip(TalkTarget t) => find.byKey(ValueKey('talk-construction-${t.sceneId}-${t.ref}'));

    Future<void> expectChips(WidgetTester tester, PlanConversation talk, String state) async {
      final row = find.byKey(const ValueKey('talk-constructions'));
      expect(row, findsOneWidget, reason: state);
      expect(tester.getSize(row).height, TalkConstructionChips.height, reason: '$state: плашки 44');
      // The first plate still to say is always on screen; the rest scroll sideways under the dock's edge.
      expect(chip(talk.targets.firstWhere((t) => !t.said)), findsOneWidget, reason: state);
    }

    // ПРАВИЛО (наряды FIX-3 §3, CLIENT-FIX-4 §2; кадры 37-7…37-10): ряд плашек-конструкций — часть дока, над
    // микрофоном во ВСЕХ состояниях ленты: роль говорит, твоя очередь (покой, запись), ход в полёте, прервал, сбои.
    // Между рядом и тем, что под ним, — 14.
    // ЛОВИТ: ряд, пропадающий в каком-то из состояний, и плашки, посчитанные телефоном.
    testWidgets('ряд плашек стоит во всех состояниях ленты', (tester) async {
      final hold = Completer<void>();
      final probe = TalkProbe()
        ..documents.addAll([open, open])
        ..holdMove = hold;
      final stand = await pumpTalk(tester, probe, recognizer: ListeningRecognizer());
      await expectChips(tester, open, 'роль говорит (37-6)');
      final caption = tester.getRect(find.byKey(const ValueKey('talk-caption')));
      expect(
        caption.top - tester.getRect(find.byKey(const ValueKey('talk-constructions'))).bottom,
        moreOrLessEquals(14, epsilon: 0.5),
      );

      await finishLine(tester, stand);
      await expectChips(tester, open, 'твоя очередь (37-7)');

      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      expect(stand.mics.single.state, MicState.listening);
      await expectChips(tester, open, 'слушаю (37-7)');
      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      await tester.pump();
      expect(stand.talk.trouble, TalkTrouble.unheard);
      await expectChips(tester, open, 'не расслышал (37-10)');

      await tester.tap(find.byKey(const ValueKey('talk-rescue')));
      await tester.pump();
      expect(stand.talk.phase, TalkPhase.sending);
      await expectChips(tester, open, 'ход в полёте (37-8)');
      hold.complete();
      await tester.pump();
      await tester.pump();
      await tester.tap(find.byKey(const ValueKey('talk-mic')));
      await tester.pump();
      await tester.pump();
      expect(find.byKey(const ValueKey('talk-interrupted')), findsOneWidget);
      await expectChips(tester, open, 'прервал (37-9)');
      await settleTalk(tester);

      final failing = TalkProbe()
        ..documents.addAll([open, open])
        ..failMove = _offline();
      final broken = await pumpTalk(tester, failing);
      await finishLine(tester, broken);
      await _say(tester);
      expect(broken.talk.trouble, TalkTrouble.offline);
      await expectChips(tester, open, 'связь пропала (37-10)');
      await settleTalk(tester);
    });

    // ПРАВИЛО (наряд CLIENT-FIX-4 §2, кадр 37-7): в ряду — только то, что ещё сказать: несказанная — бумага в контуре
    // 1,5 с пустым окном `___` латунью; сказанной СЕРВЕРОМ в ряду нет. Разговор до FIX-4 (без `state`) читается по
    // `said` — та же картина.
    // ЛОВИТ: сказанную плашку, оставшуюся в ряду (проход Дена на (20)), и плашку, закрашенную телефоном.
    testWidgets('в ряду — только несказанные: бумага, контур, пустое окно', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe);
      await finishLine(tester, stand);
      for (final t in open.targets) {
        final plate = chip(t);
        if (t.said) {
          expect(plate, findsNothing, reason: '${t.ref}: сказана — уехала из ряда');
          continue;
        }
        await tester.dragUntilVisible(plate, find.byKey(const ValueKey('talk-constructions')), const Offset(-120, 0));
        await tester.pump();
        final box = tester.widget<AnimatedContainer>(plate).decoration! as BoxDecoration;
        expect(box.color, AppColors.paper, reason: '${t.ref}: бумага');
        expect((box.border! as Border).top.width, 1.5);
        expect(find.descendant(of: plate, matching: find.byKey(const ValueKey('talk-construction-said'))), findsNothing);
        expect(find.descendant(of: plate, matching: find.text(TalkTarget.window)), findsWidgets, reason: '${t.ref}: пустое окно');
      }
      await settleTalk(tester);
    });

    // ПРАВИЛО (наряд CLIENT-FIX-4 §2, кадр 37-8d): тап по ряду — лист снизу над затемнением 40 %: «Конструкции в
    // разговоре» и крестик 24, все конструкции сцены в её порядке — сказанная на шалфее с галкой и «ты сказал: …»
    // (своё слово, не пример урока), несказанная в контуре и «из урока: …». Крестик закрывает лист.
    // ЛОВИТ: лист одной конструкции (FIX-3), «ты сказал» у несказанной и пример урока вместо своего значения.
    testWidgets('37-8d: лист — все конструкции сцены, «ты сказал» / «из урока»', (tester) async {
      final probe = TalkProbe()..documents.add(open);
      final stand = await pumpTalk(tester, probe);
      await finishLine(tester, stand);
      await tester.tap(find.byKey(const ValueKey('talk-constructions')));
      await tester.pumpAndSettle();

      final sheet = find.byKey(const ValueKey('talk-construction-sheet'));
      expect(sheet, findsOneWidget);
      expect(find.descendant(of: sheet, matching: find.text('Конструкции в разговоре')), findsOneWidget);
      // A construction the learner filled with a word OF THEIR OWN: the sheet says theirs, not the lesson's.
      final own = open.targets.firstWhere((t) => t.said && t.valueTarget != t.exampleTarget);
      final ownCard = find.byKey(ValueKey('talk-construction-card-${own.sceneId}-${own.ref}'));
      expect(find.descendant(of: ownCard, matching: find.text('ты сказал: ${own.saidWith(own.valueTarget)}')), findsOneWidget);
      final notSaid = open.targets.firstWhere((t) => !t.said);
      final notSaidCard = find.byKey(ValueKey('talk-construction-card-${notSaid.sceneId}-${notSaid.ref}'));
      await tester.ensureVisible(notSaidCard);
      expect(find.descendant(of: notSaidCard, matching: find.text('из урока: ${notSaid.lessonLine}')), findsOneWidget);
      expect(find.descendant(of: notSaidCard, matching: find.textContaining('ты сказал')), findsNothing);
      for (final t in open.targets) {
        expect(find.byKey(ValueKey('talk-construction-card-${t.sceneId}-${t.ref}')), findsOneWidget, reason: t.ref);
      }

      await tester.tap(find.byKey(const ValueKey('talk-construction-sheet-close')));
      await tester.pumpAndSettle();
      expect(sheet, findsNothing);
      await settleTalk(tester);
    });

    // ПРАВИЛО: целей у разговора нет — ряда нет ни в одном состоянии: клиент не рисует пустой док.
    // ЛОВИТ: ряд из нуля плашек и счёт фраз, посчитанный на телефоне.
    testWidgets('без targets ряда нет', (tester) async {
      final probe = TalkProbe()..documents.add(serverTalk('conversation-day-open', (json) => json['targets'] = <dynamic>[]));
      final stand = await pumpTalk(tester, probe);
      expect(find.byKey(const ValueKey('talk-constructions')), findsNothing);
      await finishLine(tester, stand);
      expect(find.byKey(const ValueKey('talk-constructions')), findsNothing);
      await settleTalk(tester);
    });
  });

  group('37-12 · конструкции — карточки итога', () {
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

    Finder card(TalkTarget t) => find.byKey(ValueKey('talk-construction-card-${t.sceneId}-${t.ref}'));

    // ПРАВИЛО (наряд FIX-3 §3, кадры 37-12, 37-12b): итог — ОДИН список конструкций в порядке сервера под
    // «Конструкции в разговоре», без счётчика и без групп по сценам. Сказанная лежит на шалфее 15 % с галкой, и под ней
    // серым «ты сказал: <каркас со своим значением>»; несказанная — в контуре чернил, окно пустое, под ней «вернётся
    // завтра» у дня и «повтори перед разговором» там, где завтра нет.
    // ЛОВИТ: счётчик «N из M», группы «Не прозвучало» и обещание вернуть завтра то, что сервер не вернёт.
    testWidgets('день: закрашенные с «ты сказал», несказанные — «вернётся завтра»', (tester) async {
      final talk = serverTalk('conversation-day-ended', (json) {
        final summary = json['summary'] as Map<String, dynamic>;
        summary['returns_tomorrow'] = true;
        for (final ref in ['p6', 'p7']) {
          final phrase = (summary['phrases'] as List<dynamic>).firstWhere((p) => (p as Map<String, dynamic>)['ref'] == ref) as Map<String, dynamic>;
          phrase['said'] = false;
          phrase['value_target'] = null;
        }
      });
      await pumpSummary(tester, talk);
      final phrases = talk.summary!.phrases;
      expect(phrases.where((p) => p.said), isNotEmpty);
      expect(phrases.where((p) => !p.said), isNotEmpty);
      expect(find.text('КОНСТРУКЦИИ В РАЗГОВОРЕ'), findsOneWidget);
      expect(find.textContaining('ИЗ 7'), findsNothing, reason: 'счётчика на итоге нет');

      final tops = <double>[];
      for (final p in phrases) {
        final row = card(p);
        expect(row, findsOneWidget, reason: p.ref);
        final box = tester.widget<Container>(row).decoration! as BoxDecoration;
        final check = find.descendant(of: row, matching: find.byKey(const ValueKey('talk-construction-said')));
        if (p.said) {
          expect(box.color, AppColors.sessionSageWash, reason: '${p.ref}: шалфей 15 %');
          expect(box.border, isNull);
          expect(check, findsOneWidget);
          expect(find.descendant(of: row, matching: find.text('ты сказал: ${p.saidWith(p.valueTarget)}')), findsOneWidget);
        } else {
          expect(box.color, isNull, reason: '${p.ref}: без подложки');
          expect((box.border! as Border).top.color, AppColors.markerOutline);
          expect(check, findsNothing);
          expect(find.descendant(of: row, matching: find.text('вернётся завтра')), findsOneWidget);
        }
        tops.add(tester.getRect(row).top);
      }
      expect([...tops]..sort(), tops, reason: 'в порядке сервера');
    });

    // ПРАВИЛО (кадр 37-12b): у репетиции завтра событие, а не день плана (`returns_tomorrow: false`) — несказанное
    // читается «повтори перед разговором»; заголовок — «Ты готов к разговору» (формы события сервер не шлёт — отчёт
    // CLIENT-FIX-4 §7), строка «понял» со своим значком.
    // ЛОВИТ: «вернётся завтра» в репетиции, заголовок дня на репетиции и «к приёму» у плана, где событие — тренировка.
    testWidgets('37-12b: репетиция — «повтори перед разговором», без «завтра»', (tester) async {
      final talk = serverTalk('conversation-rehearsal-ended', (json) {
        final summary = json['summary'] as Map<String, dynamic>;
        final phrase = (summary['phrases'] as List<dynamic>).last as Map<String, dynamic>;
        phrase['said'] = false;
        phrase['value_target'] = null;
      });
      await pumpSummary(tester, talk);
      expect(talk.summary!.returnsTomorrow, isFalse);
      expect(find.text('Ты готов к разговору'), findsOneWidget);
      expect(find.byKey(const ValueKey('talk-summary-understood-check')), findsOneWidget);
      final check = tester.getRect(find.byKey(const ValueKey('talk-summary-understood-check')));
      final understood = tester.getRect(find.byKey(const ValueKey('talk-summary-understood')));
      expect(check.size, const Size(20, 20));
      expect(understood.left - check.right, moreOrLessEquals(12, epsilon: 0.5));
      expect(find.text('повтори перед разговором'), findsOneWidget);
      expect(find.textContaining('завтра'), findsNothing);
    });

    // ПРАВИЛО (наряд FIX-3 §5, CONV-2 п. 2): повтор поверх пройденного этапа ничего не возвращает завтра — и карточки
    // несказанного читаются «повтори перед разговором», как в репетиции: клиент печатает ответ сервера, а не вывод из
    // вида дня.
    // ЛОВИТ: «вернётся завтра» на повторе, который ничего не вернёт.
    testWidgets('повтор дня: несказанное — «повтори перед разговором»', (tester) async {
      final talk = serverTalk('conversation-day-ended', (json) {
        final summary = json['summary'] as Map<String, dynamic>;
        summary['returns_tomorrow'] = false;
        final phrase = (summary['phrases'] as List<dynamic>).last as Map<String, dynamic>;
        phrase['said'] = false;
        phrase['value_target'] = null;
      });
      expect(talk.replay, isTrue);
      await pumpSummary(tester, talk);
      expect(find.text('повтори перед разговором'), findsOneWidget);
      expect(find.textContaining('вернётся завтра'), findsNothing);
    });

    // ПРАВИЛО (наряд FIX-3 §§3, 5): на итоге разговора «Ещё раз» больше нет — ещё один разговор начинается с ряда
    // этапа в окне дня, где сервер и считает повторы.
    // ЛОВИТ: две двери к повтору и «Ещё раз», ведущее мимо лимита сервера.
    testWidgets('на итоге нет «Ещё раз» — одна кнопка «Дальше»', (tester) async {
      await pumpSummary(tester, serverTalk('conversation-day-ended'));
      expect(find.byKey(const ValueKey('talk-again')), findsNothing);
      expect(find.byType(SessionTextExit), findsNothing);
      expect(find.byKey(const ValueKey('talk-next')), findsOneWidget);
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
          backend: FakeTalkBackend(TalkProbe()..documents.add(open)),
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

  group('документы сервера FIX-3', () {
    // ПРАВИЛО (handoff FIX-3 §9 п. 4): документы разговора, как их переснял сервер наряда, — живые и рисуются без
    // правок: цель это КОНСТРУКЦИЯ (`frame_target` с окном, `example_target` урока, `value_target` ученика),
    // `phrases_used` хода — пара «сцена · ref», итог — тот же список.
    // ЛОВИТ: документ сервера, который клиент перестал разбирать, и цель, прочитанную как фразу.
    test('разбор: цели — каркасы с окном, ход называет цель парой, итог — тот же список', () {
      final open = serverTalk('conversation-day-open');
      expect(open.titleNative, 'Поговори с сотрудником стойки');
      expect(open.targets, hasLength(7));
      expect(open.targets.first.frameTarget, 'Here is ___.');
      expect(open.targets.first.exampleTarget, 'my passport');
      expect([for (final t in open.targets) if (t.said) t.ref], ['p1', 'p2', 'p3', 'p5']);
      // Что ученик вставил в окно — его, а не пример урока: «London» в уроке, «Lisbon» в разговоре.
      final flying = open.targets.firstWhere((t) => t.ref == 'p2');
      expect((flying.exampleTarget, flying.valueTarget), ('London', 'Lisbon'));
      expect([for (final u in open.turns[1].phrasesUsed) u.ref], ['p1']);
      expect(open.turns[1].phrasesUsed.single.sceneId, open.targets.first.sceneId, reason: 'пара, а не один ref');

      final ended = serverTalk('conversation-day-ended');
      expect(ended.summary!.said, hasLength(7));
      expect(ended.summary!.phrases.first.valueTarget, 'my passport');
      expect((ended.summary!.phrasesUsed, ended.summary!.phrasesTotal), (7, 7));

      final rehearsal = serverTalk('conversation-rehearsal-ended');
      expect(rehearsal.type, TalkType.rehearsal);
      expect(rehearsal.scenes, hasLength(2));
      expect(rehearsal.summary!.returnsTomorrow, isFalse);
      // Каркас без окна приходит целиком и без примера — его нечем заполнять.
      final whole = rehearsal.targets.firstWhere((t) => t.exampleTarget == null);
      expect(whole.frameTarget.contains(TalkTarget.window), isFalse);
    });

    // ПРАВИЛО (кадр 37-8, наряд FIX-3 §6): свой пузырь подчёркивает то, что СЕРВЕР засчитал этому ходу — какая
    // конструкция, говорит `phrases_used` парой, а её слова читаются из `targets[]`.
    // ЛОВИТ: подчерк по своему совпадению слов и пузырь без подчерка там, где сервер цель засчитал.
    testWidgets('лента: засчитанное сервером подчёркнуто по словам конструкции', (tester) async {
      final probe = TalkProbe()..documents.add(serverTalk('conversation-day-open'));
      final stand = await pumpTalk(tester, probe);
      await finishLine(tester, stand);
      expect(tester.widget<TalkOwnBubble>(find.byKey(ValueKey('turn-${firstOwn.index}'))).marks, isNotEmpty);
      await settleTalk(tester);
    });
  });

  group('контракт', () {
    // ПРАВИЛО НАРЯДА: нет поля на проводе — честная ошибка, а не догадка.
    // ЛОВИТ: разбор, который подставляет ноль вместо пропавшего счёта и рисует «0 из 0».
    test('документ без поля — ошибка контракта, а не нарисованное состояние', () {
      final json = serverFixtureJson('conversation-day-open')..remove('turns_left');
      expect(() => PlanConversation.fromJson(json), throwsA(isA<FormatException>()));

      final state = serverFixtureJson('conversation-day-open')..['state'] = 'thinking';
      expect(() => PlanConversation.fromJson(state), throwsA(isA<FormatException>()));
    });

    // ПРАВИЛО: `returns_tomorrow` — ответ сервера, а не вывод из вида дня. У репетиции завтра
    // событие, и несказанное читается «повтори перед разговором».
    // ЛОВИТ: клиент, выводящий возврат из `type == rehearsal` вместо поля итога.
    test('итог репетиции: несказанное не возвращается завтра', () {
      final rehearsal = serverTalk('conversation-rehearsal-ended');
      expect(rehearsal.type, TalkType.rehearsal);
      expect(rehearsal.scenes, hasLength(2));
      expect(rehearsal.summary!.returnsTomorrow, isFalse);
    });

    // ПРАВИЛО (наряд FIX-3 §6): список целей ДОБАВОЧНЫЙ — его нет, и разговор тот же, только без плашек; цель без
    // своего каркаса выпадает из списка, а не рисуется пустой плашкой.
    // ЛОВИТ: разговор, который перестал открываться без `targets`, и плашку без слов.
    test('цели — добавочные: без них разговор тот же, цель без каркаса выпадает', () {
      final none = serverTalk('conversation-day-open', (json) => json.remove('targets'));
      expect(none.targets, isEmpty);
      expect(none.turns, isNotEmpty);

      final broken = serverTalk('conversation-day-open', (json) {
        ((json['targets'] as List<dynamic>)[2] as Map<String, dynamic>).remove('frame_target');
      });
      expect([for (final t in broken.targets) t.ref], ['p1', 'p2', 'p4', 'p5', 'p6', 'p7']);
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

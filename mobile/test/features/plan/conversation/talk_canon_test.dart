import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/conversation/conversation_models.dart';
import 'package:eng_std/features/plan/conversation/conversation_controller.dart';
import 'package:eng_std/features/plan/conversation/talk_ribbon.dart';
import 'package:eng_std/features/plan/session/session_mic.dart';

import '../../../support/session_harness.dart' show enterHeard;
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
      expect(find.text('Скажи, что У моего сына температура.'), findsOneWidget);
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

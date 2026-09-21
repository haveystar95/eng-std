import 'package:dio/dio.dart';
import 'package:drift/native.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/local/app_database.dart';
import 'package:eng_std/data/plan/conversation/conversation_models.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/conversation/talk_replay_screen.dart';

import '../../support/day_window_harness.dart';
import '../../support/plan_goldens.dart';
import '../../support/session_harness.dart' show SilentRecognizer;
import '../../support/talk_harness.dart' show talkV2;

/// «ПОВТОРИТЬ РАЗГОВОР» FROM THE WINDOW (наряд CLIENT-CONV-1c §9г; `window.talk_again` of BACK-TAILS-2) — on a walked day
/// of any kind. The field is not in the server's fixtures yet: the tests write it onto a reply as the branch serializes
/// it. The start is the one POST of a talk; its answer is heard where the button is.
void main() {
  setUpAll(setUpPlanGoldens);

  /// The walked scene day of the window fixtures («Ещё раз»), with `talk_again` as the server says it.
  PlanDayRoom walkedScene({bool? talkAgain}) => windowRoom('passed', (json) {
    final data = (json['data'] as Map<String, dynamic>?) ?? json;
    if (talkAgain != null) (data['window'] as Map<String, dynamic>)['talk_again'] = talkAgain;
    return json;
  });

  /// The same day with no action of the cards — the walked rehearsal of 37-1, where the talk's replay is the one button.
  PlanDayRoom walkedNoAction() => windowRoom('passed', (json) {
    final data = (json['data'] as Map<String, dynamic>?) ?? json;
    (data['window'] as Map<String, dynamic>)
      ..['allowed_action'] = null
      ..['talk_again'] = true;
    return json;
  });

  Future<_Api> pumpWith(WidgetTester tester, PlanDayRoom room, {Object? failure}) async {
    final api = _Api(failure: failure);
    final db = AppDatabase.forTesting(NativeDatabase.memory());
    addTearDown(db.close);
    // A new window is a new scope: the providers of the one before do not answer for this reply.
    await tester.pumpWidget(const SizedBox());
    await pumpDayWindowServer(tester, WindowServer(room, api: api, db: db, recognizer: SilentRecognizer()));
    return api;
  }

  // RULE (§9г; 37-1 «пройден»): a walked day that has its own action keeps it as the button — «Ещё раз» of the cards is
  // untouched — and «Повторить разговор» stands over it as a brass link; a day with no action has «Повторить разговор»
  // as its one button; no `talk_again` — neither.
  // CATCHES: «Ещё раз» replaced by the talk's replay, the replay offered on a day the server did not open it for.
  testWidgets('«Ещё раз» остаётся кнопкой, «Повторить разговор» — ссылкой над ней; без действия — одной кнопкой', (tester) async {
    await pumpWith(tester, walkedScene(talkAgain: true));
    expect(tester.widget<Text>(find.descendant(of: find.byKey(const ValueKey('window-action')), matching: find.byType(Text))).data, 'Ещё раз');
    expect(find.descendant(of: find.byKey(const ValueKey('window-action-secondary')), matching: find.text('Повторить разговор')), findsOneWidget);

    await pumpWith(tester, walkedNoAction());
    expect(find.descendant(of: find.byKey(const ValueKey('window-action')), matching: find.text('Повторить разговор')), findsOneWidget);
    expect(find.byKey(const ValueKey('window-action-secondary')), findsNothing);

    await pumpWith(tester, walkedScene());
    expect(find.text('Повторить разговор'), findsNothing, reason: 'no `talk_again` — no replay');
    await pumpWith(tester, walkedScene(talkAgain: false));
    expect(find.text('Повторить разговор'), findsNothing);
  });

  // RULE (§9г): the tap is ONE POST of a new talk (not `again` — that is the talk summary's «Ещё раз» over an open talk)
  // with the learner's «Без подсказок», and the talk opens on the talk's own screens.
  // CATCHES: a second request, a start sent as `again`, a talk that opens without asking the server.
  testWidgets('тап — один POST разговора, и открывается экран разговора', (tester) async {
    final api = await pumpWith(tester, walkedScene(talkAgain: true));
    await tester.tap(find.byKey(const ValueKey('window-action-secondary')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    await tester.pump(const Duration(milliseconds: 400));
    expect(api.starts, [(again: false, hints: true)]);
    expect(find.byType(TalkReplayScreen), findsOneWidget);
    expect(find.byKey(const ValueKey('window-notice-sheet')), findsNothing);
  });

  // RULE (§9г): 409 `plan_conversation_replay_limit` — a sheet over the window, «Разговор сегодня уже повторяли —
  // вернись завтра», «Понятно» closes it; no talk screen opens and the window stays.
  // CATCHES: the limit said as «Разговор не начался — попробуй ещё раз» (a retry that is refused again), and an empty
  // talk screen opened over a refusal.
  testWidgets('409 лимита — лист «вернись завтра» над окном, экран разговора не открывается', (tester) async {
    final api = await pumpWith(tester, walkedNoAction(), failure: _problem(409, 'plan_conversation_replay_limit'));
    await tester.tap(find.byKey(const ValueKey('window-action')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    await tester.pump(const Duration(milliseconds: 400));
    expect(api.starts, hasLength(1));
    expect(find.byType(TalkReplayScreen), findsNothing);
    expect(tester.widget<Text>(find.byKey(const ValueKey('window-notice-text'))).data, 'Разговор сегодня уже повторяли — вернись завтра');
    await tester.tap(find.text('Понятно'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    expect(find.byKey(const ValueKey('window-notice-sheet')), findsNothing);
    expect(find.text('Повторить разговор'), findsOneWidget, reason: 'the window stays');

    // Any other refusal is the talk's own «could not start».
    final other = await pumpWith(tester, walkedNoAction(), failure: _problem(422, 'plan_conversation_not_available'));
    await tester.tap(find.byKey(const ValueKey('window-action')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    await tester.pump(const Duration(milliseconds: 400));
    expect(other.starts, hasLength(1));
    expect(tester.widget<Text>(find.byKey(const ValueKey('window-notice-text'))).data, 'Разговор не начался — попробуй ещё раз');
  });
}

DioException _problem(int status, String code) {
  final request = RequestOptions(path: '/plans/ulid-plan/days/1/conversation');
  return DioException(
    requestOptions: request,
    type: DioExceptionType.badResponse,
    response: Response(
      requestOptions: request,
      statusCode: status,
      data: {
        'type': 'about:blank',
        'status': status,
        'code': code,
        'meta': {'retry_after_utc': '2026-09-22T21:00:00Z'},
      },
    ),
  );
}

/// The server of the window's talk: a start answered with the open talk of CONV-2, or refused with [failure].
class _Api implements ApiClient {
  _Api({this.failure});

  final Object? failure;
  final List<({bool again, bool hints})> starts = [];

  @override
  Future<PlanConversation> startConversation(String planId, int number, {bool again = false, bool hints = true}) async {
    starts.add((again: again, hints: hints));
    if (failure case final f?) throw f;
    return talkV2('talk_day_open_v2');
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

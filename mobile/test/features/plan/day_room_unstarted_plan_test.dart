import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan/day_contract.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/features/plan/day/day_room_screen.dart';

import '../../goldens/golden_support.dart';

/// КАБИНЕТ ДНЯ НЕНАЧАТОГО ПЛАНА (13.09, с телефона).
///
/// `GET /plans/current` отдаёт собранный и не запущенный план (`ready`), и в кабинет его дня 1 можно
/// войти до «Начать». «Начать» в кабинете вёл прямо в сессию, сессия открывала день — сервер отвечал
/// 409 `plan_state` («open day» needs the plan active|overdue), экран показывал «Не получилось
/// загрузить план», и «Повторить» получал тот же 409.
void main() {
  setUpAll(loadAppFonts);

  late Directory audioDir;
  setUp(() => audioDir = Directory.systemTemp.createTempSync('room-unstarted'));
  tearDown(() {
    try {
      audioDir.deleteSync(recursive: true);
    } catch (_) {}
  });

  // ПРАВИЛО: «Начать» дня 1 у неначатого плана — это «Начать» плана: сначала `POST /start`, потом
  // открытие дня, и ровно по одному разу.
  // ЛОВИТ: сессию, которая открывает день плана в статусе `ready` (409 и «Не получилось загрузить
  // план»), и двойной запуск.
  testWidgets('«Начать» в кабинете неначатого плана запускает план, потом открывает день', (tester) async {
    final active = Plan.fromJson(fixture('plan-beginner'));
    final ready = Plan.fromJson({...active.raw, 'status': 'ready'});
    final api = _ServerThatNeedsStart(
      plan: ready,
      room: PlanDayRoom.fromJson(fixture('room-beginner-d1-open')),
      cards: [],
      sheet: DaySheet.fromJson(fixture('sheet-beginner-d1')),
    );
    expect(ready.status, PlanStatus.ready);

    await setFrame(tester, height: 1900);
    muteNativeChannels(tester);
    await tester.pumpWidget(
      goldenApp(home: DayRoomScreen(plan: ready, number: 1), api: api, recognizer: ScriptedRecognizer(), audioDir: audioDir),
    );
    await settle(tester, frames: 8);

    await tester.tap(find.text('Начать').last);
    await settle(tester, frames: 10);

    expect(api.starts, 1, reason: 'план запускается один раз');
    expect(api.opensRefused, 0, reason: 'день не открывается у плана, который не начат');
    expect(api.opens, 1, reason: 'после запуска день открыт');
    expect(find.text('Не получилось загрузить план'), findsNothing);
  });
}

/// Сервер, который, как backend2, не открывает день неначатого плана.
class _ServerThatNeedsStart extends GoldenApi {
  _ServerThatNeedsStart({required super.plan, required super.room, required super.cards, super.sheet});

  int starts = 0;
  int opens = 0;
  int opensRefused = 0;

  @override
  Future<Plan> startPlan(String planId) async {
    starts++;
    current = Plan.fromJson({...current.raw, 'status': 'active'});

    return current;
  }

  @override
  Future<DayCards> openDay(String planId, int number) async {
    if (!current.status.isLive) {
      opensRefused++;
      final request = RequestOptions(path: '/plans/$planId/days/$number/open');
      throw DioException(
        requestOptions: request,
        response: Response(requestOptions: request, statusCode: 409, data: {'code': 'plan_state'}),
        type: DioExceptionType.badResponse,
      );
    }
    opens++;

    return super.openDay(planId, number);
  }
}

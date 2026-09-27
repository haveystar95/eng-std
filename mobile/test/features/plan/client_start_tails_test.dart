import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/api_client.dart';
import 'package:eng_std/data/app_version.dart';
import 'package:eng_std/data/audio_mixer.dart';
import 'package:eng_std/data/line_audio.dart';
import 'package:eng_std/data/plan/conversation/conversation_models.dart';
import 'package:eng_std/data/plan/day_window.dart' show WindowSourceRef;
import 'package:eng_std/data/plan/plan_languages.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/session/session_day.dart';
import 'package:eng_std/data/plan/session/session_outcomes.dart';
import 'package:eng_std/data/plan/session/speech_match.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/conversation/talk_constructions.dart';
import 'package:eng_std/features/plan/conversation/talk_entry.dart';
import 'package:eng_std/features/plan/entry/plan_entry_screen.dart';
import 'package:eng_std/features/plan/plan_providers.dart';
import 'package:eng_std/features/plan/plan_tab_screen.dart';
import 'package:eng_std/features/plan/rescue_kit_card.dart';
import 'package:eng_std/features/plan/session/session_controller.dart';
import 'package:eng_std/features/plan/session/session_screen.dart';
import 'package:eng_std/features/profile/profile_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/l10n/app_localizations_ru.dart';
import 'package:eng_std/theme/theme.dart';

import '../../support/day_window_harness.dart';
import '../../support/nbsp.dart';
import '../../support/plan_goldens.dart';
import '../../support/server_fixtures.dart';
import '../../support/session_harness.dart' show SilentRecognizer;
import '../../support/start_harness.dart';

/// THE SERVER'S TAILS ON THE CLIENT (work order CLIENT-START §6) — each by the server's own answers: the e2e renders of
/// `test/fixtures/plan/` (read READ ONLY), the server's fixtures refreshed with the fields they predate
/// (`withE2eFields`).
void main() {
  setUpAll(setUpPlanGoldens);
  final ru = AppLocalizationsRu();

  Widget shell(Widget home) => MaterialApp(
    debugShowCheckedModeBanner: false,
    theme: buildAppTheme(),
    locale: const Locale('ru'),
    supportedLocales: const [Locale('ru'), Locale('en')],
    localizationsDelegates: AppLocalizations.localizationsDelegates,
    home: MediaQuery(data: const MediaQueryData(disableAnimations: true), child: Scaffold(body: home)),
  );

  // ── line_native ───────────────────────────────────────────────────────────────────────────────────────────────
  group('37-5 · строка урока на родном — `line_native` как есть (LANG-1b §3)', () {
    Future<void> rows(WidgetTester tester, List<TalkTarget> targets) async {
      tester.view
        ..devicePixelRatio = 2
        ..physicalSize = const Size(390, 1600) * 2;
      addTearDown(tester.view.reset);
      await tester.pumpWidget(shell(ListView(children: [for (final t in targets) TalkConstructionRow(target: t)])));
    }

    // ЛОВИТ: склейку каркаса на родном со значением — «Мне нужно запись на приём», которая не согласуется с каркасом.
    testWidgets('ru→de с e2e: «Ich brauche einen Termin. · Мне нужна запись на приём.» — не склейка', (tester) async {
      final talk = PlanConversation.fromJson(planFixture('conversation_line_native_day'));
      await rows(tester, talk.targets);

      expect(find.text(ru.planWindowJoin('Ich brauche einen Termin.', 'Мне нужна запись на приём.')), findsOneWidget);
      expect(find.textContaining('Мне нужно запись'), findsNothing);
      for (final t in talk.targets.where((t) => t.exampleTarget != null && t.lineNative != null)) {
        expect(find.text(ru.planWindowJoin(t.lessonLine, t.lineNative!)), findsOneWidget, reason: t.ref);
      }
    });

    // ЛОВИТ: каркас без окна, у которого строка урока пропала, и каркас, повторённый дважды.
    testWidgets('каркас без окна — он целиком и под ним строка урока на родном', (tester) async {
      final talk = PlanConversation.fromJson(planFixture('conversation_line_native_rehearsal'));
      final whole = talk.targets.firstWhere((t) => t.exampleTarget == null);
      await rows(tester, [whole]);

      expect(find.text("He doesn't have a fever."), findsOneWidget);
      expect(find.text('Нет, температуры нет.'), findsOneWidget);
      expect(find.byType(Text), findsNWidgets(2));
    });

    // ЛОВИТ: склейку, вернувшуюся запасным путём у разговора, где сервер строки не прислал.
    testWidgets('строки нет (разговор до LANG-1b) — только строка на цели, склейки нет', (tester) async {
      final raw = planFixture('conversation_line_native_day');
      for (final t in (raw['targets'] as List).cast<Map<String, dynamic>>()) {
        t['line_native'] = null;
      }
      final first = PlanConversation.fromJson(raw).targets.first;
      await rows(tester, [first]);

      expect(find.text('Ich brauche einen Termin.'), findsOneWidget);
      expect(find.textContaining('Мне'), findsNothing);
    });
  });

  // ── partner_gender ────────────────────────────────────────────────────────────────────────────────────────────
  group('правило входа — род роли от сервера (`partner_gender`, FIX-4c §3)', () {
    Future<void> entry(WidgetTester tester, Map<String, dynamic> dayJson, {bool oneScene = false}) async {
      tester.view
        ..devicePixelRatio = 2
        ..physicalSize = const Size(390, 1400) * 2;
      addTearDown(tester.view.reset);
      final plan = planFrom('plan_rehearsal');
      final day = SessionDay.fromJson(dayJson);
      final row = day.window!.stages.firstWhere((s) => s.stage == PlanStage.conversation);
      final scenes = talkEntryScenes(
        row.targets,
        order: [for (final WindowSourceRef s in day.window!.sources) (sceneId: s.sceneId, title: s.titleNative, female: s.partnerFemale)],
        sceneById: plan.sceneById,
      );
      await tester.pumpWidget(shell(TalkEntryView(
        scene: plan.sceneById(scenes.first.sceneId),
        minutes: row.minutes,
        title: row.talkTitleNative,
        scenesCount: row.scenesCount,
        targets: row.targets,
        scenes: oneScene ? scenes.take(1).toList() : scenes,
        rehearsal: true,
        noHints: false,
        onNoHints: (_) {},
        onStart: () {},
        onBack: () {},
      )));
      await tester.pump();
    }

    // ЛОВИТ: «Регистратор начнёт первым» у роли-женщины — род, угаданный телефоном, и «…и он повторит проще» строкой
    // ниже, когда первая уже сказала «первой» (доработка CLIENT-START п. 2).
    testWidgets('роль — женщина: «Регистратор начнёт первой.», «…и она повторит проще.»', (tester) async {
      await entry(tester, serverFixtureJson('day-rehearsal'));
      expect(find.text(nbTypo('Регистратор начнёт первой. Отвечай и спрашивай сам.')), findsOneWidget);
      expect(find.text(nbTypo('Не понял — нажми «Не понял», и она повторит проще.')), findsOneWidget);
    });

    testWidgets('роль — мужчина: «Регистратор начнёт первым.», «…и он повторит проще.»', (tester) async {
      final json = serverFixtureJson('day-rehearsal');
      for (final s in ((json['window'] as Map<String, dynamic>)['sources'] as List).cast<Map<String, dynamic>>()) {
        s['partner_gender'] = 'male';
      }
      await entry(tester, json);
      expect(find.text(nbTypo('Регистратор начнёт первым. Отвечай и спрашивай сам.')), findsOneWidget);
      expect(find.text(nbTypo('Не понял — нажми «Не понял», и он повторит проще.')), findsOneWidget);
    });

    // ЛОВИТ: «она повторит» под «Собеседник начнёт первым» — род роли, которую строка не называет.
    testWidgets('роли нет (разговор одной сцены): «Собеседник начнёт первым.», «…и он повторит проще.»', (tester) async {
      await entry(tester, serverFixtureJson('day-rehearsal'), oneScene: true);
      expect(find.text(nbTypo('Собеседник начнёт первым. Отвечай и спрашивай сам.')), findsOneWidget);
      expect(find.text(nbTypo('Не понял — нажми «Не понял», и он повторит проще.')), findsOneWidget);
    });
  });

  // ── rescue kit ────────────────────────────────────────────────────────────────────────────────────────────────
  group('спасательный набор — новая форма (LANG-1b §2)', () {
    // ЛОВИТ: набор старой формы (пять строк с чтением), чтение под строкой, свёрнутый набор без «все N →».
    testWidgets('шесть строк цели с переводом, без чтения; «все 6 →» раскрывает', (tester) async {
      tester.view
        ..devicePixelRatio = 2
        ..physicalSize = const Size(390, 1400) * 2;
      addTearDown(tester.view.reset);
      final plan = planFrom('current_subscription_building');
      expect(plan.rescueKit, hasLength(6));
      final lines = RecordingLines();
      await tester.pumpWidget(ProviderScope(
        overrides: [lineAudioCacheProvider.overrideWithValue(lines)],
        child: shell(PlanRescueKitCard(plan: plan)),
      ));
      await tester.pump();
      expect(
        [for (final r in lines.asked) r.url],
        [for (final p in plan.rescueKit) p.audioUrl],
        reason: 'the server\'s files in the learner\'s voice go to disk first, so a tap plays at once',
      );

      expect(find.text('СПАСАТЕЛЬНЫЙ НАБОР'), findsOneWidget);
      expect(find.text(ru.planKitSub(6)), findsOneWidget);
      expect(find.text('Poftim?'), findsOneWidget);
      expect(find.text('Простите?'), findsOneWidget);
      expect(find.text(ru.planKitAll(6)), findsOneWidget);

      await tester.tap(find.byKey(const ValueKey('plan-rescue-kit-toggle')));
      await tester.pump();
      for (final p in plan.rescueKit) {
        expect(find.text(p.textTarget), findsOneWidget, reason: p.textTarget);
        expect(find.text(p.textNative), findsOneWidget, reason: p.textNative);
      }
      for (var i = 0; i < 6; i++) {
        expect(find.byKey(ValueKey('plan-rescue-play-$i')), findsOneWidget, reason: 'a circle that says line $i');
      }
    });
  });

  // ── «по подписке» ─────────────────────────────────────────────────────────────────────────────────────────────
  group('день по подписке (ACC-1, кадры 21-3 · 23-0a «по подписке»)', () {
    Future<void> tab(WidgetTester tester, PlanTabState state) async {
      tester.view
        ..devicePixelRatio = 2
        ..physicalSize = const Size(390, 1600) * 2;
      addTearDown(tester.view.reset);
      await tester.pumpWidget(ProviderScope(
        overrides: [
          ...accountOverrides(auth: () => ScriptedAuth(restored: denUser())),
          planTabProvider.overrideWith(() => _StubTab(state)),
          planHintsProvider.overrideWith(_SeenHints.new),
        ],
        child: planGoldenShell(const Scaffold(backgroundColor: AppColors.ground, body: PlanTabScreen())),
      ));
      for (var i = 0; i < 4; i++) {
        await tester.pump(const Duration(milliseconds: 100));
      }
    }

    // ЛОВИТ: день по подписке, нарисованный запертым «по дате» или с «Начать», «Подписка», которая никуда не ведёт, и
    // «ДЕНЬ 2 · ДОГОНЯЕМ» в шапке — день ждёт подписку, а не закрытия прошлого (доработка п. 5).
    testWidgets('плита: «ДЕНЬ 2», «по подписке», «Откроется с подпиской», «Подписка» → группа подписки профиля', (tester) async {
      // The e2e plan's event has passed (21-14 stands over the plate); the same plan the day before it — `active`,
      // and catching up (`catch_up`, as the e2e plan of 21-3 is).
      await tab(
        tester,
        PlanTabState(plan: planFrom('current_subscription', (json) => json..['status'] = 'active'..['catch_up'] = true), finished: const []),
      );

      expect(find.text('ДЕНЬ 2'), findsOneWidget);
      expect(find.textContaining('ДОГОНЯЕМ'), findsNothing);
      expect(find.textContaining('по подписке'), findsWidgets);
      expect(find.text(nbTypo('Откроется с подпиской')), findsOneWidget);
      final button = find.byKey(const ValueKey('day-plate-subscription'));
      expect(button, findsOneWidget);
      expect(find.text('Начать'), findsNothing);

      await tester.tap(button);
      for (var i = 0; i < 6; i++) {
        await tester.pump(const Duration(milliseconds: 100));
      }
      final profile = tester.widget<ProfileScreen>(find.byType(ProfileScreen));
      expect(profile.focusSubscription, isTrue, reason: 'until PAY-1 the subscription group of the profile is where it leads');
    });

    // ЛОВИТ: узлы маршрута дней по подписке в виде «откроется <дата>».
    testWidgets('маршрут: у дней по подписке — латунный контур с точкой и «откроется с подпиской»', (tester) async {
      final plan = planFrom('current_subscription_building');
      await tab(tester, PlanTabState(plan: plan, finished: const []));
      await tester.scrollUntilVisible(find.byKey(const ValueKey('route-subscription-node')).first, 200);

      final locked = plan.days.where((d) => d.lockedBySubscription).length;
      expect(locked, 2);
      expect(find.byKey(const ValueKey('route-subscription-node')), findsNWidgets(locked));
      expect(find.textContaining(nbTypo('откроется с подпиской')), findsWidgets);
    });

    // ЛОВИТ: окно дня по подписке с «Начать», которое упрётся в 409, и «догоняем» в шапке плана, который догоняет.
    testWidgets('окно дня 23-0a: «ДЕНЬ 2», вместо «Начать» — «Подписка» и «Откроется с подпиской»', (tester) async {
      final plan = planFrom('current_subscription_building');
      expect(plan.catchUp, isTrue, reason: 'the e2e plan catches up');
      await pumpDayWindow(tester, roomFrom('room_subscription_locked'), plan: plan);
      expect(find.text('ДЕНЬ 2'), findsOneWidget);
      expect(find.textContaining('ДОГОНЯЕМ'), findsNothing);
      expect(find.text('Подписка'), findsOneWidget);
      expect(find.text(nbTypo('Откроется с подпиской')), findsOneWidget);
      expect(find.text('Начать'), findsNothing);
    });

    // ЛОВИТ: тост «день заперт» поверх экрана вместо плиты, когда сервер отказал день по подписке.
    testWidgets('сессия: 409 plan_day_locked · subscription — плита «по подписке», без тоста', (tester) async {
      await _openSession(tester, _LockedBackend());
      expect(find.byKey(const ValueKey('session-locked-subscription')), findsOneWidget);
      expect(find.text(nbTypo('Откроется с подпиской')), findsOneWidget);
      expect(find.byType(SnackBar), findsNothing);
      expect(find.text('День не загрузился'), findsNothing);
      await tester.pumpWidget(const SizedBox());
      await tester.pump(const Duration(seconds: 5));
    });
  });

  // ── the entry's refusals ──────────────────────────────────────────────────────────────────────────────────────
  group('вход: сервер отказал в плане (ACC-1 §2)', () {
    Future<void> toPreview(WidgetTester tester, _RefusingApi api) async {
      tester.view
        ..devicePixelRatio = 2
        ..physicalSize = const Size(390, 844) * 2;
      addTearDown(tester.view.reset);
      await tester.pumpWidget(ProviderScope(
        overrides: [
          ...accountOverrides(auth: () => ScriptedAuth(restored: denUser())),
          apiClientProvider.overrideWithValue(api),
          planLanguagesProvider.overrideWith((ref) => loadPlanLanguages(api)),
          connectivityProvider.overrideWith((ref) => Stream.value(true)),
          planHintsProvider.overrideWith(_SeenHints.new),
        ],
        child: planGoldenShell(PlanEntryScreen(now: () => DateTime(2026, 9, 12, 12))),
      ));
      await tester.pump();
      await tester.tap(find.text('К врачу с ребёнком, первый раз в местной клинике'));
      await tester.pump();
      for (var i = 0; i < 3; i++) {
        await tester.tap(find.text('Далее'));
        await tester.pump();
        await tester.pump(const Duration(milliseconds: 200));
      }
      await tester.tap(find.text('Собрать план'));
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 400));
    }

    // ЛОВИТ: «Попробовать ещё» под 402 — тот же запрос будет отвергнут снова.
    testWidgets('402 plan_subscription_required — «Второй план — по подписке» и «Подписка» → профиль', (tester) async {
      await toPreview(tester, _RefusingApi(402, 'plan_subscription_required'));

      expect(find.text(nbTypo('Второй план — по подписке')), findsOneWidget);
      expect(find.text('Попробовать ещё'), findsNothing);
      await tester.tap(find.byKey(const ValueKey('entry-refused-subscription')));
      for (var i = 0; i < 6; i++) {
        await tester.pump(const Duration(milliseconds: 100));
      }
      expect(tester.widget<ProfileScreen>(find.byType(ProfileScreen)).focusSubscription, isTrue);
    });

    testWidgets('409 plan_active_limit — «Не больше трёх планов сразу» и «К плану»', (tester) async {
      await toPreview(tester, _RefusingApi(409, 'plan_active_limit'));

      expect(find.text(nbTypo('Не больше трёх планов сразу')), findsOneWidget);
      expect(find.byKey(const ValueKey('entry-refused-close')), findsOneWidget);
      expect(find.text('Попробовать ещё'), findsNothing);
    });
  });

  // ── the failed day ────────────────────────────────────────────────────────────────────────────────────────────
  group('день не собрался — это урок, а не сеть (plan-api «Для CLIENT-START»)', () {
    Plan failedPlan() => planFrom('current_started', (json) {
      final current = json['current_day'] as Map<String, dynamic>;
      current['lesson_status'] = 'failed';
      for (final d in (json['days'] as List).cast<Map<String, dynamic>>()) {
        if (d['number'] == current['number']) d['lesson_status'] = 'failed';
      }
      return json;
    });

    Future<_StubTab> tab(WidgetTester tester, {Object? retryError}) async {
      tester.view
        ..devicePixelRatio = 2
        ..physicalSize = const Size(390, 1200) * 2;
      addTearDown(tester.view.reset);
      final stub = _StubTab(PlanTabState(plan: failedPlan(), finished: const []), retryError: retryError);
      await tester.pumpWidget(ProviderScope(
        overrides: [
          ...accountOverrides(auth: () => ScriptedAuth(restored: denUser())),
          planTabProvider.overrideWith(() => stub),
          planHintsProvider.overrideWith(_SeenHints.new),
        ],
        child: planGoldenShell(const Scaffold(backgroundColor: AppColors.ground, body: PlanTabScreen())),
      ));
      for (var i = 0; i < 4; i++) {
        await tester.pump(const Duration(milliseconds: 100));
      }
      return stub;
    }

    // ЛОВИТ: «Сеть пропала» у урока, не прошедшего ворота дважды (сборка (21)).
    testWidgets('«Не получилось собрать день» и «Повторить»; «Нет сети» — нет', (tester) async {
      await tab(tester);
      expect(find.text(nbTypo('Не получилось собрать день')), findsOneWidget);
      expect(find.text('Повторить'), findsOneWidget);
      expect(find.text('Нет сети'), findsNothing);
    });

    // ЛОВИТ: «Повторить», которое молча не ушло без сети.
    testWidgets('«Повторить» не ушло без сети — только тогда «Нет сети»', (tester) async {
      final stub = await tab(
        tester,
        retryError: DioException(
          requestOptions: RequestOptions(path: '/plans/x/retry'),
          type: DioExceptionType.connectionError,
          error: const SocketException('Network is unreachable'),
        ),
      );
      await tester.tap(find.text('Повторить'));
      for (var i = 0; i < 4; i++) {
        await tester.pump(const Duration(milliseconds: 100));
      }
      expect(stub.retries, 1);
      expect(find.text('Нет сети'), findsOneWidget);
    });
  });

  // ── the numbers of the new languages ──────────────────────────────────────────────────────────────────────────
  group('числа новых языков — по пакету сервера (e2e)', () {
    SpeechRules rulesOf(Map<String, dynamic> json) => SpeechRules.fromJson(json['speech'] as Map<String, dynamic>);

    // ЛОВИТ: «douăzeci și unu», прочитанное как «20 și 1», — сказанное число, которое карточка не засчитает.
    test('ro «douăzeci și unu» = 21', () {
      final ro = rulesOf(planFixture('room_subscription_locked'));
      expect(SpeechMatch.words('douăzeci și unu', ro), ['21']);
      expect(SpeechMatch.heardAll('douăzeci și unu', '21', ro), isTrue);
    });

    // ЛОВИТ: «quatre-vingt-dix», прочитанное по словам («4 20 10»).
    test('fr «quatre-vingt-dix» = 90', () {
      final fr = rulesOf(jsonDecode(File('test/fixtures/plan/speech_fr.json').readAsStringSync()) as Map<String, dynamic>);
      expect(SpeechMatch.words('quatre-vingt-dix', fr), ['90']);
      expect(SpeechMatch.heardAll('quatre-vingt-dix', '90', fr), isTrue);
      expect(SpeechMatch.heardAll('nonante', '90', fr), isTrue);
    });
  });
}

class _StubTab extends PlanTabController {
  _StubTab(this._state, {this.retryError});

  final PlanTabState _state;
  final Object? retryError;
  int retries = 0;

  @override
  Future<PlanTabState> build() async => _state;

  @override
  Future<void> refresh({bool silent = true}) async {}

  @override
  Future<void> retryLesson(String planId, String sceneId) async {
    retries++;
    if (retryError case final e?) throw e;
  }
}

class _SeenHints extends PlanHintsController {
  @override
  Future<PlanHints> build() async => const PlanHints(tabShown: true, closeShown: true, howShown: true);
}

/// `POST /plans` refused with [status] and the problem [code] (RFC 7807) — everything else as the entry's own test.
class _RefusingApi implements ApiClient {
  _RefusingApi(this.status, this.code);

  final int status;
  final String code;

  @override
  Future<PlanBuild> createPlan({
    required String goalText,
    required String targetLang,
    required PlanLevel level,
    required int daysTotal,
    String? eventDate,
  }) async {
    final options = RequestOptions(path: '/plans');
    throw DioException(
      requestOptions: options,
      type: DioExceptionType.badResponse,
      response: Response<Object?>(
        requestOptions: options,
        statusCode: status,
        data: {'type': 'about:blank', 'title': code, 'status': status, 'code': code, 'meta': <String, Object?>{}},
      ),
    );
  }

  @override
  Future<PlanLanguages> pairLanguages() async => PlanLanguages.fromJson(languagesFixture());

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

/// A day the server locks for the subscription: `409 plan_day_locked`, `meta.lock_reason: subscription`.
class _LockedBackend implements SessionBackend {
  static DioException _locked() {
    final options = RequestOptions(path: '/plans/x/days/2');
    return DioException(
      requestOptions: options,
      type: DioExceptionType.badResponse,
      response: Response<Object?>(
        requestOptions: options,
        statusCode: 409,
        data: {
          'code': 'plan_day_locked',
          'status': 409,
          'meta': {'lock_reason': 'subscription'},
        },
      ),
    );
  }

  @override
  Future<SessionDay> day(String planId, int number) async => throw _locked();

  @override
  Future<void> open(String planId, int number) async => throw _locked();

  @override
  Future<Plan> plan(String planId) async => planFrom('current_subscription');

  @override
  Future<Plan> retryLesson(String planId, String sceneId) async => planFrom('current_subscription');

  @override
  Future<SessionAnswerOutcome> answer(String planId, int number, String cardId, SessionAnswer answer) => throw UnimplementedError();

  @override
  Future<SessionJudgeOutcome> judge(String planId, int number, String cardId, {required String heard, required bool hinted}) =>
      throw UnimplementedError();

  @override
  Future<SessionDay> close(String planId, int number) => throw UnimplementedError();
}

Future<void> _openSession(WidgetTester tester, SessionBackend backend) async {
  tester.view
    ..devicePixelRatio = 2
    ..physicalSize = const Size(390, 844) * 2;
  addTearDown(tester.view.reset);
  final messenger = tester.binding.defaultBinaryMessenger;
  for (final channel in [const MethodChannel('flutter_tts'), const MethodChannel('com.denis.engstd/app_info'), AudioMixer.channel]) {
    messenger.setMockMethodCallHandler(channel, (call) async => null);
    addTearDown(() => messenger.setMockMethodCallHandler(channel, null));
  }
  final plan = planFrom('current_subscription');
  await tester.pumpWidget(ProviderScope(
    overrides: [
      ...accountOverrides(auth: () => ScriptedAuth(restored: denUser())),
      lineAudioCacheProvider.overrideWithValue(LineAudioCache(directory: Directory.systemTemp)),
      speechRecognizerProvider.overrideWithValue(SilentRecognizer()),
      appVersionProvider.overrideWith((ref) async => null),
    ],
    child: MaterialApp(
      theme: buildAppTheme(),
      locale: const Locale('ru'),
      localizationsDelegates: AppLocalizations.localizationsDelegates,
      supportedLocales: const [Locale('ru'), Locale('en')],
      builder: (context, child) => MediaQuery(data: MediaQuery.of(context).copyWith(disableAnimations: true), child: child!),
      home: SessionScreen(plan: plan, number: 2, backend: backend),
    ),
  ));
  for (var i = 0; i < 6; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

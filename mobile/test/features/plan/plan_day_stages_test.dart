import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/plan_models.dart';
import 'package:eng_std/data/providers.dart';
import 'package:eng_std/features/plan/plan_day_screen.dart';
import 'package:eng_std/features/plan/plan_day_stages.dart';
import 'package:eng_std/features/plan/plan_fail_reason.dart';
import 'package:eng_std/features/plan/plan_screen.dart';
import 'package:eng_std/l10n/app_localizations.dart';
import 'package:eng_std/l10n/app_localizations_ru.dart';

/// ЭТАПЫ ДНЯ НА ЭКРАНЕ — наряд DAY-GATE-1, Ч.2.1–Ч.2.3.
void main() {
  final l = AppLocalizationsRu();

  PlanDay day(
    List<Map<String, dynamic>> stages, {
    int index = 1,
    int? lockedBy,
    String status = 'ready',
    String title = 'Начать приём',
  }) => PlanDay.fromJson({
    'id': 'd$index',
    'index': index,
    'kind': 'intro',
    'title': title,
    'status': status,
    'stages': stages,
    'locked_by_day_index': lockedBy,
  });

  group('состояние этапа словом', () {
    // ПРАВИЛО: наряд Ч.2.1 — состояния называются словами «пройдено / сейчас / после <имя>».
    // ЛОВИТ: «закрыт» вместо «после чего откроется». Поле `opens_after` едет ВСЕГДА и существует
    // ровно затем, чтобы запертый этап был дорогой, а не тупиком: человек, прочитавший «закрыт»,
    // не знает, что ему сделать, — и живой прогон 07.09 показал именно этот вопрос вслух.
    test('запертый этап называет, после чего откроется, а не «закрыт»', () {
      final d = day([
        {'stage': 'material', 'state': 'done', 'opens_after': null},
        {'stage': 'conversation', 'state': 'current', 'opens_after': 'material'},
        {'stage': 'rehearsal', 'state': 'locked', 'opens_after': 'conversation'},
      ]);

      expect(planStageStateWord(l, d.stages[0]), 'пройдено');
      expect(planStageStateWord(l, d.stages[1]), 'сейчас');
      expect(planStageStateWord(l, d.stages[2]), contains('Разговор'));
    });
  });

  group('строка «до следующего дня»', () {
    // ПРАВИЛО: наряд Ч.2.1 + решение 294 — следующий день встаёт по факту «день N пройден», и
    // «пройден» значит все ОБЯЗАТЕЛЬНЫЕ этапы (решение 291).
    // ЛОВИТ: строку, которая обещает открытый следующий день, пока разговор ещё не пройден, — и
    // обратную ошибку, где день держат ради «Повторить ошибки», который дня не держит.
    test('перечисляет незакрытые обязательные этапы и не считает «Повторить ошибки»', () {
      final d = day([
        {'stage': 'material', 'state': 'done', 'opens_after': null},
        {'stage': 'conversation', 'state': 'current', 'opens_after': 'material'},
        {'stage': 'rehearsal', 'state': 'locked', 'opens_after': 'conversation'},
        {'stage': 'retrain', 'state': 'current', 'opens_after': null},
      ]);

      final line = planNextDayLine(l, d, hasNextDay: true)!;
      expect(line, contains('до дня 2'));
      expect(line, contains('Разговор'));
      expect(line, contains('Скажи сам'));
      expect(line, isNot(contains('Повторить ошибки')));
    });

    test('все обязательные этапы закрыты — следующий день открыт, даже если «Повторить» осталось', () {
      final d = day([
        {'stage': 'material', 'state': 'done', 'opens_after': null},
        {'stage': 'conversation', 'state': 'done', 'opens_after': 'material'},
        {'stage': 'rehearsal', 'state': 'done', 'opens_after': 'conversation'},
        {'stage': 'retrain', 'state': 'current', 'opens_after': null},
      ]);

      expect(planNextDayLine(l, d, hasNextDay: true), 'день 2 открыт');
    });

    // ПРАВИЛО: наряд, требования к качеству — «нет поля → ошибка в лог и честный экран, не догадка».
    // ЛОВИТ: экран, выводящий этапы из `day_state`. Ровно этот вывод и закрывал день раньше, чем он
    // был пройден: `day_state` знает про материал и разговор, а про прогон — нет.
    test('день без этапов не рождает строку из ничего', () {
      expect(planNextDayLine(l, day(const []), hasNextDay: true), isNull);
    });

    test('за последним днём плана следующего нет, и строки тоже', () {
      final d = day([
        {'stage': 'material', 'state': 'done', 'opens_after': null},
        {'stage': 'conversation', 'state': 'current', 'opens_after': 'material'},
        {'stage': 'rehearsal', 'state': 'locked', 'opens_after': 'conversation'},
      ]);

      expect(planNextDayLine(l, d, hasNextDay: false), isNull);
    });
  });

  group('итог присеста зовёт туда, где человек действительно дальше', () {
    List<Map<String, dynamic>> upTo(String current) => [
      {'stage': 'material', 'state': current == 'material' ? 'current' : 'done'},
      {
        'stage': 'conversation',
        'state': switch (current) {
          'material' => 'locked',
          'conversation' => 'current',
          _ => 'done',
        },
        'opens_after': 'material',
      },
      {
        'stage': 'rehearsal',
        'state': switch (current) {
          'rehearsal' => 'current',
          'done' => 'done',
          _ => 'locked',
        },
        'opens_after': 'conversation',
      },
    ];

    // ПРАВИЛО: наряд DAY-GATE-1 (доработка), п. 2 — пока день не пройден, итог зовёт в ТЕКУЩИЙ
    // этап, а не в следующий день.
    // ЛОВИТ: строку «Дальше · День 2 — У стойки» под присестом «Слова и фразы» дня 1, у которого
    // впереди ещё разговор. Живой прогон 07.09: экран дня говорил «до дня 2 — ещё Разговор · Скажи
    // сам», а итог того же дня звал в день 2 — два экрана об одном дне, и один зовёт не туда.
    test('день не пройден — зовёт в текущий этап, а не в следующий день', () {
      final next = day(const [], index: 2, status: 'ready', title: 'У стойки');

      expect(
        planSittingNextTitle(l, day: day(upTo('conversation')), nextDay: next, dayPassed: false),
        'Разговор',
      );
      expect(
        planSittingNextTitle(l, day: day(upTo('rehearsal')), nextDay: next, dayPassed: false),
        'Скажи сам',
      );
    });

    test('день пройден и следующий открыт — зовёт в него', () {
      expect(
        planSittingNextTitle(
          l,
          day: day(upTo('done')),
          nextDay: day(const [], index: 2, status: 'ready', title: 'У стойки'),
          dayPassed: true,
        ),
        'День 2 — У стойки',
      );
    });

    // ПРАВИЛО: тот же п. 2 — следующий день ещё пишется, и об этом говорится словом «собираю», а не
    // приглашением в дверь, которой пока нет (решение 294: день N+1 встаёт в очередь по факту).
    // ЛОВИТ: «Дальше · День 2 — …» над днём, которого ещё нет: человек жмёт и попадает на экран
    // сборки, не понимая, почему обещанный день оказался спиннером.
    test('следующий день ещё собирается — так и говорит', () {
      for (final status in ['pending', 'generating']) {
        expect(
          planSittingNextTitle(
            l,
            day: day(upTo('done')),
            nextDay: day(const [], index: 2, status: status, title: 'У стойки'),
            dayPassed: true,
          ),
          'Собираю день 2',
          reason: status,
        );
      }
    });

    test('звать некуда — строки нет: последний день, запертая дверь, день без этапов', () {
      // Последний день плана: следующего нет вовсе.
      expect(
        planSittingNextTitle(l, day: day(upTo('done')), nextDay: null, dayPassed: true),
        isNull,
      );
      // Следующий день заперт (сервер держит его другим днём) — в запертую дверь итог не зовёт.
      expect(
        planSittingNextTitle(
          l,
          day: day(upTo('done')),
          nextDay: day(const [], index: 2, lockedBy: 1),
          dayPassed: true,
        ),
        isNull,
      );
      // День со старого сервера, без этапов: текущего этапа нет — и выдумывать его не из чего.
      expect(
        planSittingNextTitle(l, day: day(const []), nextDay: null, dayPassed: false),
        isNull,
      );
    });
  });

  group('отказы плана словами (Ч.2.3)', () {
    DioException refusal(String code, {Map<String, dynamic>? meta}) => DioException(
      requestOptions: RequestOptions(path: '/plans/01PLAN/session'),
      response: Response(
        requestOptions: RequestOptions(path: '/plans/01PLAN/session'),
        statusCode: 409,
        data: {'code': code, 'meta': ?meta},
      ),
    );

    // ПРАВИЛО: наряд Ч.2.3 — оба 409 локализуются ПО КОДУ; ни один не даёт «нечего повторять» или
    // пустой экран.
    // ЛОВИТ: общую «не удалось загрузить тренировку» с кнопкой «Ещё раз». Запертый день от повтора
    // не откроется, а закрытый этап не наполнится: человек жал бы кнопку, получал то же самое и
    // оставался без единого слова о том, что делать.
    test('запертый день называет держателя из meta', () {
      final text = planRefusalText(l, refusal('plan_day_locked', meta: {'blocked_by_day': 1}));

      expect(text, isNotNull);
      expect(text, contains('день 1'));
    });

    test('пустая посадка говорит, что этап закрыт, а не молчит', () {
      expect(planRefusalText(l, refusal('plan_sitting_empty')), isNotNull);
    });

    test('чужие ошибки этой функции не принадлежат — она отвечает за два кода, не за связь', () {
      expect(planRefusalText(l, refusal('generation_quota_exceeded')), isNull);
      expect(
        planRefusalText(
          l,
          DioException(
            requestOptions: RequestOptions(path: '/x'),
            type: DioExceptionType.connectionError,
          ),
        ),
        isNull,
      );
    });
  });

  group('экран дня', () {
    Widget host(PlanDayDetail detail, LearningPlan plan) => ProviderScope(
      overrides: [
        planDayProvider((planId: plan.id, dayIndex: detail.day.index))
            .overrideWith((ref) async => detail),
      ],
      child: MaterialApp(
        locale: const Locale('ru'),
        localizationsDelegates: AppLocalizations.localizationsDelegates,
        supportedLocales: const [Locale('ru')],
        home: PlanDayScreen(plan: plan, dayIndex: detail.day.index),
      ),
    );

    Map<String, dynamic> dayJson(List<Map<String, dynamic>> stages) => {
      'id': 'd1',
      'index': 1,
      'kind': 'intro',
      'title': 'Начать приём',
      'status': 'ready',
      'plan_id': '01PLAN',
      'plan_title': 'К врачу',
      'support_lang': 'ru',
      'target_lang': 'en',
      'stages': stages,
      'terms': const [],
    };

    LearningPlan plan() => LearningPlan.fromJson({
      'id': '01PLAN',
      'status': 'active',
      'title': 'К врачу',
      'goal_text': 'Иду к врачу',
      'support_lang': 'ru',
      'target_lang': 'en',
      'level': 'basic',
      'minutes_per_day': 20,
      'focus_day_index': 1,
      'next_day_index': 2,
      'days': [
        {'id': 'd1', 'index': 1, 'kind': 'intro', 'title': 'Начать приём', 'status': 'ready'},
        {'id': 'd2', 'index': 2, 'kind': 'intro', 'title': 'Уточнить', 'status': 'pending'},
      ],
    });

    // ПРАВИЛО: наряд Ч.2.1 — блок этапов рисуется ИЗ `day_state`, четырьмя именами.
    // ЛОВИТ: экран, который знает про день одно слово. Живой прогон: человек не понимал, почему
    // «Продолжить» ведёт то в слова, то в разговор, и почему день не пройден, когда «всё сделал».
    testWidgets('рисует четыре имени этапов и одну кнопку «Продолжить»', (tester) async {
      final detail = PlanDayDetail.fromJson(
        dayJson([
          {'stage': 'material', 'state': 'done', 'opens_after': null},
          {'stage': 'conversation', 'state': 'current', 'opens_after': 'material'},
          {'stage': 'rehearsal', 'state': 'locked', 'opens_after': 'conversation'},
          {'stage': 'retrain', 'state': 'current', 'opens_after': null},
        ]),
      );
      await tester.pumpWidget(host(detail, plan()));
      await tester.pumpAndSettle();

      expect(find.text('Слова и фразы'), findsOneWidget);
      expect(find.text('Разговор'), findsOneWidget);
      expect(find.text('Скажи сам'), findsOneWidget);
      // «Повторить ошибки» — и строкой этапа, и своим входом, и подписано необязательным.
      expect(find.text('Повторить ошибки'), findsNWidgets(2));
      expect(find.text('необязательно'), findsOneWidget);
      expect(find.text('Продолжить'), findsOneWidget);
      // ЧИСЕЛ НЕТ: «N из M» на экранах плана не бывает.
      expect(find.textContaining(RegExp(r'\d+ из \d+')), findsNothing);
    });

    // ПРАВИЛО: наряд Ч.2.2 — запертый день серый, со словами «сначала закончи день N» из поля
    // `locked_by_day_index`, и внутрь не пускает.
    // ЛОВИТ: замок, выведенный клиентом, и строку, которая открывается в 409. Клиентское правило про
    // ту же дверь однажды разойдётся с серверным — и разойдётся в сторону отказа на экране.
    testWidgets('вкладка «План»: запертый день говорит, кто его держит, и не открывается', (
      tester,
    ) async {
      final locked = LearningPlan.fromJson({
        'id': '01PLAN',
        'status': 'active',
        'title': 'К врачу',
        'goal_text': 'Иду к врачу',
        'support_lang': 'ru',
        'target_lang': 'en',
        'level': 'basic',
        'minutes_per_day': 20,
        'focus_day_index': 1,
        'next_day_index': 2,
        'days': [
          {'id': 'd1', 'index': 1, 'kind': 'intro', 'title': 'Начать приём', 'status': 'ready'},
          {
            'id': 'd2',
            'index': 2,
            'kind': 'intro',
            'title': 'Уточнить',
            'status': 'ready',
            'locked_by_day_index': 1,
          },
        ],
      });

      await tester.pumpWidget(
        ProviderScope(
          overrides: [planProvider('01PLAN').overrideWith((ref) async => locked)],
          child: MaterialApp(
            locale: const Locale('ru'),
            localizationsDelegates: AppLocalizations.localizationsDelegates,
            supportedLocales: const [Locale('ru')],
            home: const PlanScreen(planId: '01PLAN'),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('сначала закончи день 1'), findsOneWidget);

      // …и тап по строке никуда не ведёт: за ней стоит 409.
      await tester.tap(find.text('Уточнить'));
      await tester.pumpAndSettle();
      expect(find.text('сначала закончи день 1'), findsOneWidget);
    });

    // ПРАВИЛО: наряд, требования к качеству — экран не выводит состояние сам.
    // ЛОВИТ: блок этапов, нарисованный по `day_state` у сервера, который этапов не шлёт. Выдуманный
    // «пройдено» — это ровно та догадка, из-за которой день закрывался раньше срока.
    testWidgets('день без этапов не рисует блок и не выдумывает его', (tester) async {
      await tester.pumpWidget(host(PlanDayDetail.fromJson(dayJson(const [])), plan()));
      await tester.pumpAndSettle();

      expect(find.text('Слова и фразы'), findsNothing);
      expect(find.text('Скажи сам'), findsNothing);
      expect(find.textContaining('до дня'), findsNothing);
    });
  });
}

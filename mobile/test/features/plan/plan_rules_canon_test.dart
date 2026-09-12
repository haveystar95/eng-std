import 'dart:async';
import 'dart:io';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/image_loader.dart';
import 'package:eng_std/data/plan/plan_models.dart';
import 'package:eng_std/data/plan/plan_reminder_rules.dart';
import 'package:eng_std/data/speech/speech_recognizer.dart';
import 'package:eng_std/features/plan/entry/dictation_wave.dart';
import 'package:eng_std/features/plan/entry/entry_state.dart';
import 'package:eng_std/features/plan/entry/goal_dictation.dart';
import 'package:eng_std/features/plan/route/route_fill.dart';
import 'package:eng_std/theme/theme.dart';
import 'package:eng_std/ui/ui.dart';

import '../../support/plan_goldens.dart';

/// КАНОН ПЛАНА — чистые правила наряда PLAN-UI-3: заливка линии, уведомления, загрузчик картинок,
/// тайминги прелоадера, печать голосом. Каждый тест назван правилом и говорит, какой дефект ловит.
void main() {
  // ── ЛИНИЯ ─────────────────────────────────────────────────────────────────────────────────
  group('линия заливается до последнего пройденного этапа', () {
    const passed = RouteFillNode(passed: true);
    const ahead = RouteFillNode(passed: false);
    const current = RouteFillNode(passed: false, current: true);

    // ЛОВИТ: латунь не на том отрезке (например, после текущего этапа, а не перед ним).
    test('шалфей до последнего пройденного, латунь входит в текущий, дальше серое', () {
      expect(routeSegments([passed, passed, passed, current, ahead, ahead]), [
        RouteSegment.walked,
        RouteSegment.walked,
        RouteSegment.current,
        RouteSegment.ahead,
        RouteSegment.ahead,
      ]);
    });

    // ЛОВИТ: «дырявый» прогресс — пройденный узел за непройденным перекрасил бы линию.
    test('пройденный узел после непройденного линию не заливает', () {
      expect(routeSegments([passed, ahead, passed, passed]), [RouteSegment.ahead, RouteSegment.ahead, RouteSegment.ahead]);
    });

    // ЛОВИТ: латунь у дня, закрытого сегодня, когда следующий откроется завтра (кадр 21-4).
    test('после закрытого дня текущего этапа нет — латуни нет', () {
      expect(routeSegments([passed, passed, passed, ahead]).contains(RouteSegment.current), isFalse);
    });
  });

  // ── УВЕДОМЛЕНИЯ ───────────────────────────────────────────────────────────────────────────
  group('уведомление не чаще раза в сутки', () {
    final now = DateTime(2026, 9, 12, 9, 0);
    Plan live({String? slotDate = '2026-09-12', String? event = '2026-09-16', String status = 'active'}) => Plan.fromJson({
      'id': 'p',
      'status': status,
      'days_total': 5,
      'event_date': event,
      'current_day': {
        'id': 'd2',
        'number': 2,
        'status': 'open',
        'title_native': 'Приём у врача',
        'slot': {'code': 'today', 'date': slotDate},
      },
      'days': const [],
    });

    // ЛОВИТ: два напоминания в один день (напоминание и «ждёт со вчера», напоминание и событие).
    test('на каждую дату — не больше одного', () {
      final notices = planLocalNotices(live(), now, const VisitTime(19, 0));
      final dates = notices.map((n) => DateTime(n.at.year, n.at.month, n.at.day)).toList();

      expect(dates.toSet().length, dates.length);
      expect(notices, isNotEmpty);
    });

    // ЛОВИТ: «ждёт со вчера» в тот же день, что и сам день, и напоминание вместо события.
    test('в дату дня — напоминание, позже — «ждёт со вчера», в дату события — событие', () {
      final kinds = {for (final n in planLocalNotices(live(), now, const VisitTime(19, 0))) n.at.day: n.kind};

      expect(kinds[12], PlanNoticeKind.reminder);
      expect(kinds[13], PlanNoticeKind.skipped);
      expect(kinds[16], PlanNoticeKind.eventToday);
    });

    // ЛОВИТ: напоминание, поставленное в прошлое (iOS показал бы его сразу при открытии).
    test('ничего раньше «сейчас»', () {
      final late = DateTime(2026, 9, 12, 20, 0);
      expect(planLocalNotices(live(), late, const VisitTime(19, 0)).every((n) => n.at.isAfter(late)), isTrue);
    });

    // ЛОВИТ: напоминания у плана, который не идёт (не начат или завершён).
    test('план не идёт — уведомлений нет', () {
      expect(planLocalNotices(live(status: 'ready'), now, VisitTime.evening), isEmpty);
      expect(planLocalNotices(live(status: 'finished'), now, VisitTime.evening), isEmpty);
      expect(planLocalNotices(null, now, VisitTime.evening), isEmpty);
    });
  });

  group('час напоминания — обычный заход', () {
    // ЛОВИТ: напоминание в полночь у человека, который ещё ни разу не заходил.
    test('заходов нет — 19:00', () => expect(usualVisitTime(const []), VisitTime.evening));

    // ЛОВИТ: среднее вместо медианы — один ночной заход утащил бы напоминание на ночь.
    test('медиана последних семи, вниз до четверти часа', () {
      final visits = [
        for (final (d, h, m) in const [(1, 8, 0), (2, 19, 10), (3, 19, 20), (4, 19, 40), (5, 23, 50), (6, 19, 5), (7, 18, 55), (8, 20, 0)])
          DateTime(2026, 9, d, h, m),
      ];
      // Последние семь: 19:10 19:20 19:40 23:50 19:05 18:55 20:00 → медиана 19:20 → 19:15.
      expect(usualVisitTime(visits), const VisitTime(19, 15));
    });
  });

  // ── КАРТИНКИ ──────────────────────────────────────────────────────────────────────────────
  group('один загрузчик картинок', () {
    late ImageLoader loader;
    setUp(() {
      loader = ImageLoader.instance
        ..store = null
        ..headers = ((_) => const {});
    });

    // ЛОВИТ: маршрут из десяти дней, открывающий десять соединений разом.
    test('не больше шести запросов одновременно', () async {
      var active = 0, peak = 0;
      final gate = Completer<void>();
      loader.fetcher = (uri, _) async {
        active++;
        peak = active > peak ? active : peak;
        await gate.future;
        active--;

        return Uint8List.fromList([1]);
      };
      final all = Future.wait([for (var i = 0; i < 10; i++) loader.bytes('https://x/p$i.jpg')]);
      await Future<void>.delayed(const Duration(milliseconds: 20));
      expect(peak, ImageLoader.maxParallel);
      gate.complete();
      await all;
    });

    // ЛОВИТ: обрыв на ненадёжной сети, после которого круг так и остаётся тоном.
    test('обрыв соединения — повтор, 404 — без повтора', () async {
      var calls = 0;
      loader.fetcher = (uri, _) async {
        calls++;
        if (uri.path.contains('flaky') && calls == 1) throw const SocketException('reset');
        if (uri.path.contains('gone')) throw ImageHttpError(404, uri);

        return Uint8List.fromList([7]);
      };
      expect(await loader.bytes('https://x/flaky.jpg'), [7]);
      expect(calls, 2);

      calls = 0;
      await expectLater(loader.bytes('https://x/gone.jpg'), throwsA(isA<ImageHttpError>()));
      expect(calls, 1);
    });

    // ЛОВИТ: круг маршрута и обложка плиты, скачивающие одну и ту же картинку дважды.
    test('один адрес — одна загрузка', () async {
      var calls = 0;
      final gate = Completer<void>();
      loader.fetcher = (uri, _) async {
        calls++;
        await gate.future;

        return Uint8List.fromList([1]);
      };
      final a = loader.bytes('https://x/same.jpg');
      final b = loader.bytes('https://x/same.jpg');
      gate.complete();
      await Future.wait([a, b]);
      expect(calls, 1);
    });

    // ЛОВИТ: на iPhone @3x кроп 112 для круга 56 — мыльная картинка.
    test('кроп по плотности: 112 для @2x, 448 для @3x', () {
      const image = PlanImage(url: 'o', url112: 's', url448: 'l');
      expect(image.urlFor(56, 2), 's');
      expect(image.urlFor(56, 3), 'l');
      expect(const PlanImage(url: 'o').urlFor(56, 3), 'o');
    });
  });

  // ПРАВИЛО (§3): бумажный круг → тон → фото; ни одного мигания пустым.
  // ЛОВИТ: круг, который до прихода байтов стоит пустой (серый или прозрачный).
  testWidgets('пока фото в пути — круг залит тоном', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: Center(child: SceneCircle(image: _NeverImage(), tone: Color(0xFF958E88)))),
    );
    await tester.pump(const Duration(milliseconds: 300));
    final box = tester.widget<AnimatedContainer>(find.byType(AnimatedContainer));

    expect((box.decoration! as BoxDecoration).color, const Color(0xFF958E88));
  });

  // ── ПРЕЛОАДЕР ─────────────────────────────────────────────────────────────────────────────
  group('прелоадер по @keyframes om-pre-*', () {
    const node = Keyframes([(0, 0), (.08, 0), (.18, 1), (1, 1)], Cubic(.34, 1.4, .5, 1));
    const line2 = Keyframes([(0, 0), (.32, 0), (.38, 1), (.62, 1), (.68, 0), (1, 0)]);

    // ЛОВИТ: узлы, зажигающиеся одновременно, и таймингы «на глаз» вместо ключей канвы.
    test('узел погашен до 8 % цикла и горит с 18 %', () {
      expect(node.at(.05), 0);
      expect(node.at(.18), 1);
      expect(node.at(.5), 1);
    });

    // ЛОВИТ: вторую строку статуса, видимую вместе с первой.
    test('строка 2 видна с 38 % до 62 % цикла 6 с', () {
      expect(line2.at(.30), 0);
      expect(line2.at(.50), 1);
      expect(line2.at(.70), 0);
    });
  });

  // ── ПЕЧАТЬ ГОЛОСОМ ────────────────────────────────────────────────────────────────────────
  group('печать голосом в поле цели', () {
    // ПРАВИЛО (§2): текст появляется в поле по мере речи, запись закрывается сама, состояния
    // «распознаю» нет — после записи микрофон в покое, текст в поле.
    // ЛОВИТ: поле, пустое до конца записи («запись → распознавание»), и промежуточное состояние.
    test('частичный результат — сразу в поле, после записи — «сказал»', () async {
      final field = TextEditingController(text: 'Иду к врачу,');
      final recognizer = _ScriptedRecognizer(['болит', 'болит спина']);
      final dictation = GoalDictation(recognizer: recognizer, field: field);
      final seen = <String>[];
      field.addListener(() => seen.add(field.text));

      await dictation.toggle(localeId: 'ru_RU');

      expect(seen, contains('Иду к врачу, болит'));
      expect(field.text, 'Иду к врачу, болит спина');
      expect(dictation.state, EntryMicState.done);
      expect(EntryMicState.values.map((e) => e.name), isNot(contains('recognising')));
      dictation.dispose();
    });

    // ПРАВИЛО (22-1): последнее слово серое — оно ещё уточняется.
    // ЛОВИТ: весь текст одного цвета, пока человек говорит.
    testWidgets('последнее слово — серое', (tester) async {
      await tester.pumpWidget(
        planGoldenApp(
          const DictatedText(base: '', heard: 'болит спина', style: TextStyle(color: AppColors.ink)),
        ),
      );
      await tester.pump();
      final rich = tester.widget<Text>(find.byType(Text)).textSpan! as TextSpan;
      final last = rich.children!.last as TextSpan;

      expect(last.text, 'спина');
      expect(last.style!.color, AppColors.dictationPending);
    });
  });
}

class _NeverImage extends ImageProvider<_NeverImage> {
  const _NeverImage();

  @override
  Future<_NeverImage> obtainKey(ImageConfiguration configuration) async => this;

  @override
  ImageStreamCompleter loadImage(_NeverImage key, ImageDecoderCallback decode) =>
      OneFrameImageStreamCompleter(Completer<ImageInfo>().future);
}

/// Плагин, который отдаёт частичные результаты по сценарию и закрывает попытку последним.
class _ScriptedRecognizer implements SpeechRecognizer {
  _ScriptedRecognizer(this.partials);

  final List<String> partials;
  var _done = false;

  @override
  Future<SpeechAttempt> listenOnce({
    required List<String> expected,
    required String localeId,
    Duration timeout = const Duration(seconds: 8),
    Duration pauseFor = const Duration(seconds: 2),
    List<String> contextualStrings = const [],
    ValueChanged<String>? onPartial,
    ValueChanged<double>? onLevel,
  }) async {
    if (_done) return const SpeechAttempt.unavailable();
    _done = true;
    for (final p in partials) {
      onLevel?.call(6);
      onPartial?.call(p);
    }

    return SpeechAttempt.heard(partials.last);
  }

  @override
  Future<void> stop() async {}

  @override
  Future<void> cancel() async {}

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

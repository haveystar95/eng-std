import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:eng_std/data/qa_report.dart';
import 'package:eng_std/data/speech/speech_diagnostics.dart';
import 'package:eng_std/features/profile/build_stamp.dart';
import 'package:eng_std/l10n/app_localizations_ru.dart';

import '../../support/nbsp.dart';

/// ВЕРСИЯ И «ЖАЛОБА» — наряд DAY-GATE-1, Ч.0.4 и Ч.0.5.
void main() {
  final l = AppLocalizationsRu();

  group('строка версии', () {
    // ПРАВИЛО: наряд Ч.0.4 — версия зашивается ПРИ СБОРКЕ (`scripts/build_ios.sh`), не руками.
    // ЛОВИТ: строку, которая выдумывает версию у сборки, собранной мимо скрипта. Она заведена
    // ровно для того, чтобы за полсекунды закрыть вопрос «ту ли сборку мы смотрим»; строка,
    // способная соврать, отвечает на него хуже, чем отсутствующая, — ей поверят.
    test('несобранная скриптом сборка честно говорит «без метки»', () {
      final text = buildStampText(l, const AsyncValue.data('def5678'));

      expect(text, contains(nbTypo('без метки')));
      expect(text, contains('def5678'));
    });

    // ПРАВИЛО: то же, вторая половина — SHA сервера приезжает живьём.
    // ЛОВИТ: строку, которая при мёртвой сети показывает пустоту или прошлое значение. «Нет связи»
    // и «сервер такой-то» — разные ответы, и путать их значит гоняться за поломкой не на той стороне.
    test('сервер не ответил — так и написано, без выдуманного SHA', () {
      final text = buildStampText(
        l,
        AsyncValue.error(Exception('offline'), StackTrace.empty),
      );

      expect(text, contains('нет связи'));
    });
  });

  group('слепок «жалобы»', () {
    // ПРАВИЛО: наряд Ч.0.5 — отчёт несёт идентификаторы, журнал микрофона и версии обеих сторон.
    // ЛОВИТ: отчёт, потерявший половину себя. Разбор поломки идёт по этому файлу: без адреса не
    // сходить в базу, без журнала микрофона не назвать причину — а именно этих двух вещей и не
    // было при разборе живого прогона 07.09.
    test('несёт адрес экрана, журнал микрофона и обе версии', () {
      final speech = SpeechDiagnostics()
        ..note('opening')
        ..partial('my back hurts')
        ..phaseIs(SpeechPhase.failed, code: 'channel_down');

      final report = buildQaReport(
        context: const QaContext(
          screen: 'session',
          planId: '01PLAN',
          dayIndex: 2,
          sessionId: '01SESSION',
          cardTermId: '01TERM',
        ),
        speech: speech,
        backendCommit: 'def5678',
      );

      expect(report['context'], containsPair('plan_id', '01PLAN'));
      expect(report['context'], containsPair('day_index', 2));
      expect(report['context'], containsPair('session_id', '01SESSION'));
      expect(report['context'], containsPair('card_term_id', '01TERM'));
      expect((report['server']! as Map)['commit'], 'def5678');

      final mic = report['speech']! as Map<String, dynamic>;
      expect(mic['phase'], 'failed');
      expect(mic['last_error'], 'channel_down');
      expect(mic['last_partial'], 'my back hurts');
      expect(mic['log'], isNotEmpty);
    });

    // ПРАВИЛО: наряд Ч.0.5 — «жалоба» нажимается там, где что-то не так, с любого экрана.
    // ЛОВИТ: отчёт, который волочит за собой адрес ПРОШЛОГО экрана. Неверный адрес хуже
    // отсутствующего: по нему идут в базу и разбирают не ту посадку.
    test('вход на экран заменяет адрес целиком, а не дописывает к прошлому', () {
      final container = ProviderContainer();
      addTearDown(container.dispose);
      final qa = container.read(qaContextProvider.notifier);

      qa.enter(const QaContext(screen: 'session', planId: '01PLAN', sessionId: '01SESSION'));
      qa.card(termId: '01TERM', mode: 'speaking');
      qa.enter(const QaContext(screen: 'home'));

      final now = container.read(qaContextProvider);
      expect(now.screen, 'home');
      expect(now.sessionId, isNull);
      expect(now.cardTermId, isNull);
    });
  });
}
